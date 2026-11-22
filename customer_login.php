<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

if (customer_user()) {
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_base_path() . '/menu.php#customer-login');
    exit;
}

$error = '';
$name = '';
$phone = '';

$name = post_value('nama', 100);
$phoneInput = post_value('no_hp', 25);
$phone = preg_replace('/\D+/', '', $phoneInput) ?? '';

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
} elseif ($name === '' || strlen($phone) < 8 || strlen($phone) > 20) {
    $error = 'Isi nama dan nomor telepon yang valid (8-20 angka).';
} else {
    $insert = db()->prepare(
        'INSERT INTO customers (nama, no_hp) VALUES (:name, :phone)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $insert->execute(['name' => $name, 'phone' => $phone]);
    $customer = db_one(
        'SELECT id, nama FROM customers WHERE no_hp = :phone AND nama = :name LIMIT 1',
        ['phone' => $phone, 'name' => $name]
    );

    if (!$customer) {
        $error = 'Nama atau nomor telepon tidak cocok. Periksa kembali data yang dimasukkan.';
    } else {
        $backfill = db()->prepare(
            "UPDATE orders
             SET customer_id = :customer_id
             WHERE sumber = 'online'
               AND customer_id IS NULL
               AND REGEXP_REPLACE(no_hp, '[^0-9]', '') = :phone
               AND nama_pelanggan = :name"
        );
        $backfill->execute([
            'customer_id' => $customer['id'],
            'phone' => $phone,
            'name' => $name,
        ]);

        log_in_customer((int) $customer['id']);
        header('Location: ' . app_base_path() . '/menu.php');
        exit;
    }
}

$_SESSION['customer_login_values'] = ['name' => $name, 'phone' => $phoneInput];
set_flash('error', $error);
header('Location: ' . app_base_path() . '/menu.php#customer-login');
exit;
