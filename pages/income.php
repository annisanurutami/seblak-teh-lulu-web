<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

[$from, $to] = valid_date_range();
$payments = db_all(
    'SELECT p.tanggal_bayar, p.jumlah_bayar, p.metode, o.kode_pesanan, o.nama_pelanggan, u.nama AS petugas
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     LEFT JOIN users u ON u.id = p.user_id
     WHERE DATE(p.tanggal_bayar) BETWEEN :from_date AND :to_date
     ORDER BY p.tanggal_bayar DESC
     LIMIT 250',
    ['from_date' => $from, 'to_date' => $to]
);
$totalIncome = (float) db_one(
    'SELECT COALESCE(SUM(jumlah_bayar), 0) AS total
     FROM payments
     WHERE DATE(tanggal_bayar) BETWEEN :from_date AND :to_date',
    ['from_date' => $from, 'to_date' => $to]
)['total'];
$paymentCount = (int) db_one(
    'SELECT COUNT(*) AS total
     FROM payments
     WHERE DATE(tanggal_bayar) BETWEEN :from_date AND :to_date',
    ['from_date' => $from, 'to_date' => $to]
)['total'];
page_title('Keuangan', 'Pemasukan dari penjualan', 'Pembayaran pesanan tercatat otomatis sebagai pemasukan.');
?>
<section class="panel">
    <form class="inline-form date-filter" method="get">
        <input type="hidden" name="page" value="income">
        <label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>
</section>
<section class="stat-grid stat-grid-two">
    <article class="stat-card"><span>Total pemasukan diterima</span><strong><?= e(rupiah($totalIncome)) ?></strong></article>
    <article class="stat-card"><span>Transaksi pembayaran</span><strong><?= $paymentCount ?></strong></article>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Riwayat pemasukan penjualan</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>Pesanan</th><th>Pelanggan</th><th>Metode</th><th>Petugas</th><th>Jumlah</th></tr></thead>
            <tbody>
                <?php if (!$payments) table_empty_row(6, 'Belum ada pembayaran pada periode ini.'); ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= e($payment['tanggal_bayar']) ?></td>
                        <td><?= e($payment['kode_pesanan']) ?></td>
                        <td><?= e($payment['nama_pelanggan'] ?? 'Pelanggan') ?></td>
                        <td><?= e(strtoupper($payment['metode'])) ?></td>
                        <td><?= e($payment['petugas'] ?? 'Website') ?></td>
                        <td><?= e(rupiah($payment['jumlah_bayar'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
