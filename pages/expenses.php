<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$categories = db_all("SELECT id, nama_kategori FROM finance_categories WHERE jenis = 'pengeluaran' ORDER BY nama_kategori");
if (is_admin($user)) {
    $entries = db_all(
        "SELECT fe.*, fc.nama_kategori, u.nama FROM finance_entries fe
         LEFT JOIN finance_categories fc ON fc.id = fe.finance_category_id
         LEFT JOIN users u ON u.id = fe.user_id
         WHERE fe.jenis = 'pengeluaran' ORDER BY fe.tanggal DESC, fe.id DESC LIMIT 100"
    );
} else {
    $entries = db_all(
        "SELECT fe.*, fc.nama_kategori, u.nama FROM finance_entries fe
         LEFT JOIN finance_categories fc ON fc.id = fe.finance_category_id
         LEFT JOIN users u ON u.id = fe.user_id
         WHERE fe.jenis = 'pengeluaran' AND fe.user_id = :user_id
         ORDER BY fe.tanggal DESC, fe.id DESC LIMIT 100",
        ['user_id' => $user['id']]
    );
}
page_title('Operasional', 'Pengeluaran', 'Catat biaya operasional. Karyawan hanya melihat catatan pengeluarannya sendiri.');
?>
<section class="panel">
    <div class="panel-heading"><h2>Catat pengeluaran</h2></div>
    <form class="inline-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
        <?php csrf_field(); ?><input type="hidden" name="action" value="expense_save">
        <label>Kategori<select name="finance_category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['nama_kategori']) ?></option><?php endforeach; ?></select></label>
        <label>Jumlah<input name="jumlah" type="number" min="1" step="1" required></label>
        <label>Tanggal<input name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Keterangan<input name="keterangan" maxlength="255" placeholder="Opsional"></label>
        <button class="button button-primary" type="submit">Simpan pengeluaran</button>
    </form>
</section>
<section class="panel">
    <div class="panel-heading"><h2><?= is_admin($user) ? 'Semua pengeluaran' : 'Pengeluaran yang dicatat' ?></h2></div>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><?php if (is_admin($user)): ?><th>Petugas</th><?php endif; ?><th>Jumlah</th></tr></thead><tbody>
    <?php if (!$entries) table_empty_row(is_admin($user) ? 5 : 4, 'Belum ada pengeluaran.'); ?>
    <?php foreach ($entries as $entry): ?>
        <tr><td><?= e($entry['tanggal']) ?></td><td><?= e($entry['nama_kategori'] ?? 'Lainnya') ?></td><td><?= e($entry['keterangan'] ?? '-') ?></td><?php if (is_admin($user)): ?><td><?= e($entry['nama'] ?? '-') ?></td><?php endif; ?><td><?= e(rupiah($entry['jumlah'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
