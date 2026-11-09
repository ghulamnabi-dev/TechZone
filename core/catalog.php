<?php
/**
 * Catalogue data for the storefront v2 ("The Price Board", docs/STOREFRONT.md):
 * product attributes, key specs, rate history and changes, the rate board, price drops,
 * the category tree and sellers. Data only: no HTML, no output, prepared statements only.
 *
 * Prices: products.price is today's rate. price_history holds weekly points whose newest
 * point equals products.price (record_price_point() keeps it that way when staff edit a price).
 */

require_once __DIR__ . '/errors.php';

/** Builder / taxonomy part types (categories.part_type). */
const CATALOG_PART_TYPES = ['cpu', 'motherboard', 'memory', 'gpu', 'psu', 'case', 'cooler', 'storage', 'other'];

/** Attribute keys that are hints or builder flags, not rows in a specs table. */
const CATALOG_HIDDEN_ATTRS = ['key_spec_1', 'key_spec_2'];

/** Human labels for attribute keys (specs table). Unknown keys fall back to "Attr key". */
const CATALOG_ATTR_LABELS = [
    'socket' => 'Socket', 'cores' => 'Cores', 'threads' => 'Threads', 'base_ghz' => 'Base clock', 'boost_ghz' => 'Boost clock',
    'tdp_w' => 'Power (TDP)', 'igpu' => 'Integrated graphics', 'bundled_cooler' => 'Cooler in the box',
    'chipset' => 'Chipset', 'memory_type' => 'Memory type', 'form_factor' => 'Form factor', 'm2_slots' => 'M.2 slots', 'wifi' => 'Wi-Fi',
    'capacity_gb' => 'Capacity', 'modules' => 'Modules', 'speed_mts' => 'Speed',
    'chip' => 'Graphics chip', 'brand' => 'Brand', 'vram_gb' => 'Video memory', 'length_mm' => 'Card length', 'min_psu_w' => 'Recommended PSU',
    'wattage' => 'Wattage', 'efficiency' => 'Efficiency', 'modular' => 'Modular cables',
    'form_factors' => 'Motherboard support', 'max_gpu_mm' => 'Max graphics card length', 'max_cooler_mm' => 'Max cooler height',
    'type' => 'Type', 'sockets' => 'Sockets', 'max_tdp_w' => 'Cooling capacity', 'height_mm' => 'Height', 'radiator_mm' => 'Radiator',
    'interface' => 'Interface', 'read_mbs' => 'Read speed', 'internal' => 'Internal drive',
    'size_in' => 'Screen size', 'refresh_hz' => 'Refresh rate', 'resolution' => 'Resolution', 'panel' => 'Panel',
    'connection' => 'Connection', 'dpi' => 'Sensor', 'buttons' => 'Buttons', 'battery' => 'Battery', 'devices' => 'Devices',
    'layout' => 'Layout', 'switches' => 'Switches', 'surround' => 'Sound', 'driver_mm' => 'Drivers',
    'wifi_standard' => 'Wi-Fi standard', 'speed_class' => 'Speed class', 'lan_ports' => 'LAN ports', 'gigabit' => 'Gigabit ports',
    'max_load_kg' => 'Max load', 'armrests' => 'Armrests', 'recline_deg' => 'Recline', 'motors' => 'Motors',
    'height_range_cm' => 'Height range', 'back' => 'Back', 'mechanism' => 'Mechanism', 'headrest' => 'Headrest',
    'width_cm' => 'Width', 'top' => 'Top', 'frame' => 'Frame',
];

/** Units appended to numeric attribute values in the specs table. */
const CATALOG_ATTR_UNITS = [
    'base_ghz' => ' GHz', 'boost_ghz' => ' GHz', 'tdp_w' => ' W', 'capacity_gb' => ' GB', 'speed_mts' => ' MT/s', 'vram_gb' => ' GB',
    'length_mm' => ' mm', 'min_psu_w' => ' W', 'wattage' => ' W', 'max_gpu_mm' => ' mm', 'max_cooler_mm' => ' mm', 'max_tdp_w' => ' W',
    'height_mm' => ' mm', 'radiator_mm' => ' mm', 'read_mbs' => ' MB/s', 'size_in' => '-inch', 'refresh_hz' => ' Hz', 'driver_mm' => ' mm',
    'max_load_kg' => ' kg', 'recline_deg' => '°', 'width_cm' => ' cm', 'height_range_cm' => ' cm', 'dpi' => ' DPI',
];

/** Attribute keys whose value is a 0/1 flag ("Yes" / "No"). */
const CATALOG_ATTR_FLAGS = ['igpu', 'bundled_cooler', 'wifi', 'modular', 'internal', 'gigabit', 'headrest'];

/**
 * One product's attributes, key => value, in sort order.
 *
 * @return array<string, string>  e.g. ['socket' => 'AM5', 'cores' => '6', 'key_spec_1' => 'AM5', …]
 */
function product_attributes(PDO $conn, int $id): array
{
    return products_attributes($conn, [$id])[$id] ?? [];
}

/**
 * Attributes for many products in one query.
 *
 * @param int[] $ids
 * @return array<int, array<string, string>>  product_id => [key => value]; products without rows are absent
 */
function products_attributes(PDO $conn, array $ids): array
{
    $ids = catalog_ids($ids);
    if (!$ids) {
        return [];
    }
    $st = $conn->prepare('SELECT product_id, attr_key, attr_value FROM product_attributes WHERE product_id IN ('
        . catalog_placeholders($ids) . ') ORDER BY product_id, sort_order, attr_key');
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['product_id']][$r['attr_key']] = (string) $r['attr_value'];
    }
    return $out;
}

/**
 * The two short display specs for a card or rate row ("AM5", "6 cores").
 * Uses key_spec_1 / key_spec_2; falls back to the first two visible attributes.
 *
 * @param array<string, string> $attrs from product_attributes()
 * @return string[] 0–2 strings
 */
function key_specs(array $attrs): array
{
    $out = [];
    foreach (['key_spec_1', 'key_spec_2'] as $k) {
        if (isset($attrs[$k]) && trim($attrs[$k]) !== '') {
            $out[] = trim($attrs[$k]);
        }
    }
    if (!$out) {
        foreach (spec_rows($attrs) as $row) {
            $out[] = $row['value'];
            if (count($out) === 2) {
                break;
            }
        }
    }
    return $out;
}

/**
 * Specs table rows: label + formatted value, hint keys removed, flags as Yes/No, units added.
 *
 * @param array<string, string> $attrs
 * @return array<int, array{key:string, label:string, value:string}>
 */
function spec_rows(array $attrs): array
{
    $rows = [];
    foreach ($attrs as $k => $v) {
        if (in_array($k, CATALOG_HIDDEN_ATTRS, true) || $v === '') {
            continue;
        }
        if (in_array($k, CATALOG_ATTR_FLAGS, true)) {
            $value = $v === '1' ? 'Yes' : 'No';
        } elseif ($k === 'sockets' || $k === 'form_factors') {
            $value = str_replace(',', ', ', $v);
        } elseif ($k === 'type' && in_array($v, ['air', 'aio'], true)) {
            $value = $v === 'aio' ? 'All-in-one liquid' : 'Air';
        } elseif ($k === 'capacity_gb' && is_numeric($v) && (int) $v >= 1000) {
            $value = rtrim(rtrim(number_format((int) $v / 1000, 1), '0'), '.') . ' TB';
        } elseif (isset(CATALOG_ATTR_UNITS[$k]) && is_numeric($v)) {
            $value = (abs((float) $v) >= 1000 ? number_format((float) $v) : $v) . CATALOG_ATTR_UNITS[$k];
        } else {
            $value = $v;
        }
        $rows[] = ['key' => $k, 'label' => CATALOG_ATTR_LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k)), 'value' => $value];
    }
    return $rows;
}

/**
 * Today's price against the rate $days ago, per product.
 *
 * "Then" is the newest price_history point recorded at least $days ago (or the oldest point when
 * the history is shorter). No history at all counts as a hold.
 *
 * @param int[] $ids
 * @return array<int, array{price:float, previous:float, delta:float, pct:float, direction:string}>
 *         delta = price - previous (negative = cheaper); direction 'drop' | 'rise' | 'hold'
 */
function price_change(PDO $conn, array $ids, int $days = 7): array
{
    $ids = catalog_ids($ids);
    if (!$ids) {
        return [];
    }
    $days = max(1, $days);
    $st = $conn->prepare('SELECT p.id, p.price,
            COALESCE(
                (SELECT h.price FROM price_history h WHERE h.product_id = p.id AND h.recorded_at <= NOW() - INTERVAL ? DAY ORDER BY h.recorded_at DESC, h.id DESC LIMIT 1),
                (SELECT h.price FROM price_history h WHERE h.product_id = p.id ORDER BY h.recorded_at ASC, h.id ASC LIMIT 1),
                p.price) AS previous
        FROM products p WHERE p.id IN (' . catalog_placeholders($ids) . ')');
    $st->execute(array_merge([$days], $ids));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['id']] = price_change_row((float) $r['price'], (float) $r['previous']);
    }
    return $out;
}

/**
 * Shape one change (shared by price_change(), rate_board(), price_drops()).
 *
 * @return array{price:float, previous:float, delta:float, pct:float, direction:string}
 */
function price_change_row(float $price, float $previous): array
{
    $delta = round($price - $previous, 2);
    return [
        'price' => $price,
        'previous' => $previous,
        'delta' => $delta,
        'pct' => $previous > 0 ? round($delta / $previous * 100, 1) : 0.0,
        'direction' => $delta < 0 ? 'drop' : ($delta > 0 ? 'rise' : 'hold'),
    ];
}

/**
 * Rate history for the sparkline: points from the last $weeks weeks, oldest first.
 *
 * @return array<int, array{date:string, price:float}>  date = Y-m-d of recorded_at
 */
function price_series(PDO $conn, int $id, int $weeks = 12): array
{
    return price_series_many($conn, [$id], $weeks)[$id] ?? [];
}

/**
 * price_series() for many products in one query (rates page, one sparkline per row).
 *
 * @param int[] $ids
 * @return array<int, array<int, array{date:string, price:float}>>
 */
function price_series_many(PDO $conn, array $ids, int $weeks = 12): array
{
    $ids = catalog_ids($ids);
    if (!$ids) {
        return [];
    }
    $st = $conn->prepare('SELECT product_id, price, DATE(recorded_at) AS d FROM price_history
        WHERE product_id IN (' . catalog_placeholders($ids) . ') AND recorded_at >= CURDATE() - INTERVAL ? DAY
        ORDER BY product_id, recorded_at, id');
    $st->execute(array_merge($ids, [max(1, $weeks) * 7]));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['product_id']][] = ['date' => (string) $r['d'], 'price' => (float) $r['price']];
    }
    return $out;
}

/**
 * Low and high of a series ("12-week low Rs 96,000 · high Rs 109,600" on the product page).
 *
 * @param array<int, array{date:string, price:float}> $series
 * @return array{low:?float, high:?float}
 */
function price_range(array $series): array
{
    if (!$series) {
        return ['low' => null, 'high' => null];
    }
    $prices = array_column($series, 'price');
    return ['low' => (float) min($prices), 'high' => (float) max($prices)];
}

/**
 * Record today's rate after a price edit (admin products API). Adds a point only when the price
 * differs from the newest one, so the "newest point = products.price" invariant holds.
 */
function record_price_point(PDO $conn, int $productId, float $price): void
{
    try {
        $st = $conn->prepare('SELECT price FROM price_history WHERE product_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1');
        $st->execute([$productId]);
        $last = $st->fetchColumn();
        if ($last === false || abs((float) $last - $price) >= 0.005) {
            $conn->prepare('INSERT INTO price_history (product_id, price, recorded_at) VALUES (?, ?, NOW())')->execute([$productId, $price]);
        }
    } catch (PDOException $e) {
        log_exception($e, 'price_history');   // table missing before migrate: never block a product save
    }
}

/**
 * SQL condition selecting storefront-visible products (is_active and status 'active').
 * $alias is the products table alias; the fragment has no placeholders.
 */
function catalog_visible_sql(string $alias = 'p'): string
{
    return "$alias.is_active = 1 AND $alias.status = 'active'";
}

/**
 * Today's rate board: the most-watched components and monitors with their weekly change.
 *
 * "Watched" score = 3 × orders in the last 30 days (not cancelled; bulk buys count once) + 2 × wishlist saves
 * + 1 × waiting back-in-stock alerts; ties go to the all-time best seller. There is no page-view
 * log, so wishlists and alerts stand in for "views".
 *
 * @return array<int, array> rows: id, name, price, inventory, category_id, category_name, part_type,
 *         seller_id, score, key_specs (string[]), change (see price_change_row())
 */
function rate_board(PDO $conn, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    $sql = "SELECT p.id, p.name, p.price, p.inventory, p.sold, p.category_id, p.seller_id, c.name AS category_name, c.part_type,
            (3 * COALESCE(o.orders_30d, 0) + 2 * COALESCE(w.n, 0) + COALESCE(a.n, 0)) AS score,
            COALESCE(
                (SELECT h.price FROM price_history h WHERE h.product_id = p.id AND h.recorded_at <= NOW() - INTERVAL 7 DAY ORDER BY h.recorded_at DESC, h.id DESC LIMIT 1),
                p.price) AS previous
        FROM products p
        JOIN categories c ON c.id = p.category_id
        LEFT JOIN (SELECT i.product_id, COUNT(DISTINCT i.order_id) AS orders_30d FROM order_items i JOIN orders od ON od.id = i.order_id
                   WHERE od.order_date >= NOW() - INTERVAL 30 DAY AND od.status <> 'Cancelled' GROUP BY i.product_id) o ON o.product_id = p.id
        LEFT JOIN (SELECT product_id, COUNT(*) AS n FROM wishlists GROUP BY product_id) w ON w.product_id = p.id
        LEFT JOIN (SELECT product_id, COUNT(*) AS n FROM stock_alerts WHERE notified_at IS NULL GROUP BY product_id) a ON a.product_id = p.id
        WHERE " . catalog_visible_sql() . " AND (c.part_type <> 'other' OR c.slug = 'monitors')
        ORDER BY score DESC, p.sold DESC, p.id
        LIMIT $limit";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return catalog_decorate($conn, $rows);
}

/**
 * Products whose rate fell in the last $days days, biggest percentage drop first.
 *
 * @return array<int, array> full product rows (p.*, category_name, part_type) + key_specs + change
 */
function price_drops(PDO $conn, int $limit = 8, int $days = 7): array
{
    $limit = max(1, min(100, $limit));
    $st = $conn->prepare("SELECT * FROM (
            SELECT p.*, c.name AS category_name, c.part_type,
                (SELECT h.price FROM price_history h WHERE h.product_id = p.id AND h.recorded_at <= NOW() - INTERVAL ? DAY ORDER BY h.recorded_at DESC, h.id DESC LIMIT 1) AS previous
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE " . catalog_visible_sql() . "
        ) x
        WHERE x.previous IS NOT NULL AND x.price < x.previous
        ORDER BY (x.previous - x.price) / x.previous DESC, x.id
        LIMIT $limit");
    $st->execute([max(1, $days)]);
    return catalog_decorate($conn, $st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Add key_specs and change to product rows that carry `price` and `previous`.
 *
 * @internal
 */
function catalog_decorate(PDO $conn, array $rows): array
{
    $attrs = products_attributes($conn, array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $r['key_specs'] = key_specs($attrs[(int) $r['id']] ?? []);
        $r['change'] = price_change_row((float) $r['price'], (float) ($r['previous'] ?? $r['price']));
        unset($r['previous']);
    }
    unset($r);
    return $rows;
}

/**
 * Categories as a tree, with storefront counts rolled up into parents.
 *
 * @return array<int, array> top-level categories (sorted by name), each with: id, name, slug,
 *         part_type, parent_id, image, product_count (visible, incl. children), in_stock_count,
 *         from_price (?float, cheapest visible), children (same shape, no further nesting)
 */
function category_tree(PDO $conn): array
{
    $cats = $conn->query("SELECT c.id, c.name, c.slug, c.part_type, c.parent_id, c.image, c.description,
            COUNT(p.id) AS product_count, SUM(p.inventory > 0) AS in_stock_count, MIN(p.price) AS from_price
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND " . catalog_visible_sql() . "
        GROUP BY c.id, c.name, c.slug, c.part_type, c.parent_id, c.image, c.description
        ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
    $byId = [];
    foreach ($cats as $c) {
        $c['id'] = (int) $c['id'];
        $c['parent_id'] = $c['parent_id'] !== null ? (int) $c['parent_id'] : null;
        $c['product_count'] = (int) $c['product_count'];
        $c['in_stock_count'] = (int) $c['in_stock_count'];
        $c['from_price'] = $c['from_price'] !== null ? (float) $c['from_price'] : null;
        $c['children'] = [];
        $byId[$c['id']] = $c;
    }
    $roots = [];
    foreach ($byId as $id => $c) {
        if ($c['parent_id'] !== null && isset($byId[$c['parent_id']])) {
            $byId[$c['parent_id']]['children'][] = $c;
        }
    }
    foreach ($byId as $c) {
        if ($c['parent_id'] === null || !isset($byId[$c['parent_id']])) {
            foreach ($c['children'] as $ch) {
                $c['product_count'] += $ch['product_count'];
                $c['in_stock_count'] += $ch['in_stock_count'];
                if ($ch['from_price'] !== null && ($c['from_price'] === null || $ch['from_price'] < $c['from_price'])) {
                    $c['from_price'] = $ch['from_price'];
                }
            }
            $roots[] = $c;
        }
    }
    return $roots;
}

/**
 * A category id plus its children's ids (catalogue filter "?category=2" covers all components).
 *
 * @return int[]
 */
function category_with_children(PDO $conn, int $categoryId): array
{
    $st = $conn->prepare('SELECT id FROM categories WHERE id = ? OR parent_id = ?');
    $st->execute([$categoryId, $categoryId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) ?: [$categoryId];
}

/**
 * The seller of a product (falls back to the house seller, id 1).
 *
 * @return array|null sellers row (id, slug, name, market, address, city, phone, whatsapp, rating, …) or null
 */
function seller_for(PDO $conn, int $productId): ?array
{
    $st = $conn->prepare('SELECT s.* FROM products p JOIN sellers s ON s.id = p.seller_id WHERE p.id = ?');
    $st->execute([$productId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $st = $conn->query('SELECT * FROM sellers WHERE id = 1');
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * All sellers by id (cached per request) — for "Sold by …" lines on many cards.
 *
 * @return array<int, array> seller_id => sellers row
 */
function sellers_by_id(PDO $conn): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($conn->query('SELECT * FROM sellers ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $cache[(int) $s['id']] = $s;
            }
        } catch (PDOException $e) {
            log_exception($e, 'sellers');
        }
    }
    return $cache;
}

/** @internal positive unique ints */
function catalog_ids(array $ids): array
{
    return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
}

/** @internal "?, ?, ?" */
function catalog_placeholders(array $ids): string
{
    return implode(', ', array_fill(0, count($ids), '?'));
}
