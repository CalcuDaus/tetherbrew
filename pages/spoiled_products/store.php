<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/spoiled-products'); csrf_verify();
$date = $_POST['date']; $items = $_POST['items'] ?? []; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT id FROM spoiled_products WHERE branch_id = ? AND date = ?"); $check->execute([$branchId, $date]);
if($check->fetch()) redirect_with('/admin/spoiled-products/create', 'error', 'Data produk basi untuk tanggal tersebut sudah ada! Edit data yang sudah ada.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO spoiled_products (branch_id, user_id, date, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    $stmt->execute([$branchId, auth_id(), $date]); $spoiledId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO spoiled_product_items (spoiled_product_id, product_id, quantity, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    foreach ($items as $productId => $qty) {
        if((int)$qty > 0) $itemStmt->execute([$spoiledId, $productId, (int)$qty]);
    }
    $pdo->commit();
    redirect_with('/admin/spoiled-products', 'success', 'Data produk basi berhasil disimpan!');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/admin/spoiled-products/create', 'error', 'Gagal: '.$e->getMessage());
}
