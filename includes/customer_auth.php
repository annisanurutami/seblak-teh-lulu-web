<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function customer_local_date(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

function customer_user(): ?array
{
    $customerId = $_SESSION['customer_id'] ?? null;
    if (!is_int($customerId)) {
        return null;
    }

    if (($_SESSION['customer_login_date'] ?? null) !== customer_local_date()) {
        unset(
            $_SESSION['customer_id'],
            $_SESSION['customer_login_date'],
            $_SESSION['online_order_draft'],
            $_SESSION['online_order_payment_method_id'],
            $_SESSION['customer_online_order_history']
        );
        session_regenerate_id(true);
        return null;
    }

    $customer = db_one(
        'SELECT id, nama, no_hp FROM customers WHERE id = :id LIMIT 1',
        ['id' => $customerId]
    );
    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_login_date']);
        session_regenerate_id(true);
        return null;
    }

    return $customer;
}

function require_customer(): array
{
    $customer = customer_user();
    if (!$customer) {
        header('Location: ' . app_base_path() . '/customer_login.php');
        exit;
    }

    return $customer;
}

function log_in_customer(int $customerId): void
{
    unset($_SESSION['user_id']);
    clear_remember_cookie();
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_login_date'] = customer_local_date();
    unset(
        $_SESSION['online_order_draft'],
        $_SESSION['online_order_payment_method_id'],
        $_SESSION['customer_online_order_history']
    );
}

function active_order_type_options(): array
{
    return db_all(
        'SELECT id, kode, nama, perlu_alamat
         FROM order_type_options
         WHERE status = 1
         ORDER BY urutan, nama'
    );
}

function order_type_option(string $code, bool $activeOnly = true): ?array
{
    return db_one(
        'SELECT id, kode, nama, perlu_alamat
         FROM order_type_options
         WHERE kode = :code' . ($activeOnly ? ' AND status = 1' : '') . '
         LIMIT 1',
        ['code' => $code]
    );
}

function order_type_label(string $code): string
{
    static $labels = null;
    if ($labels === null) {
        $labels = [];
        foreach (db_all('SELECT kode, nama FROM order_type_options') as $option) {
            $labels[$option['kode']] = $option['nama'];
        }
    }

    if (isset($labels[$code])) {
        return (string) $labels[$code];
    }

    return match ($code) {
        'dine_in' => 'Makan di tempat',
        'takeaway' => 'Dibawa pulang',
        'delivery' => 'Dianter',
        default => $code,
    };
}
