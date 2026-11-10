<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/inventories');
csrf_verify();

$cartId = $_POST['cart_id'];
$stocks = $_POST['stocks'] ?? [];

foreach ($stocks as $item) {
    $pdo->prepare("INSERT INTO inventories (cart_id, product_id, stock, created_at, updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE stock=VALUES(stock), updated_at=NOW()")
        ->execute([$cartId, $item['product_id'], (int)$item['stock']]);
}

clear_carts_map_cache();

redirect_with("/inventories?cart_id={$cartId}", 'success', 'Stok berhasil diperbarui!');
