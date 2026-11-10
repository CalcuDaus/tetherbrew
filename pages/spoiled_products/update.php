<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/spoiled-products'); csrf_verify();
$id = $_POST['id']; $date = $_POST['date']; $items = $_POST['items'] ?? []; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT id FROM spoiled_products WHERE branch_id = ? AND date = ? AND id != ?"); $check->execute([$branchId, $date, $id]);
if($check->fetch()) redirect_with("/admin/spoiled-products/edit?id={$id}", 'error', 'Data produk basi untuk tanggal tersebut sudah ada!');

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE spoiled_products SET date = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")->execute([$date, $id, $branchId]);
    $pdo->prepare("DELETE FROM spoiled_product_items WHERE spoiled_product_id = ?")->execute([$id]);

    $itemStmt = $pdo->prepare("INSERT INTO spoiled_product_items (spoiled_product_id, product_id, quantity, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    foreach ($items as $productId => $qty) {
        if((int)$qty > 0) $itemStmt->execute([$id, $productId, (int)$qty]);
    }
    $pdo->commit();
    redirect_with('/admin/spoiled-products', 'success', 'Data produk basi berhasil diperbarui!');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with("/admin/spoiled-products/edit?id={$id}", 'error', 'Gagal: '.$e->getMessage());
}
