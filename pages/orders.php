<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$orders = db_all(
    "SELECT o.*, u.nama AS petugas,
            (SELECT COALESCE(SUM(p.jumlah_bayar), 0) FROM payments p WHERE p.order_id = o.id) AS dibayar
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE o.sumber = 'online' ORDER BY o.created_at DESC LIMIT 100"
);
$latestOnlineId = (int) db()->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE sumber = 'online'")->fetchColumn();
$audioFile = __DIR__ . '/../assets/audio/new-order.mp3';
$audioUrl = is_file($audioFile) ? app_base_path() . '/assets/audio/new-order.mp3' : '';
page_title('Transaksi', 'Pesanan online', 'Periksa pesanan pelanggan dan perbarui statusnya.');
?>
<section class="panel">
    <div class="panel-heading order-live-controls" data-order-live data-latest-id="<?= $latestOnlineId ?>" data-endpoint="<?= e(app_base_path() . '/order_updates.php') ?>" data-audio-url="<?= e($audioUrl) ?>">
        <div><h2>Pesanan masuk</h2><p class="muted" data-order-live-status>Memeriksa pesanan baru otomatis setiap beberapa detik.</p></div>
        <button class="button button-outline" type="button" data-enable-order-sound aria-pressed="false">Aktifkan suara</button>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Waktu</th><th>Total</th><th>Pembayaran</th><th>Status pesanan</th><th>Aksi</th></tr></thead><tbody>
    <?php if (!$orders) table_empty_row(7, 'Belum ada pesanan online.'); ?>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><strong><?= e($order['kode_pesanan']) ?></strong><small><?= e(order_type_label((string) $order['jenis_pesanan'])) ?></small></td>
            <td><?= e($order['nama_pelanggan'] ?? 'Pelanggan') ?><small><?= e($order['no_hp'] ?? '') ?></small></td>
            <td><?= e($order['created_at']) ?></td>
            <td><?= e(rupiah($order['total'])) ?><small>Terbayar <?= e(rupiah($order['dibayar'])) ?></small></td>
            <td><?= status_badge($order['status_pembayaran']) ?><?php if (!empty($order['metode_pembayaran'])): ?><small><?= e($order['metode_pembayaran']) ?></small><?php endif; ?><?php if (!empty($order['bukti_pembayaran'])): ?><small><a href="<?= e(app_base_path() . '/payment_proof.php?order=' . (int) $order['id']) ?>">Lihat bukti pembayaran</a></small><?php endif; ?></td>
            <td><?= status_badge($order['status_pesanan']) ?></td>
            <td>
                <?php if (in_array($order['status_pesanan'], ['menunggu', 'dikonfirmasi', 'diproses'], true)): ?>
                <form class="compact-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
                    <?php csrf_field(); ?><input type="hidden" name="action" value="order_status"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <select name="status_pesanan" aria-label="Status pesanan">
                        <?php if ($order['status_pesanan'] === 'menunggu'): ?><option value="dikonfirmasi">Konfirmasi</option><option value="ditolak">Tolak</option><option value="dibatalkan">Batalkan</option>
                        <?php elseif ($order['status_pesanan'] === 'dikonfirmasi'): ?><option value="diproses">Proses</option><option value="dibatalkan">Batalkan</option>
                        <?php else: ?><option value="selesai">Selesai</option><option value="dibatalkan">Batalkan</option><?php endif; ?>
                    </select><button class="button button-small button-primary" type="submit">Simpan</button>
                </form>
                <?php endif; ?>
                <?php if ($order['status_pembayaran'] !== 'lunas'): ?><a class="button button-small button-outline" href="?page=payments&order=<?= (int) $order['id'] ?>">Pembayaran</a><?php endif; ?>
            </td>
        </tr>
        <?php $lines = db_all('SELECT oi.*, (SELECT GROUP_CONCAT(CONCAT(oiv.nama_grup, ": ", oiv.nama_pilihan) SEPARATOR ", ") FROM order_item_variants oiv WHERE oiv.order_item_id = oi.id) AS variants, (SELECT GROUP_CONCAT(CONCAT(oit.nama_topping, " x", oit.jumlah, " (", oit.jumlah * oit.pcs_per_paket, " pcs)") SEPARATOR ", ") FROM order_item_toppings oit WHERE oit.order_item_id = oi.id) AS toppings FROM order_items oi WHERE oi.order_id = :id', ['id' => $order['id']]); ?>
        <tr class="order-detail-row"><td colspan="7"><strong>Isi:</strong> <?php foreach ($lines as $line): ?><span class="order-detail"><?= e($line['nama_produk']) ?> × <?= (int) $line['jumlah'] ?><?= $line['variants'] ? ' · ' . e($line['variants']) : '' ?><?= $line['toppings'] ? ' · ' . e($line['toppings']) : '' ?> · <?= e(rupiah($line['subtotal'])) ?></span><?php endforeach; ?><?php if (!empty($order['alamat_pengantaran'])): ?><span class="order-detail">Alamat pengantaran: <?= e($order['alamat_pengantaran']) ?></span><?php endif; ?><?= $order['catatan'] ? '<span class="order-detail">Catatan: ' . e($order['catatan']) . '</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
