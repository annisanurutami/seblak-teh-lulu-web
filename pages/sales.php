<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

[$from, $to] = valid_date_range();
$orders = db_all(
    "SELECT o.*, u.nama AS petugas FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE DATE(o.created_at) BETWEEN :from_date AND :to_date
       AND o.status_pesanan IN ('dikonfirmasi', 'diproses', 'selesai')
     ORDER BY o.created_at DESC LIMIT 250",
    ['from_date' => $from, 'to_date' => $to]
);
$summary = db_one(
    "SELECT COUNT(*) AS jumlah_pesanan, COALESCE(SUM(total), 0) AS total_penjualan
     FROM orders WHERE DATE(created_at) BETWEEN :from_date AND :to_date
       AND status_pesanan IN ('dikonfirmasi', 'diproses', 'selesai')",
    ['from_date' => $from, 'to_date' => $to]
);
page_title('Laporan', 'Penjualan', 'Laporan pesanan berdasarkan periode yang dipilih.');
?>
<section class="panel">
    <form class="inline-form date-filter" method="get">
        <input type="hidden" name="page" value="sales">
        <label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>
</section>
<section class="stat-grid stat-grid-two">
    <article class="stat-card"><span>Jumlah pesanan</span><strong><?= (int) $summary['jumlah_pesanan'] ?></strong></article>
    <article class="stat-card"><span>Nilai penjualan</span><strong><?= e(rupiah($summary['total_penjualan'])) ?></strong></article>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Rincian pesanan</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Kode</th><th>Sumber</th><th>Pelanggan</th><th>Status</th><th>Total</th></tr></thead><tbody>
    <?php if (!$orders) table_empty_row(6, 'Tidak ada penjualan pada periode tersebut.'); ?>
    <?php foreach ($orders as $order): ?>
        <tr><td><?= e($order['created_at']) ?></td><td><?= e($order['kode_pesanan']) ?></td><td><?= e(ucfirst($order['sumber'])) ?></td><td><?= e($order['nama_pelanggan'] ?? 'Pelanggan') ?></td><td><?= status_badge($order['status_pesanan']) ?></td><td><?= e(rupiah($order['total'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
