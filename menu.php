<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';
require_once __DIR__ . '/includes/layout.php';
$message = take_flash();
$storeIsOpen = store_is_open();
$menuProducts = $storeIsOpen ? active_products() : [];
$orderFormPrasmananOptions = $storeIsOpen ? active_prasmanan_options() : ['toppings' => [], 'variants' => []];
$activeWhatsapp = db_one('SELECT id FROM whatsapp_numbers WHERE status = 1 ORDER BY id LIMIT 1');
$menuReady = $storeIsOpen && ($menuProducts || !empty($orderFormPrasmananOptions['toppings'])) && $activeWhatsapp;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/style.css?v=card-controls-fit-20261115') ?>">
    <link rel="stylesheet" href="<?= e(app_base_path() . '/assets/css/responsive.css?v=card-controls-fit-20261115') ?>">
    <script src="<?= e(app_base_path() . '/assets/js/app.js?v=category-square-photo-20261115') ?>" defer></script>
</head>
<body class="store-page">
    <header class="store-header">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>">
            <img class="brand-logo store-brand-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>">
        </a>
        <div class="store-header-actions">
            <span class="store-open-indicator <?= $storeIsOpen ? 'is-ready' : 'is-preparing' ?>"><i></i><?= $storeIsOpen ? ($menuReady ? 'Siap menerima pesanan' : 'Menu sedang disiapkan') : 'Toko tutup' ?></span>
            <a class="button button-outline" href="<?= e(app_base_path() . '/order_history.php') ?>">Riwayat pesanan</a>
            <a class="button button-outline" href="<?= e(app_base_path() . '/login.php') ?>">Masuk karyawan <span aria-hidden="true">↗</span></a>
        </div>
    </header>
    <?php if ($storeIsOpen): ?>
    <section class="store-hero">
        <div class="hero-inner">
            <div class="hero-copy">
                <h1>Ayo pesen<br><em>Seblak Teh Lulu</em></h1>
                <p class="hero-description">Pilih level pedas dan topping favoritmu, lalu pesan tanpa perlu daftar.</p>
                <div class="hero-actions">
                    <a class="button button-hero" href="#menu">Pesan sekarang <span aria-hidden="true">↓</span></a>
                    <span class="hero-action-note"><span aria-hidden="true">✦</span> Gak perlu daftar akun</span>
                </div>
            </div>
            <div class="hero-logo-card">
                <img src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="Logo Seblak Teh Lulu">
            </div>
        </div>
    </section>
    <main class="store-content">
        <?php if ($message): ?><div class="alert alert-<?= e($message['type'] ?? 'info') ?>" role="alert"><?= e($message['message'] ?? '') ?></div><?php endif; ?>
        <form class="stack-form order-form" method="post" action="<?= e(app_base_path() . '/order_review.php') ?>" data-order-form>
            <?php csrf_field(); ?>
            <section class="panel store-customer-panel">
                <div class="store-section-heading">
                    <div class="section-number">01</div>
                    <div><p class="section-kicker">MULAI DARI SINI</p><h2>Pesanan untuk siapa?</h2><p class="muted">Gak perlu daftar akun, isi singkat saja.</p></div>
                </div>
                <div class="form-row form-row-three store-customer-fields">
                    <label>Nama pemesan<input name="nama_pelanggan" maxlength="120" required placeholder="Nama kamu"></label>
                    <label>Nomor WhatsApp<input name="no_hp" maxlength="25" required placeholder="08xx atau 62xx"></label>
                    <label>Mau dinikmati<select name="jenis_pesanan"><option value="takeaway">Dibawa pulang</option><option value="dine_in">Makan di tempat</option></select></label>
                </div>
            </section>
            <section class="panel store-menu-panel" id="menu">
                <div class="store-section-heading store-menu-heading">
                    <div class="section-number">02</div>
                    <div><p class="section-kicker">PILIHAN HARI INI</p><h2>Temukan menu favoritmu</h2><p class="muted">Pilih jumlah, lalu racik sesuai seleramu.</p></div>
                    <span class="menu-note"><span aria-hidden="true">✦</span> Dibuat sesuai pesanan</span>
                </div>
                <?php $isPublicMenu = true; ?>
                <?php require __DIR__ . '/includes/order_form.php'; ?>
            </section>
            <section class="panel checkout-panel store-checkout-panel">
                <div class="section-number">03</div>
                <div class="checkout-details">
                    <label>Catatan kecil untuk kami<textarea name="catatan" rows="3" maxlength="2000" placeholder="Contoh: pedasnya dipisah ya"></textarea></label>
                    <p class="muted">Pesananmu disimpan lebih dulu. Rincian akan tampil sebelum kamu membuka WhatsApp untuk konfirmasi.</p>
                </div>
                <?php if (!empty($orderFormAvailable)): ?>
                    <button class="button button-hero store-submit-button" type="submit">Lanjut pesan <span aria-hidden="true">↗</span></button>
                <?php else: ?>
                    <button class="button button-hero store-submit-button" type="button" disabled>Menu segera hadir</button>
                <?php endif; ?>
            </section>
        </form>
    </main>
    <?php else: ?>
    <main class="store-content">
        <?php if ($message): ?><div class="alert alert-<?= e($message['type'] ?? 'info') ?>" role="alert"><?= e($message['message'] ?? '') ?></div><?php endif; ?>
        <section class="panel store-closed-panel">
            <p class="section-kicker">SEBLAK TEH LULU</p>
            <h1>Toko kami masih tutup</h1>
            <p class="muted">Pemesanan akan tersedia kembali saat toko dibuka.</p>
            <a class="button button-outline" href="<?= e(app_base_path() . '/order_history.php') ?>">Riwayat pesanan</a>
        </section>
    </main>
    <?php endif; ?>
    <footer class="store-footer">
        <a class="brand store-brand" href="<?= e(app_base_path() . '/menu.php') ?>"><img class="brand-logo store-footer-logo" src="<?= e(app_base_path() . '/assets/images/seblak-teh-lulu-logo.png') ?>" alt="<?= e(APP_NAME) ?>"></a>
        <span>Seblak pedas, bikin hari ceria.</span>
        <span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span>
    </footer>
</body>
</html>
