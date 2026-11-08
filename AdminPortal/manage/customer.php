<?php
require_once __DIR__ . '/../config/paths.php';
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$navActive = 'customers.php';
$pageTitle = $recordId > 0 ? 'Customer #' . $recordId : 'Customer';
$pageBack = ['href' => admin_url('manage/customers.php'), 'label' => 'Customers'];
require_once __DIR__ . '/../includes/header.php';
$wip = [
    'icon' => 'fa-user',
    'title' => 'The customer page is in progress',
    'text' => 'For now, open the customer from the customers list.',
    'items' => [
        ['fa-id-card', 'Profile, tags and internal notes'],
        ['fa-chart-line', 'Lifetime value and order history'],
        ['fa-timeline', 'Timeline of orders and reviews'],
    ],
    'link' => [admin_url('manage/customers.php'), 'Back to customers'],
];
include __DIR__ . '/../includes/in-progress.php';
require_once __DIR__ . '/../includes/footer.php';
