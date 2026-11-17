<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$paymentMethods = db_all('SELECT id, nama, tipe, nomor_rekening, nama_penerima, detail FROM payment_methods WHERE status = 1 ORDER BY nama');
$selectedOrder = null;
if (isset($_GET['order'])) {
    $selectedOrder = db_one('SELECT id, kode_pesanan, total, status_pembayaran FROM orders WHERE id = :id', ['id' => (int) $_GET['order']]);
}
$unpaidOrders = db_all(
    "SELECT o.id, o.kode_pesanan, o.nama_pelanggan, o.total, o.status_pembayaran,
            (SELECT COALESCE(SUM(p.jumlah_bayar), 0) FROM payments p WHERE p.order_id = o.id) AS dibayar
     FROM orders o WHERE o.status_pesanan NOT IN ('dibatalkan', 'ditolak')
     AND o.status_pembayaran <> 'lunas' ORDER BY o.created_at DESC LIMIT 100"
);
$payments = db_all(
    'SELECT p.*, o.kode_pesanan, o.nama_pelanggan, u.nama AS petugas
     FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.tanggal_bayar DESC LIMIT 100'
);
page_title('Transaksi', 'Pembayaran', 'Catat pembayaran awal atau pelunasan; status pembayaran terpisah dari status pesanan.');
?>
<?php if ($selectedOrder && $selectedOrder['status_pembayaran'] === 'lunas'): ?>
<section class="panel payment-entry-panel">
    <p class="muted">Pesanan <?= e($selectedOrder['kode_pesanan']) ?> sudah lunas. Pembayaran sudah tercatat.</p>
</section>
<?php elseif ($selectedOrder): ?>
<section class="panel payment-entry-panel">
    <div class="panel-heading"><div><h2>Catat pembayaran · <?= e($selectedOrder['kode_pesanan']) ?></h2><p class="muted">Total pesanan <?= e(rupiah($selectedOrder['total'])) ?> · <?= e(ucfirst(str_replace('_', ' ', $selectedOrder['status_pembayaran']))) ?></p></div></div>
    <?php if ($paymentMethods): ?>
        <form class="inline-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="payment_save"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
            <label>Jumlah pembayaran<input name="jumlah_bayar" type="number" min="1" step="1000" required></label>
            <label>Metode<select name="metode" required>
                <?php foreach ($paymentMethods as $method): ?>
                    <option value="<?= (int) $method['id'] ?>"><?= e($method['nama']) ?><?php if ($method['tipe'] === 'rekening' && $method['nomor_rekening']): ?> - <?= e($method['nomor_rekening']) ?><?php endif; ?><?php if ($method['tipe'] === 'rekening' && $method['nama_penerima']): ?> (<?= e($method['nama_penerima']) ?>)<?php endif; ?><?= $method['detail'] ? ' - ' . e($method['detail']) : '' ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="button button-primary" type="submit">Catat pembayaran</button>
        </form>
    <?php else: ?>
        <p class="muted">Belum ada metode pembayaran aktif. <a href="<?= e(app_base_path() . '/index.php?page=settings') ?>">Atur metode pembayaran</a> terlebih dahulu.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2>Tagihan belum lunas</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Total</th><th>Dibayar</th><th>Sisa</th><th>Status</th><th></th></tr></thead><tbody>
    <?php if (!$unpaidOrders) table_empty_row(7, 'Tidak ada tagihan tertunda.'); ?>
    <?php foreach ($unpaidOrders as $order): $remaining = max(0, (float) $order['total'] - (float) $order['dibayar']); ?>
        <tr><td><?= e($order['kode_pesanan']) ?></td><td><?= e($order['nama_pelanggan'] ?? 'Pelanggan') ?></td><td><?= e(rupiah($order['total'])) ?></td><td><?= e(rupiah($order['dibayar'])) ?></td><td><strong><?= e(rupiah($remaining)) ?></strong></td><td><?= status_badge($order['status_pembayaran']) ?></td><td><a class="button button-small button-outline" href="?page=payments&order=<?= (int) $order['id'] ?>">Bayar</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Riwayat pembayaran</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pesanan</th><th>Pelanggan</th><th>Jumlah</th><th>Metode</th><th>Petugas</th></tr></thead><tbody>
    <?php if (!$payments) table_empty_row(6, 'Belum ada pembayaran.'); ?>
    <?php foreach ($payments as $payment): ?>
        <tr><td><?= e($payment['tanggal_bayar']) ?></td><td><?= e($payment['kode_pesanan']) ?></td><td><?= e($payment['nama_pelanggan'] ?? 'Pelanggan') ?></td><td><?= e(rupiah($payment['jumlah_bayar'])) ?></td><td><?= e(strtoupper($payment['metode'])) ?></td><td><?= e($payment['petugas'] ?? 'Website') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
