<?php
/**
 * Storefront presentation helpers (no I/O): money formatting and promotion badges.
 * Included from includes/header.php.
 */

if (!function_exists('format_pkr')) {
    /**
     * "Rs 8,999" — whole rupees, thousands separators, sign in front for negatives ("-Rs 500").
     */
    function format_pkr($amount): string
    {
        $n = (float) $amount;
        $sign = $n < 0 ? '-' : '';
        return $sign . 'Rs ' . number_format(abs(round($n)), 0, '.', ',');
    }
}

if (!function_exists('format_pkr_delta')) {
    /** Price modifier for a variant: "+Rs 500", "-Rs 250". */
    function format_pkr_delta($amount): string
    {
        $n = (float) $amount;
        return ($n > 0 ? '+' : '') . format_pkr($n);
    }
}

if (!function_exists('promo_badge_for_product')) {
    /**
     * Returns a short badge label ("10% off") when one of the given (already-active) promotions
     * targets this product, either through its category (link_url ...?category=N) or the product
     * itself (link_url ...?id=N). Store-wide offers (no target) do not badge individual products.
     */
    function promo_badge_for_product(array $product, array $promotions): ?string
    {
        foreach ($promotions as $promo) {
            $query = parse_url((string) ($promo['link_url'] ?? ''), PHP_URL_QUERY);
            if (!$query) {
                continue;
            }
            parse_str($query, $params);
            $hitsCategory = isset($params['category']) && (string) $params['category'] === (string) ($product['category_id'] ?? '');
            $hitsProduct = isset($params['id']) && (string) $params['id'] === (string) ($product['id'] ?? '');
            if (!$hitsCategory && !$hitsProduct) {
                continue;
            }
            if ((float) ($promo['discount_percentage'] ?? 0) > 0) {
                return rtrim(rtrim(number_format((float) $promo['discount_percentage'], 2), '0'), '.') . '% off';
            }
            if ((float) ($promo['discount_amount'] ?? 0) > 0) {
                return format_pkr($promo['discount_amount']) . ' off';
            }
        }
        return null;
    }
}

if (!function_exists('promo_price_for_product')) {
    /**
     * Same targeting rule as promo_badge_for_product(), but returns the price the customer pays
     * with the promotion's code: ['price' => float, 'was' => float, 'code' => string, 'label' => string].
     */
    function promo_price_for_product(array $product, array $promotions): ?array
    {
        $label = promo_badge_for_product($product, $promotions);
        if ($label === null) {
            return null;
        }
        $price = (float) ($product['price'] ?? 0);
        foreach ($promotions as $promo) {
            $query = parse_url((string) ($promo['link_url'] ?? ''), PHP_URL_QUERY);
            if (!$query) {
                continue;
            }
            parse_str($query, $params);
            $hits = (isset($params['category']) && (string) $params['category'] === (string) ($product['category_id'] ?? ''))
                || (isset($params['id']) && (string) $params['id'] === (string) ($product['id'] ?? ''));
            if (!$hits) {
                continue;
            }
            $pct = (float) ($promo['discount_percentage'] ?? 0);
            $amt = (float) ($promo['discount_amount'] ?? 0);
            $new = $pct > 0 ? $price * (1 - $pct / 100) : max(0, $price - $amt);
            return ['price' => $new, 'was' => $price, 'code' => (string) ($promo['promo_code'] ?? ''), 'label' => $label];
        }
        return null;
    }
}

if (!function_exists('tz_icon')) {
    /** Small inline stroke icons (16px default), aria-hidden. */
    function tz_icon(string $name, int $size = 16): string
    {
        static $paths = [
            'truck'   => '<path d="M3 6h11v9H3z"/><path d="M14 9h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
            'rotate'  => '<path d="M4 12a8 8 0 0 1 14-5.3L20 8"/><path d="M20 4v4h-4"/><path d="M20 12a8 8 0 0 1-14 5.3L4 16"/><path d="M4 20v-4h4"/>',
            'shield'  => '<path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
            'pin'     => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
            'cart'    => '<path d="M3 4h2.2l2.3 11h10.3l2-8H6.4"/><circle cx="9" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/>',
            'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20c1.2-3.6 4-5.5 7.5-5.5s6.3 1.9 7.5 5.5"/>',
            'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
            'phone'   => '<path d="M5 4h4l1.5 4.5-2.3 1.4a11 11 0 0 0 6 6l1.4-2.3L20 15v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
            'mail'    => '<rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="M4 7l8 6 8-6"/>',
            'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'filter'  => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'chevron' => '<path d="M9 6l6 6-6 6"/>',
            'down'    => '<path d="M6 9l6 6 6-6"/>',
            'trash'   => '<path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12"/>',
            'check'   => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
            'cash'    => '<rect x="3" y="6.5" width="18" height="11" rx="1.5"/><circle cx="12" cy="12" r="2.5"/>',
            'heart'   => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.4C19.5 15.4 12 20 12 20z"/>',
            'whatsapp'=> '<path d="M4.5 19.5l1.2-3.6A8 8 0 1 1 8.6 18.6z"/><path d="M9.3 8.6c.2-.4.5-.4.8-.4h.4c.2 0 .4.1.5.4l.6 1.5c.1.2 0 .4-.1.6l-.5.6c.6 1.1 1.5 2 2.6 2.6l.6-.5c.2-.1.4-.2.6-.1l1.5.6c.3.1.4.3.4.5v.4c0 .3 0 .6-.4.8-.5.3-1.2.5-1.9.3-2.4-.7-4.5-2.8-5.2-5.2-.2-.7 0-1.4.3-1.9z"/>',
            'store'   => '<path d="M4 9.5V20h16V9.5"/><path d="M3 9.5L5 4h14l2 5.5c0 1.4-1.1 2.5-2.5 2.5S16 10.9 16 9.5c0 1.4-1.1 2.5-2.5 2.5h-3C9.1 12 8 10.9 8 9.5 8 10.9 6.9 12 5.5 12S3 10.9 3 9.5z"/><path d="M10 20v-5h4v5"/>',
            'wallet'  => '<path d="M4 7.5h15a1 1 0 0 1 1 1V18a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6.5A1.5 1.5 0 0 1 5.5 5H17"/><circle cx="16" cy="13.5" r="1.2"/>',
        ];
        $p = $paths[$name] ?? '';
        return '<svg class="tz-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }
}

if (!function_exists('stock_line')) {
    /** "In stock" / "Only 3 left" / "Out of stock" with a status dot. */
    function stock_line(int $inventory, string $extraClass = ''): string
    {
        if ($inventory <= 0) {
            $state = 'out';
            $text = 'Out of stock';
        } elseif ($inventory <= 5) {
            $state = 'low';
            $text = 'Only ' . $inventory . ' left';
        } else {
            $state = 'ok';
            $text = 'In stock';
        }
        return '<p class="stock stock--' . $state . ($extraClass !== '' ? ' ' . $extraClass : '') . '"><span class="stock__dot" aria-hidden="true"></span>' . $text . '</p>';
    }
}

if (!function_exists('rating_stars')) {
    /** Five small stars (filled to the nearest half) as one accessible image. */
    function rating_stars(float $avg, int $size = 14): string
    {
        $avg = max(0, min(5, $avg));
        $out = '<span class="stars" role="img" aria-label="Rated ' . number_format($avg, 1) . ' out of 5">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $avg >= $i ? 'full' : ($avg >= $i - 0.5 ? 'half' : 'empty');
            $out .= '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 20 20" class="star star--' . $fill . '" aria-hidden="true" focusable="false">'
                . '<path d="M10 1.8l2.5 5.3 5.8.7-4.3 4 1.1 5.7L10 14.7l-5.1 2.8 1.1-5.7-4.3-4 5.8-.7z"/></svg>';
        }
        return $out . '</span>';
    }
}

if (!function_exists('price_tag')) {
    /** The yellow tag (hero, sale cards, buy box only). */
    function price_tag(float $amount, string $size = 'sm', string $extra = ''): string
    {
        return '<span class="price-tag price-tag--' . $size . ($extra !== '' ? ' ' . $extra : '') . '">' . format_pkr($amount) . '</span>';
    }
}

if (!function_exists('storefront_card_prime')) {
    /**
     * Preload what product cards need (key specs, weekly change, seller, wishlist state) for many
     * products in a few queries. render_product_card() primes single products on demand, so calling
     * this first is an optimisation, not a requirement.
     *
     * @param array $rows product rows (need 'id'; 'key_specs' / 'change' are used when already present)
     */
    function storefront_card_prime(PDO $conn, array $rows): void
    {
        $store = &storefront_card_store();
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0 && !isset($store['meta'][$id])) {
                $ids[] = $id;
            }
        }
        if (!isset($store['wish'])) {
            $store['wish'] = [];
            if (function_exists('wishlist_ids')) {
                try {
                    $store['wish'] = array_flip(wishlist_ids($conn));
                } catch (Throwable $e) {
                    $store['wish'] = [];
                }
            }
        }
        if (!$ids || !function_exists('products_attributes')) {
            return;
        }
        try {
            $attrs = products_attributes($conn, $ids);
            $changes = price_change($conn, $ids, 7);
            $sellers = sellers_by_id($conn);
        } catch (Throwable $e) {
            $attrs = $changes = $sellers = [];
        }
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0 || isset($store['meta'][$id])) {
                continue;
            }
            $seller = $sellers[(int) ($r['seller_id'] ?? 1)] ?? null;
            $store['meta'][$id] = [
                'attrs' => $attrs[$id] ?? [],
                'key_specs' => $r['key_specs'] ?? key_specs($attrs[$id] ?? []),
                'change' => $r['change'] ?? ($changes[$id] ?? null),
                'seller' => $seller,
            ];
        }
    }

    /** @internal per-request card data */
    function &storefront_card_store(): array
    {
        static $store = ['meta' => []];
        return $store;
    }

    /** Card data for one product (primes it when missing). */
    function storefront_card_meta(array $p): array
    {
        $id = (int) ($p['id'] ?? 0);
        $store = &storefront_card_store();
        if (!isset($store['meta'][$id]) && ($GLOBALS['conn'] ?? null) instanceof PDO) {
            storefront_card_prime($GLOBALS['conn'], [$p]);
        }
        return $store['meta'][$id] ?? ['attrs' => [], 'key_specs' => $p['key_specs'] ?? [], 'change' => $p['change'] ?? null, 'seller' => null];
    }

    /** Whether the visitor saved this product (after storefront_card_prime()). */
    function storefront_is_wished(int $id): bool
    {
        $store = &storefront_card_store();
        if (!isset($store['wish']) && ($GLOBALS['conn'] ?? null) instanceof PDO) {
            storefront_card_prime($GLOBALS['conn'], []);
        }
        return isset($store['wish'][$id]);
    }
}

if (!function_exists('render_wish_button')) {
    /** Heart toggle (api/wishlist.php, guests too). $class adds placement classes. */
    function render_wish_button(array $p, string $class = 'pcard__heart', bool $withLabel = false): string
    {
        $id = (int) $p['id'];
        $on = storefront_is_wished($id);
        $name = htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="wish ' . $class . '" data-wish="' . $id . '" aria-pressed="' . ($on ? 'true' : 'false') . '"'
            . ($withLabel ? '' : ' aria-label="Save ' . $name . ' to your wishlist"') . '>'
            . tz_icon('heart', 20) . ($withLabel ? '<span class="wish__label" data-on="Saved" data-off="Save">' . ($on ? 'Saved' : 'Save') . '</span>' : '')
            . '</button>';
    }
}

if (!function_exists('render_key_specs')) {
    /** Two key specs as separate spans (hairline between them in CSS, no middle dots). */
    function render_key_specs(array $specs, string $class = 'pcard__specs'): string
    {
        $specs = array_slice(array_values(array_filter(array_map('strval', $specs), static fn($s) => trim($s) !== '')), 0, 2);
        if (!$specs) {
            return '';
        }
        $h = '<p class="specs ' . $class . '">';
        foreach ($specs as $s) {
            $h .= '<span>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        return $h . '</p>';
    }
}

if (!function_exists('render_product_card')) {
    /**
     * Shared storefront product card (docs/STOREFRONT.md "Components"): image well or spec tile,
     * price-drop tag, wishlist heart, name, two key specs, condensed price, stock, seller, compare
     * checkbox, add to cart (POST form + CSRF; hover on fine pointers, always on touch).
     *
     * Call as render_product_card($p), render_product_card($p, $promotions, $rating) (legacy) or
     * render_product_card($p, ['promotions' => [...], 'heading' => 'h3']).
     * Prime many cards first with storefront_card_prime($conn, $rows) to batch the queries.
     */
    function render_product_card(array $p, array $promotions = [], ?array $rating = null, array $opts = []): string
    {
        if ($promotions && array_keys($promotions) !== range(0, count($promotions) - 1)) {
            $opts = $promotions;   // called with an options array
            $promotions = $opts['promotions'] ?? [];
        }
        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $id = (int) $p['id'];
        $url = site_url('shop/product-details.php?id=' . $id);
        $inv = (int) ($p['inventory'] ?? 0);
        $sale = promo_price_for_product($p, $promotions);
        $meta = storefront_card_meta($p);
        $chg = $meta['change'];
        $dropped = $chg && ($chg['direction'] ?? '') === 'drop';
        $tagH = $opts['heading'] ?? 'h3';
        $tagH = in_array($tagH, ['h2', 'h3', 'h4'], true) ? $tagH : 'h3';

        $h = '<article class="pcard' . ($inv <= 0 ? ' pcard--out' : '') . '" data-product="' . $id . '">';
        $h .= '<div class="pcard__media">';
        $h .= render_product_media($p, ['width' => 480]);
        if ($dropped) {
            $h .= '<span class="price-tag price-tag--sm price-tag--figure pcard__tag"><span class="visually-hidden">Down </span>▼ '
                . format_pkr(abs((float) $chg['delta'])) . '<span class="visually-hidden"> this week</span></span>';
        }
        $h .= render_wish_button($p);
        $h .= '</div>';
        $h .= '<div class="pcard__body">';
        $h .= '<' . $tagH . ' class="pcard__name"><a class="pcard__link" href="' . $e($url) . '">' . $e($p['name']) . '</a></' . $tagH . '>';
        $h .= render_key_specs($meta['key_specs']);
        $h .= '<p class="pcard__price">' . format_pkr($sale ? $sale['price'] : $p['price']) . '</p>';
        if ($sale) {
            $h .= '<p class="pcard__was"><s>' . format_pkr($sale['was']) . '</s>' . ($sale['code'] !== '' ? ' with code ' . $e($sale['code']) : '') . '</p>';
        }
        $h .= stock_line($inv, 'pcard__stock');
        if (!empty($meta['seller']['name'])) {
            $h .= '<p class="pcard__seller">Sold by ' . $e($meta['seller']['name']) . '</p>';
        }
        $h .= '</div>';
        $h .= '<label class="pcard__compare"><input type="checkbox" class="compare-check" data-compare="' . $id . '" data-name="' . $e($p['name']) . '"><span>Compare</span></label>';
        if ($inv > 0) {
            $h .= '<div class="pcard__cta">' . cart_add_form($id, 'btn--primary btn--block pcard__add', 'Add to cart') . '</div>';
        }
        return $h . '</article>';
    }
}

if (!function_exists('format_phone_pk')) {
    /** "923011427871" / "+92 301 1427871" → "0301 1427871"; landlines are returned as given. */
    function format_phone_pk(string $n): string
    {
        $d = preg_replace('/\D+/', '', $n);
        if (strlen($d) === 12 && strpos($d, '923') === 0) {
            return '0' . substr($d, 2, 3) . ' ' . substr($d, 5);
        }
        if (strlen($d) === 11 && strpos($d, '03') === 0) {
            return substr($d, 0, 4) . ' ' . substr($d, 4);
        }
        return trim($n);
    }

    /** Digits for wa.me ("0301 1427871" → "923011427871"). */
    function whatsapp_digits(string $n): string
    {
        $d = preg_replace('/\D+/', '', $n);
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = '92' . substr($d, 1);
        }
        return $d;
    }
}

if (!function_exists('store_hours_today')) {
    /**
     * "Open today 11 am – 10 pm" / "Closed today" from the store_hours setting
     * ("Mon–Sat 11 am – 10 pm, Sunday closed").
     */
    function store_hours_today(string $hours, ?int $weekday = null): string
    {
        $weekday = $weekday ?? (int) date('w');   // 0 = Sunday
        if ($hours === '') {
            return '';
        }
        if ($weekday === 0 && preg_match('/sun\w*\s+closed/iu', $hours)) {
            return 'Closed today, open Monday 11 am';
        }
        if (preg_match('/(\d{1,2}(?::\d{2})?\s*[ap]m)\s*[–-]\s*(\d{1,2}(?::\d{2})?\s*[ap]m)/iu', $hours, $m)) {
            return 'Open today ' . $m[1] . ' – ' . $m[2];
        }
        return $hours;
    }
}

if (!function_exists('recently_viewed_ids')) {
    /** Product ids from $_SESSION['recently_viewed'] (newest first), optionally excluding one. */
    function recently_viewed_ids(int $excludeId = 0, int $limit = 6): array
    {
        $ids = isset($_SESSION['recently_viewed']) && is_array($_SESSION['recently_viewed']) ? $_SESSION['recently_viewed'] : [];
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($i) => $i > 0 && $i !== $excludeId));
        return array_slice($ids, 0, $limit);
    }
}

if (!function_exists('remember_recently_viewed')) {
    /** Push a product id to the front of the session list (deduped, max 8). */
    function remember_recently_viewed(int $productId): void
    {
        if ($productId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $ids = recently_viewed_ids($productId, 8);
        array_unshift($ids, $productId);
        $_SESSION['recently_viewed'] = array_slice($ids, 0, 8);
    }
}
/* ==========================================================================
   Storefront v2: product images, spec tiles, JSON shape (docs/STOREFRONT.md "Imagery")
   ========================================================================== */

if (!function_exists('product_image_url')) {
    /**
     * Web URL of a product's photo, or null when it has none (render a spec tile instead).
     * Order: products.imagelink (what the admin shows and replaces; skipped when a local file is
     * missing) → AdminPortal/uploads/catalog/{id:02d}-*.{png,jpg,jpeg,webp} (a photo dropped in before
     * `php database/migrate.php` links it) → null. The catalog folder is listed once per request.
     *
     * @param array $p product row with 'id' (or 'product_id') and optionally 'imagelink'
     */
    function product_image_url(array $p): ?string
    {
        static $catalog = null;
        if ($catalog === null) {
            $catalog = [];
            $root = function_exists('admin_portal_filesystem_root') ? admin_portal_filesystem_root() : '';
            $dir = $root !== '' ? $root . '/uploads/catalog' : '';
            $files = $dir !== '' && is_dir($dir) ? (scandir($dir) ?: []) : [];
            foreach ($files as $f) {
                if (preg_match('/^(\d{2,})-[^\/\\\\]*\.(png|jpe?g|webp)$/i', $f, $m) && !isset($catalog[(int) $m[1]])) {
                    $catalog[(int) $m[1]] = $f;
                }
            }
        }
        // 1. the product's own image (what the admin shows and replaces), when it exists
        $link = trim(str_replace('\\', '/', (string) ($p['imagelink'] ?? '')));
        if ($link !== '' && preg_match('#^https?://#i', $link)) {
            return $link;
        }
        if ($link !== '') {
            $rel = ltrim($link, '/');
            $pos = strpos($rel, 'uploads/');
            if ($pos === false) {
                return $link;
            }
            $rel = substr($rel, $pos);
            $root = function_exists('admin_portal_filesystem_root') ? admin_portal_filesystem_root() : '';
            if ($root === '' || is_file($root . '/' . $rel)) {
                return admin_portal_asset_url(implode('/', array_map('rawurlencode', explode('/', $rel))));
            }
        }
        // 2. a catalogue photo dropped into uploads/catalog/NN-*.webp before the DB was migrated
        $id = (int) ($p['id'] ?? $p['product_id'] ?? 0);
        if ($id > 0 && isset($catalog[$id])) {
            return admin_portal_asset_url('uploads/catalog/' . rawurlencode($catalog[$id]));
        }
        // 3. none: the caller renders the spec tile
        return null;
    }
}

if (!function_exists('product_part_type')) {
    /**
     * Part type of a product (cpu, motherboard, memory, gpu, psu, case, cooler, storage, other) from
     * $p['part_type'] or its category; categories are read once per request. Monitors report 'monitor'.
     */
    function product_part_type(array $p): string
    {
        static $byCat = null;
        if ($byCat === null) {
            $byCat = [];
            $cats = $GLOBALS['tz_categories'] ?? null;
            if (!is_array($cats) || ($cats && !array_key_exists('part_type', $cats[0]))) {
                $cats = [];
                $conn = $GLOBALS['conn'] ?? null;
                if ($conn instanceof PDO) {
                    try {
                        $cats = $conn->query('SELECT id, name, slug, part_type FROM categories')->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $cats = [];
                    }
                }
            }
            foreach ($cats as $c) {
                $type = (string) ($c['part_type'] ?? 'other');
                if ($type === 'other' && (($c['slug'] ?? '') === 'monitors' || strcasecmp((string) $c['name'], 'Monitors') === 0)) {
                    $type = 'monitor';
                }
                $byCat[(int) $c['id']] = $type;
            }
        }
        if (!empty($p['part_type']) && $p['part_type'] !== 'other') {
            return (string) $p['part_type'];
        }
        return $byCat[(int) ($p['category_id'] ?? 0)] ?? 'other';
    }
}

if (!function_exists('spec_glyph')) {
    /**
     * Outlined category glyph (inline SVG, 64-unit grid, 1.5px graphite stroke at any size).
     * Types: cpu, motherboard, memory, gpu, psu, case, cooler, storage; anything else gets the
     * generic display glyph (monitors, peripherals, other).
     */
    function spec_glyph(string $type, int $size = 64): string
    {
        static $g = [
            'cpu' => '<rect x="16" y="16" width="32" height="32" rx="3"/><rect x="24" y="24" width="16" height="16" rx="1.5"/><path d="M23 9v7M29 9v7M35 9v7M41 9v7M23 48v7M29 48v7M35 48v7M41 48v7M9 23h7M9 29h7M9 35h7M9 41h7M48 23h7M48 29h7M48 35h7M48 41h7"/>',
            'motherboard' => '<rect x="10" y="8" width="44" height="48" rx="3"/><rect x="17" y="15" width="16" height="16" rx="1.5"/><path d="M40 14v20M45 14v20M17 40h30M17 47h22"/><circle cx="46" cy="47" r="2.5"/><path d="M10 20H7M10 26H7"/>',
            'memory' => '<rect x="5" y="20" width="54" height="18" rx="2"/><rect x="10" y="24" width="8" height="9" rx="1"/><rect x="21" y="24" width="8" height="9" rx="1"/><rect x="35" y="24" width="8" height="9" rx="1"/><rect x="46" y="24" width="8" height="9" rx="1"/><path d="M5 38v6h25v-3h4v3h25v-6"/>',
            'gpu' => '<rect x="6" y="16" width="48" height="28" rx="3"/><circle cx="21" cy="30" r="8.5"/><circle cx="21" cy="30" r="2"/><circle cx="40" cy="30" r="8.5"/><circle cx="40" cy="30" r="2"/><path d="M54 12v36M54 12h4M14 44v5h24v-5"/>',
            'psu' => '<rect x="7" y="14" width="50" height="36" rx="3"/><circle cx="26" cy="32" r="11"/><circle cx="26" cy="32" r="5"/><path d="M26 21v6M26 37v6M15 32h6M31 32h6"/><rect x="44" y="21" width="7" height="6" rx="1"/><rect x="44" y="35" width="7" height="8" rx="1"/>',
            'case' => '<rect x="18" y="6" width="28" height="50" rx="3"/><path d="M24 13h16M24 18h16M24 26h16M24 30h16M24 34h16M22 56v3M42 56v3"/><circle cx="32" cy="47" r="3"/>',
            'cooler' => '<rect x="14" y="8" width="36" height="34" rx="2"/><path d="M14 14h36M14 20h36M14 26h36M14 32h36M14 38h36M24 42v10M32 42v10M40 42v10"/><rect x="18" y="52" width="28" height="5" rx="1"/>',
            'storage' => '<rect x="4" y="24" width="56" height="16" rx="2"/><rect x="11" y="28" width="10" height="8" rx="1"/><rect x="25" y="28" width="12" height="8" rx="1"/><rect x="40" y="28" width="10" height="8" rx="1"/><circle cx="55.5" cy="32" r="2"/><path d="M4 29H1M4 35H1"/>',
            'generic' => '<rect x="6" y="10" width="52" height="34" rx="3"/><path d="M32 44v8M22 54h20"/>',
        ];
        $paths = $g[$type] ?? $g['generic'];
        return '<svg class="spec-glyph" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . str_replace('/>', ' vector-effect="non-scaling-stroke"/>', $paths) . '</svg>';
    }
}

if (!function_exists('render_spec_tile')) {
    /**
     * Spec tile for a product without a photo: concrete well, outlined category glyph and the model
     * name in Archivo condensed 600. It fills its (positioned) container.
     *
     * @param array  $p    product row (id, name, category_id or part_type)
     * @param string $size 'md' (64px glyph + name, cards / product page) or 'sm' (glyph only, thumbnails)
     */
    function render_spec_tile(array $p, string $size = 'md'): string
    {
        $name = htmlspecialchars((string) ($p['name'] ?? $p['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $size = $size === 'sm' ? 'sm' : 'md';
        $h = '<span class="spec-tile spec-tile--' . $size . '" role="img" aria-label="' . $name . '">';
        $h .= spec_glyph(product_part_type($p), $size === 'sm' ? 28 : 64);
        if ($size === 'md') {
            $h .= '<span class="spec-tile__name" aria-hidden="true">' . $name . '</span>';
        }
        return $h . '</span>';
    }
}

if (!function_exists('render_product_media')) {
    /**
     * The photo (<img>, lazy, with width/height) or the spec tile when there is none.
     *
     * @param array $p    product row
     * @param array $opts width, height (default 600), alt (default ''), lazy (default true), tile ('md'|'sm'),
     *                    attrs (extra raw attributes for the <img>, e.g. 'fetchpriority="high"')
     */
    function render_product_media(array $p, array $opts = []): string
    {
        $url = product_image_url($p);
        if ($url === null) {
            return render_spec_tile($p, (string) ($opts['tile'] ?? 'md'));
        }
        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $w = (int) ($opts['width'] ?? 600);
        $hgt = (int) ($opts['height'] ?? $w);
        return '<img src="' . $e($url) . '" alt="' . $e($opts['alt'] ?? '') . '" width="' . $w . '" height="' . $hgt . '"'
            . (($opts['lazy'] ?? true) ? ' loading="lazy" decoding="async"' : '')
            . (!empty($opts['attrs']) ? ' ' . $opts['attrs'] : '') . '>';
    }
}

if (!function_exists('product_json')) {
    /**
     * The storefront's JSON shape for a product (builder, wishlist, rates APIs).
     *
     * @param array $p     product row (id, name, price, inventory, …; attrs / key_specs / change when present)
     * @param array $extra merged last
     */
    function product_json(array $p, array $extra = []): array
    {
        $id = (int) $p['id'];
        $out = [
            'id' => $id,
            'name' => (string) $p['name'],
            'price' => (float) $p['price'],
            'price_text' => format_pkr($p['price']),
            'inventory' => (int) ($p['inventory'] ?? 0),
            'in_stock' => (int) ($p['inventory'] ?? 0) > 0,
            'url' => site_url('shop/product-details.php?id=' . $id),
            'image' => product_image_url($p),
            'part_type' => product_part_type($p),
            'seller_id' => (int) ($p['seller_id'] ?? 1),
        ];
        if (isset($p['category_name'])) {
            $out['category'] = (string) $p['category_name'];
        }
        if (isset($p['key_specs'])) {
            $out['key_specs'] = array_values($p['key_specs']);
        }
        if (isset($p['change'])) {
            $out['change'] = $p['change'];
        }
        return $out + $extra;
    }
}

if (!function_exists('render_delta')) {
    /**
     * Weekly change as "▼ Rs 1,500" / "▲ Rs 800" / "—" (the arrow comes from CSS .delta--*),
     * with the direction spelled out for screen readers.
     *
     * @param array|null $change from price_change() / rate_board(): delta, direction
     */
    function render_delta(?array $change, string $extraClass = ''): string
    {
        $dir = $change['direction'] ?? 'hold';
        $dir = in_array($dir, ['drop', 'rise'], true) ? $dir : 'hold';
        $label = ['drop' => 'Down ', 'rise' => 'Up ', 'hold' => 'No change'][$dir];
        $amount = $dir === 'hold' ? '' : format_pkr(abs((float) $change['delta']));
        return '<span class="delta delta--' . $dir . ($extraClass !== '' ? ' ' . $extraClass : '') . '">'
            . '<span class="visually-hidden">' . $label . '</span>' . $amount . '</span>';
    }
}

if (!function_exists('render_sparkline')) {
    /**
     * 64×20 inline SVG polyline of a price series (oldest first), last point as a signal dot.
     * Empty string for fewer than two points.
     *
     * @param array $points from price_series(): [['date' => …, 'price' => …], …]
     */
    function render_sparkline(array $points, int $w = 64, int $h = 20): string
    {
        $prices = array_map(static fn($p) => (float) $p['price'], array_values($points));
        $n = count($prices);
        if ($n < 2) {
            return '';
        }
        $min = min($prices);
        $max = max($prices);
        $pad = 2.5;
        $span = $max - $min ?: 1;
        $xy = [];
        foreach ($prices as $i => $v) {
            $x = $pad + ($w - 2 * $pad) * $i / ($n - 1);
            $y = $max === $min ? $h / 2 : $pad + ($h - 2 * $pad) * (1 - ($v - $min) / $span);
            $xy[] = round($x, 1) . ',' . round($y, 1);
        }
        [$lx, $ly] = explode(',', end($xy));
        $first = reset($prices);
        $last = end($prices);
        $label = 'Rate over ' . $n . ' weeks: from ' . format_pkr($first) . ' to ' . format_pkr($last);
        return '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<polyline class="sparkline__line" points="' . implode(' ', $xy) . '"/>'
            . '<circle class="sparkline__dot" cx="' . $lx . '" cy="' . $ly . '" r="2.5"/></svg>';
    }
}
