<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_role('owner');

$branchId = $_GET['branch_id'] ?? activeBranchId();
$riderId = $_GET['rider_id'] ?? null; if(!$riderId) redirect('/dashboard');
$month = $_GET['month'] ?? date('m'); $year = $_GET['year'] ?? date('Y');

$rider = $pdo->prepare("SELECT name FROM users WHERE id=?"); $rider->execute([$riderId]); $riderName = $rider->fetchColumn();

// Fetch Sales
$salesStmt = $pdo->prepare("SELECT SUM(total_gross_income) as t_omset, SUM(cash_amount) as t_cash, SUM(qris_amount) as t_qris, SUM(actual_setor) as t_setor, SUM(minus_amount) as t_minus FROM rider_daily_sales WHERE rider_id=? AND branch_id=? AND MONTH(date)=? AND YEAR(date)=?");
$salesStmt->execute([$riderId, $branchId, $month, $year]); $sales = $salesStmt->fetch();

// Fetch Cups
$cupsStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items i JOIN rider_daily_sales s ON i.rider_daily_sale_id=s.id WHERE s.rider_id=? AND s.branch_id=? AND MONTH(s.date)=? AND YEAR(s.date)=?");
$cupsStmt->execute([$riderId, $branchId, $month, $year]); $cups = (int)$cupsStmt->fetchColumn();

// Fetch Kasbon & Uang Makan (Current Outstanding vs Monthly)
$kStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='kasbon'"); $kStmt->execute([$riderId]); $totalK = (int)$kStmt->fetchColumn();
$kdStmt = $pdo->prepare("SELECT SUM(kasbon_deducted) FROM payroll_records WHERE rider_id=? AND status='confirmed'"); $kdStmt->execute([$riderId]); $dedK = (int)$kdStmt->fetchColumn();
$outstandingKasbon = max(0, $totalK - $dedK);

// Setting Target UM
$sStmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='uang_makan_target_cup'"); $sStmt->execute(); $targetCup = (int)$sStmt->fetchColumn() ?: 1040;
$sStmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='uang_makan_base_amount'"); $sStmt->execute(); $baseUM = (int)$sStmt->fetchColumn() ?: 650000;
$sStmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='bonus_per_cup'"); $sStmt->execute(); $bonus = (int)$sStmt->fetchColumn() ?: 2000;

$estUM = $targetCup > 0 ? ($cups / $targetCup) * $baseUM : 0;
$estGaji = $cups * $bonus;

$pageTitle = 'Detail Performa Rider: ' . e($riderName);
ob_start();
?>
<div style="max-width:900px; margin:0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Rider: <?= e($riderName) ?></h2>
        <form method="GET" style="display:flex; gap:10px;"><input type="hidden" name="rider_id" value="<?= $riderId ?>"><input type="hidden" name="branch_id" value="<?= $branchId ?>"><select name="month" class="form-input"><?php for($i=1;$i<=12;$i++): $m=str_pad($i,2,'0',STR_PAD_LEFT); ?><option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option><?php endfor; ?></select><input type="number" name="year" class="form-input" value="<?= $year ?>" style="width:100px;"><button type="submit" class="btn btn-primary">Filter</button></form>
    </div>

    <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card"><div class="stat-icon blue"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg></div><div class="stat-value"><?= formatRupiah($cups) ?></div><div class="stat-label">Total Cup</div></div>
        <div class="stat-card"><div class="stat-icon gold"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div><div class="stat-value"><?= formatRupiah($sales['t_omset']) ?></div><div class="stat-label">Total Omset</div></div>
        <div class="stat-card"><div class="stat-icon red"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></div><div class="stat-value"><?= formatRupiah($sales['t_minus']) ?></div><div class="stat-label">Minus Bulanan</div></div>
    </div>
    
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
        <div class="card">
            <div class="card-header"><div class="card-title">Estimasi Gaji & Uang Makan (Bulan Ini)</div></div>
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-color);"><span>Estimasi Gaji Kotor (<?= formatRupiah($bonus) ?>/cup)</span><strong><?= formatRupiah($estGaji) ?></strong></div>
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-color);"><span>Pencapaian Cup (Target: <?= $targetCup ?>)</span><strong><?= round($targetCup>0?($cups/$targetCup)*100:0, 1) ?>%</strong></div>
                <div style="display:flex; justify-content:space-between; padding:8px 0;"><span>Estimasi Uang Makan Diperoleh</span><strong style="color:var(--accent-blue);"><?= formatRupiah($estUM) ?></strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Hutang Aktif (Semua Waktu)</div></div>
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-color);"><span>Sisa Kasbon</span><strong style="color:var(--accent-red);"><?= formatRupiah($outstandingKasbon) ?></strong></div>
                <div style="padding-top:16px;"><a href="<?= url('/payroll?rider_id=' . $riderId) ?>" class="btn btn-primary" style="width:100%; text-align:center;">Cetak Slip Gaji</a></div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
