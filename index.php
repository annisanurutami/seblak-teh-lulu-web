<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/domain.php';

if (!isset($_GET['page']) && !current_user()) {
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$user = require_login();
$page = (string) ($_GET['page'] ?? 'dashboard');
$allowedPages = [
    'dashboard', 'categories', 'products', 'toppings', 'variants',
    'users', 'pos', 'orders', 'payments', 'expenses', 'income', 'sales',
    'finance', 'profile', 'settings',
];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}

$adminPages = ['categories', 'products', 'toppings', 'variants', 'users', 'income', 'finance', 'settings'];
if (in_array($page, $adminPages, true) && !is_admin($user)) {
    http_response_code(403);
    $page = 'dashboard';
    set_flash('error', 'Halaman tersebut hanya dapat diakses admin.');
}

define('APP_ROUTED_REQUEST', true);
require __DIR__ . '/includes/layout.php';
render_header($user, $page);
require __DIR__ . '/pages/' . $page . '.php';
render_footer();