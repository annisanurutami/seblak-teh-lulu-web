<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$edit = isset($_GET['edit']) ? db_one('SELECT id, nama, username, email, no_hp, role, status FROM users WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$users = db_all('SELECT id, nama, username, email, no_hp, role, status, created_at FROM users ORDER BY nama');
page_title('Admin', 'Akun pengguna', 'Buat dan nonaktifkan akun admin atau karyawan.');
?>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah akun' : 'Buat akun' ?></h2></div>
        <form class="stack-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="user_save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama lengkap<input name="nama" maxlength="100" required value="<?= e($edit['nama'] ?? '') ?>"></label>
            <label>Username<input name="username" maxlength="50" required value="<?= e($edit['username'] ?? '') ?>"></label>
            <div class="form-row"><label>Email<input type="email" name="email" maxlength="100" value="<?= e($edit['email'] ?? '') ?>"></label><label>No. HP<input name="no_hp" maxlength="20" value="<?= e($edit['no_hp'] ?? '') ?>"></label></div>
            <label>Kata sandi <?= $edit ? '<span class="muted">(kosongkan jika tidak diubah)</span>' : '' ?><input type="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" autocomplete="new-password"></label>
            <div class="form-row"><label>Role<select name="role"><option value="karyawan" <?= ($edit['role'] ?? '') === 'karyawan' ? 'selected' : '' ?>>Karyawan</option><option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label><label>Status<select name="status"><option value="aktif" <?= ($edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="nonaktif" <?= ($edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option></select></label></div>
            <button class="button button-primary" type="submit">Simpan akun</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Pengguna terdaftar</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$users) table_empty_row(5, 'Belum ada akun.'); ?>
        <?php foreach ($users as $account): ?>
            <tr><td><strong><?= e($account['nama']) ?></strong><small><?= e($account['email'] ?? '') ?></small></td><td><?= e($account['username']) ?></td><td><?= e(ucfirst($account['role'])) ?></td><td><?= status_badge($account['status']) ?></td><td><a class="button button-small button-outline" href="?page=users&edit=<?= (int) $account['id'] ?>">Ubah</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
