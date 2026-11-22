<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/layout.php';

$customer = require_customer();

$orders = db_all(
    "SELECT id, kode_pesanan, nama_pelanggan, no_hp, jenis_pesanan, alamat_pengantaran, metode_pembayaran,
            status_pesanan, status_pembayaran, total, catatan, bukti_pembayaran, created_at
     FROM orders
     WHERE sumber = 'online' AND customer_id = :customer_id
     ORDER BY created_at DESC, id DESC",
    ['customer_id' => $customer['id']]
);

$selectedId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$selectedOrder = null;
if ($orders) {
    if (!$selectedId) {
        $selectedId = (int) $orders[0]['id'];
    }
    foreach ($orders as $order) {
        if ((int) $order['id'] === $selectedId) {
            $selectedOrder = $order;
            break;
        }
    }
    if (!$selectedOrder) {
        $selectedId = (int) $orders[0]['id'];
        $selectedOrder = $orders[0];
    }
}

$orderItems = [];
$whatsappMessage = '';
$whatsappNumbers = [];
if ($selectedOrder) {
    $orderItems = db_all(
        'SELECT id, nama_produk, jumlah, subtotal, catatan FROM order_items WHERE order_id = :id ORDER BY id',
        ['id' => $selectedId]
    );
    $whatsappMessage = online_order_whatsapp_message($selectedOrder, $orderItems);
    if ($selectedOrder['status_pesanan'] === 'menunggu') {
        foreach (db_all('SELECT nama, nomor, status FROM whatsapp_numbers ORDER BY status DESC, nama, id') as $number) {
            $normalizedNumber = preg_replace('/\D+/', '', (string) $number['nomor']) ?? '';
            if ($normalizedNumber === '') {
                continue;
            }
            $number['normalized_number'] = $normalizedNumber;
            $whatsappNumbers[] = $number;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Pesanan | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=customer-order-flow-20261122') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=customer-order-flow-20261122') ?>">
    <script src="<?= e(app_base_path() . '/assets/js/app.js?v=customer-order-flow-20261122') ?>" defer></script>
</head>
<body class="store-page">
    <header class="store-header">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>">
            <img class="brand-logo store-brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        </a>
        <div class="store-header-actions">
            <a class="button button-outline" href="<?= e(app_base_path() . '/menu.php') ?>">Kembali ke menu</a>
            <form method="post" action="<?= e(app_base_path() . '/customer_logout.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="button button-outline" type="submit">Keluar</button>
            </form>
        </div>
    </header>
    <main class="store-content">
        <div class="store-section-heading">
            <div class="section-number">✓</div>
            <div>
                <p class="section-kicker">PESANAN ONLINE</p>
                <h1>Riwayat pesanan</h1>
                <p class="muted">Pesanan tersimpan di akunmu dan tetap bisa dilihat setelah kamu keluar atau menutup website. Sesi login berakhir setiap pukul 00.00 WIB.</p>
            </div>
        </div>
        <?php if (!$orders): ?>
            <section class="panel order-history-empty">
                <h2>Belum ada riwayat pesanan</h2>
                <p class="muted">Pesanan online yang kamu buat akan muncul di halaman ini.</p>
                <a class="button button-primary" href="<?= e(app_base_path() . '/menu.php') ?>">Lihat menu</a>
            </section>
        <?php else: ?>
            <div class="order-history-layout">
                <section class="panel order-history-panel">
                    <h2>Daftar pesanan</h2>
                    <div class="order-history-list">
                        <?php foreach ($orders as $order): ?>
                            <a class="order-history-link <?= (int) $order['id'] === (int) $selectedId ? 'active' : '' ?>" href="<?= e(app_base_path() . '/order_history.php?order=' . (int) $order['id']) ?>">
                                <strong><?= e($order['kode_pesanan']) ?></strong>
                                <span><?= e(rupiah($order['total'])) ?> · <?= e($order['created_at']) ?></span>
                                <span class="order-history-status"><?= e(ucfirst(str_replace('_', ' ', $order['status_pesanan']))) ?> · <?= e(ucfirst(str_replace('_', ' ', $order['status_pembayaran']))) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php if ($selectedOrder): ?>
                    <section class="panel order-review-panel">
                        <div>
                            <p class="section-kicker">RINCIAN PESANAN</p>
                            <h2><?= e($selectedOrder['kode_pesanan']) ?></h2>
                            <p class="muted">Dibuat <?= e($selectedOrder['created_at']) ?></p>
                        </div>
                        <div class="order-tracking-result">
                            <strong>Status pesanan: <?= e(ucfirst(str_replace('_', ' ', $selectedOrder['status_pesanan']))) ?></strong>
                            <span>Status pembayaran: <?= e(ucfirst(str_replace('_', ' ', $selectedOrder['status_pembayaran']))) ?></span>
                        </div>
                        <div class="order-review-customer">
                            <strong><?= e($selectedOrder['nama_pelanggan'] ?? 'Pelanggan') ?></strong>
                            <?php if (!empty($selectedOrder['no_hp'])): ?><span><?= e($selectedOrder['no_hp']) ?></span><?php endif; ?>
                            <span><?= e(order_type_label((string) $selectedOrder['jenis_pesanan'])) ?></span>
                            <?php if (!empty($selectedOrder['alamat_pengantaran'])): ?><span>Alamat: <?= e($selectedOrder['alamat_pengantaran']) ?></span><?php endif; ?>
                            <?php if (!empty($selectedOrder['metode_pembayaran'])): ?><span>Bayar via <?= e($selectedOrder['metode_pembayaran']) ?></span><?php endif; ?>
                        </div>
                        <ol class="order-review-list">
                            <?php foreach ($orderItems as $item): ?>
                                <?php
                                $variants = db_all(
                                    'SELECT nama_grup, nama_pilihan FROM order_item_variants WHERE order_item_id = :id ORDER BY id',
                                    ['id' => $item['id']]
                                );
                                $toppings = db_all(
                                    'SELECT nama_topping, jumlah, pcs_per_paket FROM order_item_toppings WHERE order_item_id = :id ORDER BY id',
                                    ['id' => $item['id']]
                                );
                                ?>
                                <li>
                                    <div class="order-review-item-heading">
                                        <strong><?= e($item['nama_produk']) ?> × <?= (int) $item['jumlah'] ?></strong>
                                        <strong><?= e(rupiah($item['subtotal'])) ?></strong>
                                    </div>
                                    <?php foreach ($variants as $variant): ?>
                                        <div class="order-review-item-detail"><?= e($variant['nama_grup']) ?>: <?= e($variant['nama_pilihan']) ?></div>
                                    <?php endforeach; ?>
                                    <?php foreach ($toppings as $topping): ?>
                                        <div class="order-review-item-detail">Topping: <?= e($topping['nama_topping']) ?> × <?= (int) $topping['jumlah'] ?> (<?= (int) $topping['jumlah'] * (int) $topping['pcs_per_paket'] ?> pcs)</div>
                                    <?php endforeach; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php if (!empty($selectedOrder['catatan'])): ?><p class="muted">Catatan: <?= nl2br(e($selectedOrder['catatan'])) ?></p><?php endif; ?>
                        <div class="order-review-total"><span>Total pesanan</span><strong><?= e(rupiah($selectedOrder['total'])) ?></strong></div>
                        <a class="button button-outline" href="<?= e(app_base_path() . '/order_receipt.php?order=' . (int) $selectedOrder['id']) ?>" target="_blank" rel="noopener">Cetak struk</a>
                        <?php if ($selectedOrder['status_pesanan'] === 'menunggu'): ?>
                            <label class="order-history-whatsapp-choice" for="order-whatsapp-number">
                                Nomor WhatsApp tujuan
                                <select id="order-whatsapp-number" <?= $whatsappNumbers ? '' : 'disabled' ?>>
                                    <option value="">Pilih nomor WhatsApp...</option>
                                    <?php foreach ($whatsappNumbers as $number): ?>
                                        <option value="<?= e($number['normalized_number']) ?>" data-admin-name="<?= e($number['nama']) ?>" <?= $number['status'] ? '' : 'disabled' ?>>
                                            <?= e($number['nama']) ?> — <?= e($number['nomor']) ?> (<?= $number['status'] ? 'Aktif' : 'Nonaktif' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="button button-primary order-history-whatsapp" id="order-whatsapp-send" type="button" data-message="<?= e($whatsappMessage) ?>" disabled>Buka WhatsApp / kirim ulang konfirmasi</button>
                            <?php if (!$whatsappNumbers): ?>
                                <p class="alert alert-error" role="alert">Nomor WhatsApp toko tidak tersedia, tetapi pesananmu tetap tersimpan.</p>
                            <?php else: ?>
                                <p class="muted">Pilih nomor tujuan terlebih dahulu untuk mengirim ulang konfirmasi.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    <footer class="store-footer"><span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span></footer>
    <script>
        (() => {
            const numberSelect = document.getElementById('order-whatsapp-number');
            const sendButton = document.getElementById('order-whatsapp-send');
            if (!numberSelect || !sendButton) {
                return;
            }

            numberSelect.addEventListener('change', () => {
                sendButton.disabled = numberSelect.value === '';
            });
            sendButton.addEventListener('click', () => {
                const recipient = numberSelect.selectedOptions[0];
                const phone = numberSelect.value.replace(/\D/g, '');
                if (!phone || !recipient) {
                    return;
                }

                const message = sendButton.dataset.message.replace(
                    /^Halo Admin [^\r\n]*/,
                    `Halo Admin ${recipient.dataset.adminName}`
                );
                const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
                window.open(url, '_blank', 'noopener');
            });
        })();
    </script>
</body>
</html>
