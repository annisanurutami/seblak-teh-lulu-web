<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$paymentMethods = db_all('SELECT id, nama, tipe, nomor_rekening, nama_penerima, detail FROM payment_methods WHERE status = 1 ORDER BY nama');
page_title('Transaksi', 'Kasir', 'Pilih produk atau racik Seblak Prasmanan, lalu simpan pesanan.');
?>
<form class="stack-form order-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>" data-order-form>
    <?php csrf_field(); ?><input type="hidden" name="action" value="pos_save">
    <section class="panel">
        <div class="panel-heading"><div><h2>Data pelanggan</h2></div></div>
        <div class="form-row">
            <label>Nama pelanggan<input name="nama_pelanggan" maxlength="120" required></label>
            <label>Jenis pesanan<select name="jenis_pesanan"><option value="takeaway">Bawa pulang</option><option value="dine_in">Makan di tempat</option></select></label>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Pilih menu</h2><p class="muted">Jumlah 0 berarti produk tidak dimasukkan.</p></div></div>
        <?php require __DIR__ . '/../includes/order_form.php'; ?>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Pembayaran awal</h2></div>
        <div class="form-row">
            <label>Jumlah dibayar<input type="number" name="jumlah_bayar" min="0" step="1000" value="0"><small>Masukkan 0 jika pelanggan membayar nanti.</small></label>
            <?php if ($paymentMethods): ?>
                <label>Metode<select name="metode" required>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= (int) $method['id'] ?>"><?= e($method['nama']) ?><?php if ($method['tipe'] === 'rekening' && $method['nomor_rekening']): ?> - <?= e($method['nomor_rekening']) ?><?php endif; ?><?php if ($method['tipe'] === 'rekening' && $method['nama_penerima']): ?> (<?= e($method['nama_penerima']) ?>)<?php endif; ?><?= $method['detail'] ? ' - ' . e($method['detail']) : '' ?></option>
                    <?php endforeach; ?>
                </select></label>
            <?php else: ?>
                <div class="empty-state">Belum ada metode pembayaran aktif. <a href="<?= e(app_base_path() . '/index.php?page=settings') ?>">Atur metode pembayaran</a>.</div>
            <?php endif; ?>
        </div>
        <label>Catatan pesanan<textarea name="catatan" rows="2"></textarea></label>
        <div class="form-actions"><a class="button button-outline" href="<?= e(app_base_path() . '/index.php') ?>">Batal</a><button class="button button-primary" type="submit">Simpan pesanan kasir</button></div>
    </section>
</form>
