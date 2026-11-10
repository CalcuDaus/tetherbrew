<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');
csrf_verify();

$lat = $_POST['latitude'];
$lng = $_POST['longitude'];

$cart = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
$cart->execute([auth_id()]);
$cart = $cart->fetch();

if (!$cart) { redirect_back('error', 'Gerobak tidak ditemukan'); }

$pdo->prepare("
    INSERT INTO cart_locations (cart_id, latitude, longitude, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), updated_at = NOW()
")->execute([$cart['id'], $lat, $lng]);
clear_carts_map_cache();

redirect_back('success', 'Lokasi berhasil diperbarui!');
