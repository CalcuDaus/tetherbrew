<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/rider-finances/kasbon');
}

csrf_verify();
$branchId = activeBranchId();
$type = $_POST['type'] ?? 'kasbon';
$redirectUrl = $type === 'uang_makan' ? '/rider-finances/uang-makan' : '/rider-finances/kasbon';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM rider_finances WHERE branch_id = ? AND type = ?");
    $stmt->execute([$branchId, $type]);
    
    $pdo->commit();
    redirect_with($redirectUrl, 'success', 'Semua data ' . ($type == 'uang_makan' ? 'uang makan' : 'kasbon') . ' berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with($redirectUrl, 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
