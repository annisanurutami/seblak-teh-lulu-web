<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

[$from, $to] = valid_date_range();
$sales = (float) db_one(
    "SELECT COALESCE(SUM(total), 0) AS amount FROM orders
     WHERE DATE(created_at) BETWEEN :from_date AND :to_date
       AND status_pesanan IN ('dikonfirmasi', 'diproses', 'selesai')",
    ['from_date' => $from, 'to_date' => $to]
)['amount'];
$incomeQuery = db()->prepare(
    "SELECT COALESCE(SUM(jumlah_bayar), 0) FROM payments
     WHERE DATE(tanggal_bayar) BETWEEN :from_date AND :to_date"
);
$incomeQuery->execute(['from_date' => $from, 'to_date' => $to]);
$income = (float) $incomeQuery->fetchColumn();
$expenseQuery = db()->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) FROM finance_entries
     WHERE jenis = 'pengeluaran' AND tanggal BETWEEN :from_date AND :to_date"
);
$expenseQuery->execute(['from_date' => $from, 'to_date' => $to]);
$expenses = (float) $expenseQuery->fetchColumn();
page_title('Laporan admin', 'Laporan keuangan', 'Pemasukan pembayaran penjualan, pengeluaran, dan selisih untuk periode pilihan.');
?>
<section class="panel">
    <form class="inline-form date-filter" method="get">
        <input type="hidden" name="page" value="finance">
        <label>Dari tanggal<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Sampai tanggal<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>
</section>
<section class="stat-grid">
    <article class="stat-card"><span>Penjualan</span><strong><?= e(rupiah($sales)) ?></strong><small>Pesanan bukan ditolak/dibatalkan</small></article>
    <article class="stat-card"><span>Pemasukan diterima</span><strong><?= e(rupiah($income)) ?></strong><small>Dari pembayaran pesanan, tercatat satu kali</small></article>
    <article class="stat-card"><span>Pengeluaran</span><strong><?= e(rupiah($expenses)) ?></strong><small>Biaya operasional tercatat</small></article>
    <article class="stat-card"><span>Selisih kas</span><strong><?= e(rupiah($income - $expenses)) ?></strong><small>Pemasukan dikurangi pengeluaran</small></article>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Arus kas berdasarkan tanggal pembayaran</h2></div>
    <?php $daily = db_all(
        "SELECT day_date AS tanggal, SUM(income) AS pemasukan, SUM(expense) AS pengeluaran
         FROM (
            SELECT DATE(tanggal_bayar) AS day_date, jumlah_bayar AS income, 0 AS expense FROM payments WHERE DATE(tanggal_bayar) BETWEEN :from_payments AND :to_payments
            UNION ALL
            SELECT tanggal AS day_date, 0 AS income, jumlah AS expense FROM finance_entries WHERE jenis = 'pengeluaran' AND tanggal BETWEEN :from_entries AND :to_entries
         ) cash GROUP BY day_date ORDER BY day_date DESC",
        ['from_payments' => $from, 'to_payments' => $to, 'from_entries' => $from, 'to_entries' => $to]
    ); ?>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Pemasukan</th><th>Pengeluaran</th><th>Selisih</th></tr></thead><tbody>
    <?php if (!$daily) table_empty_row(4, 'Belum ada arus kas pada periode ini.'); ?>
    <?php foreach ($daily as $day): ?><tr><td><?= e($day['tanggal']) ?></td><td><?= e(rupiah($day['pemasukan'])) ?></td><td><?= e(rupiah($day['pengeluaran'])) ?></td><td><strong><?= e(rupiah((float) $day['pemasukan'] - (float) $day['pengeluaran'])) ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
