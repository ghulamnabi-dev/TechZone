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

if (!function_exists('render_product_card')) {
    /**
     * Shared storefront product card (home + listing + related).
     * $promotions: active promotions (for the sale tag); $rating: ['avg' => float, 'count' => int] or null.
     */
    function render_product_card(array $p, array $promotions = [], ?array $rating = null): string
    {
        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $id = (int) $p['id'];
        $url = site_url('shop/product-details.php?id=' . $id);
        $inv = (int) ($p['inventory'] ?? 0);
        $sale = promo_price_for_product($p, $promotions);
        $img = getProductImagePath($p['imagelink'] ?? '', false);

        $h = '<article class="pcard' . ($inv <= 0 ? ' pcard--out' : '') . '">';
        $h .= '<div class="pcard__media">';
        if ($img !== '') {
            $h .= '<img src="' . $e($img) . '" alt="" width="600" height="600" loading="lazy" decoding="async">';
        }
        if ($sale) {
            $h .= price_tag($sale['price'], 'sm', 'pcard__tag');
        }
        $h .= '</div>';
        $h .= '<div class="pcard__body">';
        if (!empty($p['category_name'])) {
            $h .= '<p class="pcard__cat">' . $e($p['category_name']) . '</p>';
        }
        $h .= '<h3 class="pcard__name"><a class="pcard__link" href="' . $e($url) . '">' . $e($p['name']) . '</a></h3>';
        if ($rating && (int) $rating['count'] > 0) {
            $h .= '<p class="pcard__rating">' . rating_stars((float) $rating['avg'], 13) . '<span>' . (int) $rating['count'] . '</span></p>';
        } else {
            $h .= '<p class="pcard__rating pcard__rating--none">No reviews yet</p>';
        }
        $h .= '<p class="pcard__price">' . format_pkr($sale ? $sale['price'] : $p['price']) . '</p>';
        if ($sale) {
            $h .= '<p class="pcard__was"><s>' . format_pkr($sale['was']) . '</s>' . ($sale['code'] !== '' ? ' with code ' . $e($sale['code']) : '') . '</p>';
        }
        $h .= stock_line($inv, 'pcard__stock');
        $h .= '</div>';
        if ($inv > 0) {
            $h .= '<div class="pcard__cta">' . cart_add_form($id, 'btn btn-ink pcard__add', 'Add to cart') . '</div>';
        }
        return $h . '</article>';
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
