<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

$user = require_login();
$action = (string) ($_POST['action'] ?? '');
$page = (string) ($_POST['return_page'] ?? 'dashboard');
$pageMap = [
    'category_save' => 'categories',
    'product_save' => 'products',
    'product_delete' => 'products',
    'topping_save' => 'toppings',
    'topping_delete' => 'toppings',
    'variant_save' => 'variants',
    'user_save' => 'users',
    'pos_save' => 'pos',
    'order_status' => 'orders',
    'payment_save' => 'payments',
    'expense_save' => 'expenses',
    'profile_save' => 'profile',
    'whatsapp_save' => 'settings',
    'whatsapp_status' => 'settings',
    'whatsapp_delete' => 'settings',
    'store_status_save' => 'settings',
    'payment_method_save' => 'settings',
    'payment_method_status' => 'settings',
    'payment_method_delete' => 'settings',
];
$page = $pageMap[$action] ?? $page;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
}

$adminActions = [
    'category_save', 'product_save', 'product_delete', 'topping_save', 'topping_delete', 'variant_save',
    'user_save', 'whatsapp_save', 'whatsapp_status', 'whatsapp_delete',
    'store_status_save',
    'payment_method_save', 'payment_method_status', 'payment_method_delete',
];
if (in_array($action, $adminActions, true) && !is_admin($user)) {
    http_response_code(403);
    exit('Akses hanya untuk admin.');
}

try {
    switch ($action) {
        case 'category_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama_kategori', 100);
            if ($name === '') {
                throw new InvalidArgumentException('Nama kategori wajib diisi.');
            }
            $description = post_value('deskripsi', 5000);
            $status = isset($_POST['status']) ? 1 : 0;
            if ($id > 0) {
                $statement = db()->prepare('UPDATE categories SET nama_kategori = ?, deskripsi = ?, status = ? WHERE id = ?');
                $statement->execute([$name, $description ?: null, $status, $id]);
            } else {
                $statement = db()->prepare('INSERT INTO categories (nama_kategori, deskripsi, status) VALUES (?, ?, ?)');
                $statement->execute([$name, $description ?: null, $status]);
            }
            set_flash('success', 'Kategori berhasil disimpan.');
            break;

        case 'product_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama_produk', 150);
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $price = nonnegative_money($_POST['harga'] ?? null, 'Harga produk');
            if ($name === '') {
                throw new InvalidArgumentException('Data produk tidak valid.');
            }
            if ($categoryId > 0 && !db_one('SELECT id FROM categories WHERE id = :id', ['id' => $categoryId])) {
                throw new InvalidArgumentException('Kategori tidak ditemukan.');
            }
            $existingProduct = $id > 0 ? db_one('SELECT gambar FROM products WHERE id = :id', ['id' => $id]) : null;
            if ($id > 0 && !$existingProduct) {
                throw new InvalidArgumentException('Produk tidak ditemukan.');
            }
            $oldImage = $existingProduct['gambar'] ?? null;
            $image = $oldImage;
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $image = save_catalog_image($_FILES['gambar'], 'products');
            }
            if (!$image) {
                throw new InvalidArgumentException('Foto produk wajib diunggah.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $values = [$categoryId ?: null, $name, post_value('deskripsi', 5000) ?: null, $price, $image, isset($_POST['status']) ? 1 : 0];
                if ($id > 0) {
                    $statement = $pdo->prepare('UPDATE products SET category_id = ?, nama_produk = ?, deskripsi = ?, harga = ?, gambar = ?, status = ? WHERE id = ?');
                    $statement->execute([...$values, $id]);
                } else {
                    $statement = $pdo->prepare('INSERT INTO products (category_id, nama_produk, deskripsi, harga, gambar, status) VALUES (?, ?, ?, ?, ?, ?)');
                    $statement->execute($values);
                    $id = (int) $pdo->lastInsertId();
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($image !== $oldImage) {
                    remove_catalog_image($image);
                }
                throw $exception;
            }
            if ($oldImage !== $image) {
                remove_catalog_image($oldImage);
            }
            set_flash('success', 'Produk berhasil disimpan.');
            break;

        case 'product_delete':
            $id = positive_int($_POST['id'] ?? null, 'Produk');
            $product = db_one('SELECT gambar FROM products WHERE id = :id', ['id' => $id]);
            if (!$product) {
                throw new InvalidArgumentException('Produk tidak ditemukan.');
            }
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            remove_catalog_image($product['gambar']);
            set_flash('success', 'Produk berhasil dihapus.');
            break;

        case 'topping_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama_topping', 100);
            if ($name === '') {
                throw new InvalidArgumentException('Nama topping wajib diisi.');
            }
            $price = nonnegative_money($_POST['harga'] ?? null, 'Harga');
            $piecesPerPackage = positive_int($_POST['pcs_per_paket'] ?? null, 'Jumlah pcs', 1000);
            $existingTopping = $id > 0 ? db_one('SELECT gambar FROM toppings WHERE id = :id', ['id' => $id]) : null;
            if ($id > 0 && !$existingTopping) {
                throw new InvalidArgumentException('Topping tidak ditemukan.');
            }
            $oldImage = $existingTopping['gambar'] ?? null;
            $image = $oldImage;
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $image = save_catalog_image($_FILES['gambar'], 'toppings');
            }
            if (!$image) {
                throw new InvalidArgumentException('Foto topping wajib diunggah.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE toppings SET nama_topping = ?, harga = ?, pcs_per_paket = ?, gambar = ?, status = ? WHERE id = ?')->execute([$name, $price, $piecesPerPackage, $image, isset($_POST['status']) ? 1 : 0, $id]);
                } else {
                    $pdo->prepare('INSERT INTO toppings (nama_topping, harga, pcs_per_paket, gambar, status) VALUES (?, ?, ?, ?, ?)')->execute([$name, $price, $piecesPerPackage, $image, isset($_POST['status']) ? 1 : 0]);
                    $id = (int) $pdo->lastInsertId();
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($image !== $oldImage) {
                    remove_catalog_image($image);
                }
                throw $exception;
            }
            if ($oldImage !== $image) {
                remove_catalog_image($oldImage);
            }
            set_flash('success', 'Topping berhasil disimpan.');
            break;

        case 'topping_delete':
            $id = positive_int($_POST['id'] ?? null, 'Topping');
            $topping = db_one('SELECT gambar FROM toppings WHERE id = :id', ['id' => $id]);
            if (!$topping) {
                throw new InvalidArgumentException('Topping tidak ditemukan.');
            }
            db()->prepare('DELETE FROM toppings WHERE id = ?')->execute([$id]);
            remove_catalog_image($topping['gambar']);
            set_flash('success', 'Topping berhasil dihapus.');
            break;

        case 'variant_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama_varian', 100);
            $type = (string) ($_POST['tipe_pilihan'] ?? 'satu');
            $choices = parse_variant_choices($_POST['pilihan_nama'] ?? null, $_POST['pilihan_harga'] ?? null);
            if ($name === '' || !in_array($type, ['satu', 'banyak'], true)) {
                throw new InvalidArgumentException('Data kelompok varian tidak valid.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE variant_groups SET nama_varian = ?, tipe_pilihan = ?, wajib_dipilih = ?, status = ?, urutan = ? WHERE id = ?')
                        ->execute([$name, $type, isset($_POST['wajib_dipilih']) ? 1 : 0, isset($_POST['status']) ? 1 : 0, (int) ($_POST['urutan'] ?? 0), $id]);
                } else {
                    $pdo->prepare('INSERT INTO variant_groups (nama_varian, tipe_pilihan, wajib_dipilih, status, urutan) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$name, $type, isset($_POST['wajib_dipilih']) ? 1 : 0, isset($_POST['status']) ? 1 : 0, (int) ($_POST['urutan'] ?? 0)]);
                    $id = (int) $pdo->lastInsertId();
                }
                $pdo->prepare('DELETE FROM variant_options WHERE variant_group_id = ?')->execute([$id]);
                $insert = $pdo->prepare('INSERT INTO variant_options (variant_group_id, nama_pilihan, harga_tambahan, status) VALUES (?, ?, ?, 1)');
                foreach ($choices as $choice) {
                    $insert->execute([$id, $choice['name'], $choice['price']]);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Kelompok varian berhasil disimpan.');
            break;

        case 'user_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama', 100);
            $username = post_value('username', 50);
            $email = post_value('email', 100);
            $phone = post_value('no_hp', 20);
            $role = (string) ($_POST['role'] ?? 'karyawan');
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || $username === '' || !in_array($role, ['admin', 'karyawan'], true)) {
                throw new InvalidArgumentException('Data akun tidak valid.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Format email tidak valid.');
            }
            if ($id === (int) $user['id'] && $role !== $user['role']) {
                throw new InvalidArgumentException('Peran akun yang sedang digunakan tidak dapat diubah.');
            }
            $status = (string) ($_POST['status'] ?? 'aktif');
            if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                throw new InvalidArgumentException('Status akun tidak valid.');
            }
            if ($id === (int) $user['id'] && $status !== 'aktif') {
                throw new InvalidArgumentException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
            }
            if ($id > 0) {
                $currentAccount = db_one('SELECT role, status FROM users WHERE id = :id', ['id' => $id]);
                if (!$currentAccount) {
                    throw new InvalidArgumentException('Akun tidak ditemukan.');
                }
                if ($currentAccount['role'] === 'admin' && $currentAccount['status'] === 'aktif'
                    && ($role !== 'admin' || $status !== 'aktif')
                    && (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'aktif'")->fetchColumn() <= 1) {
                    throw new InvalidArgumentException('Minimal satu akun admin aktif harus tetap tersedia.');
                }
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        throw new InvalidArgumentException('Kata sandi minimal 8 karakter.');
                    }
                    $statement = db()->prepare('UPDATE users SET nama = ?, username = ?, email = ?, no_hp = ?, password = ?, role = ?, status = ? WHERE id = ?');
                    $statement->execute([$name, $username, $email ?: null, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $status, $id]);
                } else {
                    $statement = db()->prepare('UPDATE users SET nama = ?, username = ?, email = ?, no_hp = ?, role = ?, status = ? WHERE id = ?');
                    $statement->execute([$name, $username, $email ?: null, $phone ?: null, $role, $status, $id]);
                }
            } else {
                if (strlen($password) < 8) {
                    throw new InvalidArgumentException('Kata sandi minimal 8 karakter.');
                }
                $statement = db()->prepare('INSERT INTO users (nama, username, email, no_hp, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([$name, $username, $email ?: null, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
            }
            set_flash('success', 'Akun berhasil disimpan.');
            break;

        case 'pos_save':
            $submittedItems = $_POST['items'] ?? [];
            if (!is_array($submittedItems)) {
                throw new InvalidArgumentException('Daftar produk tidak valid.');
            }
            $items = normalized_order_items($submittedItems);
            $name = post_value('nama_pelanggan', 120);
            if ($name === '') {
                throw new InvalidArgumentException('Nama pelanggan wajib diisi.');
            }
            $orderType = (string) ($_POST['jenis_pesanan'] ?? 'takeaway');
            $payment = nonnegative_money($_POST['jumlah_bayar'] ?? 0, 'Pembayaran');
            $paymentMethod = '';
            if ($payment > 0) {
                $paymentMethodId = positive_int($_POST['metode'] ?? null, 'Metode pembayaran');
                $method = db_one('SELECT nama FROM payment_methods WHERE id = :id AND status = 1', ['id' => $paymentMethodId]);
                if (!$method) {
                    throw new InvalidArgumentException('Pilih metode pembayaran yang masih aktif.');
                }
                $paymentMethod = $method['nama'];
            }
            $total = array_sum(array_column($items, 'total'));
            if (!in_array($orderType, ['dine_in', 'takeaway'], true) || $payment > $total) {
                throw new InvalidArgumentException('Data pesanan atau pembayaran tidak valid.');
            }
            $orderId = create_order(
                $items,
                'kasir',
                ['name' => $name, 'phone' => null, 'note' => post_value('catatan', 2000)],
                (int) $user['id'],
                $orderType,
                'dikonfirmasi',
                $payment,
                $paymentMethod
            );
            set_flash('success', 'Pesanan kasir berhasil disimpan. Nomor pesanan #' . $orderId . '.');
            break;

        case 'order_status':
            $orderId = positive_int($_POST['order_id'] ?? null, 'Pesanan');
            $newStatus = (string) ($_POST['status_pesanan'] ?? '');
            $allowed = ['dikonfirmasi', 'diproses', 'selesai', 'ditolak', 'dibatalkan'];
            if (!in_array($newStatus, $allowed, true)) {
                throw new InvalidArgumentException('Status pesanan tidak valid.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $orderQuery = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
                $orderQuery->execute([$orderId]);
                $order = $orderQuery->fetch();
                if (!$order || $order['sumber'] !== 'online') {
                    throw new InvalidArgumentException('Pesanan online tidak ditemukan.');
                }
                $transitions = [
                    'menunggu' => ['dikonfirmasi', 'ditolak', 'dibatalkan'],
                    'dikonfirmasi' => ['diproses', 'dibatalkan'],
                    'diproses' => ['selesai', 'dibatalkan'],
                    'selesai' => [],
                    'ditolak' => [],
                    'dibatalkan' => [],
                ];
                if (!in_array($newStatus, $transitions[$order['status_pesanan']], true)) {
                    throw new InvalidArgumentException('Perubahan status pesanan tersebut tidak diizinkan.');
                }
                if ($newStatus === 'dikonfirmasi') {
                    $paidQuery = $pdo->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM payments WHERE order_id = ?');
                    $paidQuery->execute([$orderId]);
                    $remaining = round((float) $order['total'] - (float) $paidQuery->fetchColumn(), 2);
                    if ($remaining > 0) {
                        $paymentMethod = trim((string) $order['metode_pembayaran']);
                        if ($paymentMethod === '') {
                            throw new InvalidArgumentException('Metode pembayaran pesanan tidak ditemukan.');
                        }
                        $pdo->prepare('INSERT INTO payments (order_id, user_id, jumlah_bayar, metode) VALUES (?, ?, ?, ?)')
                            ->execute([$orderId, $user['id'], $remaining, $paymentMethod]);
                    }
                    $pdo->prepare("UPDATE orders SET status_pembayaran = 'lunas' WHERE id = ?")->execute([$orderId]);
                }
                $pdo->prepare('UPDATE orders SET status_pesanan = ? WHERE id = ?')->execute([$newStatus, $orderId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash(
                'success',
                $newStatus === 'dikonfirmasi'
                    ? 'Pesanan dikonfirmasi dan pembayaran tercatat lunas.'
                    : 'Status pesanan berhasil diperbarui.'
            );
            break;

        case 'payment_save':
            $orderId = positive_int($_POST['order_id'] ?? null, 'Pesanan');
            $amount = nonnegative_money($_POST['jumlah_bayar'] ?? null, 'Jumlah pembayaran');
            $methodId = positive_int($_POST['metode'] ?? null, 'Metode pembayaran');
            $method = db_one('SELECT nama FROM payment_methods WHERE id = :id AND status = 1', ['id' => $methodId]);
            if ($amount <= 0 || !$method) {
                throw new InvalidArgumentException('Data pembayaran tidak valid.');
            }
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare('SELECT total, status_pesanan FROM orders WHERE id = ? FOR UPDATE');
                $lock->execute([$orderId]);
                $order = $lock->fetch();
                if (!$order) {
                    throw new InvalidArgumentException('Pesanan tidak ditemukan.');
                }
                if (in_array($order['status_pesanan'], ['ditolak', 'dibatalkan'], true)) {
                    throw new InvalidArgumentException('Pembayaran tidak dapat dicatat untuk pesanan yang dibatalkan atau ditolak.');
                }
                $paidQuery = $pdo->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM payments WHERE order_id = ?');
                $paidQuery->execute([$orderId]);
                if ($amount > (float) $order['total'] - (float) $paidQuery->fetchColumn()) {
                    throw new InvalidArgumentException('Jumlah pembayaran melebihi sisa tagihan.');
                }
                $pdo->prepare('INSERT INTO payments (order_id, user_id, jumlah_bayar, metode) VALUES (?, ?, ?, ?)')
                    ->execute([$orderId, $user['id'], $amount, $method['nama']]);
                recalculate_payment_status($pdo, $orderId);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            set_flash('success', 'Pembayaran berhasil dicatat.');
            break;

        case 'expense_save':
            $categoryId = positive_int($_POST['finance_category_id'] ?? null, 'Kategori pengeluaran');
            $amount = nonnegative_money($_POST['jumlah'] ?? null, 'Jumlah pengeluaran');
            $date = (string) ($_POST['tanggal'] ?? date('Y-m-d'));
            $description = post_value('keterangan', 255);
            if ($amount <= 0 || !valid_iso_date($date)
                || !db_one("SELECT id FROM finance_categories WHERE id = :id AND jenis = 'pengeluaran'", ['id' => $categoryId])) {
                throw new InvalidArgumentException('Data pengeluaran tidak valid.');
            }
            db()->prepare("INSERT INTO finance_entries (finance_category_id, user_id, jenis, jumlah, tanggal, keterangan) VALUES (?, ?, 'pengeluaran', ?, ?, ?)")
                ->execute([$categoryId, $user['id'], $amount, $date, $description ?: null]);
            set_flash('success', 'Pengeluaran berhasil dicatat.');
            break;

        case 'profile_save':
            $name = post_value('nama', 100);
            $username = post_value('username', 50);
            $email = post_value('email', 100);
            $phone = post_value('no_hp', 20);
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || $username === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                throw new InvalidArgumentException('Data profil tidak valid.');
            }
            if ($password !== '' && strlen($password) < 8) {
                throw new InvalidArgumentException('Kata sandi baru minimal 8 karakter.');
            }
            if ($password !== '' && !password_verify((string) ($_POST['current_password'] ?? ''), $user['password'])) {
                throw new InvalidArgumentException('Kata sandi saat ini tidak sesuai.');
            }
            if ($password !== '') {
                db()->prepare('UPDATE users SET nama = ?, username = ?, email = ?, no_hp = ?, password = ? WHERE id = ?')
                    ->execute([$name, $username, $email ?: null, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } else {
                db()->prepare('UPDATE users SET nama = ?, username = ?, email = ?, no_hp = ? WHERE id = ?')
                    ->execute([$name, $username, $email ?: null, $phone ?: null, $user['id']]);
            }
            set_flash('success', 'Profil berhasil diperbarui.');
            break;

        case 'whatsapp_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama', 100);
            $phone = preg_replace('/[\s()+-]/', '', post_value('nomor', 25));
            if ($name === '' || !preg_match('/^\d{8,20}$/', $phone)) {
                throw new InvalidArgumentException('Isi nama dan nomor WhatsApp dengan kode negara, hanya angka.');
            }
            if ($id > 0) {
                if (!db_one('SELECT id FROM whatsapp_numbers WHERE id = :id', ['id' => $id])) {
                    throw new InvalidArgumentException('Nomor WhatsApp tidak ditemukan.');
                }
                db()->prepare('UPDATE whatsapp_numbers SET nama = ?, nomor = ?, status = ? WHERE id = ?')
                    ->execute([$name, $phone, isset($_POST['status']) ? 1 : 0, $id]);
            } else {
                db()->prepare('INSERT INTO whatsapp_numbers (nama, nomor, status) VALUES (?, ?, 1)')->execute([$name, $phone]);
            }
            set_flash('success', 'Nomor WhatsApp berhasil disimpan.');
            break;

        case 'whatsapp_status':
            $id = positive_int($_POST['id'] ?? null, 'Nomor WhatsApp');
            db()->prepare('UPDATE whatsapp_numbers SET status = IF(status = 1, 0, 1) WHERE id = ?')->execute([$id]);
            set_flash('success', 'Status nomor WhatsApp diperbarui.');
            break;

        case 'whatsapp_delete':
            $id = positive_int($_POST['id'] ?? null, 'Nomor WhatsApp');
            if (!db_one('SELECT id FROM whatsapp_numbers WHERE id = :id', ['id' => $id])) {
                throw new InvalidArgumentException('Nomor WhatsApp tidak ditemukan.');
            }
            db()->prepare('DELETE FROM whatsapp_numbers WHERE id = ?')->execute([$id]);
            set_flash('success', 'Nomor WhatsApp berhasil dihapus. Riwayat pesanan tetap disimpan.');
            break;

        case 'store_status_save':
            $status = (string) ($_POST['store_status'] ?? '');
            if (!in_array($status, ['buka', 'tutup'], true)) {
                throw new InvalidArgumentException('Status toko tidak valid.');
            }
            db()->prepare(
                'INSERT INTO store_settings (id, status) VALUES (1, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)'
            )->execute([$status]);
            set_flash('success', $status === 'buka' ? 'Status toko sekarang buka.' : 'Status toko sekarang tutup.');
            break;

        case 'payment_method_save':
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_value('nama_metode', 100);
            $type = (string) ($_POST['tipe'] ?? '');
            $accountNumber = post_value('nomor_rekening', 100);
            $recipient = post_value('nama_penerima', 100);
            $detail = post_value('detail', 1000);
            if ($name === '') {
                throw new InvalidArgumentException('Nama metode pembayaran wajib diisi.');
            }
            if (!in_array($type, ['rekening', 'gambar', 'lainnya'], true)) {
                throw new InvalidArgumentException('Tipe metode pembayaran tidak valid.');
            }
            if ($type === 'rekening' && ($accountNumber === '' || $recipient === '')) {
                throw new InvalidArgumentException('Nomor rekening dan nama penerima wajib diisi.');
            }
            if ($type === 'rekening' && !preg_match('/\A[0-9]+\z/', $accountNumber)) {
                throw new InvalidArgumentException('Nomor rekening harus berisi angka saja.');
            }
            $existingMethod = $id > 0
                ? db_one('SELECT id, tipe, gambar FROM payment_methods WHERE id = :id', ['id' => $id])
                : null;
            if ($id > 0 && !$existingMethod) {
                throw new InvalidArgumentException('Metode pembayaran tidak ditemukan.');
            }
            $oldImage = $existingMethod['gambar'] ?? null;
            $imageUpload = isset($_FILES['gambar']) && ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($type !== 'gambar' && $imageUpload) {
                throw new InvalidArgumentException('Gambar hanya dapat digunakan untuk metode QRIS / gambar.');
            }
            $image = $type === 'gambar' ? $oldImage : null;
            if ($type === 'gambar' && $imageUpload) {
                $image = save_catalog_image($_FILES['gambar'], 'payment_methods');
            }
            if ($type === 'gambar' && !$image) {
                throw new InvalidArgumentException('Gambar QRIS wajib diunggah.');
            }
            $numberValue = $type === 'rekening' ? $accountNumber : null;
            $recipientValue = $type === 'rekening' ? $recipient : null;
            $status = $id > 0 ? (isset($_POST['status']) ? 1 : 0) : 1;
            if ($id > 0) {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE payment_methods SET nama = ?, tipe = ?, nomor_rekening = ?, nama_penerima = ?, gambar = ?, detail = ?, status = ? WHERE id = ?')
                        ->execute([$name, $type, $numberValue, $recipientValue, $image, $detail ?: null, $status, $id]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($image !== $oldImage) {
                        remove_catalog_image($image);
                    }
                    throw $exception;
                }
                if ($oldImage !== $image) {
                    remove_catalog_image($oldImage);
                }
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('INSERT INTO payment_methods (nama, tipe, nomor_rekening, nama_penerima, gambar, detail, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$name, $type, $numberValue, $recipientValue, $image, $detail ?: null, $status]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($image !== $oldImage) {
                        remove_catalog_image($image);
                    }
                    throw $exception;
                }
            }
            set_flash('success', 'Metode pembayaran berhasil disimpan.');
            break;

        case 'payment_method_status':
            $id = positive_int($_POST['id'] ?? null, 'Metode pembayaran');
            $statement = db()->prepare('UPDATE payment_methods SET status = IF(status = 1, 0, 1) WHERE id = ?');
            $statement->execute([$id]);
            if ($statement->rowCount() === 0 && !db_one('SELECT id FROM payment_methods WHERE id = :id', ['id' => $id])) {
                throw new InvalidArgumentException('Metode pembayaran tidak ditemukan.');
            }
            set_flash('success', 'Status metode pembayaran diperbarui.');
            break;

        case 'payment_method_delete':
            $id = positive_int($_POST['id'] ?? null, 'Metode pembayaran');
            $method = db_one('SELECT id, gambar FROM payment_methods WHERE id = :id', ['id' => $id]);
            if (!$method) {
                throw new InvalidArgumentException('Metode pembayaran tidak ditemukan.');
            }
            db()->prepare('DELETE FROM payment_methods WHERE id = ?')->execute([$id]);
            remove_catalog_image($method['gambar']);
            set_flash('success', 'Metode pembayaran berhasil dihapus. Riwayat transaksi tetap menyimpan nama metode.');
            break;

        default:
            http_response_code(404);
            exit('Aksi tidak dikenal.');
    }
} catch (InvalidArgumentException $exception) {
    set_flash('error', $exception->getMessage());
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '23000') {
        set_flash('error', 'Data sudah digunakan atau masih terhubung dengan data lain.');
    } else {
        throw $exception;
    }
}

header('Location: ' . app_base_path() . '/index.php?page=' . rawurlencode($page));
exit;

function parse_variant_choices(mixed $names, mixed $prices): array
{
    if (!is_array($names) || !is_array($prices) || count($names) !== count($prices)) {
        throw new InvalidArgumentException('Daftar pilihan varian tidak valid.');
    }

    $choices = [];
    foreach ($names as $index => $rawName) {
        if (!is_string($rawName) || !array_key_exists($index, $prices)) {
            throw new InvalidArgumentException('Daftar pilihan varian tidak valid.');
        }

        $choiceName = trim($rawName);
        if (!preg_match('/^.{1,100}$/us', $choiceName)) {
            throw new InvalidArgumentException('Nama pilihan wajib diisi dan maksimal 100 karakter.');
        }
        $choices[] = [
            'name' => $choiceName,
            'price' => nonnegative_money($prices[$index], 'Harga tambahan'),
        ];
    }

    if (!$choices) {
        throw new InvalidArgumentException('Minimal satu pilihan harus diisi.');
    }
    return $choices;
}

function save_catalog_image(array $file, string $kind): string
{
    if (!in_array($kind, ['products', 'toppings', 'payment_methods'], true)) {
        throw new InvalidArgumentException('Jenis foto katalog tidak valid.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('Foto gagal diunggah atau ukurannya melebihi 3 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Format foto harus JPG, PNG, atau WEBP.');
    }
    $directory = __DIR__ . '/uploads/' . $kind;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder foto katalog tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Foto gagal disimpan.');
    }
    return 'uploads/' . $kind . '/' . $filename;
}

function remove_catalog_image(?string $image): void
{
    if ($image === null || !preg_match('#^uploads/(?:products|toppings|payment_methods)/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $image)) {
        return;
    }
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Foto katalog lama gagal dihapus.');
    }
}
