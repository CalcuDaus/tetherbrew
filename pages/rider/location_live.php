<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');
csrf_verify();

header('Content-Type: application/json');

$lat = $_POST['latitude'] ?? null;
$lng = $_POST['longitude'] ?? null;

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Koordinat tidak lengkap']);
    exit;
}

$cart = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
$cart->execute([auth_id()]);
$cart = $cart->fetch();

if (!$cart) {
    echo json_encode(['success' => false, 'message' => 'Gerobak tidak ditemukan']);
    exit;
}

$pdo->prepare("
    INSERT INTO cart_locations (cart_id, latitude, longitude, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = NOW()
")->execute([$cart['id'], $lat, $lng]);
clear_carts_map_cache();

echo json_encode(['success' => true, 'message' => 'Lokasi berhasil diperbarui!']);
