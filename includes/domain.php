<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function db_all(string $sql, array $parameters = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function db_one(string $sql, array $parameters = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $row = $statement->fetch();
    return $row ?: null;
}

function post_value(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException('Input terlalu panjang.');
    }
    return $value;
}

function positive_int(mixed $value, string $label, int $maximum = 1000000): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1 || $number > $maximum) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }
    return $number;
}

function nonnegative_money(mixed $value, string $label): float
{
    if (!is_numeric($value) || (float) $value < 0 || (float) $value > 9999999999) {
        throw new InvalidArgumentException($label . ' tidak valid.');
    }
    return round((float) $value, 2);
}

function store_status(): string
{
    $settings = db_one('SELECT status FROM store_settings WHERE id = 1');
    return $settings['status'] ?? 'buka';
}

function store_is_open(): bool
{
    return store_status() === 'buka';
}

function active_products(): array
{
    return db_all(
        "SELECT p.*, c.nama_kategori
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 1 AND (c.id IS NULL OR c.status = 1)
         ORDER BY p.nama_produk"
    );
}

function is_egg_related_name(string $name): bool
{
    return stripos($name, 'telur') !== false;
}

function active_prasmanan_options(): array
{
    return [
        'variants' => db_all(
            "SELECT vg.id, vg.nama_varian, vg.tipe_pilihan, vg.wajib_dipilih
             FROM variant_groups vg
             WHERE vg.status = 1
               AND EXISTS (SELECT 1 FROM variant_options vo WHERE vo.variant_group_id = vg.id AND vo.status = 1)
             ORDER BY vg.urutan, vg.nama_varian"
        ),
        'toppings' => db_all(
            'SELECT id, nama_topping, harga, pcs_per_paket, gambar
             FROM toppings WHERE status = 1 ORDER BY nama_topping'
        ),
    ];
}

function normalized_order_items(array $submittedItems): array
{
    $result = [];
    $entries = [];
    foreach ($submittedItems as $productId => $input) {
        if (!is_array($input)) {
            continue;
        }
        if ((string) $productId === 'prasmanan' && array_key_exists('bowls', $input)) {
            if (!is_array($input['bowls']) || count($input['bowls']) > 20) {
                throw new InvalidArgumentException('Daftar mangkuk Seblak tidak valid.');
            }
            foreach ($input['bowls'] as $bowlInput) {
                if (!is_array($bowlInput)) {
                    throw new InvalidArgumentException('Pilihan mangkuk Seblak tidak valid.');
                }
                $entries[] = ['product_id' => 'prasmanan', 'input' => $bowlInput];
            }
            continue;
        }
        $entries[] = ['product_id' => $productId, 'input' => $input];
    }

    foreach ($entries as $entry) {
        $productId = $entry['product_id'];
        $input = $entry['input'];
        $quantity = filter_var($input['quantity'] ?? 0, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0 || $quantity > 50) {
            throw new InvalidArgumentException('Jumlah produk tidak valid.');
        }
        if ($quantity === 0) {
            continue;
        }

        $isPrasmanan = (string) $productId === 'prasmanan';
        if ($isPrasmanan) {
            $rawBowlName = $input['name'] ?? '';
            if (!is_string($rawBowlName) || strlen($rawBowlName) > 320) {
                throw new InvalidArgumentException('Nama mangkuk Seblak tidak valid.');
            }
            $bowlName = preg_replace('/\s+/u', ' ', trim($rawBowlName));
            if ($bowlName === null || !preg_match('/^.{0,80}$/us', $bowlName)) {
                throw new InvalidArgumentException('Nama mangkuk Seblak maksimal 80 karakter.');
            }
            $product = [
                'id' => null,
                'nama_produk' => $bowlName === '' ? 'Seblak' : 'Seblak - ' . $bowlName,
                'harga' => 0,
            ];
            $options = active_prasmanan_options();
        } else {
            $product = db_one(
                'SELECT p.id, p.nama_produk, p.harga FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :id AND p.status = 1 AND (c.id IS NULL OR c.status = 1)',
                ['id' => positive_int($productId, 'Produk')]
            );
            if (!$product) {
                throw new InvalidArgumentException('Salah satu produk tidak tersedia.');
            }
            $options = ['variants' => [], 'toppings' => []];
        }
        $variantInputs = $input['variants'] ?? [];
        $toppingInputs = $input['toppings'] ?? [];
        if (!is_array($variantInputs) || !is_array($toppingInputs)) {
            throw new InvalidArgumentException('Pilihan produk tidak valid.');
        }
        $unitAdditions = 0.0;
        $variants = [];
        $toppings = [];

        foreach ($options['variants'] as $group) {
            if (is_egg_related_name((string) $group['nama_varian'])) {
                $hasEggTopping = false;
                foreach ($options['toppings'] as $topping) {
                    if (!is_egg_related_name((string) $topping['nama_topping'])) {
                        continue;
                    }
                    $eggQuantity = filter_var($toppingInputs[$topping['id']] ?? 0, FILTER_VALIDATE_INT);
                    if ($eggQuantity !== false && $eggQuantity > 0) {
                        $hasEggTopping = true;
                        break;
                    }
                }
                if (!$hasEggTopping) {
                    continue;
                }
            }

            $selected = $variantInputs[$group['id']] ?? [];
            $selectedIds = is_array($selected) ? $selected : [$selected];
            $selectedIds = array_values(array_filter($selectedIds, static fn($value) => (string) $value !== ''));
            if ($group['wajib_dipilih'] && count($selectedIds) === 0) {
                throw new InvalidArgumentException('Pilih ' . $group['nama_varian'] . ' untuk ' . $product['nama_produk'] . '.');
            }
            if ($group['tipe_pilihan'] === 'satu' && count($selectedIds) > 1) {
                throw new InvalidArgumentException('Pilihan ' . $group['nama_varian'] . ' hanya boleh satu.');
            }
            foreach ($selectedIds as $optionId) {
                $option = db_one(
                    'SELECT id, nama_pilihan, harga_tambahan FROM variant_options WHERE id = :id AND variant_group_id = :group_id AND status = 1',
                    ['id' => positive_int($optionId, 'Varian'), 'group_id' => $group['id']]
                );
                if (!$option) {
                    throw new InvalidArgumentException('Pilihan varian tidak tersedia.');
                }
                $variants[] = [
                    'group_id' => (int) $group['id'],
                    'option_id' => (int) $option['id'],
                    'group' => $group['nama_varian'],
                    'name' => $option['nama_pilihan'],
                    'price' => (float) $option['harga_tambahan'],
                ];
                $unitAdditions += (float) $option['harga_tambahan'];
            }
        }

        foreach ($options['toppings'] as $topping) {
            $packageQuantity = filter_var($toppingInputs[$topping['id']] ?? 0, FILTER_VALIDATE_INT);
            if ($packageQuantity === false || $packageQuantity < 0 || $packageQuantity > 20) {
                throw new InvalidArgumentException('Jumlah paket topping tidak valid.');
            }
            if ($packageQuantity > 0) {
                $unitPrice = (float) $topping['harga'];
                $toppings[] = [
                    'id' => (int) $topping['id'],
                    'name' => $topping['nama_topping'],
                    'quantity' => $packageQuantity,
                    'pieces_per_package' => (int) $topping['pcs_per_paket'],
                    'package_price' => $unitPrice,
                ];
                $unitAdditions += $unitPrice * $packageQuantity;
            }
        }
        if ($isPrasmanan && !$toppings) {
            throw new InvalidArgumentException('Pilih minimal satu paket topping untuk Seblak Prasmanan.');
        }

        $note = $input['note'] ?? '';
        if (!is_string($note) || strlen($note) > 255) {
            throw new InvalidArgumentException('Catatan item tidak valid.');
        }
        $result[] = [
            'product_id' => $product['id'] === null ? null : (int) $product['id'],
            'name' => $product['nama_produk'],
            'quantity' => $quantity,
            'base_price' => (float) $product['harga'],
            'unit_additions' => $unitAdditions,
            'total' => round(((float) $product['harga'] + $unitAdditions) * $quantity, 2),
            'note' => trim($note),
            'variants' => $variants,
            'toppings' => $toppings,
        ];
    }

    if (!$result) {
        throw new InvalidArgumentException('Pilih minimal satu produk atau menu Seblak Prasmanan.');
    }
    return $result;
}

function next_daily_order_code(PDO $pdo): string
{
    $now = new DateTimeImmutable();
    $orderDate = $now->format('Y-m-d');
    $pdo->prepare('INSERT IGNORE INTO order_code_sequences (order_date, last_number) VALUES (?, 0)')
        ->execute([$orderDate]);

    $sequenceQuery = $pdo->prepare('SELECT last_number FROM order_code_sequences WHERE order_date = ? FOR UPDATE');
    $sequenceQuery->execute([$orderDate]);
    $lastNumber = $sequenceQuery->fetchColumn();
    if ($lastNumber === false) {
        throw new RuntimeException('Nomor urut pesanan harian tidak dapat dibuat.');
    }

    $nextNumber = (int) $lastNumber + 1;
    $pdo->prepare('UPDATE order_code_sequences SET last_number = ? WHERE order_date = ?')
        ->execute([$nextNumber, $orderDate]);

    return sprintf('SM-%s-%02d', $now->format('ymd'), $nextNumber);
}

function create_order(
    array $items,
    string $source,
    array $customer,
    ?int $userId,
    string $orderType,
    string $status,
    float $initialPayment = 0,
    string $paymentMethod = 'tunai',
    ?int $whatsappNumberId = null,
    ?string $onlinePaymentMethod = null,
    ?string $paymentProof = null
): int
{
    $pdo = db();
    $subtotal = array_sum(array_column($items, 'total'));
    $pdo->beginTransaction();
    try {
        $orderCode = next_daily_order_code($pdo);
        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (kode_pesanan, sumber, nama_pelanggan, no_hp, jenis_pesanan, whatsapp_number_id, status_pesanan, status_pembayaran, subtotal, diskon, total, catatan, user_id, metode_pembayaran, bukti_pembayaran)
             VALUES (:code, :source, :name, :phone, :type, :whatsapp_number_id, :status, :payment_status, :subtotal, 0, :total, :note, :user_id, :payment_method, :payment_proof)'
        );
        $insertOrder->execute([
            'code' => $orderCode,
            'source' => $source,
            'name' => $customer['name'] ?: null,
            'phone' => $customer['phone'] ?: null,
            'type' => $orderType,
            'whatsapp_number_id' => $whatsappNumberId,
            'status' => $status,
            'payment_status' => 'belum_bayar',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'note' => $customer['note'] ?: null,
            'user_id' => $userId,
            'payment_method' => $onlinePaymentMethod,
            'payment_proof' => $paymentProof,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        foreach ($items as $item) {
            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, nama_produk, jumlah, harga, subtotal, catatan)
                 VALUES (:order_id, :product_id, :name, :quantity, :price, :subtotal, :note)'
            );
            $insertItem->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['base_price'],
                'subtotal' => $item['total'],
                'note' => $item['note'] ?: null,
            ]);
            $itemId = (int) $pdo->lastInsertId();
            save_order_choices($pdo, $itemId, $item);
        }

        if ($initialPayment > 0) {
            $payment = $pdo->prepare(
                'INSERT INTO payments (order_id, user_id, jumlah_bayar, metode) VALUES (?, ?, ?, ?)'
            );
            $payment->execute([$orderId, $userId, $initialPayment, $paymentMethod]);
            recalculate_payment_status($pdo, $orderId);
        }
        $pdo->commit();
        return $orderId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function payment_proof_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment_proofs';
}

function previous_payment_proof_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'payment_proofs';
}

function legacy_payment_proof_directory(): string
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . basename(dirname(__DIR__)) . '_private'
        . DIRECTORY_SEPARATOR . 'payment_proofs';
}

function save_payment_proof(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('Bukti pembayaran gagal diunggah atau ukurannya melebihi 3 MB.');
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Bukti pembayaran tidak valid. Pilih ulang file bukti.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Bukti pembayaran harus berupa JPG, PNG, atau WEBP.');
    }
    $directory = payment_proof_directory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan bukti pembayaran tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Bukti pembayaran gagal disimpan.');
    }
    return $filename;
}

function remove_payment_proof(?string $filename): void
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', $filename)) {
        return;
    }
    foreach ([payment_proof_directory(), previous_payment_proof_directory(), legacy_payment_proof_directory()] as $directory) {
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Bukti pembayaran gagal dihapus.');
        }
    }
}

function save_order_choices(PDO $pdo, int $itemId, array $item): void
{
    $variantInsert = $pdo->prepare(
        'INSERT INTO order_item_variants (order_item_id, variant_group_id, variant_option_id, nama_grup, nama_pilihan, harga_tambahan)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($item['variants'] as $variant) {
        $variantInsert->execute([$itemId, $variant['group_id'], $variant['option_id'], $variant['group'], $variant['name'], $variant['price']]);
    }
    $toppingInsert = $pdo->prepare(
        'INSERT INTO order_item_toppings (order_item_id, topping_id, nama_topping, jumlah, pcs_per_paket, harga_satuan, subtotal)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($item['toppings'] as $topping) {
        $packageCount = $topping['quantity'] * $item['quantity'];
        $toppingInsert->execute([$itemId, $topping['id'], $topping['name'], $packageCount, $topping['pieces_per_package'], $topping['package_price'], $topping['package_price'] * $packageCount]);
    }
}

function recalculate_payment_status(PDO $pdo, int $orderId): void
{
    $order = db_one('SELECT total FROM orders WHERE id = :id', ['id' => $orderId]);
    $statement = $pdo->prepare('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM payments WHERE order_id = :id');
    $statement->execute(['id' => $orderId]);
    $paid = (float) $statement->fetchColumn();
    $status = $paid <= 0 ? 'belum_bayar' : ($paid >= (float) $order['total'] ? 'lunas' : 'sebagian');
    $statement = $pdo->prepare('UPDATE orders SET status_pembayaran = :status WHERE id = :id');
    $statement->execute(['status' => $status, 'id' => $orderId]);
}

function valid_date_range(): array
{
    $from = (string) ($_GET['from'] ?? date('Y-m-01'));
    $to = (string) ($_GET['to'] ?? date('Y-m-d'));
    if (!valid_iso_date($from) || !valid_iso_date($to) || $from > $to) {
        return [date('Y-m-01'), date('Y-m-d')];
    }
    return [$from, $to];
}

function valid_iso_date(string $date): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
}
