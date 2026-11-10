<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$branchId = activeBranchId();
$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); $riders->execute([$branchId]); $riders = $riders->fetchAll();

$riderId = $_GET['rider_id'] ?? null;
$filterType = $_GET['filter_type'] ?? 'weekly';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$weekStart = $_GET['week_start'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Settings
$sStmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
$settings = []; while($s = $sStmt->fetch()) { $settings[$s['key']] = $s['value']; }
$bonusPerCup = (int)($settings['bonus_per_cup'] ?? 2000);
$targetCup = (int)($settings['uang_makan_target_cup'] ?? 1040);
$baseUangMakan = (int)($settings['uang_makan_base_amount'] ?? 650000);

$payrollData = null;

if($riderId) {
    $rStmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $rStmt->execute([$riderId]); $rider = $rStmt->fetch();
    
    $startDate = null; $endDate = null;
    if($filterType === 'weekly' && $weekStart) {
        if(strpos($weekStart, '|') !== false) {
            $parts = explode('|', $weekStart); $startDate = date('Y-m-d', strtotime($parts[0])); $endDate = date('Y-m-d', strtotime($parts[1]));
        } else {
            $startDate = date('Y-m-d', strtotime($weekStart)); $endDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));
        }
    } elseif($filterType === 'custom' && $dateFrom && $dateTo) {
        $startDate = $dateFrom; $endDate = $dateTo;
    } else {
        $filterType = 'monthly';
        $startDate = date('Y-m-d', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    if($startDate && $endDate) {
        $exStmt = $pdo->prepare("SELECT id FROM payroll_records WHERE rider_id=? AND period_start=? AND period_end=?");
        $exStmt->execute([$riderId, $startDate, $endDate]); $existingPayroll = $exStmt->fetch();

        $salesStmt = $pdo->prepare("SELECT id, total_gross_income FROM rider_daily_sales WHERE rider_id=? AND date BETWEEN ? AND ?");
        $salesStmt->execute([$riderId, $startDate, $endDate]); $sales = $salesStmt->fetchAll();
        $totalSalesRevenue = 0; $totalCups = 0;
        foreach($sales as $s) {
            $totalSalesRevenue += $s['total_gross_income'];
            $cStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items WHERE rider_daily_sale_id=?");
            $cStmt->execute([$s['id']]); $totalCups += (int)$cStmt->fetchColumn();
        }

        // Outstanding Kasbon
        $tkStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='kasbon' AND date <= ?");
        $tkStmt->execute([$riderId, $endDate]); $totalKasbonAllTime = (int)$tkStmt->fetchColumn();
        
        $tkdStmt = $pdo->prepare("SELECT SUM(kasbon_deducted + kasbon_paid_cash) FROM payroll_records WHERE rider_id=? AND status='confirmed'");
        $tkdStmt->execute([$riderId]); $totalKasbonDeducted = (int)$tkdStmt->fetchColumn();
        
        $outstandingKasbon = max(0, $totalKasbonAllTime - $totalKasbonDeducted);

        // Outstanding Minus
        $tmStmt = $pdo->prepare("SELECT SUM(minus_amount) FROM rider_daily_sales WHERE rider_id=? AND minus_amount > 0 AND date <= ?");
        $tmStmt->execute([$riderId, $endDate]); $totalMinusAllTime = (int)$tmStmt->fetchColumn();
        
        $tmdStmt = $pdo->prepare("SELECT SUM(minus_deducted) FROM payroll_records WHERE rider_id=? AND status='confirmed'");
        $tmdStmt->execute([$riderId]); $totalMinusDeducted = (int)$tmdStmt->fetchColumn();
        
        $outstandingMinus = max(0, $totalMinusAllTime - $totalMinusDeducted);

        // Uang Makan (Monthly only)
        $includeUangMakan = ($filterType === 'monthly');
        $totalUangMakan = 0; $achPercentage = 0; $earnedUangMakan = 0; $sisaUangMakan = 0;
        
        if($includeUangMakan) {
            $umStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='uang_makan' AND MONTH(date)=? AND YEAR(date)=?");
            $umStmt->execute([$riderId, $month, $year]); $totalUangMakan = (int)$umStmt->fetchColumn();

            $msStmt = $pdo->prepare("SELECT id FROM rider_daily_sales WHERE rider_id=? AND MONTH(date)=? AND YEAR(date)=?");
            $msStmt->execute([$riderId, $month, $year]);
            $monthlyCups = 0;
            foreach($msStmt->fetchAll() as $ms) {
                $cStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items WHERE rider_daily_sale_id=?");
                $cStmt->execute([$ms['id']]); $monthlyCups += (int)$cStmt->fetchColumn();
            }

            $achPercentage = $targetCup > 0 ? ($monthlyCups / $targetCup) * 100 : 0;
            $earnedUangMakan = $targetCup > 0 ? ($monthlyCups / $targetCup) * $baseUangMakan : 0;
            $sisaUangMakan = $earnedUangMakan - $totalUangMakan;
        }

        $grossIncome = $totalCups * $bonusPerCup;
        $netIncome = $grossIncome - $outstandingKasbon - $outstandingMinus;
        if($includeUangMakan) $netIncome += $sisaUangMakan;

        $payrollData = compact('rider', 'filterType', 'startDate', 'endDate', 'month', 'year', 'totalCups', 'totalSalesRevenue', 'outstandingKasbon', 'outstandingMinus', 'totalUangMakan', 'targetCup', 'achPercentage', 'earnedUangMakan', 'sisaUangMakan', 'grossIncome', 'netIncome', 'includeUangMakan', 'bonusPerCup', 'existingPayroll');
    }
}
$pageTitle = 'Generate Slip Gaji';
ob_start();
?>
<div class="card mb-4 print-hide" style="margin-bottom: 8px;">
    <div class="card-header">
        <h3 class="card-title">Filter Payroll</h3>
    </div>
    <div class="card-body">
        <form method="GET" id="filter-form" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 160px; margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Tipe Gaji
                </label>
                <select name="filter_type" id="filter_type" class="form-input payroll-select" onchange="toggleFilterFields()">
                    <option value="weekly" <?= $filterType === 'weekly' ? 'selected' : '' ?>>Gaji Mingguan</option>
                    <option value="custom" <?= $filterType === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    <option value="monthly" <?= $filterType === 'monthly' ? 'selected' : '' ?>>Gaji Akhir Bulan</option>
                </select>
            </div>

            <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Rider
                </label>
                <select name="rider_id" class="form-input payroll-select" required>
                    <option value="">Pilih Rider...</option>
                    <?php foreach($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderId == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Monthly / Weekly specific: Month/Year -->
            <div class="form-group filter-monthly filter-weekly" style="flex: 1; min-width: 130px; margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Bulan
                </label>
                <select name="month" id="month" class="form-input payroll-select" onchange="updateWeeklyOptions()">
                    <?php for($i = 1; $i <= 12; $i++): $m = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group filter-monthly filter-weekly" style="flex: 1; min-width: 110px; margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Tahun
                </label>
                <select name="year" id="year" class="form-input payroll-select" onchange="updateWeeklyOptions()">
                    <?php for($i = date('Y'); $i >= 2023; $i--): ?>
                        <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Weekly specific: Week Start Selection -->
            <div class="form-group filter-weekly" style="flex: 2; min-width: 220px; margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Minggu
                </label>
                <select name="week_start" id="week_start" class="form-input payroll-select"></select>
            </div>

            <!-- Custom specific: Date Range -->
            <div class="form-group filter-custom" style="flex: 1; min-width: 140px; margin-bottom: 0; display: none;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary);">Dari Tanggal</label>
                <div class="input-with-icon-wrapper">
                    <input type="text" name="date_from" value="<?= e($dateFrom) ?>" class="form-input datepicker" placeholder="Pilih Tanggal">
                    <svg width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>

            <div class="form-group filter-custom" style="flex: 1; min-width: 140px; margin-bottom: 0; display: none;">
                <label class="form-label" style="font-size: 0.8rem; font-weight: 600; color: var(--text-secondary);">Sampai Tanggal</label>
                <div class="input-with-icon-wrapper">
                    <input type="text" name="date_to" value="<?= e($dateTo) ?>" class="form-input datepicker" placeholder="Pilih Tanggal">
                    <svg width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 20px; font-weight: 600; border-radius: 10px;">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<?php if($payrollData): ?>
    <?php if($payrollData['existingPayroll']): ?>
        <div class="card" style="margin-bottom:20px; border-left:4px solid var(--accent-blue);">
            <div class="card-body">Slip gaji untuk periode ini sudah pernah dibuat. <a href="<?= url('/payroll/show?id=' . $payrollData['existingPayroll']['id']) ?>" class="btn btn-primary btn-sm">Lihat Slip</a></div>
        </div>
    <?php endif; ?>
    
    <?php if(!$payrollData['existingPayroll']): ?>
        <div class="card mb-4 print-hide" style="margin-bottom: 8px;">
            <div class="card-header">
                <h3 class="card-title">Simpan Slip Gaji &mdash; Custom Pembayaran</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('/payroll/store') ?>" id="payroll-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="rider_id" value="<?= $riderId ?>">
                    <input type="hidden" name="filter_type" value="<?= $filterType ?>">
                    <input type="hidden" name="period_start" value="<?= $payrollData['startDate'] ?>">
                    <input type="hidden" name="period_end" value="<?= $payrollData['endDate'] ?>">
        
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                        <div style="flex: 1; min-width: 200px; padding: 16px; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 12px;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px;">Gaji Kotor</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #22c55e;" id="display-gross">Rp <?= number_format($payrollData['grossIncome'], 0, ',', '.') ?></div>
                        </div>
                        <div style="flex: 1; min-width: 200px; padding: 16px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px;">Outstanding Kasbon</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #ef4444;">Rp <?= number_format($payrollData['outstandingKasbon'], 0, ',', '.') ?></div>
                        </div>
                        <div style="flex: 1; min-width: 200px; padding: 16px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px;">Outstanding Minus</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #ef4444;">Rp <?= number_format($payrollData['outstandingMinus'], 0, ',', '.') ?></div>
                        </div>
                    </div>
        
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Potong Kasbon dari Gaji (Rp) <small style="color: #64748b; font-weight: 500;">Max: Rp <?= number_format($payrollData['outstandingKasbon'], 0, ',', '.') ?></small></label>
                            <div class="input-with-icon-wrapper">
                                <input type="number" name="kasbon_deducted" id="kasbon_deducted" class="form-input" min="0" max="<?= $payrollData['outstandingKasbon'] ?>" value="<?= $payrollData['outstandingKasbon'] ?>" oninput="recalculateTHP()">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Bayar Kasbon (via Cash) <small style="color: #64748b; font-weight: 500;">(Tidak potong gaji)</small></label>
                            <div class="input-with-icon-wrapper">
                                <input type="number" name="kasbon_paid_cash" id="kasbon_paid_cash" class="form-input" min="0" max="<?= $payrollData['outstandingKasbon'] ?>" value="0" oninput="recalculateTHP()">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Potong Minus dari Gaji (Rp) <small style="color: #64748b; font-weight: 500;">Max: Rp <?= number_format($payrollData['outstandingMinus'], 0, ',', '.') ?></small></label>
                            <div class="input-with-icon-wrapper">
                                <input type="number" name="minus_deducted" id="minus_deducted" class="form-input" min="0" max="<?= $payrollData['outstandingMinus'] ?>" value="<?= $payrollData['outstandingMinus'] ?>" oninput="recalculateTHP()">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Bayar Minus (via Cash) <small style="color: #64748b; font-weight: 500;">(Tidak potong gaji)</small></label>
                            <div class="input-with-icon-wrapper">
                                <input type="number" name="minus_paid_cash" id="minus_paid_cash" class="form-input" min="0" max="<?= $payrollData['outstandingMinus'] ?>" value="0" oninput="recalculateTHP()">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                            </div>
                        </div>
                    </div>
        
                    <?php if($payrollData['includeUangMakan']): ?>
                        <input type="hidden" id="um_adj" value="<?= $payrollData['sisaUangMakan'] ?>">
                    <?php else: ?>
                        <input type="hidden" id="um_adj" value="0">
                    <?php endif; ?>
        
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; align-items: center;">
                        <div style="flex: 1; min-width: 300px; padding: 16px 20px; background: #22c55e; color: white; border-radius: 12px; box-shadow: 0 4px 10px rgba(34, 197, 94, 0.15);">
                            <div style="font-size: 0.8rem; margin-bottom: 2px; opacity: 0.9; font-weight: 500;">Take Home Pay (Estimasi)</div>
                            <div style="font-size: 1.4rem; font-weight: 700;" id="display-thp">Rp <?= number_format($payrollData['netIncome'], 0, ',', '.') ?></div>
                            <div style="font-size: 0.75rem; margin-top: 2px; opacity: 0.8;" id="display-sisa-info">
                                <?php if($payrollData['outstandingMinus'] > 0): ?>
                                    Sisa minus carry-over: Rp <span id="sisa-minus-display">0</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Catatan (Opsional)</label>
                            <div class="input-with-icon-wrapper">
                                <input type="text" name="notes" class="form-input" placeholder="Catatan tambahan...">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            </div>
                        </div>
                    </div>
        
                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary flex-center" style="gap: 0.5rem; padding: 10px 24px;">
                            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Simpan Slip Gaji
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card" id="payslip-card">
            <div class="card-header print-hide" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">Preview Fee Mitra Rider</h3>
                <div style="display: flex; gap: 8px;">
                    <button onclick="window.print()" class="btn btn-secondary btn-sm flex-center" style="gap: 0.5rem;">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="payslip-container" style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: 'Poppins', sans-serif;">
                    <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px dashed #cbd5e1; padding-bottom: 20px;">
                        <img src="<?= base_url('assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;" onerror="this.src='<?= base_url('assets/images/tether-icon-head.webp') ?>'; this.onerror=null;">
                        <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">TETHER BREW</h2>
                        <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">
                            <?php if($payrollData['filterType'] === 'weekly'): ?>
                                Fee Mitra Rider (Senin - Minggu)
                            <?php elseif($payrollData['filterType'] === 'custom'): ?>
                                Fee Mitra Rider Custom Range
                            <?php else: ?>
                                Fee Mitra Rider Akhir Bulan
                            <?php endif; ?>
                        </p>
                        <p style="margin: 5px 0 0; color: var(--text-primary); font-weight: 600;">
                            <?php if($payrollData['filterType'] === 'monthly'): ?>
                                Periode: <?= date('F Y', mktime(0, 0, 0, $payrollData['month'], 1, $payrollData['year'])) ?>
                            <?php else: ?>
                                Periode: <?= date('d M Y', strtotime($payrollData['startDate'])) ?> - <?= date('d M Y', strtotime($payrollData['endDate'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>
        
                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                        <div>
                            <p style="margin: 0 0 5px;"><strong>Nama Rider:</strong> <?= e($payrollData['rider']['name']) ?></p>
                            <p style="margin: 0 0 5px;"><strong>ID Rider:</strong> #<?= str_pad($payrollData['rider']['id'], 4, '0', STR_PAD_LEFT) ?></p>
                        </div>
                        <div style="text-align: right;">
                            <?php if($payrollData['includeUangMakan']): ?>
                                <p style="margin: 0 0 5px;"><strong>Target Penjualan:</strong> <?= number_format($payrollData['targetCup'], 0, ',', '.') ?> Cup</p>
                            <?php endif; ?>
                            <p style="margin: 0 0 5px;"><strong>Total Cup Terjual:</strong> <?= $payrollData['totalCups'] ?> Cup
                                <?php if($payrollData['includeUangMakan']): ?>
                                    <span style="display: inline-block; padding: 2px 6px; background: <?= $payrollData['achPercentage'] >= 100 ? '#22c55e' : '#ef4444' ?>; color: white; border-radius: 4px; font-size: 0.8rem; font-weight: bold; margin-left: 5px;">ACH <?= rtrim(rtrim(number_format($payrollData['achPercentage'], 2, ',', '.'), '0'), ',') ?>%</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
        
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: var(--bg-secondary); border-top: 2px solid var(--border-color); border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px; text-align: left; font-weight: 600;">Keterangan</th>
                                <th style="padding: 12px; text-align: right; font-weight: 600;">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Gaji Bonus Penjualan <small>(<?= $payrollData['totalCups'] ?> Cup x Rp <?= number_format($payrollData['bonusPerCup'], 0, ',', '.') ?>)</small></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right;">Rp <?= number_format($payrollData['grossIncome'], 0, ',', '.') ?></td>
                            </tr>
        
                            <?php if($payrollData['includeUangMakan']): ?>
                                <?php if($payrollData['sisaUangMakan'] >= 0): ?>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #22c55e;">Sisa Uang Makan <small>(Estimasi Rp <?= number_format($payrollData['earnedUangMakan'], 0, ',', '.') ?> - Diambil Rp <?= number_format($payrollData['totalUangMakan'], 0, ',', '.') ?>)</small></td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #22c55e;">+ Rp <?= number_format($payrollData['sisaUangMakan'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Uang Makan <br><small style="color: #64748b;">(Uang makan diambil tidak sesuai target: Estimasi Rp <?= number_format($payrollData['earnedUangMakan'], 0, ',', '.') ?> - Diambil Rp <?= number_format($payrollData['totalUangMakan'], 0, ',', '.') ?>)</small></td>
                                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format(abs($payrollData['sisaUangMakan']), 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
        
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Kasbon <small>(Outstanding)</small></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format($payrollData['outstandingKasbon'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Minus Penjualan <small>(Outstanding)</small></td>
                                <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format($payrollData['outstandingMinus'], 0, ',', '.') ?></td>
                            </tr>
                            <tr style="background: var(--bg-secondary);">
                                <td style="padding: 15px 12px; font-weight: 700; font-size: 16px;">TAKE HOME PAY</td>
                                <td style="padding: 15px 12px; text-align: right; font-weight: 700; font-size: 18px; color: <?= $payrollData['netIncome'] >= 0 ? 'var(--text-primary)' : '#ef4444' ?>;">Rp <?= number_format($payrollData['netIncome'], 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
        
                    <div style="background: #fefce8; border: 1px solid #fef08a; padding: 12px; border-radius: 8px; margin-bottom: 30px;">
                        <p style="margin: 0 0 5px; font-size: 0.85rem; font-weight: 700; color: #854d0e;">Catatan Penting:</p>
                        <ul style="margin: 0; padding-left: 20px; font-size: 0.8rem; color: #854d0e;">
                            <li>Potongan Kasbon & Minus dihitung dari sisa outstanding (total - yang sudah pernah dibayar).</li>
                            <li>Rider dapat memilih jumlah pembayaran kasbon/minus saat menyimpan slip.</li>
                            <li>Sisa minus yang belum terbayar akan otomatis carry-over ke periode berikutnya.</li>
                            <?php if($payrollData['includeUangMakan']): ?>
                                <li>Uang makan rider hanya tampil di pembayaran gaji akhir bulan.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
        
                    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <div style="text-align: center;">
                            <p style="margin-bottom: 60px;">Penerima (Rider),</p>
                            <p style="font-weight: 600;">( <?= e($payrollData['rider']['name']) ?> )</p>
                        </div>
                        <div style="text-align: center;">
                            <p style="margin-bottom: 60px;">Mengetahui (Admin),</p>
                            <p style="font-weight: 600;">( Admin )</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            const gross = <?= $payrollData['grossIncome'] ?>;
            const um = parseFloat(document.getElementById('um_adj').value) || 0;
            const maxMinus = <?= $payrollData['outstandingMinus'] ?>;
            const maxKasbon = <?= $payrollData['outstandingKasbon'] ?>;
            
            function recalculateTHP() {
                let kasbon = parseFloat(document.getElementById('kasbon_deducted').value) || 0;
                let kasbonCash = parseFloat(document.getElementById('kasbon_paid_cash').value) || 0;
                let minus = parseFloat(document.getElementById('minus_deducted').value) || 0;
                let minusCash = parseFloat(document.getElementById('minus_paid_cash').value) || 0;
                let net = gross - kasbon - minus + um;
                
                let sisaMinus = maxMinus - minus - minusCash;
                
                document.getElementById('display-thp').innerText = 'Rp ' + formatNumber(net);
                
                const sisaEl = document.getElementById('sisa-minus-display');
                if (sisaEl) {
                    sisaEl.textContent = formatNumber(sisaMinus);
                }
            }
            
            function formatNumber(num) {
                return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        </script>
    <?php endif; ?>
<?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        toggleFilterFields();
        if(typeof flatpickr !== 'undefined') {
            flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });
        }
    });

    function toggleFilterFields() {
        const type = document.getElementById('filter_type').value;
        const allFields = ['filter-weekly', 'filter-monthly', 'filter-custom'];

        // Hide all
        allFields.forEach(cls => {
            document.querySelectorAll('.' + cls).forEach(el => el.style.display = 'none');
        });

        // Show specific
        if (type === 'weekly') {
            document.querySelectorAll('.filter-weekly').forEach(el => el.style.display = 'block');
            updateWeeklyOptions();
        } else if (type === 'custom') {
            document.querySelectorAll('.filter-custom').forEach(el => el.style.display = 'block');
        } else if (type === 'monthly') {
            document.querySelectorAll('.filter-monthly').forEach(el => el.style.display = 'block');
        }
    }

    function updateWeeklyOptions() {
        const month = parseInt(document.getElementById('month').value);
        const year = parseInt(document.getElementById('year').value);
        const weekSelect = document.getElementById('week_start');
        const selectedValue = "<?= e($weekStart) ?>";

        weekSelect.innerHTML = '';

        let currentDate = new Date(year, month - 1, 1);
        let lastDayOfMonth = new Date(year, month, 0);

        while (currentDate <= lastDayOfMonth) {
            let periodStart = new Date(currentDate);
            
            // Find the next Sunday or the last day of the month
            let periodEnd = new Date(currentDate);
            while (periodEnd.getDay() !== 0 && periodEnd < lastDayOfMonth) {
                periodEnd.setDate(periodEnd.getDate() + 1);
            }

            const startValue = periodStart.toISOString().split('T')[0];
            const endValue = periodEnd.toISOString().split('T')[0];
            const value = startValue + '|' + endValue;
            
            const label = periodStart.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ' - ' +
                          periodEnd.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

            const option = new Option(label, value);
            if (value === selectedValue) option.selected = true;
            weekSelect.add(option);

            // Move to the next day
            currentDate = new Date(periodEnd);
            currentDate.setDate(currentDate.getDate() + 1);
        }
    }
</script>
<style>
    /* Custom compact and elegant form inputs with SVG icons (only for text inputs) */
    .input-with-icon-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
    .input-with-icon-wrapper .form-input { padding-left: 2.75rem !important; border-radius: 10px !important; height: 40px !important; font-size: 0.9rem !important; background-color: var(--bg-card) !important; border: 1px solid var(--border-color) !important; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important; }
    .input-with-icon-wrapper .form-input:focus { border-color: #8b5c2a !important; box-shadow: 0 0 0 4px rgba(139, 92, 42, 0.15) !important; outline: none !important; }
    .input-with-icon-wrapper svg { position: absolute; left: 12px; color: var(--text-muted); transition: color 0.25s ease; pointer-events: none; }
    .input-with-icon-wrapper .form-input:focus+svg { color: #8b5c2a !important; }

    /* Standalone select styling (no icon inside) */
    .payroll-select { border-radius: 10px !important; height: 40px !important; font-size: 0.9rem !important; padding: 0 12px !important; background-color: var(--bg-card) !important; border: 1px solid var(--border-color) !important; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important; cursor: pointer; }
    .payroll-select:focus { border-color: #8b5c2a !important; box-shadow: 0 0 0 4px rgba(139, 92, 42, 0.15) !important; outline: none !important; }
</style>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
