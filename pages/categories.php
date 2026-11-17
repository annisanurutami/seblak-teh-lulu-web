<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM categories WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$categories = db_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS jumlah_produk FROM categories c ORDER BY c.nama_kategori');
page_title('Katalog', 'Kategori', 'Atur pengelompokan produk yang ditampilkan di kasir dan website.');
?>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah kategori' : 'Tambah kategori' ?></h2></div>
        <form class="stack-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="category_save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama kategori<input name="nama_kategori" maxlength="100" required value="<?= e($edit['nama_kategori'] ?? '') ?>"></label>
            <label>Deskripsi<textarea name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea></label>
            <label class="check-line"><input type="checkbox" name="status" value="1" <?= !isset($edit['status']) || $edit['status'] ? 'checked' : '' ?>> Aktif</label>
            <button class="button button-primary" type="submit">Simpan kategori</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Daftar kategori</h2><p class="muted"><?= count($categories) ?> kategori</p></div></div>
        <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Produk</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$categories) table_empty_row(4, 'Belum ada kategori.'); ?>
        <?php foreach ($categories as $category): ?>
            <tr><td><strong><?= e($category['nama_kategori']) ?></strong><small><?= e($category['deskripsi'] ?? '') ?></small></td><td><?= (int) $category['jumlah_produk'] ?></td><td><?= status_badge($category['status'] ? 'aktif' : 'nonaktif') ?></td><td><a class="button button-small button-outline" href="?page=categories&edit=<?= (int) $category['id'] ?>">Ubah</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
