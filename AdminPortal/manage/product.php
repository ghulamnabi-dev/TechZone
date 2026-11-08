<?php
require_once __DIR__ . '/../config/paths.php';
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$navActive = 'products.php';
$pageTitle = $recordId > 0 ? 'Product #' . $recordId : 'Product';
$pageBack = ['href' => admin_url('manage/products.php'), 'label' => 'Products'];
require_once __DIR__ . '/../includes/header.php';
$wip = [
    'icon' => 'fa-tag',
    'title' => 'The product page is in progress',
    'text' => 'For now, open the product from the products list.',
    'items' => [
        ['fa-image', 'Media, description and SEO'],
        ['fa-coins', 'Price, compare-at price and cost (margin)'],
        ['fa-boxes-stacked', 'Inventory with adjustments, options and status'],
    ],
    'link' => [admin_url('manage/products.php'), 'Back to products'],
];
include __DIR__ . '/../includes/in-progress.php';
require_once __DIR__ . '/../includes/footer.php';
