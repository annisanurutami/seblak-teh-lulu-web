<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid. Kembali ke menu dan coba lagi.');
}

if (!store_is_open()) {
    set_flash('error', 'Toko sedang tutup dan belum menerima pesanan online.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$draft = $_SESSION['online_order_draft'] ?? null;
$methodId = (int) ($_SESSION['online_order_payment_method_id'] ?? 0);
if (!is_array($draft) || !isset($draft['created_at'], $draft['items'])
    || time() - (int) $draft['created_at'] > 1800 || !is_array($draft['items']) || $methodId < 1) {
    unset($_SESSION['online_order_draft'], $_SESSION['online_order_payment_method_id']);
    set_flash('error', 'Rincian pesanan sudah kedaluwarsa. Silakan buat pesanan kembali.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$method = db_one(
    'SELECT id, nama, tipe, nomor_rekening, nama_penerima, gambar FROM payment_methods WHERE id = :id AND status = 1',
    ['id' => $methodId]
);
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
    set_flash('error', 'Instruksi metode pembayaran belum dilengkapi admin. Silakan pilih metode lain.');
    header('Location: ' . app_base_path() . '/order_review.php');
    exit;
}

$requiresProof = in_array($method['tipe'], ['rekening', 'gambar'], true);
$proofUpload = $_FILES['bukti_pembayaran'] ?? null;
if ($requiresProof && (!$proofUpload || ($proofUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
    set_flash('error', 'Unggah bukti pembayaran sebelum mengirim pesanan.');
    header('Location: ' . app_base_path() . '/order_payment.php');
    exit;
}
if (!$requiresProof && $proofUpload && ($proofUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Metode pembayaran ini tidak menerima unggahan bukti.');
    header('Location: ' . app_base_path() . '/order_payment.php');
    exit;
}

$whatsapp = db_one('SELECT id, nama, nomor FROM whatsapp_numbers WHERE status = 1 ORDER BY id LIMIT 1');
if (!$whatsapp) {
    set_flash('error', 'Pemesanan online belum tersedia. Admin perlu mengaktifkan nomor WhatsApp terlebih dahulu.');
    header('Location: ' . app_base_path() . '/menu.php');
    exit;
}

$proofFilename = null;
try {
    if ($requiresProof) {
        $proofFilename = save_payment_proof($proofUpload);
    }
    $orderId = create_order(
        $draft['items'],
        'online',
        ['name' => $draft['name'], 'phone' => $draft['phone'], 'note' => $draft['note']],
        null,
        $draft['order_type'],
        'menunggu',
        0,
        'tunai',
        (int) $whatsapp['id'],
        $method['nama'],
        $proofFilename
    );
} catch (InvalidArgumentException $exception) {
    remove_payment_proof($proofFilename);
    set_flash('error', $exception->getMessage());
    header('Location: ' . app_base_path() . '/order_payment.php');
    exit;
} catch (Throwable $exception) {
    remove_payment_proof($proofFilename);
    throw $exception;
}

$order = db_one('SELECT kode_pesanan, total FROM orders WHERE id = :id', ['id' => $orderId]);
$orderLines = db_all(
    'SELECT id, nama_produk, jumlah, subtotal FROM order_items WHERE order_id = :id ORDER BY id',
    ['id' => $orderId]
);
$bowlCount = 0;
$otherItemCount = 0;
foreach ($orderLines as $line) {
    if (str_starts_with($line['nama_produk'], 'Seblak')) {
        $bowlCount += (int) $line['jumlah'];
    } else {
        $otherItemCount += (int) $line['jumlah'];
    }
}
$message = "Halo Admin {$whatsapp['nama']}\n";
$message .= "Saya mau konfirmasi pesanan.\n\n";
$message .= "*KODE PESANAN*\n{$order['kode_pesanan']}\n\n";
$message .= "━━━━━━━━━━━━━━\n";
$message .= "*PEMESAN*\n";
$message .= "Nama         : {$draft['name']}\n";
$message .= "No. WhatsApp : {$draft['phone']}\n";
$message .= 'Jenis pesanan: ' . ($draft['order_type'] === 'dine_in' ? 'Makan di tempat' : 'Bawa pulang') . "\n\n";
$message .= "━━━━━━━━━━━━━━\n";
$message .= $bowlCount > 0
    ? "*RINCIAN* ({$bowlCount} mangkuk)\n\n"
    : "*RINCIAN* ({$otherItemCount} item)\n\n";
$bowlIndex = 0;
$itemIndex = 0;
foreach ($orderLines as $index => $line) {
    $isBowl = str_starts_with($line['nama_produk'], 'Seblak');
    if ($isBowl) {
        $bowlIndex++;
        $bowlName = preg_match('/\ASeblak\s*-\s*(.+)\z/u', $line['nama_produk'], $matches) ? trim($matches[1]) : '';
        $message .= '*MANGKUK ' . $bowlIndex . '*';
        if ($bowlName !== '') {
            $message .= ' (untuk: ' . $bowlName . ')';
        }
        $message .= "\n";
        $menuName = 'Seblak';
    } else {
        $itemIndex++;
        $message .= '*ITEM ' . $itemIndex . "*\n";
        $menuName = $line['nama_produk'];
    }
    $message .= '• Menu        : ' . $menuName . ' x' . (int) $line['jumlah'] . "\n";
    $variants = db_all(
        'SELECT nama_grup, nama_pilihan FROM order_item_variants WHERE order_item_id = :id ORDER BY id',
        ['id' => $line['id']]
    );
    foreach ($variants as $variant) {
        if (stripos($variant['nama_grup'], 'pedas') !== false) {
            $message .= '• Level pedas : ' . $variant['nama_pilihan'] . "\n";
        } else {
            $message .= '• ' . $variant['nama_grup'] . ' : ' . $variant['nama_pilihan'] . "\n";
        }
    }
    $toppings = db_all(
        'SELECT nama_topping, jumlah, pcs_per_paket FROM order_item_toppings WHERE order_item_id = :id ORDER BY id',
        ['id' => $line['id']]
    );
    $toppingDetails = [];
    foreach ($toppings as $topping) {
        $pieces = (int) $topping['jumlah'] * (int) $topping['pcs_per_paket'];
        $toppingDetails[] = $topping['nama_topping'] . ' ' . $pieces . ' pcs';
    }
    if ($toppingDetails) {
        $message .= '• Topping     : ' . implode(', ', $toppingDetails) . "\n";
    }
    $message .= '• Subtotal    : ' . rupiah($line['subtotal']) . "\n\n";
}
$message .= "━━━━━━━━━━━━━━\n";
$message .= '*TOTAL BAYAR: ' . rupiah($order['total']) . "*\n\n";
$message .= "Bayar via : {$method['nama']}\n";
$message .= $proofFilename
    ? "Bukti bayar: Sudah diunggah\nCek di menu *Pesanan Online*"
    : 'Bukti bayar: Tidak diperlukan';
if ($draft['note'] !== '') {
    $message .= "\n\nCatatan: " . $draft['note'];
}

$history = $_SESSION['customer_online_order_history'] ?? [];
if (!is_array($history)) {
    $history = [];
}
$history[(string) $orderId] = [
    'whatsapp_number' => (string) $whatsapp['nomor'],
    'message' => $message,
];
if (count($history) > 20) {
    $history = array_slice($history, -20, null, true);
}
$_SESSION['customer_online_order_history'] = $history;

unset($_SESSION['online_order_draft'], $_SESSION['online_order_payment_method_id']);
header('Location: ' . app_base_path() . '/order_history.php?order=' . $orderId, true, 303);
exit;
