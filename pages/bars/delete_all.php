<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/bars');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'bar' AND branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    redirect_with('/bars', 'success', 'Semua akun bar di cabang ini berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/bars', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
