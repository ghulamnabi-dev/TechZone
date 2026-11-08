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
