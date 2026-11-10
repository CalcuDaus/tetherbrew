<?php
require_once __DIR__ . '/../../core/bootstrap.php'; 
require_auth(); 
require_admin_or_owner();

$branchId = activeBranchId();
$branchData = activeBranch();

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isUangMakanPage = strpos($currentPath, 'uang-makan') !== false;
$type = $isUangMakanPage ? 'uang_makan' : 'kasbon';
$pageTitle = $isUangMakanPage ? 'Uang Makan Rider' : 'Kasbon Rider';

$riderId = $_GET['rider_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); 
$riders->execute([$branchId]); 
$riders = $riders->fetchAll();

$query = "SELECT f.*, r.name as rider_name FROM rider_finances f JOIN users r ON f.rider_id=r.id WHERE f.branch_id=? AND f.type=?";
$params = [$branchId, $type];
if($riderId) { $query .= " AND f.rider_id=?"; $params[] = $riderId; }
if($startDate) { $query .= " AND f.date >= ?"; $params[] = $startDate; }
if($endDate) { $query .= " AND f.date <= ?"; $params[] = $endDate; }
$query .= " ORDER BY f.date DESC, f.id DESC";

$pagination = null;
$finances = [];

$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $finances = $stmt->fetchAll();
} else {
    $pagination = paginate($pdo, $query, $params, 15);
    $finances = $pagination['data'];
}

// Kalkulasi Total
$totalKasbon = 0; $totalTerbayar = 0; $totalSisa = 0;
$totalEstimasiMax = 0; $totalEarned = 0; $totalSudahDiterima = 0; $sisaBelumDibayar = 0;

if (!$isUangMakanPage) {
    // Kasbon
    $kQuery = "SELECT SUM(amount) FROM rider_finances WHERE branch_id=? AND type='kasbon'";
    $kParams = [$branchId];
    if($riderId) { $kQuery .= " AND rider_id=?"; $kParams[] = $riderId; }
    if($startDate) { $kQuery .= " AND date >= ?"; $kParams[] = $startDate; }
    if($endDate) { $kQuery .= " AND date <= ?"; $kParams[] = $endDate; }
    $kStmt = $pdo->prepare($kQuery); $kStmt->execute($kParams);
    $totalKasbon = (float)$kStmt->fetchColumn();

    $pQuery = "SELECT SUM(kasbon_deducted) FROM payroll_records WHERE branch_id=? AND status='confirmed'";
    $pParams = [$branchId];
    if($riderId) { $pQuery .= " AND rider_id=?"; $pParams[] = $riderId; }
    $pStmt = $pdo->prepare($pQuery); $pStmt->execute($pParams);
    $totalTerbayar = (float)$pStmt->fetchColumn();
    
    $totalSisa = $totalKasbon - $totalTerbayar;
} else {
    // Uang Makan
    $kQuery = "SELECT SUM(amount) FROM rider_finances WHERE branch_id=? AND type='uang_makan'";
    $kParams = [$branchId];
    if($riderId) { $kQuery .= " AND rider_id=?"; $kParams[] = $riderId; }
    if($startDate) { $kQuery .= " AND date >= ?"; $kParams[] = $startDate; }
    if($endDate) { $kQuery .= " AND date <= ?"; $kParams[] = $endDate; }
    $kStmt = $pdo->prepare($kQuery); $kStmt->execute($kParams);
    $totalSudahDiterima = (float)$kStmt->fetchColumn(); 
    $totalKasbon = $totalSudahDiterima;

    $sStmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='uang_makan_target_cup'"); $sStmt->execute(); $targetCup = (int)$sStmt->fetchColumn() ?: 1040;
    $sStmt = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='uang_makan_base_amount'"); $sStmt->execute(); $baseUM = (int)$sStmt->fetchColumn() ?: 650000;

    $riderCount = $riderId ? 1 : count($riders);
    $totalEstimasiMax = $riderCount * $baseUM;

    $month = $startDate ? date('m', strtotime($startDate)) : date('m');
    $year = $startDate ? date('Y', strtotime($startDate)) : date('Y');
    
    $salesQ = "SELECT s.rider_id, SUM(i.stock_sold) as cups FROM rider_daily_sale_items i JOIN rider_daily_sales s ON i.rider_daily_sale_id = s.id WHERE s.branch_id=? AND MONTH(s.date)=? AND YEAR(s.date)=?";
    $sParams = [$branchId, $month, $year];
    if($riderId) { $salesQ .= " AND s.rider_id=?"; $sParams[] = $riderId; }
    $salesQ .= " GROUP BY s.rider_id";
    
    $salesStmt = $pdo->prepare($salesQ); $salesStmt->execute($sParams);
    $salesData = $salesStmt->fetchAll();
    
    foreach($salesData as $sd) {
        $cups = (int)$sd['cups'];
        $earned = $targetCup > 0 ? ($cups / $targetCup) * $baseUM : 0;
        $totalEarned += $earned;
    }
    
    $sisaBelumDibayar = $totalEarned - $totalSudahDiterima;
}

$printParams = $_GET;
$printParams['print'] = 1;
$printUrl = '?' . http_build_query($printParams);

ob_start();
?>
<style>
    @media print {
        @page { margin: 15mm; }
        body { font-size: 11pt; }
        body * { visibility: hidden; }
        #print-area, #print-area * { visibility: visible; }
        #print-area { position: absolute; left: 0; top: 0; width: 100%; padding: 10px; box-sizing: border-box; }
        
        .print-title { display: block !important; text-align: center; padding-top: 20px; padding-bottom: 15px; border-bottom: 2px dashed #cbd5e1; margin-bottom: 20px !important; }
        .print-title h2 { margin: 0; color: #22c55e; font-size: 16pt; }
        .print-title p { margin: 5px 0 0 0; color: #64748b; font-size: 10pt; }

        .print-summary-row { display: flex !important; gap: 15px !important; }
        .print-summary-row > div { border: 1px solid #cbd5e1 !important; border-radius: 8px !important; background: white !important; }

        .topbar, .sidebar, .btn, .print-hide, form, #financeModal, .modal-overlay-animate { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; break-inside: avoid; margin-bottom: 20px !important; border-radius: 8px !important; }
        .card-header, .card-body form { display: none !important; }
        .card-body { padding: 15px 16px !important; }

        table { width: 100% !important; border-collapse: collapse !important; }
        tr { display: table-row !important; }
        th { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1 !important; padding: 10px 8px !important; font-weight: bold !important; font-size: 9.5pt !important; }
        td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; font-size: 10pt !important; }
        th:last-child, td:last-child { display: none !important; }
    }

    /* Card animated and absolute bg icon effects */
    .stat-card-animated { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border-color); }
    .stat-card-animated:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important; }
    .stat-card-animated .stat-icon-bg { transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
    .stat-card-animated:hover .stat-icon-bg { transform: scale(1.1) rotate(-5deg); opacity: 0.25 !important; }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-overlay-animate {
        animation: fadeIn 0.3s ease-out forwards;
    }

    .modal-content-animate {
        animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        background: white;
    }
    html:not(.light-theme) .modal-content-animate {
        background: var(--bg-card);
    }
</style>

<div id="print-area">
    <div class="print-title print-only" style="display: none;">
        <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;">
        <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Tether Brew</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">Laporan <?= $pageTitle ?> — Dicetak: <?= date('d F Y') ?></p>
    </div>

    <div class="print-summary-row" style="display: none; margin-bottom: 20px;">
    <?php if(!$isUangMakanPage): ?>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Total Kasbon</div>
            <div style="font-size: 14pt; font-weight: bold; color: #ef4444;">Rp <?= number_format($totalKasbon, 0, ',', '.') ?></div>
        </div>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Total Terbayar</div>
            <div style="font-size: 14pt; font-weight: bold; color: #10b981;">Rp <?= number_format($totalTerbayar, 0, ',', '.') ?></div>
        </div>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Total Sisa</div>
            <div style="font-size: 14pt; font-weight: bold; color: #f59e0b;">Rp <?= number_format($totalSisa, 0, ',', '.') ?></div>
        </div>
    <?php else: ?>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Total Estimasi Max</div>
            <div style="font-size: 14pt; font-weight: bold; color: #3b82f6;">Rp <?= number_format($totalEstimasiMax, 0, ',', '.') ?></div>
        </div>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Belum Dibayar/Diambil</div>
            <div style="font-size: 14pt; font-weight: bold; color: #6366f1;">Rp <?= number_format($totalEarned, 0, ',', '.') ?></div>
        </div>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Sudah Dibayar</div>
            <div style="font-size: 14pt; font-weight: bold; color: #22c55e;">Rp <?= number_format($totalSudahDiterima, 0, ',', '.') ?></div>
        </div>
        <div style="flex: 1; padding: 15px; text-align: center;">
            <div style="font-size: 10pt; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Sisa</div>
            <div style="font-size: 14pt; font-weight: bold; color: <?= $sisaBelumDibayar >= 0 ? '#eab308' : '#ef4444' ?>;"><?= $sisaBelumDibayar >= 0 ? '+' : '-' ?> Rp <?= number_format(abs($sisaBelumDibayar), 0, ',', '.') ?></div>
        </div>
    <?php endif; ?>
    </div>

<?php if($isUangMakanPage): ?>
<div class="card mb-4 print-hide" style="margin-bottom:20px;background: linear-gradient(to right, rgba(59, 130, 246, 0.05), rgba(99, 102, 241, 0.05)); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px;">
    <div class="card-body" style="padding: 20px;">
        <h4 onclick="toggleInfoBox()" style="cursor: pointer; color: var(--text-primary); margin-top: 0; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; font-size: 1.1rem; user-select: none;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg> Cara Kerja Perhitungan Uang Makan
            </div>
            <svg id="infoBoxIcon" style="transform: rotate(0deg); transition: transform 0.3s;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </h4>
        <div id="infoBoxContent" style="display: none; transition: max-height 0.3s ease-out; overflow: hidden;">
            <ul style="margin: 12px 0 0 0; padding-left: 20px; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
                <li><strong>Total Estimasi Max:</strong> Target maksimal uang makan bulan ini (Target Cup &times; Uang Makan per Cup).</li>
                <li><strong>Total Uang Makan Belum Dibayar/Diambil:</strong> Hak uang makan berdasarkan <strong style="color:var(--text-primary);">cup terjual sebenarnya bulan ini</strong>.</li>
                <li><strong>Sudah Dibayar:</strong> Total kasbon uang makan yang telah dicairkan bulan ini.</li>
                <li><strong>Sisa:</strong> Selisih antara Hak (Belum Dibayar/Diambil) dengan Kasbon yang sudah ditarik. Jika minus, artinya kasbon uang makan melebihi hak penjualan berjalan.</li>
                <li><strong style="color:#ef4444;">Note:</strong> Minus uang makan bulan lalu <strong style="color:#ef4444;">tidak otomatis memotong</strong> uang makan bulan berjalan, melainkan menjadi <strong>Outstanding Minus</strong> pada saat proses penggajian bulanan (Payroll).</li>
            </ul>
        </div>
    </div>
</div>
<script>
function toggleInfoBox() {
    var content = document.getElementById('infoBoxContent');
    var icon = document.getElementById('infoBoxIcon');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>
<?php endif; ?>

<div class="stats-grid print-hide" style="margin-bottom:20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    <?php if(!$isUangMakanPage): ?>
        <?php // Card Kasbon ?>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #ef4444; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #ef4444; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">
                Rp <?= number_format($totalKasbon, 0, ',', '.') ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.95rem;">Total Kasbon</div>
        </div>

        <?php // Card Terbayar ?>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #10b981; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #10b981; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">
                Rp <?= number_format($totalTerbayar, 0, ',', '.') ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.95rem;">Total Terbayar</div>
        </div>
        
        <?php // Card Sisa ?>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #f59e0b; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="12" y1="12" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #f59e0b; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">
                Rp <?= number_format($totalSisa, 0, ',', '.') ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.95rem;">Total Sisa</div>
        </div>
    <?php else: ?>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #3b82f6; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #3b82f6; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">Rp <?= number_format($totalEstimasiMax, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.9rem;">Total Estimasi Max</div>
        </div>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #6366f1; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #6366f1; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">Rp <?= number_format($totalEarned, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.9rem;">Belum Dibayar/Diambil</div>
        </div>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #22c55e; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #22c55e; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;">Rp <?= number_format($totalSudahDiterima, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.9rem;">Sudah Dibayar</div>
        </div>
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: <?= $sisaBelumDibayar >= 0 ? '#eab308' : '#ef4444' ?>; z-index: -1;">
                <?php if($sisaBelumDibayar >= 0): ?>
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <?php else: ?>
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <?php endif; ?>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: <?= $sisaBelumDibayar >= 0 ? '#eab308' : '#ef4444' ?>; font-size: 1.8rem; font-weight: bold; margin-bottom: 5px;"><?= $sisaBelumDibayar >= 0 ? '+' : '-' ?> Rp <?= number_format(abs($sisaBelumDibayar), 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2; color: #64748b; font-size: 0.9rem;">Sisa</div>
        </div>
    <?php endif; ?>
</div>

<div class="card print-hide" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:150px;">
                <label class="form-label" style="font-size:13px; font-weight:600; color:var(--text-secondary);">Mulai Tanggal</label>
                <div class="flatpickr-input-container" style="position:relative;">
                    <input type="text" name="start_date" class="form-input datepicker" value="<?= e($startDate) ?>" placeholder="Pilih tanggal..." style="height:42px;">
                    <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:150px;">
                <label class="form-label" style="font-size:13px; font-weight:600; color:var(--text-secondary);">Sampai Tanggal</label>
                <div class="flatpickr-input-container" style="position:relative;">
                    <input type="text" name="end_date" class="form-input datepicker" value="<?= e($endDate) ?>" placeholder="Pilih tanggal..." style="height:42px;">
                    <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:180px; max-width:260px;">
                <label class="form-label" style="font-size:13px; font-weight:600; color:var(--text-secondary);">Rider</label>
                <select name="rider_id" class="form-input" style="width:100%; height:42px;">
                    <option value="">Semua Rider</option>
                    <?php foreach($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderId==$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
                <div style="display:flex; gap:10px; margin-bottom:0;">
                    <button type="submit" class="btn btn-primary" style="height:42px; background:#22c55e; border-color:#22c55e; padding: 0 20px;">Filter</button>
                    <a href="<?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?>" class="btn btn-secondary" style="height:42px; padding: 0 20px; display:flex; align-items:center;">Reset</a>
                </div>
            </form>
        </div>
    </div>

<!-- Modal Input Kasbon / Uang Makan -->
<div id="financeModal" class="modal-overlay-animate" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); padding: 20px;">
    <div class="card modal-content-animate" style="width: 100%; max-width: 520px; margin: auto; box-shadow: var(--shadow-lg); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color);">
        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
            <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Tambah <?= $isUangMakanPage ? 'Uang Makan' : 'Kasbon Rider' ?>
            </h3>
            <button type="button" onclick="closeFinanceModal()" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="<?= url('/rider-finances/store') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="<?= $type ?>">
                
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Rider *</label>
                    <select name="rider_id" id="finance_rider" class="form-input" required style="width: 100%; height: 42px;">
                        <option value="">-- Pilih Rider --</option>
                        <?php foreach($riders as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Tanggal *</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="date" id="finance_date" class="form-input datepicker" value="<?= date('Y-m-d') ?>" required placeholder="Pilih tanggal..." style="width: 100%; height: 42px;">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>

                <?php if($isUangMakanPage): ?>
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Cup Terjual</label>
                    <input type="number" name="reference_cups" id="reference_cups" class="form-input" readonly placeholder="Otomatis dari data penjualan" style="width: 100%; height: 42px; background: var(--bg-secondary);">
                    <small style="color: #64748b; font-size: 0.8rem; margin-top: 4px; display: block;">Otomatis terisi jika ada data penjualan pada rider & tanggal tersebut.</small>
                </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Jumlah Uang (Rp) *</label>
                    <input type="number" name="amount" class="form-input" required min="1" placeholder="0" style="width: 100%; height: 42px;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Catatan</label>
                    <input type="text" name="notes" class="form-input" placeholder="Opsional..." style="width: 100%; height: 42px;">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--border-color);">
                    <button type="button" onclick="closeFinanceModal()" class="btn btn-secondary" style="padding: 0 18px; height: 42px; border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0 20px; height: 42px; border-radius: 8px; background: #22c55e; border-color: #22c55e;">Simpan <?= $isUangMakanPage ? 'Uang Makan' : 'Kasbon' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if(!$isUangMakanPage): ?>
<!-- Modal Bayar Kasbon -->
<div id="paymentModal" class="modal-overlay-animate" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); padding: 20px;">
    <div class="card modal-content-animate" style="width: 100%; max-width: 520px; margin: auto; box-shadow: var(--shadow-lg); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color);">
        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
            <h3 class="card-title" style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                Bayar Kasbon (via Cash)
            </h3>
            <button type="button" onclick="closePaymentModal()" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="card-body" style="padding: 24px;">
            <form method="POST" action="<?= url('/rider-finances/store-payment') ?>">
                <?= csrf_field() ?>
                
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Rider *</label>
                    <select name="rider_id" class="form-input" required style="width: 100%; height: 42px;">
                        <option value="">-- Pilih Rider --</option>
                        <?php foreach($riders as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Tanggal Pembayaran *</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="date" class="form-input datepicker" value="<?= date('Y-m-d') ?>" required placeholder="Pilih tanggal..." style="width: 100%; height: 42px;">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Nominal Pembayaran (Rp) *</label>
                    <input type="number" name="amount" class="form-input" required min="1" placeholder="0" style="width: 100%; height: 42px;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-secondary); font-size: 0.875rem;">Catatan</label>
                    <input type="text" name="notes" class="form-input" placeholder="Opsional..." style="width: 100%; height: 42px;">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--border-color);">
                    <button type="button" onclick="closePaymentModal()" class="btn btn-secondary" style="padding: 0 18px; height: 42px; border-radius: 8px;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0 20px; height: 42px; border-radius: 8px; background: #3b82f6; border-color: #3b82f6;">Konfirmasi Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Table Header & Action Toolbar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;" class="print-hide">
    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);"><?= $isUangMakanPage ? 'Riwayat Uang Makan' : 'Riwayat Kasbon Rider' ?></h3>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        <form id="deleteAll-riderFinance" method="POST" action="<?= url('/rider-finances/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= $type ?>">
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none; height:36px; padding: 0 14px; border-radius:8px; font-weight:600; display:flex; align-items:center; gap:6px;" onclick="confirmDeleteAll('deleteAll-riderFinance')">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Hapus Semua
            </button>
        </form>
        <a href="<?= $printUrl ?>" target="_blank" class="btn btn-secondary btn-sm flex-center" style="height: 36px; gap:0.5rem; text-decoration: none; padding: 0 14px; border-radius: 8px;">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Print
        </a>
        <button type="button" onclick="openFinanceModal()" class="btn btn-primary btn-sm flex-center" style="height:36px; padding: 0 14px; border-radius: 8px; gap:0.5rem; white-space:nowrap; cursor: pointer;">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
            </svg> Input <?= $isUangMakanPage ? 'Uang Makan' : 'Kasbon' ?>
        </button>
        <?php if(!$isUangMakanPage): ?>
        <button type="button" onclick="openPaymentModal()" class="btn btn-secondary btn-sm flex-center" style="height:36px; padding: 0 14px; border-radius: 8px; gap:0.5rem; white-space:nowrap; cursor: pointer; color: var(--text-primary); border-color: var(--border-color); background: var(--bg-card);">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg> Bayar Kasbon
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color);">
    <?php if(empty($finances)):?>
        <div style="text-align:center; padding:40px; color:var(--text-muted);"><h3>Belum ada data riwayat <?= $isUangMakanPage ? 'uang makan' : 'kasbon' ?>.</h3></div>
    <?php else:?>
    <div class="table-container">
        <table class="table" style="width:100%; border-collapse:collapse; margin-bottom:0;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:left; background:var(--bg-card);">Tanggal</th>
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:left; background:var(--bg-card);">Rider</th>
                    <?php if($isUangMakanPage): ?>
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:left; background:var(--bg-card);">Cup Terjual</th>
                    <?php endif; ?>
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:center; background:var(--bg-card);">Jumlah (Rp)</th>
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:left; background:var(--bg-card);">Catatan</th>
                    <th style="padding:15px 20px; font-weight:600; font-size:12px; color:var(--text-secondary); text-transform:uppercase; text-align:left; background:var(--bg-card);">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($finances as $f): ?>
                <tr style="border-bottom:1px solid var(--border-color); background:var(--bg-card);">
                    <td style="padding:15px 20px; color:var(--text-secondary); font-size:14px;"><?= date('d/m/Y', strtotime($f['date'])) ?></td>
                    <td style="padding:15px 20px; color:var(--text-secondary); font-size:14px;"><?= e($f['rider_name']) ?></td>
                    <?php if($isUangMakanPage): ?>
                    <td style="padding:15px 20px;">
                        <span style="background:#dcfce7; color:#166534; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?= (int)$f['reference_cups'] ?> Cup</span>
                    </td>
                    <?php endif; ?>
                    <td style="padding:15px 20px; font-weight:600; text-align:center; color:<?= $f['type']==='kasbon'?'#ef4444':'#22c55e' ?>;">
                        Rp <?= number_format($f['amount'], 0, ',', '.') ?>
                    </td>
                    <td style="padding:15px 20px; color:#3b82f6; font-size:14px;"><?= e($f['notes']??'-') ?></td>
                    <td style="padding:15px 20px;">
                        <form method="POST" action="<?= url('/rider-finances/delete') ?>" data-confirm="Apakah Anda yakin ingin menghapus data ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="color:#ef4444; background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239, 68, 68, 0.2); border-radius:6px; display:flex; align-items:center; gap:4px; font-weight:500; padding:6px 12px;">
                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if($pagination): ?>
    <div class="print-hide" style="padding:16px 20px; border-top:1px solid var(--border-color); background:var(--bg-card);"><?= render_pagination($pagination) ?></div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div> <!-- End #print-area -->

<script>
    function openFinanceModal() {
        document.getElementById('financeModal').style.display = 'flex';
    }

    function closeFinanceModal() {
        document.getElementById('financeModal').style.display = 'none';
    }

    function openPaymentModal() {
        document.getElementById('paymentModal').style.display = 'flex';
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('financeModal');
        const pModal = document.getElementById('paymentModal');
        if (event.target === modal) {
            closeFinanceModal();
        }
        if (pModal && event.target === pModal) {
            closePaymentModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if(typeof flatpickr !== 'undefined') {
            const commonConfig = {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d F Y",
                allowInput: true,
                disableMobile: "true"
            };
            flatpickr(".datepicker", commonConfig);
            flatpickr("#finance_date", commonConfig);
        }

        const riderSelect = document.getElementById('finance_rider');
        const dateInput = document.getElementById('finance_date');
        const cupsInput = document.getElementById('reference_cups');

        if(cupsInput && riderSelect && dateInput) {
            function fetchCups() {
                const riderId = riderSelect.value;
                const date = dateInput.value;

                if (riderId && date) {
                    fetch(`<?= url('/api/rider-cups') ?>?rider_id=${riderId}&date=${date}`)
                        .then(response => response.json())
                        .then(data => {
                            cupsInput.value = data.cups || 0;
                        })
                        .catch(error => {
                            console.error('Error fetching cups:', error);
                            cupsInput.value = '';
                        });
                } else {
                    cupsInput.value = '';
                }
            }

            riderSelect.addEventListener('change', fetchCups);
            dateInput.addEventListener('change', fetchCups);
        }
    });
</script>

<?php if ($isPrint): ?>
<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
<?php endif; ?>

<?php 
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php'; 
?>
