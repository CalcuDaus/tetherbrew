<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/rider-sales'); csrf_verify();
$date = $_POST['date']; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT id FROM rider_sales_journal_confirmations WHERE branch_id = ? AND date = ?");
$check->execute([$branchId, $date]); if($check->fetch()) redirect_with('/rider-sales', 'error', 'Penjualan tanggal ini sudah dikonfirmasi.');

$salesStmt = $pdo->prepare("SELECT rs.*, a.role as admin_role FROM rider_daily_sales rs LEFT JOIN users a ON rs.admin_id = a.id WHERE rs.branch_id = ? AND DATE(rs.date) = ?");
$salesStmt->execute([$branchId, $date]); $sales = $salesStmt->fetchAll();

if(empty($sales)) redirect_with('/rider-sales', 'error', 'Tidak ada data penjualan untuk tanggal ini.');

$unverified = 0; $totalCash=0; $totalQris=0; $totalMinus=0; $totalOmset=0; $totalActualSetor=0;
$riders = [];
foreach($sales as $s) {
    if(!in_array($s['admin_role'], ['admin','owner'])) $unverified++;
    $totalCash += $s['cash_amount']; $totalQris += $s['qris_amount']; $totalMinus += $s['minus_amount'];
    $totalOmset += $s['total_gross_income']; $totalActualSetor += $s['actual_setor'];
    $riders[$s['rider_id']] = true;
}

if($unverified > 0) redirect_with('/rider-sales', 'error', "Ada {$unverified} data yang belum Anda periksa. Harap periksa semua sebelum mengonfirmasi ke Jurnal.");

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO rider_sales_journal_confirmations (date, branch_id, total_cash, total_qris, total_minus, total_omset, rider_count, confirmed_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$date, $branchId, $totalCash, $totalQris, $totalMinus, $totalOmset, count($riders), auth_id()]);
    
    // Journaling
    $catStmt = $pdo->prepare("SELECT id FROM journal_categories WHERE name='Penjualan Cabang'"); $catStmt->execute(); $catId = $catStmt->fetchColumn();
    if(!$catId) {
        $pdo->prepare("INSERT INTO journal_categories (name, created_at, updated_at) VALUES ('Penjualan Cabang', NOW(),NOW())")->execute();
        $catId = $pdo->lastInsertId();
    }

    $formattedDate = date('d/m/Y', strtotime($date));
    if($totalActualSetor > 0) {
        $pdo->prepare("INSERT INTO journals (branch_id, journal_category_id, date, type, amount, description, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$branchId, $catId, $date, 'debit', $totalActualSetor, "Penjualan Rider (CASH) - {$formattedDate}", auth_id()]);
    }
    if($totalQris > 0) {
        $pdo->prepare("INSERT INTO journals (branch_id, journal_category_id, date, type, amount, description, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$branchId, $catId, $date, 'debit', $totalQris, "Penjualan Rider (QRIS) - {$formattedDate}", auth_id()]);
    }

    $pdo->commit();
    redirect_with('/rider-sales', 'success', 'Berhasil mengonfirmasi penjualan ke Jurnal Umum.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/rider-sales', 'error', 'Gagal: '.$e->getMessage());
}
