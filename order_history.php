<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/layout.php';

$storedHistory = $_SESSION['customer_online_order_history'] ?? [];
$historyEntries = [];
if (is_array($storedHistory)) {
    foreach ($storedHistory as $rawId => $entry) {
        $id = filter_var((string) $rawId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || !is_array($entry)
            || !is_string($entry['whatsapp_number'] ?? null)
            || !is_string($entry['message'] ?? null)) {
            continue;
        }
        $historyEntries[(int) $id] = $entry;
    }
}

$orders = [];
if ($historyEntries) {
    $parameters = [];
    $placeholders = [];
    foreach (array_keys($historyEntries) as $index => $id) {
        $placeholder = 'order_' . $index;
        $placeholders[] = ':' . $placeholder;
        $parameters[$placeholder] = $id;
    }
    $orders = db_all(
        'SELECT id, kode_pesanan, nama_pelanggan, no_hp, jenis_pesanan, metode_pembayaran,
                status_pesanan, status_pembayaran, total, catatan, created_at
         FROM orders
         WHERE sumber = \'online\' AND id IN (' . implode(', ', $placeholders) . ')
         ORDER BY created_at DESC, id DESC',
        $parameters
    );
}

$selectedId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$selectedOrder = null;
if ($orders) {
    if (!$selectedId || !isset($historyEntries[$selectedId])) {
        $selectedId = (int) $orders[0]['id'];
    }
    foreach ($orders as $order) {
        if ((int) $order['id'] === $selectedId) {
            $selectedOrder = $order;
            break;
        }
    }
}

$orderItems = [];
$whatsappMessage = '';
$whatsappNumbers = [];
if ($selectedOrder) {
    $orderItems = db_all(
        'SELECT id, nama_produk, jumlah, subtotal FROM order_items WHERE order_id = :id ORDER BY id',
        ['id' => $selectedId]
    );
    if ($selectedOrder['status_pesanan'] === 'menunggu') {
        $whatsappMessage = $historyEntries[$selectedId]['message'];
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
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=order-history-whatsapp-picker-20261115') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=order-history-whatsapp-picker-20261115') ?>">
    <script src="<?= e(app_base_path() . '/assets/js/app.js?v=order-history-custom-select-20261115') ?>" defer></script>
</head>
<body class="store-page">
    <header class="store-header">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>">
            <img class="brand-logo store-brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        </a>
        <div class="store-header-actions">
            <a class="button button-outline" href="<?= e(app_base_path() . '/menu.php') ?>">Kembali ke menu</a>
        </div>
    </header>
    <main class="store-content">
        <div class="store-section-heading">
            <div class="section-number">✓</div>
            <div>
                <p class="section-kicker">PESANAN ONLINE</p>
                <h1>Riwayat pesanan</h1>
                <p class="muted">Hingga 20 pesanan terakhir di sesi browser ini tersimpan di sini. Kamu bisa melihat rinciannya; konfirmasi WhatsApp dapat dikirim ulang selama pesanan belum dikonfirmasi.</p>
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
                    <h2>Pesanan terakhir</h2>
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
                            <span><?= $selectedOrder['jenis_pesanan'] === 'dine_in' ? 'Makan di tempat' : 'Bawa pulang' ?></span>
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
