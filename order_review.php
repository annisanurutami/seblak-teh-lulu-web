<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

$customer = require_customer();

if (!store_is_open()) {
    set_flash('error', 'Toko sedang tutup dan belum menerima pesanan online.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Kembali ke menu dan coba lagi.');
    }

    try {
        $orderType = post_value('jenis_pesanan', 40);
        $service = order_type_option($orderType);
        $address = post_value('alamat_pengantaran', 500);
        $submittedItems = $_POST['items'] ?? [];
        if (!$service || !is_array($submittedItems)) {
            throw new InvalidArgumentException('Varian pesanan atau rincian menu tidak valid.');
        }
        if ($service['perlu_alamat'] && $address === '') {
            throw new InvalidArgumentException('Alamat pengantaran wajib diisi.');
        }
        if (!db_one('SELECT id FROM whatsapp_numbers WHERE status = 1 ORDER BY id LIMIT 1')) {
            throw new InvalidArgumentException('Pemesanan online belum tersedia. Admin perlu mengaktifkan nomor WhatsApp terlebih dahulu.');
        }
        $items = normalized_order_items($submittedItems);
        $_SESSION['online_order_draft'] = [
            'created_at' => time(),
            'name' => $customer['nama'],
            'phone' => $customer['no_hp'],
            'order_type' => $service['nama'],
            'order_type_name' => $service['nama'],
            'address' => $service['perlu_alamat'] ? $address : '',
            'note' => post_value('catatan', 2000),
            'items' => $items,
        ];
        unset($_SESSION['online_order_payment_method_id']);
    } catch (InvalidArgumentException $exception) {
        set_flash('error', $exception->getMessage());
        header('Location: ' . app_base_path() . '/menu.php');
        exit;
    }
}

$draft = $_SESSION['online_order_draft'] ?? null;
if (!is_array($draft) || !isset($draft['created_at'], $draft['items'])
    || time() - (int) $draft['created_at'] > 1800 || !is_array($draft['items'])) {
    unset($_SESSION['online_order_draft'], $_SESSION['online_order_payment_method_id']);
    set_flash('error', 'Rincian pesanan sudah kedaluwarsa. Silakan buat pesanan kembali.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$paymentMethods = db_all(
    'SELECT id, nama, tipe, nomor_rekening, nama_penerima, gambar, detail FROM payment_methods WHERE status = 1 ORDER BY nama'
);
$selectedMethodId = (int) ($_SESSION['online_order_payment_method_id'] ?? 0);
$total = array_sum(array_column($draft['items'], 'total'));
$message = take_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rincian Pesanan | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=customer-order-flow-20261122') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=customer-order-flow-20261122') ?>">
    <script src="<?= e(app_base_path() . '/assets/js/app.js?v=customer-order-flow-20261122') ?>" defer></script>
</head>
<body class="store-page">
    <header class="store-header">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>">
            <img class="brand-logo store-brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        </a>
        <a class="button button-outline" href="<?= e(app_base_path() . '/menu.php') ?>">Kembali ke menu</a>
    </header>
    <main class="store-content">
        <?php if ($message): ?><div class="alert alert-<?= e($message['type'] ?? 'info') ?>" role="alert"><?= e($message['message'] ?? '') ?></div><?php endif; ?>
        <section class="panel order-review-panel">
            <div class="store-section-heading">
                <div class="section-number">04</div>
                <div><p class="section-kicker">RINCIAN PESANAN</p><h1>Periksa pesananmu</h1><p class="muted">Pastikan rincian setiap mangkuk dan jumlahnya sudah benar.</p></div>
            </div>
            <div class="order-review-customer">
                <strong><?= e($draft['name']) ?></strong>
                <span><?= e($draft['phone']) ?></span>
                <span><?= e($draft['order_type_name']) ?></span>
                <?php if ($draft['address'] !== ''): ?><span>Alamat: <?= e($draft['address']) ?></span><?php endif; ?>
            </div>
            <ol class="order-review-list">
                <?php foreach ($draft['items'] as $index => $item): ?>
                    <li>
                        <div class="order-review-item-heading">
                            <strong><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?></strong>
                            <strong><?= e(rupiah($item['total'])) ?></strong>
                        </div>
                        <?php foreach ($item['variants'] as $variant): ?>
                            <div class="order-review-item-detail">Varian: <?= e($variant['group']) ?> — <?= e($variant['name']) ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($item['toppings'] as $topping): ?>
                            <div class="order-review-item-detail">Topping: <?= e($topping['name']) ?> × <?= (int) $topping['quantity'] ?> (<?= (int) ($topping['quantity'] * $topping['pieces_per_package']) ?> pcs)</div>
                        <?php endforeach; ?>
                        <?php if ($item['note'] !== ''): ?><div class="order-review-item-detail">Catatan item: <?= e($item['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($draft['note'] !== ''): ?><p class="muted">Catatan pesanan: <?= e($draft['note']) ?></p><?php endif; ?>
            <div class="order-review-total"><span>Total pesanan</span><strong><?= e(rupiah($total)) ?></strong></div>
        </section>
        <section class="panel">
            <div class="store-section-heading">
                <div class="section-number">05</div>
                <div><p class="section-kicker">PEMBAYARAN</p><h2>Pilih metode pembayaran</h2><p class="muted">Setelah memilih, instruksi pembayaran akan ditampilkan sebelum mengunggah bukti.</p></div>
            </div>
            <?php if ($paymentMethods): ?>
                <form class="stack-form" method="post" action="<?= e(app_base_path() . '/order_payment.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label>Metode pembayaran
                        <select name="metode" required>
                            <option value="">Pilih metode pembayaran</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <?php $configured = $method['tipe'] === 'rekening'
                                    ? !empty($method['nomor_rekening']) && !empty($method['nama_penerima'])
                                    : ($method['tipe'] !== 'gambar' || !empty($method['gambar'])); ?>
                                <option value="<?= (int) $method['id'] ?>" <?= $selectedMethodId === (int) $method['id'] ? 'selected' : '' ?> <?= $configured ? '' : 'disabled' ?>>
                                    <?= e($method['nama']) ?> — <?= $method['tipe'] === 'rekening' ? 'Transfer / rekening' : ($method['tipe'] === 'gambar' ? 'QRIS / gambar' : 'Lainnya') ?><?= $configured ? '' : ' (belum diatur)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="form-actions">
                        <a class="button button-outline" href="<?= e(app_base_path() . '/menu.php') ?>">Ubah pesanan</a>
                        <button class="button button-primary" type="submit">Lanjut ke pembayaran</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="alert alert-error" role="alert">Belum ada metode pembayaran aktif. Hubungi admin toko.</p>
            <?php endif; ?>
        </section>
    </main>
    <footer class="store-footer"><span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span></footer>
</body>
</html>
