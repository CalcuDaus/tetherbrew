<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/rider-sales');
}

csrf_verify();
$branchId = activeBranchId();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE rdsi FROM rider_daily_sale_items rdsi JOIN rider_daily_sales rds ON rdsi.rider_daily_sale_id = rds.id WHERE rds.branch_id = ?");
    $stmt->execute([$branchId]);

    $stmt = $pdo->prepare("DELETE FROM rider_daily_sales WHERE branch_id = ?");
    $stmt->execute([$branchId]);

    // Hapus jurnal terkait penjualan rider yang belum tutup buku? Opsional, tapi lebih baik tidak karena jurnal sudah terpisah atau terkonfirmasi.
    
    $pdo->commit();
    redirect_with('/rider-sales', 'success', 'Semua data penjualan rider berhasil dihapus!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/rider-sales', 'error', 'Gagal menghapus data: ' . $e->getMessage());
}
