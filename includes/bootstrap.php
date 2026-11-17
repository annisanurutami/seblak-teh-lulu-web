<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const APP_NAME = 'Seblak Teh Lulu';

function app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $directory = dirname($scriptName);

    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';

ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => app_base_path() ?: '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(?string $submittedToken): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && is_string($submittedToken)
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}