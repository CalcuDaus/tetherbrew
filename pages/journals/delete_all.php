<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/journals');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM journals WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    redirect_with('/journals', 'success', 'Semua data jurnal berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/journals', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
