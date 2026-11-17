<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM toppings WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$toppings = db_all('SELECT * FROM toppings ORDER BY nama_topping');
page_title('Katalog', 'Topping', 'Atur harga topping berdasarkan jumlah pcs per paket.');
?>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah topping' : 'Tambah topping' ?></h2></div>
        <form class="stack-form catalog-form-grid" method="post" enctype="multipart/form-data" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="topping_save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama topping<input name="nama_topping" maxlength="100" required value="<?= e($edit['nama_topping'] ?? '') ?>"></label>
            <div class="topping-price-fields">
                <label>Harga<input name="harga" type="number" min="0" step="any" required value="<?= e($edit['harga'] ?? '') ?>"></label>
                <label>Pcs<input name="pcs_per_paket" type="number" min="1" max="1000" step="1" required value="<?= e((string) ($edit['pcs_per_paket'] ?? 1)) ?>"><small>Jumlah pcs dalam satu paket.</small></label>
            </div>
            <div class="catalog-image-field catalog-form-wide">
                <label>Foto topping (JPG, PNG, WEBP; maks. 3 MB)<input name="gambar" type="file" accept="image/jpeg,image/png,image/webp" <?= empty($edit['gambar']) ? 'required' : '' ?>></label>
                <?php if (!empty($edit['gambar'])): ?><img class="catalog-form-image" src="<?= e(app_base_path() . '/' . ltrim($edit['gambar'], '/')) ?>" alt="Foto <?= e($edit['nama_topping']) ?>"><?php endif; ?>
            </div>
            <label class="check-line catalog-form-wide"><input type="checkbox" name="status" value="1" <?= !isset($edit['status']) || $edit['status'] ? 'checked' : '' ?>> Aktif</label>
            <button class="button button-primary catalog-form-wide" type="submit">Simpan topping</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Daftar topping</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Foto</th><th>Nama topping</th><th>Harga</th><th>Pcs</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$toppings) table_empty_row(6, 'Belum ada topping.'); ?>
        <?php foreach ($toppings as $topping): ?>
            <tr>
                <td><?php if ($topping['gambar']): ?><img class="catalog-thumb" src="<?= e(app_base_path() . '/' . ltrim($topping['gambar'], '/')) ?>" alt="<?= e($topping['nama_topping']) ?>"><?php else: ?>-<?php endif; ?></td>
                <td><?= e($topping['nama_topping']) ?></td>
                <td><?= e(rupiah($topping['harga'])) ?></td>
                <td><?= (int) $topping['pcs_per_paket'] ?> pcs/paket</td>
                <td><?= status_badge($topping['status'] ? 'aktif' : 'nonaktif') ?></td>
                <td class="catalog-actions">
                    <a class="button button-small button-outline" href="?page=toppings&edit=<?= (int) $topping['id'] ?>">Ubah</a>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>" data-confirm-delete data-confirm-message="Hapus topping ini? Riwayat pesanan tetap disimpan.">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="topping_delete"><input type="hidden" name="id" value="<?= (int) $topping['id'] ?>">
                        <button class="button button-small button-danger-outline" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
