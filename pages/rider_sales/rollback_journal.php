<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/rider-sales'); csrf_verify();
$date = $_POST['date']; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT * FROM rider_sales_journal_confirmations WHERE branch_id = ? AND date = ?");
$check->execute([$branchId, $date]); $conf = $check->fetch();
if(!$conf) redirect_with('/rider-sales', 'error', 'Konfirmasi jurnal tidak ditemukan.');

if ((time() - strtotime($conf['created_at'])) > (2 * 24 * 3600)) {
    redirect_with('/rider-sales', 'error', 'Data yang sudah dikonfirmasi lebih dari 2 hari tidak dapat dibatalkan.');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM rider_sales_journal_confirmations WHERE id = ?")->execute([$conf['id']]);
    
    $formattedDate = date('d/m/Y', strtotime($date));
    $pdo->prepare("DELETE FROM journals WHERE branch_id = ? AND date = ? AND description IN (?, ?)")
        ->execute([$branchId, $date, "Penjualan Rider (CASH) - {$formattedDate}", "Penjualan Rider (QRIS) - {$formattedDate}"]);
    
    $pdo->commit();
    redirect_with('/rider-sales', 'success', 'Konfirmasi dibatalkan. Jurnal dihapus.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/rider-sales', 'error', 'Gagal: '.$e->getMessage());
}
