<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/office-kasbon');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE okp FROM office_kasbon_payments okp JOIN office_kasbons ok ON okp.office_kasbon_id = ok.id WHERE ok.branch_id = ?");
    $stmt->execute([$branchId]);

    $stmt = $pdo->prepare("DELETE FROM office_kasbons WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    redirect_with('/admin/office-kasbon', 'success', 'Semua riwayat kasbon office berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/admin/office-kasbon', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
