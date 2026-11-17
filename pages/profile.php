<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$profile = db_one('SELECT nama, username, email, no_hp, role FROM users WHERE id = :id', ['id' => $user['id']]);
page_title('Akun', 'Profil saya', 'Perbarui informasi akun atau ubah kata sandi.');
?>
<section class="panel profile-panel">
    <form class="stack-form" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
        <?php csrf_field(); ?><input type="hidden" name="action" value="profile_save">
        <div class="form-row"><label>Nama lengkap<input name="nama" maxlength="100" required value="<?= e($profile['nama']) ?>"></label><label>Username<input name="username" maxlength="50" required value="<?= e($profile['username']) ?>"></label></div>
        <div class="form-row"><label>Email<input type="email" name="email" maxlength="100" value="<?= e($profile['email'] ?? '') ?>"></label><label>No. HP<input name="no_hp" maxlength="20" value="<?= e($profile['no_hp'] ?? '') ?>"></label></div>
        <div class="form-row"><label>Kata sandi saat ini <span class="muted">(wajib jika mengganti kata sandi)</span><input type="password" name="current_password" autocomplete="current-password"></label><label>Kata sandi baru <span class="muted">(kosongkan jika tidak ingin mengganti)</span><input type="password" name="password" minlength="8" autocomplete="new-password"></label></div>
        <p class="muted">Role akun: <strong><?= e(ucfirst($profile['role'])) ?></strong></p>
        <button class="button button-primary" type="submit">Simpan profil</button>
    </form>
</section>
