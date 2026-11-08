<?php
/**
 * Shipping zones and rates (docs/ADMIN.md "Storefront coupling").
 *
 * A zone is a set of city names (shipping_zones.cities, JSON array). A city matches a zone
 * case-insensitively, ignoring surrounding spaces; a city in no zone falls back to the zone
 * marked is_default. Each zone's first active rate (shipping_rates) is a flat amount, shipped
 * free when the subtotal is at or above free_over.
 */

require_once __DIR__ . '/errors.php';

/** Normalised city key: "  lahore " -> "lahore". */
function shipping_city_key(string $city): string
{
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $city)), 'UTF-8');
}

/**
 * All zones with their active rate, cached per request.
 *
 * @return array<int, array{id:int,name:string,cities:string[],is_default:bool,rate:?array{id:int,name:string,amount:float,free_over:?float,eta:?string}}>
 */
function shipping_zones(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $rows = $conn->query("SELECT z.id, z.name, z.cities, z.is_default,
            r.id AS rate_id, r.name AS rate_name, r.amount, r.free_over, r.eta
        FROM shipping_zones z
        LEFT JOIN shipping_rates r ON r.id = (SELECT r2.id FROM shipping_rates r2 WHERE r2.zone_id = z.id AND r2.is_active = 1 ORDER BY r2.amount, r2.id LIMIT 1)
        ORDER BY z.sort_order, z.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $cities = json_decode((string) $r['cities'], true);
        $cache[] = [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'cities' => is_array($cities) ? array_values(array_map('strval', $cities)) : [],
            'is_default' => (int) $r['is_default'] === 1,
            'rate' => $r['rate_id'] === null ? null : [
                'id' => (int) $r['rate_id'],
                'name' => $r['rate_name'],
                'amount' => (float) $r['amount'],
                'free_over' => $r['free_over'] !== null ? (float) $r['free_over'] : null,
                'eta' => $r['eta'],
            ],
        ];
    }
    return $cache;
}

/**
 * The zone a city ships from: the zone listing it, else the default zone, else null.
 *
 * @return array{id:int,name:string,cities:string[],is_default:bool,rate:?array}|null
 */
function zone_for_city(PDO $conn, string $city): ?array
{
    $key = shipping_city_key($city);
    $default = null;
    foreach (shipping_zones($conn) as $zone) {
        if ($key !== '') {
            foreach ($zone['cities'] as $c) {
                if (shipping_city_key($c) === $key) {
                    return $zone;
                }
            }
        }
        if ($zone['is_default'] && $default === null) {
            $default = $zone;
        }
    }
    return $default;
}

/**
 * Shipping for a cart going to $city.
 *
 * fee is 0 when subtotal >= free_over. With no zones configured at all, falls back to the
 * legacy flat rule (Rs 500, free over Rs 5,000) so checkout never breaks.
 *
 * @return array{zone: ?array{id:int,name:string}, fee: float, free_over: ?float, eta: ?string, amount: float, remaining_for_free: ?float}
 */
function shipping_quote(PDO $conn, string $city, float $subtotal): array
{
    $zone = zone_for_city($conn, $city);
    if ($zone === null || $zone['rate'] === null) {
        $amount = 500.0;
        $freeOver = 5000.0;
        $eta = null;
        $zoneOut = null;
    } else {
        $amount = $zone['rate']['amount'];
        $freeOver = $zone['rate']['free_over'];
        $eta = $zone['rate']['eta'];
        $zoneOut = ['id' => $zone['id'], 'name' => $zone['name']];
    }
    $free = $freeOver !== null && $subtotal >= $freeOver;
    return [
        'zone' => $zoneOut,
        'fee' => $free ? 0.0 : $amount,
        'amount' => $amount,
        'free_over' => $freeOver,
        'eta' => $eta,
        'remaining_for_free' => ($freeOver !== null && !$free) ? round($freeOver - $subtotal, 2) : null,
    ];
}
