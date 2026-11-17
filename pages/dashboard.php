<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$todayOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURRENT_DATE AND status_pesanan IN ('dikonfirmasi', 'diproses', 'selesai')")->fetchColumn();
$todaySales = (float) db()->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE DATE(created_at) = CURRENT_DATE AND status_pesanan IN ('dikonfirmasi', 'diproses', 'selesai')")->fetchColumn();
$waitingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE sumber = 'online' AND status_pesanan = 'menunggu'")->fetchColumn();
page_title('Dashboard', 'Selamat datang, ' . $user['nama'], 'Ringkasan aktivitas ' . APP_NAME . ' hari ini.');
?>
<section class="stat-grid dashboard-stat-grid">
    <article class="stat-card"><span>Pesanan hari ini</span><strong><?= $todayOrders ?></strong><small>Pesanan aktif dan selesai</small></article>
    <article class="stat-card"><span>Penjualan hari ini</span><strong><?= e(rupiah($todaySales)) ?></strong></article>
    <article class="stat-card"><span>Pesanan online menunggu</span><strong><?= $waitingOrders ?></strong><small><a href="?page=orders">Lihat pesanan</a></small></article>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Akses cepat</h2><p class="muted">Pilih pekerjaan yang ingin dilakukan.</p></div></div>
    <div class="quick-links">
        <a class="quick-link" href="?page=pos"><strong>Kasir</strong><span>Buat pesanan pelanggan di tempat</span></a>
        <a class="quick-link" href="?page=orders"><strong>Pesanan online</strong><span>Konfirmasi dan perbarui status</span></a>
        <a class="quick-link" href="?page=expenses"><strong>Pengeluaran</strong><span>Catat biaya operasional</span></a>
        <a class="quick-link" href="<?= e(app_base_path() . '/menu.php') ?>" target="_blank" rel="noopener"><strong>Website pelanggan</strong><span>Lihat menu dan pemesanan online</span></a>
    </div>
</section>
