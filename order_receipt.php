<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

$customer = require_customer();
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$order = $orderId
    ? db_one(
        "SELECT id, kode_pesanan, nama_pelanggan, no_hp, jenis_pesanan, alamat_pengantaran,
                metode_pembayaran, status_pesanan, status_pembayaran, subtotal, total, catatan, created_at
         FROM orders
         WHERE id = :id AND customer_id = :customer_id AND sumber = 'online'
         LIMIT 1",
        ['id' => $orderId, 'customer_id' => $customer['id']]
    )
    : null;

if (!$order) {
    http_response_code(404);
    exit('Struk pesanan tidak ditemukan.');
}

$items = db_all(
    'SELECT id, nama_produk, jumlah, harga, subtotal, catatan
     FROM order_items WHERE order_id = :id ORDER BY id',
    ['id' => $order['id']]
);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk <?= e($order['kode_pesanan']) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=customer-receipt-20261122') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=customer-receipt-20261122') ?>">
</head>
<body class="store-page receipt-page">
    <main class="receipt">
        <header class="receipt-header">
            <img class="brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
            <h1><?= e(APP_NAME) ?></h1>
            <p>Struk pesanan online</p>
        </header>
        <div class="receipt-meta">
            <strong><?= e($order['kode_pesanan']) ?></strong>
            <span><?= e($order['created_at']) ?></span>
            <span><?= e($order['nama_pelanggan'] ?? 'Pelanggan') ?> · <?= e($order['no_hp'] ?? '-') ?></span>
            <span><?= e(order_type_label((string) $order['jenis_pesanan'])) ?></span>
            <?php if (!empty($order['alamat_pengantaran'])): ?><span>Alamat: <?= e($order['alamat_pengantaran']) ?></span><?php endif; ?>
        </div>
        <div class="receipt-items">
            <?php foreach ($items as $item): ?>
                <article class="receipt-item">
                    <div class="receipt-item-total">
                        <strong><?= e($item['nama_produk']) ?> × <?= (int) $item['jumlah'] ?></strong>
                        <strong><?= e(rupiah($item['subtotal'])) ?></strong>
                    </div>
                    <small><?= e(rupiah($item['harga'])) ?> per item</small>
                    <?php foreach (db_all(
                        'SELECT nama_grup, nama_pilihan, harga_tambahan
                         FROM order_item_variants WHERE order_item_id = :id ORDER BY id',
                        ['id' => $item['id']]
                    ) as $variant): ?>
                        <small><?= e($variant['nama_grup']) ?>: <?= e($variant['nama_pilihan']) ?><?= (float) $variant['harga_tambahan'] > 0 ? ' (+' . e(rupiah($variant['harga_tambahan'])) . ')' : '' ?></small>
                    <?php endforeach; ?>
                    <?php foreach (db_all(
                        'SELECT nama_topping, jumlah, pcs_per_paket, subtotal
                         FROM order_item_toppings WHERE order_item_id = :id ORDER BY id',
                        ['id' => $item['id']]
                    ) as $topping): ?>
                        <small>Topping: <?= e($topping['nama_topping']) ?> × <?= (int) $topping['jumlah'] ?> (<?= (int) $topping['jumlah'] * (int) $topping['pcs_per_paket'] ?> pcs) — <?= e(rupiah($topping['subtotal'])) ?></small>
                    <?php endforeach; ?>
                    <?php if (!empty($item['catatan'])): ?><small>Catatan: <?= e($item['catatan']) ?></small><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="receipt-total">
            <span>Subtotal</span><strong><?= e(rupiah($order['subtotal'])) ?></strong>
            <span>Total</span><strong><?= e(rupiah($order['total'])) ?></strong>
        </div>
        <?php if (!empty($order['catatan'])): ?><p class="receipt-note">Catatan pesanan: <?= nl2br(e($order['catatan'])) ?></p><?php endif; ?>
        <div class="receipt-meta">
            <span>Status: <?= e(ucfirst(str_replace('_', ' ', $order['status_pesanan']))) ?></span>
            <span>Pembayaran: <?= e(ucfirst(str_replace('_', ' ', $order['status_pembayaran']))) ?><?= !empty($order['metode_pembayaran']) ? ' · ' . e($order['metode_pembayaran']) : '' ?></span>
        </div>
        <div class="receipt-actions">
            <button class="button button-primary" type="button" onclick="window.print()">Cetak struk</button>
            <a class="button button-outline" href="<?= e(app_base_path() . '/order_history.php?order=' . (int) $order['id']) ?>">Kembali ke riwayat</a>
        </div>
    </main>
</body>
</html>
