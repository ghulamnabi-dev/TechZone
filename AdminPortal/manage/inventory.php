<?php
$pageTitle = 'Inventory';
$pageSubtitle = 'Stock levels, low stock and every adjustment';
require_once __DIR__ . '/../includes/header.php';
$wip = [
    'icon' => 'fa-boxes-stacked',
    'title' => 'Inventory is in progress',
    'text' => 'Stock already moves only through the ledger (sales, cancellations, receipts and corrections). This page will show it.',
    'items' => [
        ['fa-list', 'Stock level for every product and option, with low-stock highlighted'],
        ['fa-plus-minus', 'Adjust stock: received, damaged, returned or a count correction'],
        ['fa-clock-rotate-left', 'Adjustment history with who changed what and why'],
    ],
    'link' => [admin_url('manage/products.php'), 'Go to Products'],
];
include __DIR__ . '/../includes/in-progress.php';
require_once __DIR__ . '/../includes/footer.php';
