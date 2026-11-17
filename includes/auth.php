<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const REMEMBER_COOKIE = 'satumangkuk_remember';
const REMEMBER_DURATION = 2592000;

function set_remember_cookie(array $user): void
{
    $expires = time() + REMEMBER_DURATION;
    $nonce = bin2hex(random_bytes(32));
    $payload = $user['id'] . '.' . $expires . '.' . $nonce;
    $signature = hash_hmac('sha256', $payload, $user['password']);

    setcookie(REMEMBER_COOKIE, $payload . '.' . $signature, [
        'expires' => $expires,
        'path' => app_base_path() ?: '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => app_base_path() ?: '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function user_by_id(int $id): ?array
{
    $statement = db()->prepare(
        "SELECT id, nama, username, email, password, role, status
         FROM users
         WHERE id = :id
         LIMIT 1"
    );
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();

    return $user ?: null;
}

function remember_user(): ?array
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';

    if (!is_string($cookie) || strlen($cookie) > 256) {
        clear_remember_cookie();
        return null;
    }

    $parts = explode('.', $cookie);
    if (count($parts) !== 4 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
        clear_remember_cookie();
        return null;
    }

    [$id, $expires, $nonce, $signature] = $parts;
    if ((int) $expires < time() || !preg_match('/^[a-f0-9]{64}$/', $nonce)) {
        clear_remember_cookie();
        return null;
    }

    $user = user_by_id((int) $id);
    if (!$user || $user['status'] !== 'aktif') {
        clear_remember_cookie();
        return null;
    }

    $payload = $id . '.' . $expires . '.' . $nonce;
    $expectedSignature = hash_hmac('sha256', $payload, $user['password']);
    if (!hash_equals($expectedSignature, $signature)) {
        clear_remember_cookie();
        return null;
    }

    return $user;
}

function current_user(): ?array
{
    static $resolved = false;
    static $currentUser = null;

    if ($resolved) {
        return $currentUser;
    }

    $resolved = true;

    if (isset($_SESSION['user_id']) && is_int($_SESSION['user_id'])) {
        $currentUser = user_by_id($_SESSION['user_id']);
        if ($currentUser && $currentUser['status'] === 'aktif') {
            return $currentUser;
        }

        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    $rememberedUser = remember_user();
    if ($rememberedUser) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $rememberedUser['id'];
        $currentUser = $rememberedUser;
    }

    return $currentUser;
}

function log_in_user(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    if ($remember) {
        set_remember_cookie($user);
    } else {
        clear_remember_cookie();
    }
}

function log_out_user(): void
{
    $_SESSION = [];
    clear_remember_cookie();

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . app_base_path() . '/login.php');
        exit;
    }

    return $user;
}

function is_admin(array $user): bool
{
    return $user['role'] === 'admin';
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('Akses hanya untuk admin.');
    }

    return $user;
}