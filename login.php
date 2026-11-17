<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    header('Location: ' . app_base_path() . '/index.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Username/email dan kata sandi wajib diisi.';
    } else {
        $statement = db()->prepare(
            "SELECT id, nama, username, email, password, role, status
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1"
        );
        $statement->execute([
            'username' => $identifier,
            'email' => $identifier,
        ]);
        $user = $statement->fetch();

        if (!$user || $user['status'] !== 'aktif' || !password_verify($password, $user['password'])) {
            $error = 'Username atau password salah.';
        } else {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = db()->prepare('UPDATE users SET password = :password WHERE id = :id');
                $update->execute([
                    'password' => $newHash,
                    'id' => $user['id'],
                ]);
                $user['password'] = $newHash;
            }

            log_in_user($user, $remember);
            header('Location: ' . app_base_path() . '/index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=admin-desktop-20261115') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=admin-desktop-20261115') ?>">
</head>
<body class="auth-page">
    <div class="auth-layout">
        <a class="button button-outline auth-back-button" href="<?= e(app_base_path() . '/menu.php') ?>" aria-label="Kembali ke tampilan awal">
            <span aria-hidden="true">←</span>
        </a>
        <main class="auth-card">
            <img class="brand-logo auth-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
            <p class="eyebrow">AREA KARYAWAN</p>
            <h1>Selamat datang</h1>
            <p class="muted">Hanya admin dan karyawan aktif yang dapat masuk.</p>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(app_base_path() . '/login.php') ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="identifier">Username atau email</label>
                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    value="<?= e($identifier) ?>"
                    autocomplete="username"
                    maxlength="100"
                    required
                    autofocus
                >

                <label for="password">Kata sandi</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

                <label class="checkbox-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" class="button button-primary button-full">Masuk</button>
            </form>
        </main>
    </div>
</body>
</html>