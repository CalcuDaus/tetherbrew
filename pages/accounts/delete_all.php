<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/accounts');
}

csrf_verify();
$branchId = activeBranchId();
$currentUserId = auth_id();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM users WHERE branch_id = ? AND id != ? AND role != 'owner'");
    $stmt->execute([$branchId, $currentUserId]);
    
    $pdo->commit();
    redirect_with('/accounts', 'success', 'Semua akun di cabang ini berhasil dihapus (kecuali akun Anda sendiri dan owner)!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/accounts', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
