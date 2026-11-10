<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/carts');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE inv FROM inventories inv JOIN carts c ON inv.cart_id = c.id WHERE c.branch_id = ?");
    $stmt->execute([$branchId]);

    $stmt = $pdo->prepare("DELETE cl FROM cart_locations cl JOIN carts c ON cl.cart_id = c.id WHERE c.branch_id = ?");
    $stmt->execute([$branchId]);

    $stmt = $pdo->prepare("DELETE FROM carts WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    clear_carts_map_cache();
    redirect_with('/carts', 'success', 'Semua data gerobak berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/carts', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
