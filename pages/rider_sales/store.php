<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/rider-sales'); csrf_verify();

$user = auth_user();
$isBar = ($user['role'] === 'bar');
$branchId = activeBranchId();

$date = $_POST['date']; $riderId = $_POST['rider_id'];
$items = $_POST['items'] ?? [];
$cashAmount = (float)($_POST['cash_amount'] ?: 0);
$qrisAmount = (float)($_POST['qris_amount'] ?: 0);
$totalGross = (float)($_POST['total_gross_income'] ?: 0);
$actualSetor = (float)($_POST['actual_setor'] ?: $cashAmount); // Bar default: actual_setor = cash_amount
$minus = max(0, $cashAmount - $actualSetor);

try {
    $pdo->beginTransaction();
    // Check existing
    $check = $pdo->prepare("SELECT id FROM rider_daily_sales WHERE branch_id=? AND date=? AND rider_id=?");
    $check->execute([$branchId, $date, $riderId]); $sale = $check->fetch();
    
    if($sale) {
        $saleId = $sale['id'];
        $pdo->prepare("UPDATE rider_daily_sales SET cash_amount=?, actual_setor=?, minus_amount=?, qris_amount=?, total_setoran=?, total_gross_income=?, admin_pemeriksa=?, admin_id=?, updated_at=NOW() WHERE id=?")->execute([$cashAmount, $actualSetor, $minus, $qrisAmount, $cashAmount+$qrisAmount, $totalGross, $_POST['admin_pemeriksa']??null, auth_id(), $saleId]);
        $pdo->prepare("DELETE FROM rider_daily_sale_items WHERE rider_daily_sale_id=?")->execute([$saleId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO rider_daily_sales (branch_id, rider_id, admin_id, date, cash_amount, actual_setor, minus_amount, qris_amount, total_setoran, total_gross_income, admin_pemeriksa, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$branchId, $riderId, auth_id(), $date, $cashAmount, $actualSetor, $minus, $qrisAmount, $cashAmount+$qrisAmount, $totalGross, $_POST['admin_pemeriksa']??null]);
        $saleId = $pdo->lastInsertId();
    }

    $itemStmt = $pdo->prepare("INSERT INTO rider_daily_sale_items (rider_daily_sale_id, product_id, stock_out, stock_added, stock_return, stock_sold, branch_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
    foreach($items as $pId => $data) {
        $out = (int)($data['stock_out'] ?: 0);
        $added = (int)($data['stock_added'] ?: 0);
        $return = (int)($data['stock_return'] ?: 0);
        $sold = (int)($data['stock_sold'] ?: 0);
        $itemStmt->execute([$saleId, $pId, $out, $added, $return, $sold, $branchId]);
    }
    
    $pdo->commit();
    redirect_with('/rider-sales', 'success', 'Data penjualan harian berhasil disimpan.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/rider-sales/create', 'error', 'Gagal: '.$e->getMessage());
}
