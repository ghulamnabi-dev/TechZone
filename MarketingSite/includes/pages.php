<?php
/**
 * Helpers for the storefront feature pages (builder, rates, deals, wishlist, compare, sellers,
 * sell, help, store). Presentation only; reads go through core/*. Styles live in assets/css/pages.css.
 * Include after includes/header.php (needs format.php and $conn).
 */

require_once __DIR__ . '/../../core/catalog.php';
require_once __DIR__ . '/../../core/settings.php';

if (!function_exists('pg_e')) {
    function pg_e($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Shared product cards (render_product_card() in includes/format.php) for many rows, primed in
 * one batch when the card supports it.
 */
function pg_cards(array $rows, array $promotions = []): string
{
    if (function_exists('storefront_card_prime') && $rows) {
        storefront_card_prime($GLOBALS['conn'], $rows);
    }
    $h = '';
    foreach ($rows as $p) {
        $h .= render_product_card($p, $promotions);
    }
    return $h;
}

/**
 * Visible products with category, key specs, weekly change and seller (for cards and tables).
 *
 * @param string $where extra SQL on aliases p / c (no user input; use $params)
 */
function pg_products(PDO $conn, string $where = '1 = 1', array $params = [], string $order = 'p.name'): array
{
    $st = $conn->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.part_type, c.parent_id AS category_parent
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE ' . catalog_visible_sql() . ' AND (' . $where . ') ORDER BY ' . $order);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static fn($r) => (int) $r['id'], $rows);
    $attrs = products_attributes($conn, $ids);
    $changes = price_change($conn, $ids);
    foreach ($rows as &$r) {
        $id = (int) $r['id'];
        $r['attrs'] = $attrs[$id] ?? [];
        $r['key_specs'] = key_specs($r['attrs']);
        $r['change'] = $changes[$id] ?? null;
    }
    unset($r);
    return $rows;
}

/** Store details from Settings → Store (with the shop's real defaults). */
function pg_store(): array
{
    $wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', '923011427871'));
    $waLocal = strlen($wa) === 12 && str_starts_with($wa, '92') ? '0' . substr($wa, 2, 3) . ' ' . substr($wa, 5) : $wa;
    return [
        'name' => (string) setting('store_name', 'TechZone'),
        'address_line' => (string) setting('store_address_line', 'Hafeez Centre, Shop 214, 2nd floor'),
        'address' => (string) setting('store_address', 'Shop 214, 2nd floor, Hafeez Centre, Gulberg III, Lahore'),
        'phone' => (string) setting('store_phone', '042-35761234'),
        'email' => (string) setting('store_email', 'support@techzone.pk'),
        'hours_text' => (string) setting('store_hours', 'Mon–Sat 11 am – 10 pm, Sunday closed'),
        'whatsapp' => $wa,
        'whatsapp_text' => $waLocal,
        'since' => 2019,
    ];
}

/**
 * The week's opening hours: [['day' => 'Monday', 'short' => 'Mon', 'hours' => '11 am – 10 pm' | null, 'today' => bool], …].
 * Reads "Mon–Sat 11 am – 10 pm, Sunday closed" style text; anything else falls back to those hours.
 */
function pg_week_hours(string $text): array
{
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $open = '11 am – 10 pm';
    $closed = ['Sunday'];
    if (preg_match('/^\s*Mon\s*[–-]\s*Sat\s+(.+?)\s*,\s*Sun(day)?\s+closed\s*$/iu', $text, $m)) {
        $open = trim($m[1]);
    } elseif (preg_match('/^\s*(?:Daily|Mon\s*[–-]\s*Sun)\s+(.+)$/iu', $text, $m)) {
        $open = trim($m[1]);
        $closed = [];
    }
    $today = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Karachi')))->format('N') - 1;
    $out = [];
    foreach ($days as $i => $d) {
        $out[] = ['day' => $d, 'short' => substr($d, 0, 3), 'hours' => in_array($d, $closed, true) ? null : $open, 'today' => $i === $today];
    }
    return $out;
}

/** "Open today 11 am – 10 pm" / "Closed today". */
function pg_today_line(array $week): string
{
    foreach ($week as $d) {
        if ($d['today']) {
            return $d['hours'] ? 'Open today ' . $d['hours'] : 'Closed today, open tomorrow';
        }
    }
    return '';
}

/**
 * Abstract map of Gulberg: a few roads as strokes, one signal pin on Hafeez Centre. Original
 * drawing (no tiles), decorative, labelled for screen readers.
 */
function pg_store_map(string $class = 'store-map'): string
{
    return '<svg class="' . pg_e($class) . '" viewBox="0 0 640 420" role="img" aria-label="Map: Hafeez Centre on Main Boulevard, Gulberg III, between Liberty Market and Kalma Chowk" preserveAspectRatio="xMidYMid meet">'
        . '<rect class="store-map__ground" width="640" height="420"/>'
        // blocks
        . '<g class="store-map__blocks">'
        . '<rect x="40" y="40" width="150" height="96" rx="4"/><rect x="230" y="30" width="120" height="70" rx="4"/><rect x="430" y="40" width="170" height="90" rx="4"/>'
        . '<rect x="50" y="250" width="130" height="120" rx="4"/><rect x="420" y="250" width="180" height="130" rx="4"/><rect x="250" y="300" width="120" height="90" rx="4"/>'
        . '</g>'
        // minor roads
        . '<g class="store-map__road store-map__road--minor">'
        . '<path d="M0 190 H640"/><path d="M210 0 V420"/><path d="M400 0 V420"/><path d="M0 330 C150 320 260 360 640 320"/>'
        . '</g>'
        // main boulevard (diagonal) and a roundabout at Liberty
        . '<g class="store-map__road store-map__road--main">'
        . '<path d="M-20 400 L660 40"/>'
        . '</g>'
        . '<circle class="store-map__roundabout" cx="140" cy="337" r="22"/>'
        . '<circle class="store-map__roundabout" cx="545" cy="101" r="18"/>'
        . '<g class="store-map__labels">'
        . '<text x="172" y="376">Liberty Market</text>'
        . '<text x="470" y="72">Kalma Chowk</text>'
        . '<text x="196" y="304" transform="rotate(-27.9 196 304)">Main Boulevard</text>'
        . '<text x="430" y="290">Gulberg III</text>'
        . '</g>'
        // pin
        . '<g class="store-map__pin" transform="translate(338 214)">'
        . '<path d="M0 0 C-14 -18 -20 -28 -20 -38 a20 20 0 0 1 40 0 c0 10 -6 20 -20 38z"/>'
        . '<circle cx="0" cy="-38" r="7"/>'
        . '</g>'
        . '<text class="store-map__pin-label" x="364" y="176">Hafeez Centre</text>'
        . '</svg>';
}

/** Installment plans from Settings (banks, months, markup, minimum). */
function pg_installments(): array
{
    $p = setting('installment_plans', null);
    $p = is_array($p) ? $p : [];
    return [
        'banks' => array_values(array_filter(array_map('strval', (array) ($p['banks'] ?? ['Meezan', 'HBL', 'Alfalah'])))),
        'months' => array_values(array_map('intval', (array) ($p['months'] ?? [3, 6, 12]))),
        'markup_pct' => (float) ($p['markup_pct'] ?? 0),
        'min_amount' => (float) ($p['min_amount'] ?? 20000),
    ];
}

/** "Meezan, HBL and Alfalah" */
function pg_list_and(array $items): string
{
    $items = array_values($items);
    if (count($items) <= 1) {
        return (string) ($items[0] ?? '');
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
}

/**
 * Live promotions with their conditions written as a sentence.
 *
 * @return array<int, array{code:string, title:string, sentence:string, ends:?string, kind:string}>
 */
function pg_live_promotions(PDO $conn): array
{
    try {
        $rows = $conn->query("SELECT p.*, c.name AS cat_name, pr.name AS product_name,
                COALESCE(p.ends_at, TIMESTAMP(p.end_date, '23:59:59')) AS ends
            FROM promotions p
            LEFT JOIN categories c ON p.applies_to = 'category' AND c.id = p.target_id
            LEFT JOIN products pr ON p.applies_to = 'product' AND pr.id = p.target_id
            WHERE p.is_active = 1 AND p.promo_code IS NOT NULL AND p.promo_code <> ''
              AND (COALESCE(p.starts_at, TIMESTAMP(p.start_date)) IS NULL OR COALESCE(p.starts_at, TIMESTAMP(p.start_date)) <= NOW())
              AND (COALESCE(p.ends_at, TIMESTAMP(p.end_date, '23:59:59')) IS NULL OR COALESCE(p.ends_at, TIMESTAMP(p.end_date, '23:59:59')) >= NOW())
              AND (p.usage_limit IS NULL OR p.used_count < p.usage_limit)
            ORDER BY p.display_order, p.id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_exception($e, 'deals');
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $target = match ($r['applies_to']) {
            'category' => $r['cat_name'] ? 'everything in ' . $r['cat_name'] : 'one category',
            'product' => $r['product_name'] ?: 'one product',
            default => 'your whole order',
        };
        $pct = rtrim(rtrim(number_format((float) $r['discount_percentage'], 2), '0'), '.');
        $what = match ($r['kind']) {
            'percent' => $pct . '% off ' . $target,
            'fixed' => format_pkr($r['discount_amount']) . ' off ' . $target,
            'free_shipping' => 'Free delivery anywhere in Pakistan',
            default => (string) $r['title'],
        };
        $cond = [];
        if ((float) $r['min_order'] > 0) {
            $cond[] = 'on orders of ' . format_pkr($r['min_order']) . ' or more';
        }
        $sentence = $what . ($cond ? ' ' . implode(', ', $cond) : '') . '.';
        if ($r['usage_limit'] !== null) {
            $left = max(0, (int) $r['usage_limit'] - (int) $r['used_count']);
            $sentence .= ' ' . number_format($left) . ' uses left.';
        }
        $sentence .= ' One code per order.';
        $out[] = [
            'code' => strtoupper((string) $r['promo_code']),
            'title' => (string) $r['title'],
            'sentence' => $sentence,
            'ends' => $r['ends'] ? (new DateTimeImmutable($r['ends']))->format('j F Y') : null,
            'kind' => (string) $r['kind'],
            'link' => $r['applies_to'] === 'category' && $r['target_id'] ? site_url('shop/products.php?category=' . (int) $r['target_id'])
                : ($r['applies_to'] === 'product' && $r['target_id'] ? site_url('shop/product-details.php?id=' . (int) $r['target_id']) : site_url('shop/products.php')),
        ];
    }
    return $out;
}

/** Month-year a seller joined ("October 2025"). */
function pg_month_year(?string $date): string
{
    if (!$date) {
        return '';
    }
    try {
        return (new DateTimeImmutable($date))->format('F Y');
    } catch (Exception $e) {
        return '';
    }
}

/** wa.me link with an optional prefilled message. */
function pg_whatsapp_url(string $number, string $text = ''): string
{
    $n = preg_replace('/\D/', '', $number);
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Spec glyph SVG per part type, for client-side spec tiles (builder, compare). */
function pg_glyph_map(): array
{
    $out = [];
    foreach (['cpu', 'motherboard', 'memory', 'gpu', 'psu', 'case', 'cooler', 'storage', 'other'] as $t) {
        $out[$t] = spec_glyph($t, 28);
    }
    return $out;
}

/** Account area header: title + tabs (Orders, Profile, Wishlist). */
function pg_account_head(string $active, string $title, string $sub = ''): string
{
    $tabs = ['orders' => ['Orders', 'account/orders.php'], 'profile' => ['Profile and password', 'account/profile.php'], 'wishlist' => ['Wishlist', 'wishlist.php']];
    $h = '<header class="acct-head"><div><h1 class="pg-title">' . pg_e($title) . '</h1>' . ($sub !== '' ? '<p class="pg-lead">' . $sub . '</p>' : '') . '</div>'
       . '<nav class="acct-tabs" aria-label="Your account">';
    foreach ($tabs as $k => [$label, $path]) {
        $h .= '<a href="' . pg_e(site_url($path)) . '"' . ($k === $active ? ' aria-current="page"' : '') . '>' . pg_e($label) . '</a>';
    }
    return $h . '<a href="' . pg_e(site_url('auth/logout.php')) . '">Sign out</a></nav></header>';
}

/** Status pill for an order status (Pending, Processing, Shipped, Delivered, Cancelled). */
function pg_status_pill(string $status): string
{
    $k = strtolower(preg_replace('/[^a-z]/i', '', $status));
    return '<span class="acct-pill acct-pill--' . pg_e($k) . '">' . pg_e($status) . '</span>';
}
