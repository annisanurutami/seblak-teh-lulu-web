<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Sesi formulir tidak valid. Silakan muat ulang halaman.');
}

log_out_user();
header('Location: ' . app_base_path() . '/login.php');
exit;