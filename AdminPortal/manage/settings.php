<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Owners change settings; managers can open the Activity log. Staff have nothing here.
$pageCap = can('settings.write') || can('activity.view') ? null : 'settings.write';
$pageTitle = 'Settings';
$pageSubtitle = 'Store details, shipping, payments, staff and activity';
require_once __DIR__ . '/../includes/header.php';
$wip = [
    'icon' => 'fa-gear',
    'title' => 'Settings are in progress',
    'text' => 'The store already reads its name, address, shipping zones and payment switches from the database. The editors come next.',
    'items' => [
        ['fa-store', 'Store: name, address, phone, currency, low-stock threshold'],
        ['fa-truck', 'Shipping zones & rates: Lahore, Punjab, Nationwide'],
        ['fa-wallet', 'Payments: cash on delivery and JazzCash'],
        ['fa-user-shield', 'Staff & roles: invite, change role, deactivate'],
        ['fa-clock-rotate-left', 'Activity log: every change, by whom and when'],
    ],
];
include __DIR__ . '/../includes/in-progress.php';
require_once __DIR__ . '/../includes/footer.php';
