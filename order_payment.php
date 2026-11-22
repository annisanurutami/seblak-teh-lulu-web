<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

$customer = require_customer();

if (!store_is_open()) {
    set_flash('error', 'Toko sedang tutup dan belum menerima pesanan online.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$draft = $_SESSION['online_order_draft'] ?? null;
if (!is_array($draft) || !isset($draft['created_at'], $draft['items'])
    || time() - (int) $draft['created_at'] > 1800 || !is_array($draft['items'])) {
    unset($_SESSION['online_order_draft'], $_SESSION['online_order_payment_method_id']);
    set_flash('error', 'Rincian pesanan sudah kedaluwarsa. Silakan buat pesanan kembali.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Kembali ke rincian pesanan dan coba lagi.');
    }
    try {
        $_SESSION['online_order_payment_method_id'] = positive_int($_POST['metode'] ?? null, 'Metode pembayaran');
    } catch (InvalidArgumentException $exception) {
        set_flash('error', $exception->getMessage());
        header('Location: ' . app_base_path() . '/order_review.php');
        exit;
    }
}

$methodId = (int) ($_SESSION['online_order_payment_method_id'] ?? 0);
$method = $methodId > 0
    ? db_one('SELECT id, nama, tipe, nomor_rekening, nama_penerima, gambar, detail FROM payment_methods WHERE id = :id AND status = 1', ['id' => $methodId])
    : null;
if (!$method) {
    unset($_SESSION['online_order_payment_method_id']);
    set_flash('error', 'Metode pembayaran tidak tersedia. Silakan pilih metode lain.');
    header('Location: ' . app_base_path() . '/order_review.php');
    exit;
}

$methodConfigured = $method['tipe'] === 'rekening'
    ? !empty($method['nomor_rekening']) && !empty($method['nama_penerima'])
    : ($method['tipe'] !== 'gambar' || !empty($method['gambar']));
if (!$methodConfigured) {
    unset($_SESSION['online_order_payment_method_id']);
    set_flash('error', 'Instruksi metode pembayaran ini belum dilengkapi admin. Silakan pilih metode lain.');
    header('Location: ' . app_base_path() . '/order_review.php');
    exit;
}

$requiresProof = in_array($method['tipe'], ['rekening', 'gambar'], true);
$total = array_sum(array_column($draft['items'], 'total'));
$message = take_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=customer-order-flow-20261122') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=customer-order-flow-20261122') ?>">
    <script src="<?= e(app_base_path() . '/assets/js/app.js?v=customer-order-flow-20261122') ?>" defer></script>
</head>
<body class="store-page">
    <header class="store-header">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>">
            <img class="brand-logo store-brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        </a>
        <a class="button button-outline" href="<?= e(app_base_path() . '/order_review.php') ?>">Ganti metode</a>
    </header>
    <main class="store-content">
        <?php if ($message): ?><div class="alert alert-<?= e($message['type'] ?? 'info') ?>" role="alert"><?= e($message['message'] ?? '') ?></div><?php endif; ?>
        <section class="panel">
            <div class="store-section-heading">
                <div class="section-number">06</div>
                <div><p class="section-kicker">INSTRUKSI PEMBAYARAN</p><h1><?= e($method['nama']) ?></h1><p class="muted">Total yang perlu dibayar: <strong><?= e(rupiah($total)) ?></strong></p></div>
            </div>
            <div class="payment-instructions">
                <?php if ($method['tipe'] === 'rekening'): ?>
                    <p>Nomor rekening: <strong><?= e($method['nomor_rekening']) ?></strong></p>
                    <p>Nama penerima: <strong><?= e($method['nama_penerima']) ?></strong></p>
                <?php elseif ($method['tipe'] === 'gambar'): ?>
                    <?php if (!empty($method['gambar'])): ?>
                        <img class="payment-method-image payment-qr-image" src="<?= e(app_base_path() . '/' . ltrim($method['gambar'], '/')) ?>" alt="Kode pembayaran <?= e($method['nama']) ?>">
                    <?php else: ?>
                        <p class="alert alert-error" role="alert">Gambar pembayaran belum tersedia. Silakan pilih metode lain atau hubungi admin.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($method['detail'])): ?><p><?= nl2br(e($method['detail'])) ?></p><?php endif; ?>
            </div>
        </section>
        <section class="panel">
            <div class="store-section-heading">
                <div class="section-number">07</div>
                <div><p class="section-kicker">KONFIRMASI</p><h2><?= $requiresProof ? 'Unggah bukti pembayaran' : 'Konfirmasi pesanan' ?></h2><p class="muted"><?= $requiresProof ? 'Pastikan bukti sesuai dengan jumlah pembayaran.' : 'Metode ini tidak memerlukan bukti pembayaran.' ?></p></div>
            </div>
            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= e(app_base_path() . '/order_submit.php') ?>" data-payment-proof-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php if ($requiresProof): ?>
                    <label>Foto bukti pembayaran
                        <input type="file" name="bukti_pembayaran" accept="image/jpeg,image/png,image/webp" required data-payment-proof-input>
                        <small>JPG, PNG, atau WEBP; maksimal 3 MB. Tombol kirim aktif setelah foto dipilih.</small>
                    </label>
                <?php endif; ?>
                <div class="form-actions">
                    <a class="button button-outline" href="<?= e(app_base_path() . '/order_review.php') ?>">Kembali</a>
                    <button class="button button-primary" type="submit" data-payment-proof-submit <?= $requiresProof ? 'disabled' : '' ?>>Kirim pesanan ke WhatsApp</button>
                </div>
            </form>
        </section>
    </main>
    <footer class="store-footer"><span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span></footer>
</body>
</html>
