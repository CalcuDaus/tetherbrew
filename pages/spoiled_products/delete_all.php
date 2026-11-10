<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/spoiled-products');
}

csrf_verify();

$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    // Hapus child table terlebih dahulu
    $stmt = $pdo->prepare("DELETE spi FROM spoiled_product_items spi 
                           JOIN spoiled_products sp ON spi.spoiled_product_id = sp.id 
                           WHERE sp.branch_id = ?");
    $stmt->execute([$branchId]);

    // Hapus parent table
    $stmt = $pdo->prepare("DELETE FROM spoiled_products WHERE branch_id = ?");
    $stmt->execute([$branchId]);

    $pdo->commit();
    redirect_with('/admin/spoiled-products', 'success', 'Semua data produk basi berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/admin/spoiled-products', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
