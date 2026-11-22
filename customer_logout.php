<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/customer_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Sesi formulir tidak valid. Silakan muat ulang halaman.');
}

unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_login_date'],
    $_SESSION['online_order_draft'],
    $_SESSION['online_order_payment_method_id'],
    $_SESSION['customer_online_order_history']
);
session_regenerate_id(true);
header('Location: ' . app_base_path() . '/customer_login.php');
exit;
