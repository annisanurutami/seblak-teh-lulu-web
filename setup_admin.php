<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/database.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    if ($value === false) {
        fwrite(STDERR, "\nInput tidak dapat dibaca.\n");
        exit(1);
    }

    return trim($value);
}

$existingUsers = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($existingUsers > 0) {
    fwrite(STDERR, "Setup dihentikan: tabel users sudah berisi akun. Script ini hanya untuk admin pertama.\n");
    exit(1);
}

fwrite(STDOUT, "Membuat akun admin awal Seblak Teh Lulu\n");
$nama = prompt('Nama lengkap: ');
$username = prompt('Username: ');
$email = prompt('Email (boleh dikosongkan): ');
$password = prompt('Kata sandi (minimal 8 karakter): ');

if ($nama === '' || strlen($nama) > 100) {
    fwrite(STDERR, "Nama wajib diisi dan maksimal 100 karakter.\n");
    exit(1);
}

if ($username === '' || strlen($username) > 50) {
    fwrite(STDERR, "Username wajib diisi dan maksimal 50 karakter.\n");
    exit(1);
}

if ($email !== '' && (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
    fwrite(STDERR, "Format email tidak valid atau melebihi 100 karakter.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Kata sandi harus memiliki minimal 8 karakter.\n");
    exit(1);
}

$statement = db()->prepare(
    "INSERT INTO users (nama, username, email, password, role, status)
     VALUES (:nama, :username, :email, :password, 'admin', 'aktif')"
);
$statement->execute([
    'nama' => $nama,
    'username' => $username,
    'email' => $email === '' ? null : $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Akun admin berhasil dibuat. Silakan masuk melalui http://localhost/seblak/login.php\n");