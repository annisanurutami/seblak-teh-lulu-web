<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function render_header(array $user, string $activePage): void
{
    $hasUnfinishedOnlineOrders = (int) db()->query(
        "SELECT COUNT(*) FROM orders
         WHERE sumber = 'online' AND status_pesanan IN ('menunggu', 'dikonfirmasi', 'diproses')"
    )->fetchColumn() > 0;
    $navigationGroups = [
        [
            'label' => 'Penjualan',
            'items' => [
                ['pos', 'Kasir'],
                ['orders', 'Pesanan Online'],
                ['payments', 'Riwayat Transaksi'],
            ],
        ],
    ];
    $standaloneLinks = [];
    if (is_admin($user)) {
        array_splice($navigationGroups, 1, 0, [[
            'label' => 'Menu & Produk',
            'items' => [
                ['categories', 'Kategori'],
                ['products', 'Produk'],
                ['toppings', 'Topping'],
                ['variants', 'Varian'],
            ],
        ]]);
        $navigationGroups[] = [
            'label' => 'Keuangan',
            'items' => [
                ['income', 'Pemasukan'],
                ['expenses', 'Pengeluaran'],
                ['finance', 'Laporan Keuangan'],
            ],
        ];
        $navigationGroups[] = [
            'label' => 'Pengaturan',
            'items' => [
                ['users', 'Akun Karyawan'],
                ['profile', 'Profil'],
                ['settings', 'Pengaturan Toko'],
            ],
        ];
    } else {
        $standaloneLinks = [
            ['expenses', 'Pengeluaran'],
            ['profile', 'Profil'],
        ];
    }
    $flash = take_flash();
    $toastType = $flash && in_array($flash['type'] ?? '', ['success', 'error', 'info'], true)
        ? $flash['type']
        : 'info';
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(APP_NAME) ?><?= $activePage === 'dashboard' ? '' : ' | ' . e(ucfirst($activePage)) ?></title>
        <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=payment-proof-view-20261115') ?>">
        <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=card-controls-fit-20261115') ?>">
        <script src="<?= e(app_base_path() . '/assets/js/app.js?v=catalog-image-preview-20261115') ?>" defer></script>
    </head>
    <body class="app-page">
        <button class="nav-backdrop" type="button" data-nav-backdrop aria-label="Tutup menu"></button>
        <aside class="sidebar" id="admin-navigation" aria-label="Navigasi utama" aria-hidden="true">
            <div class="sidebar-head">
                <a class="brand sidebar-brand" href="<?= e(app_base_path() . '/index.php') ?>">
                    <img class="brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="">
                    <span><strong><?= e(APP_NAME) ?></strong></span>
                </a>
                <button class="sidebar-close" type="button" data-nav-close aria-label="Tutup menu">&times;</button>
            </div>
            <p class="sidebar-label">MENU</p>
            <nav class="side-nav" aria-label="Navigasi utama">
                <a class="side-nav-home <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= e(app_base_path() . '/index.php') ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <?php foreach ($navigationGroups as $group): ?>
                    <?php $groupPages = array_column($group['items'], 0); ?>
                    <?php $groupIsActive = in_array($activePage, $groupPages, true); ?>
                    <?php $showPendingOrder = in_array('orders', $groupPages, true) && $hasUnfinishedOnlineOrders; ?>
                    <details class="nav-group" <?= $groupIsActive || $showPendingOrder ? 'open' : '' ?>>
                        <summary><?= e($group['label']) ?></summary>
                        <div class="nav-submenu">
                            <?php foreach ($group['items'] as [$page, $label]): ?>
                                <a class="<?= $page === $activePage ? 'active' : '' ?><?= $page === 'orders' && $hasUnfinishedOnlineOrders ? ' nav-order-has-pending' : '' ?>" href="<?= e(app_base_path() . '/index.php?page=' . $page) ?>" <?= $page === $activePage ? 'aria-current="page"' : '' ?>>
                                    <?= e($label) ?>
                                    <?php if ($page === 'orders' && $hasUnfinishedOnlineOrders): ?>
                                        <span class="order-notification-dot" role="img" aria-label="Ada pesanan online yang belum selesai"></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
                <?php foreach ($standaloneLinks as [$page, $label]): ?>
                    <a class="<?= $page === $activePage ? 'active' : '' ?>" href="<?= e(app_base_path() . '/index.php?page=' . $page) ?>" <?= $page === $activePage ? 'aria-current="page"' : '' ?>>
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-user">
                <strong><?= e($user['nama']) ?></strong>
                <span><?= e(ucfirst($user['role'])) ?></span>
                <form method="post" action="<?= e(app_base_path() . '/logout.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="link-button" type="submit">Keluar</button>
                </form>
            </div>
        </aside>
        <main class="app-main">
            <header class="app-header">
                <button class="menu-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="admin-navigation" aria-label="<?= $hasUnfinishedOnlineOrders ? 'Buka menu, ada pesanan online belum selesai' : 'Buka menu' ?>">
                    <span class="menu-toggle-lines" aria-hidden="true"><i></i><i></i><i></i></span>
                    <?php if ($hasUnfinishedOnlineOrders): ?><span class="order-notification-dot menu-order-notification-dot" aria-hidden="true"></span><?php endif; ?>
                </button>
                <div class="app-header-context">
                    <span>AREA PENGELOLA</span>
                    <strong><?= e($user['nama']) ?></strong>
                </div>
                <a class="brand app-header-brand" href="<?= e(app_base_path() . '/index.php') ?>" aria-label="Beranda">
                    <img class="brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
                </a>
            </header>
            <div class="page-content">
                <?php if ($flash): ?>
                    <div class="toast-notification toast-<?= e($toastType) ?>" role="<?= $toastType === 'error' ? 'alert' : 'status' ?>" data-toast>
                        <span class="toast-icon" aria-hidden="true"><?= $toastType === 'success' ? '✓' : ($toastType === 'error' ? '!' : 'i') ?></span>
                        <p><?= e($flash['message'] ?? '') ?></p>
                        <button class="toast-close" type="button" data-toast-close aria-label="Tutup notifikasi">&times;</button>
                    </div>
                <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
            </div>
        </main>
    </body>
    </html>
    <?php
}

function page_title(string $eyebrow, string $title, string $description = ''): void
{
    ?>
    <div class="page-heading">
        <div>
            <p class="eyebrow"><?= e(strtoupper($eyebrow)) ?></p>
            <h1><?= e($title) ?></h1>
            <?php if ($description !== ''): ?><p class="muted"><?= e($description) ?></p><?php endif; ?>
        </div>
    </div>
    <?php
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function status_badge(string $status): string
{
    $label = str_replace('_', ' ', $status);
    return '<span class="status-badge status-' . e($status) . '">' . e(ucfirst($label)) . '</span>';
}

function table_empty_row(int $columns, string $message): void
{
    echo '<tr><td colspan="' . $columns . '" class="empty-cell">' . e($message) . '</td></tr>';
}
