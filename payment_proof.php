<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('Bukti pembayaran tidak ditemukan.');
}

$order = db_one(
    "SELECT kode_pesanan, bukti_pembayaran FROM orders WHERE id = :id AND sumber = 'online'",
    ['id' => $orderId]
);
$filename = $order['bukti_pembayaran'] ?? null;
if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', $filename)) {
    http_response_code(404);
    exit('Bukti pembayaran tidak ditemukan.');
}

$path = payment_proof_directory() . DIRECTORY_SEPARATOR . $filename;
if (!is_file($path) || !is_readable($path)) {
    foreach ([previous_payment_proof_directory(), legacy_payment_proof_directory()] as $directory) {
        $fallbackPath = $directory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($fallbackPath) && is_readable($fallbackPath)) {
            $path = $fallbackPath;
            break;
        }
    }
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit('File bukti pembayaran tidak tersedia.');
    }
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Format file bukti pembayaran tidak didukung.');
}

if (isset($_GET['image'])) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="bukti-pembayaran-' . (int) $orderId . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

render_header($user, 'orders');
page_title('Transaksi', 'Bukti pembayaran', 'Bukti untuk pesanan ' . $order['kode_pesanan'] . '.');
?>
<section class="panel payment-proof-viewer">
    <a class="button button-outline" href="<?= e(app_base_path() . '/index.php?page=orders') ?>">Kembali ke pesanan online</a>
    <img class="payment-proof-image" src="<?= e(app_base_path() . '/payment_proof.php?order=' . (int) $orderId . '&image=1') ?>" alt="Bukti pembayaran pesanan <?= e($order['kode_pesanan']) ?>">
</section>
<?php render_footer(); ?>
