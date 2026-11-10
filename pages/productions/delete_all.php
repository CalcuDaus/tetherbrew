<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/productions');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    // Hapus child items terlebih dahulu
    $stmt = $pdo->prepare("DELETE dpi FROM daily_production_items dpi 
                           JOIN daily_productions dp ON dpi.daily_production_id = dp.id 
                           WHERE dp.branch_id = ?");
    $stmt->execute([$branchId]);

    // Hapus parent
    $stmt = $pdo->prepare("DELETE FROM daily_productions WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    
    $pdo->commit();
    redirect_with('/admin/productions', 'success', 'Semua riwayat produksi berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/admin/productions', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
