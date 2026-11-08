<?php
require_once __DIR__ . '/../config/paths.php';
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$navActive = 'orders.php';
$pageTitle = $recordId > 0 ? 'Order #' . $recordId : 'Order';
$pageBack = ['href' => admin_url('manage/orders.php'), 'label' => 'Orders'];
require_once __DIR__ . '/../includes/header.php';
$wip = [
    'icon' => 'fa-receipt',
    'title' => 'The order page is in progress',
    'text' => 'For now, open the order from the orders list.',
    'items' => [
        ['fa-box', 'Fulfil items, record payment, add tracking and mark delivered'],
        ['fa-timeline', 'Timeline of every event, with who did what'],
        ['fa-print', 'Print an invoice or a packing slip'],
    ],
    'link' => [admin_url('manage/orders.php'), 'Back to orders'],
];
include __DIR__ . '/../includes/in-progress.php';
require_once __DIR__ . '/../includes/footer.php';
