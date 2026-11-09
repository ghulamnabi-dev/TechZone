<?php
/**
 * Help centre frame: left sub-nav + a 68ch reading column. Pages call help_start() after setting
 * $pageTitle and including includes/header.php + includes/pages.php, then help_end() before the footer.
 */

function help_pages(): array
{
    return [
        'index' => ['Help centre', 'help/index.php'],
        'how-to-order' => ['How to order', 'help/how-to-order.php'],
        'shipping' => ['Shipping and delivery', 'help/shipping.php'],
        'payments' => ['Payments and installments', 'help/payments.php'],
        'returns' => ['Returns', 'help/returns.php'],
        'warranty' => ['Warranty and claims', 'help/warranty.php'],
        'faq' => ['Questions and answers', 'help/faq.php'],
    ];
}

function help_start(string $key, string $title, string $lead = ''): void
{
    $pages = help_pages();
    echo '<div class="help pg-page"><div class="container help-grid">';
    echo '<nav class="help-nav" aria-label="Help centre"><p class="help-nav__title">Help</p><ul>';
    foreach ($pages as $k => [$label, $path]) {
        echo '<li><a href="' . pg_e(site_url($path)) . '"' . ($k === $key ? ' aria-current="page"' : '') . '>' . pg_e($label) . '</a></li>';
    }
    echo '<li><a href="' . pg_e(site_url('track.php')) . '">Track your order</a></li>';
    echo '<li><a href="' . pg_e(site_url('store.php')) . '">Visit the shop</a></li>';
    echo '</ul></nav><article class="help-body">';
    if ($key !== 'index') {
        echo '<ol class="pg-crumbs"><li><a href="' . pg_e(site_url('help/index.php')) . '">Help centre</a></li><li aria-current="page">' . pg_e($pages[$key][0] ?? $title) . '</li></ol>';
    }
    echo '<h1 class="pg-title">' . pg_e($title) . '</h1>';
    if ($lead !== '') {
        echo '<p class="pg-lead help-lead">' . pg_e($lead) . '</p>';
    }
}

function help_end(): void
{
    $s = pg_store();
    echo '<aside class="help-contact"><h2 class="pg-h3">Still stuck?</h2><p>Call <a href="tel:' . pg_e(preg_replace('/[^\d+]/', '', $s['phone'])) . '">' . pg_e($s['phone']) . '</a> or WhatsApp <a href="'
        . pg_e(pg_whatsapp_url($s['whatsapp'])) . '" target="_blank" rel="noopener">' . pg_e($s['whatsapp_text']) . '</a>, ' . pg_e($s['hours_text']) . '. Or <a href="' . pg_e(site_url('contact.php')) . '">write to us</a>; we answer within a working day.</p></aside>';
    echo '</article></div></div>';
}
