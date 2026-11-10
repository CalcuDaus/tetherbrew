<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/office-kasbon'); csrf_verify();
$id = $_POST['id']; $branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT * FROM office_kasbons WHERE id = ? AND branch_id = ?"); $stmt->execute([$id, $branchId]); $k = $stmt->fetch();
if(!$k) redirect('/admin/office-kasbon');

try {
    $pdo->beginTransaction();
    

    
    $pdo->prepare("DELETE FROM office_kasbon_payments WHERE office_kasbon_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM office_kasbons WHERE id = ?")->execute([$id]);
    
    $pdo->commit();
    redirect_with('/admin/office-kasbon', 'success', 'Data Kasbon Office berhasil dihapus.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/admin/office-kasbon', 'error', 'Gagal: '.$e->getMessage());
}
