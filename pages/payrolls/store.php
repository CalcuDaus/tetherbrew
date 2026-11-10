<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/payrolls'); csrf_verify();

$branchId = activeBranchId();
$riderId = $_POST['rider_id'];
$type = $_POST['filter_type'];
$periodStart = $_POST['period_start'];
$periodEnd = $_POST['period_end'];
$kasbonDeducted = $_POST['kasbon_deducted'] ?? 0;
$kasbonPaidCash = $_POST['kasbon_paid_cash'] ?? 0;
$minusDeducted = $_POST['minus_deducted'] ?? 0;
$minusPaidCash = $_POST['minus_paid_cash'] ?? 0;
$notes = $_POST['notes'] ?? '';

// Recalculate everything for security
$sStmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
$settings = []; while($s = $sStmt->fetch()) { $settings[$s['key']] = $s['value']; }
$bonusPerCup = (int)($settings['bonus_per_cup'] ?? 2000);
$targetCup = (int)($settings['uang_makan_target_cup'] ?? 1040);
$baseUangMakan = (int)($settings['uang_makan_base_amount'] ?? 650000);

$salesStmt = $pdo->prepare("SELECT id FROM rider_daily_sales WHERE rider_id=? AND date BETWEEN ? AND ?");
$salesStmt->execute([$riderId, $periodStart, $periodEnd]);
$totalCups = 0;
foreach($salesStmt->fetchAll() as $s) {
    $cStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items WHERE rider_daily_sale_id=?");
    $cStmt->execute([$s['id']]); $totalCups += (int)$cStmt->fetchColumn();
}
$grossIncome = $totalCups * $bonusPerCup;

$tkStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='kasbon' AND date <= ?");
$tkStmt->execute([$riderId, $periodEnd]); $totalKasbonAllTime = (int)$tkStmt->fetchColumn();
$tkdStmt = $pdo->prepare("SELECT SUM(kasbon_deducted) FROM payroll_records WHERE rider_id=? AND status='confirmed'");
$tkdStmt->execute([$riderId]); $totalKasbonDeducted = (int)$tkdStmt->fetchColumn();
$outstandingKasbon = max(0, $totalKasbonAllTime - $totalKasbonDeducted);

$tmStmt = $pdo->prepare("SELECT SUM(minus_amount) FROM rider_daily_sales WHERE rider_id=? AND minus_amount > 0 AND date <= ?");
$tmStmt->execute([$riderId, $periodEnd]); $totalMinusAllTime = (int)$tmStmt->fetchColumn();
$tmdStmt = $pdo->prepare("SELECT SUM(minus_deducted) FROM payroll_records WHERE rider_id=? AND status='confirmed'");
$tmdStmt->execute([$riderId]); $totalMinusDeducted = (int)$tmdStmt->fetchColumn();
$outstandingMinus = max(0, $totalMinusAllTime - $totalMinusDeducted);

// Ensure total kasbon deducted + cash doesn't exceed outstanding
$totalKasbonInput = $kasbonDeducted + $kasbonPaidCash;
if ($totalKasbonInput > $outstandingKasbon) {
    if ($kasbonDeducted > $outstandingKasbon) {
        $kasbonDeducted = $outstandingKasbon;
        $kasbonPaidCash = 0;
    } else {
        $kasbonPaidCash = $outstandingKasbon - $kasbonDeducted;
    }
}
// Ensure total minus deducted + cash doesn't exceed outstanding
$totalMinusInput = $minusDeducted + $minusPaidCash;
if ($totalMinusInput > $outstandingMinus) {
    if ($minusDeducted > $outstandingMinus) {
        $minusDeducted = $outstandingMinus;
        $minusPaidCash = 0;
    } else {
        $minusPaidCash = $outstandingMinus - $minusDeducted;
    }
}

$uangMakanAdj = 0;
if($type === 'monthly') {
    $m = date('m', strtotime($periodStart)); $y = date('Y', strtotime($periodStart));
    $umStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='uang_makan' AND MONTH(date)=? AND YEAR(date)=?");
    $umStmt->execute([$riderId, $m, $y]); $totalUangMakan = (int)$umStmt->fetchColumn();

    $msStmt = $pdo->prepare("SELECT id FROM rider_daily_sales WHERE rider_id=? AND MONTH(date)=? AND YEAR(date)=?");
    $msStmt->execute([$riderId, $m, $y]); $monthlyCups = 0;
    foreach($msStmt->fetchAll() as $ms) {
        $cStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items WHERE rider_daily_sale_id=?");
        $cStmt->execute([$ms['id']]); $monthlyCups += (int)$cStmt->fetchColumn();
    }
    $earnedUangMakan = $targetCup > 0 ? ($monthlyCups / $targetCup) * $baseUangMakan : 0;
    $uangMakanAdj = $earnedUangMakan - $totalUangMakan;
}

$netIncome = $grossIncome - $kasbonDeducted - $minusDeducted;
if($type === 'monthly') $netIncome += $uangMakanAdj;

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO payroll_records (branch_id, rider_id, admin_id, type, period_start, period_end, total_cups, gross_income, kasbon_outstanding, kasbon_deducted, kasbon_paid_cash, minus_outstanding, minus_deducted, minus_paid_cash, uang_makan_adjustment, net_income, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW(),NOW())");
    $stmt->execute([$branchId, $riderId, auth_id(), $type, $periodStart, $periodEnd, $totalCups, $grossIncome, $outstandingKasbon, $kasbonDeducted, $kasbonPaidCash, $outstandingMinus, $minusDeducted, $minusPaidCash, $uangMakanAdj, $netIncome, $notes]);
    $id = $pdo->lastInsertId();
    $pdo->commit();
    redirect_with("/payroll/show?id={$id}", 'success', 'Slip gaji berhasil disimpan (Draft).');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/payroll', 'error', 'Gagal: '.$e->getMessage());
}
