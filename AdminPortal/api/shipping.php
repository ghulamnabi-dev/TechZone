<?php
/**
 * Shipping zones & rates (core/shipping.php). Reads: every signed-in admin; writes: shipping.write (owner).
 *
 *   GET                         -> {zones:[{id,name,cities[],is_default,sort_order,rate:{id,amount,free_over,eta}|null}]}
 *   GET ?city=Karachi&subtotal= -> {quote: shipping_quote(), zone:{…}|null}   ("Test a city")
 *   POST   {name, cities[], amount, free_over|null, eta, is_default}  -> create
 *   PUT    {id, …same}                                                -> update
 *   DELETE ?id=                                                       -> delete (not the default zone)
 * A city belongs to one zone: saving a zone with a city listed elsewhere moves it here
 * (the response lists it under "moved"). Every write is audited.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/shipping.php';
require_once __DIR__ . '/../../core/audit.php';

checkLogin(true);
json_headers();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    require_can('shipping.write');
    enforce_csrf_json();
}

/** Fresh zone list (shipping_zones() caches per request, so read directly). */
function ship_zone_rows(PDO $conn): array
{
    $rows = $conn->query("SELECT z.id, z.name, z.cities, z.is_default, z.sort_order,
            r.id AS rate_id, r.amount, r.free_over, r.eta
        FROM shipping_zones z
        LEFT JOIN shipping_rates r ON r.id = (SELECT r2.id FROM shipping_rates r2 WHERE r2.zone_id = z.id AND r2.is_active = 1 ORDER BY r2.amount, r2.id LIMIT 1)
        ORDER BY z.sort_order, z.id")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function ($r) {
        $c = json_decode((string) $r['cities'], true);
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'cities' => is_array($c) ? array_values(array_map('strval', $c)) : [],
            'is_default' => (int) $r['is_default'] === 1,
            'sort_order' => (int) $r['sort_order'],
            'rate' => $r['rate_id'] === null ? null : [
                'id' => (int) $r['rate_id'],
                'amount' => (float) $r['amount'],
                'free_over' => $r['free_over'] === null ? null : (float) $r['free_over'],
                'eta' => $r['eta'],
            ],
        ];
    }, $rows);
}

/** Flat snapshot for audit diffs. */
function ship_snapshot(?array $z): array
{
    if ($z === null) {
        return [];
    }
    return [
        'name' => $z['name'],
        'cities' => implode(', ', $z['cities']),
        'amount' => $z['rate']['amount'] ?? null,
        'free_over' => $z['rate']['free_over'] ?? null,
        'eta' => $z['rate']['eta'] ?? null,
        'is_default' => $z['is_default'] ? 'yes' : 'no',
    ];
}

function ship_find(array $zones, int $id): ?array
{
    foreach ($zones as $z) {
        if ($z['id'] === $id) {
            return $z;
        }
    }
    return null;
}

try {
    if ($method === 'GET') {
        if (isset($_GET['city'])) {
            $city = v_str($_GET['city'], 100) ?? '';
            if (trim($city) === '') {
                json_error('Enter a city', 422);
            }
            $subtotal = v_num($_GET['subtotal'] ?? 0, 0, 1.0e8) ?? 0.0;
            $quote = shipping_quote($conn, $city, $subtotal);
            $zone = zone_for_city($conn, $city);
            $matched = false;
            if ($zone) {
                foreach ($zone['cities'] as $c) {
                    if (shipping_city_key($c) === shipping_city_key($city)) {
                        $matched = true;
                    }
                }
            }
            json_response(['success' => true, 'city' => $city, 'quote' => $quote, 'matched' => $matched,
                'fallback' => $zone !== null && !$matched, 'zone' => $zone ? ['id' => $zone['id'], 'name' => $zone['name']] : null]);
        }
        json_response(['success' => true, 'zones' => ship_zone_rows($conn)]);
    }

    $zones = ship_zone_rows($conn);

    if ($method === 'DELETE') {
        $id = v_id($_GET['id'] ?? 0);
        $z = ship_find($zones, $id);
        if ($z === null) {
            json_error('Zone not found', 404);
        }
        if ($z['is_default']) {
            json_error('This is the default zone for cities not listed anywhere. Make another zone the default first.', 409);
        }
        $conn->beginTransaction();
        $conn->prepare('DELETE FROM shipping_zones WHERE id = ?')->execute([$id]);   // rates cascade
        audit('shipping.delete', 'shipping_zone', $id, 'Deleted shipping zone ' . $z['name'], ship_snapshot($z), null);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Zone deleted', 'zones' => ship_zone_rows($conn)]);
    }

    if ($method !== 'POST' && $method !== 'PUT') {
        json_error('Invalid request method', 405);
    }

    $data = request_json();
    $errors = [];
    $id = $method === 'PUT' ? v_id($data['id'] ?? 0) : 0;
    $old = $id ? ship_find($zones, $id) : null;
    if ($method === 'PUT' && $old === null) {
        json_error('Zone not found', 404);
    }

    $name = v_str($data['name'] ?? '', 80);
    if ($name === null || trim($name) === '') {
        $errors['name'] = 'Give the zone a name (max 80 characters).';
    } else {
        $name = trim($name);
        foreach ($zones as $z) {
            if ($z['id'] !== $id && strcasecmp($z['name'], $name) === 0) {
                $errors['name'] = 'Another zone is already called ' . $z['name'] . '.';
            }
        }
    }

    $cities = [];
    $seen = [];
    foreach ((array) ($data['cities'] ?? []) as $c) {
        $c = v_str($c, 60);
        if ($c === null) {
            $errors['cities'] = 'City names are at most 60 characters.';
            continue;
        }
        $c = trim(preg_replace('/\s+/', ' ', $c));
        $k = shipping_city_key($c);
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $cities[] = mb_convert_case($c, MB_CASE_TITLE, 'UTF-8');
    }
    if (count($cities) > 200) {
        $errors['cities'] = 'A zone can list up to 200 cities.';
    }
    $isDefault = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$cities && !$isDefault) {
        $errors['cities'] = 'Add at least one city (or make this the default zone).';
    }
    if ($old && $old['is_default'] && !$isDefault) {
        $errors['is_default'] = 'One zone must stay the default. Make another zone the default instead.';
    }

    $amount = v_num($data['amount'] ?? null, 0, 100000);
    if ($amount === null) {
        $errors['amount'] = 'Rate is a rupee amount from 0 to 100,000.';
    }
    $freeRaw = $data['free_over'] ?? null;
    $freeOver = null;
    if ($freeRaw !== null && $freeRaw !== '') {
        $freeOver = v_num($freeRaw, 0, 10000000);
        if ($freeOver === null) {
            $errors['free_over'] = 'Free-over is a rupee amount (leave empty for never free).';
        }
    }
    $eta = v_str($data['eta'] ?? '', 40);
    if ($eta === null) {
        $errors['eta'] = 'Delivery estimate is at most 40 characters.';
    }

    if ($errors) {
        json_response(['success' => false, 'message' => 'Check the highlighted fields.', 'errors' => $errors], 422);
    }

    $conn->beginTransaction();
    // Move cities this zone now claims out of every other zone
    $moved = [];
    foreach ($zones as $z) {
        if ($z['id'] === $id) {
            continue;
        }
        $keep = array_values(array_filter($z['cities'], static fn($c) => !isset($seen[shipping_city_key($c)])));
        if (count($keep) !== count($z['cities'])) {
            foreach (array_diff($z['cities'], $keep) as $c) {
                $moved[] = ['city' => $c, 'from' => $z['name']];
            }
            $conn->prepare('UPDATE shipping_zones SET cities = ? WHERE id = ?')->execute([json_encode($keep, JSON_UNESCAPED_UNICODE), $z['id']]);
            audit('shipping.update', 'shipping_zone', $z['id'], 'Moved ' . implode(', ', array_diff($z['cities'], $keep)) . ' out of ' . $z['name'],
                ['cities' => implode(', ', $z['cities'])], ['cities' => implode(', ', $keep)]);
        }
    }
    if ($isDefault) {
        $conn->prepare('UPDATE shipping_zones SET is_default = 0 WHERE id <> ?')->execute([$id]);
    }
    $citiesJson = json_encode($cities, JSON_UNESCAPED_UNICODE);
    if ($method === 'POST') {
        $sort = (int) $conn->query('SELECT COALESCE(MAX(sort_order), 0) FROM shipping_zones')->fetchColumn();
        $conn->prepare('INSERT INTO shipping_zones (name, cities, is_default, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$name, $citiesJson, $isDefault ? 1 : 0, $sort + 1]);
        $id = (int) $conn->lastInsertId();
        $conn->prepare("INSERT INTO shipping_rates (zone_id, name, amount, free_over, eta) VALUES (?, 'Standard', ?, ?, ?)")
            ->execute([$id, $amount, $freeOver, $eta === '' ? null : $eta]);
    } else {
        $conn->prepare('UPDATE shipping_zones SET name = ?, cities = ?, is_default = ? WHERE id = ?')
            ->execute([$name, $citiesJson, $isDefault ? 1 : 0, $id]);
        if ($old['rate']) {
            $conn->prepare('UPDATE shipping_rates SET amount = ?, free_over = ?, eta = ? WHERE id = ?')
                ->execute([$amount, $freeOver, $eta === '' ? null : $eta, $old['rate']['id']]);
        } else {
            $conn->prepare("INSERT INTO shipping_rates (zone_id, name, amount, free_over, eta) VALUES (?, 'Standard', ?, ?, ?)")
                ->execute([$id, $amount, $freeOver, $eta === '' ? null : $eta]);
        }
    }
    $new = ship_find(ship_zone_rows($conn), $id);
    if ($method === 'POST') {
        audit('shipping.create', 'shipping_zone', $id, 'Added shipping zone ' . $name . ' (' . count($cities) . ' cit' . (count($cities) === 1 ? 'y' : 'ies') . ')', null, ship_snapshot($new));
    } else {
        [$b, $a] = audit_diff(ship_snapshot($old), ship_snapshot($new));
        if ($a) {
            audit('shipping.update', 'shipping_zone', $id, 'Updated shipping zone ' . $name . ' (' . implode(', ', array_map(static fn($k) => str_replace('_', ' ', $k), array_keys($a))) . ')', $b, $a);
        }
    }
    $conn->commit();
    json_response(['success' => true, 'message' => $method === 'POST' ? 'Zone added' : 'Zone saved', 'zone' => $new, 'moved' => $moved, 'zones' => ship_zone_rows($conn)]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not save the shipping zone.'), 500);
}
