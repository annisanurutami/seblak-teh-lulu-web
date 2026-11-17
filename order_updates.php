<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$afterId = filter_var($_GET['after_id'] ?? 0, FILTER_VALIDATE_INT);
if ($afterId === false || $afterId < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pesanan tidak valid.']);
    exit;
}

$orders = db_all(
    "SELECT id, kode_pesanan, nama_pelanggan, created_at
     FROM orders
     WHERE sumber = 'online' AND id > :after_id
     ORDER BY id ASC LIMIT 50",
    ['after_id' => $afterId]
);

echo json_encode(['orders' => $orders], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
