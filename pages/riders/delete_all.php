<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/riders');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    // Lepaskan gerobak dan set inactive jika rider dihapus
    $pdo->prepare("
        UPDATE carts c 
        INNER JOIN users u ON c.user_id = u.id 
        SET c.user_id = NULL, c.status = 'inactive', c.updated_at = NOW() 
        WHERE u.role = 'rider' AND c.branch_id = ?
    ")->execute([$branchId]);

    $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'rider' AND branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    clear_carts_map_cache();
    redirect_with('/riders', 'success', 'Semua data rider di cabang ini berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/riders', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
