<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$edit = $editId && $editId > 0
    ? db_one('SELECT * FROM whatsapp_numbers WHERE id = :id', ['id' => $editId])
    : null;
$editPaymentId = filter_var($_GET['edit_payment_method'] ?? null, FILTER_VALIDATE_INT);
$editPaymentMethod = $editPaymentId && $editPaymentId > 0
    ? db_one('SELECT * FROM payment_methods WHERE id = :id', ['id' => $editPaymentId])
    : null;
$numbers = db_all('SELECT * FROM whatsapp_numbers ORDER BY nama');
$paymentMethods = db_all('SELECT * FROM payment_methods ORDER BY nama');
$storeStatus = store_status();
page_title('Admin', 'Pengaturan Toko', 'Atur nomor WhatsApp dan metode pembayaran yang tersedia.');
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Status toko</h2><p class="muted">Saat tutup, menu online disembunyikan dan pelanggan melihat pemberitahuan toko tutup.</p></div></div>
    <form class="inline-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="store_status_save">
        <label>Status toko
            <select name="store_status" required>
                <option value="buka" <?= $storeStatus === 'buka' ? 'selected' : '' ?>>Buka</option>
                <option value="tutup" <?= $storeStatus === 'tutup' ? 'selected' : '' ?>>Tutup</option>
            </select>
        </label>
        <button class="button button-primary" type="submit">Simpan status toko</button>
    </form>
</section>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah nomor WhatsApp' : 'Tambah nomor WhatsApp' ?></h2></div>
        <form class="stack-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="whatsapp_save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama petugas / tujuan<input name="nama" maxlength="100" required placeholder="Admin Seblak Teh Lulu" value="<?= e($edit['nama'] ?? '') ?>"></label>
            <label>Nomor WhatsApp<input name="nomor" maxlength="25" required placeholder="6281234567890" value="<?= e($edit['nomor'] ?? '') ?>"><small>Gunakan kode negara, tanpa tanda + atau spasi.</small></label>
            <?php if ($edit): ?>
                <label class="check-line"><input type="checkbox" name="status" value="1" <?= $edit['status'] ? 'checked' : '' ?>> Aktif</label>
            <?php endif; ?>
            <div class="form-actions">
                <button class="button button-primary" type="submit"><?= $edit ? 'Simpan perubahan' : 'Simpan nomor' ?></button>
                <?php if ($edit): ?><a class="button button-outline" href="<?= e(app_base_path() . '/index.php?page=settings') ?>">Batal</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Nomor terdaftar</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Nomor</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$numbers) table_empty_row(4, 'Tambahkan nomor WhatsApp untuk menerima pesanan.'); ?>
        <?php foreach ($numbers as $number): ?>
            <tr><td><?= e($number['nama']) ?></td><td><?= e($number['nomor']) ?></td><td><?= status_badge($number['status'] ? 'aktif' : 'nonaktif') ?></td><td>
                <div class="catalog-actions">
                    <a class="button button-small button-outline" href="<?= e(app_base_path() . '/index.php?page=settings&edit=' . (int) $number['id']) ?>">Ubah</a>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="whatsapp_status"><input type="hidden" name="id" value="<?= (int) $number['id'] ?>"><button class="button button-small button-outline" type="submit"><?= $number['status'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>" data-confirm-delete data-confirm-message="Hapus nomor ini? Riwayat pesanan tetap disimpan, tetapi nomor tidak lagi ditautkan ke pesanan lama.">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="whatsapp_delete"><input type="hidden" name="id" value="<?= (int) $number['id'] ?>"><button class="button button-small button-danger-outline" type="submit">Hapus</button>
                    </form>
                </div>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $editPaymentMethod ? 'Ubah metode pembayaran' : 'Tambah metode pembayaran' ?></h2></div>
        <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= e(app_base_path() . '/process.php') ?>" data-payment-method-form>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="payment_method_save">
            <input type="hidden" name="id" value="<?= (int) ($editPaymentMethod['id'] ?? 0) ?>">
            <label>Tipe pembayaran
                <select name="tipe" required data-payment-type>
                    <option value="rekening" <?= ($editPaymentMethod['tipe'] ?? 'rekening') === 'rekening' ? 'selected' : '' ?>>Transfer / rekening</option>
                    <option value="gambar" <?= ($editPaymentMethod['tipe'] ?? '') === 'gambar' ? 'selected' : '' ?>>QRIS / gambar</option>
                    <option value="lainnya" <?= ($editPaymentMethod['tipe'] ?? '') === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                </select>
            </label>
            <label>Nama metode<input name="nama_metode" maxlength="100" required placeholder="Contoh: Transfer BCA, QRIS" value="<?= e($editPaymentMethod['nama'] ?? '') ?>"></label>
            <div class="payment-type-fields" data-payment-type-fields="rekening">
                <label>Nomor rekening<input name="nomor_rekening" maxlength="100" inputmode="numeric" pattern="[0-9]+" autocomplete="off" value="<?= e($editPaymentMethod['nomor_rekening'] ?? '') ?>" data-payment-required></label>
                <label>Nama penerima rekening<input name="nama_penerima" maxlength="100" value="<?= e($editPaymentMethod['nama_penerima'] ?? '') ?>" data-payment-required></label>
            </div>
            <div class="payment-type-fields" data-payment-type-fields="gambar">
                <label>Gambar QRIS<input type="file" name="gambar" accept="image/jpeg,image/png,image/webp" data-payment-image data-has-image="<?= !empty($editPaymentMethod['gambar']) ? '1' : '0' ?>"><small>Format JPG, PNG, atau WEBP; maksimal 3 MB.</small></label>
                <?php if (!empty($editPaymentMethod['gambar'])): ?>
                    <img class="payment-method-image" src="<?= e(app_base_path() . '/' . ltrim($editPaymentMethod['gambar'], '/')) ?>" alt="Gambar pembayaran <?= e($editPaymentMethod['nama']) ?>">
                <?php endif; ?>
            </div>
            <label>Instruksi tambahan (opsional)<textarea name="detail" rows="3" maxlength="1000" placeholder="Contoh: nama bank atau petunjuk pembayaran"><?= e($editPaymentMethod['detail'] ?? '') ?></textarea></label>
            <?php if ($editPaymentMethod): ?>
                <label class="check-line"><input type="checkbox" name="status" value="1" <?= $editPaymentMethod['status'] ? 'checked' : '' ?>> Aktif untuk pembayaran</label>
            <?php endif; ?>
            <div class="form-actions">
                <button class="button button-primary" type="submit"><?= $editPaymentMethod ? 'Simpan perubahan' : 'Simpan metode' ?></button>
                <?php if ($editPaymentMethod): ?><a class="button button-outline" href="<?= e(app_base_path() . '/index.php?page=settings') ?>">Batal</a><?php endif; ?>
            </div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Metode pembayaran</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Metode</th><th>Tipe dan detail</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$paymentMethods) table_empty_row(4, 'Tambahkan metode pembayaran yang digunakan toko.'); ?>
        <?php foreach ($paymentMethods as $method): ?>
            <tr><td><?= e($method['nama']) ?></td><td>
                <?php if ($method['tipe'] === 'rekening'): ?>
                    <div>Transfer / rekening</div>
                    <div>Nomor: <?= e($method['nomor_rekening'] ?: 'Belum diatur') ?></div>
                    <div>Atas nama: <?= e($method['nama_penerima'] ?: 'Belum diatur') ?></div>
                <?php elseif ($method['tipe'] === 'gambar'): ?>
                    <div>QRIS / gambar</div>
                    <?php if (!empty($method['gambar'])): ?><img class="payment-method-image" src="<?= e(app_base_path() . '/' . ltrim($method['gambar'], '/')) ?>" alt="Gambar QRIS <?= e($method['nama']) ?>"><?php endif; ?>
                    <?php if (empty($method['gambar'])): ?><div>Gambar belum diunggah.</div><?php endif; ?>
                <?php else: ?>
                    <div>Lainnya</div>
                <?php endif; ?>
                <?php if (!empty($method['detail'])): ?><div><?= nl2br(e($method['detail'])) ?></div><?php endif; ?>
            </td><td><?= status_badge($method['status'] ? 'aktif' : 'nonaktif') ?></td><td>
                <div class="catalog-actions">
                    <a class="button button-small button-outline" href="<?= e(app_base_path() . '/index.php?page=settings&edit_payment_method=' . (int) $method['id']) ?>">Ubah</a>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="payment_method_status"><input type="hidden" name="id" value="<?= (int) $method['id'] ?>"><button class="button button-small button-outline" type="submit"><?= $method['status'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>" data-confirm-delete data-confirm-message="Hapus metode ini? Riwayat pembayaran tetap menyimpan nama metode yang digunakan.">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="payment_method_delete"><input type="hidden" name="id" value="<?= (int) $method['id'] ?>"><button class="button button-small button-danger-outline" type="submit">Hapus</button>
                    </form>
                </div>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
