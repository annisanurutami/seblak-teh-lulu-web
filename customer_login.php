<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/customer_auth.php';

if (customer_user()) {
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$error = '';
$name = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=customer-login-20261122') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=customer-login-20261122') ?>">
</head>
<body class="store-page customer-login-page">
    <main class="customer-login-card">
        <img class="brand-logo customer-login-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        <p class="section-kicker">SEBLAK TEH LULU</p>
        <h1>Masuk untuk mulai pesan</h1>
        <p class="muted">Gunakan nama dan nomor telepon yang sama untuk melihat riwayat pesananmu.</p>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="stack-form" method="post" action="<?= e(app_base_path() . '/customer_login.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Nama<input name="nama" maxlength="100" value="<?= e($name) ?>" autocomplete="name" required autofocus></label>
            <label>Nomor telepon<input name="no_hp" type="tel" maxlength="25" value="<?= e($phone) ?>" autocomplete="tel" inputmode="tel" placeholder="08xx atau 62xx" required></label>
            <button class="button button-primary button-full" type="submit">Masuk</button>
        </form>
        <a class="customer-login-staff-link" href="<?= e(app_base_path() . '/login.php') ?>">Masuk sebagai karyawan</a>
    </main>
</body>
</html>
