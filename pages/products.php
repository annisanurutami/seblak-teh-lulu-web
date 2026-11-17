<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM products WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$categories = db_all('SELECT id, nama_kategori FROM categories WHERE status = 1 ORDER BY nama_kategori');
$products = db_all('SELECT p.*, c.nama_kategori FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.nama_produk');
page_title('Katalog', 'Produk', 'Kelola informasi produk.');
?>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah produk' : 'Tambah produk' ?></h2></div>
        <form class="stack-form catalog-form-grid" method="post" enctype="multipart/form-data" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="product_save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama produk<input name="nama_produk" maxlength="150" required value="<?= e($edit['nama_produk'] ?? '') ?>"></label>
            <label>Kategori<select name="category_id"><option value="0">Tanpa kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['nama_kategori']) ?></option><?php endforeach; ?></select></label>
            <label>Harga dasar<input name="harga" type="number" min="0" step="100" required value="<?= e((string) ($edit['harga'] ?? '0')) ?>"></label>
            <div class="catalog-image-field">
                <label>Foto (JPG, PNG, WEBP; maks. 3 MB)<input name="gambar" type="file" accept="image/jpeg,image/png,image/webp" <?= empty($edit['gambar']) ? 'required' : '' ?>></label>
                <?php if (!empty($edit['gambar'])): ?><img class="catalog-form-image" src="<?= e(app_base_path() . '/' . ltrim($edit['gambar'], '/')) ?>" alt="Foto <?= e($edit['nama_produk']) ?>"><?php endif; ?>
            </div>
            <label class="catalog-form-wide">Deskripsi<textarea name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea></label>
            <label class="check-line catalog-form-wide"><input type="checkbox" name="status" value="1" <?= !isset($edit['status']) || $edit['status'] ? 'checked' : '' ?>> Produk aktif</label>
            <button class="button button-primary catalog-form-wide" type="submit">Simpan produk</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Daftar produk</h2><p class="muted"><?= count($products) ?> produk</p></div></div>
        <div class="table-wrap"><table><thead><tr><th>Foto</th><th>Produk</th><th>Harga</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$products) table_empty_row(5, 'Belum ada produk.'); ?>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?php if ($product['gambar']): ?><img class="catalog-thumb" src="<?= e(app_base_path() . '/' . ltrim($product['gambar'], '/')) ?>" alt="<?= e($product['nama_produk']) ?>"><?php else: ?>- <?php endif; ?></td>
                <td><strong><?= e($product['nama_produk']) ?></strong><small><?= e($product['nama_kategori'] ?? 'Tanpa kategori') ?></small></td>
                <td><?= e(rupiah($product['harga'])) ?></td>
                <td><?= status_badge($product['status'] ? 'aktif' : 'nonaktif') ?></td>
                <td class="catalog-actions">
                    <a class="button button-small button-outline" href="?page=products&edit=<?= (int) $product['id'] ?>">Ubah</a>
                    <form method="post" action="<?= e(app_base_path() . '/process.php') ?>" data-confirm-delete data-confirm-message="Hapus produk ini? Riwayat pesanan tetap disimpan.">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="product_delete"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                        <button class="button button-small button-danger-outline" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
