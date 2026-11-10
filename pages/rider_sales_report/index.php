<?php
require_once __DIR__ . '/../../core/bootstrap.php'; 
require_auth(); 
require_admin_or_owner();

$branchId = activeBranchId();
$branchData = activeBranch();
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$riderId = $_GET['rider_id'] ?? '';
$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); 
$riders->execute([$branchId]); 
$riders = $riders->fetchAll();

$query = "SELECT s.*, r.name as rider_name FROM rider_daily_sales s JOIN users r ON s.rider_id=r.id WHERE s.branch_id=? AND MONTH(s.date)=? AND YEAR(s.date)=?";
$params = [$branchId, $month, $year];
if($riderId) { $query .= " AND s.rider_id=?"; $params[] = $riderId; }
$query .= " ORDER BY s.date DESC";

// Aggregate Totals
$sumQuery = "SELECT SUM(total_gross_income) as t_omset, SUM(cash_amount) as t_cash, SUM(qris_amount) as t_qris, SUM(actual_setor) as t_setor, SUM(minus_amount) as t_minus FROM rider_daily_sales s WHERE s.branch_id=? AND MONTH(s.date)=? AND YEAR(s.date)=?";
$sumParams = [$branchId, $month, $year];
if($riderId) { $sumQuery .= " AND s.rider_id=?"; $sumParams[] = $riderId; }
$sumStmt = $pdo->prepare($sumQuery); 
$sumStmt->execute($sumParams); 
$totals = $sumStmt->fetch();

// Calculate total cups
$cupsQuery = "SELECT SUM(stock_sold) FROM rider_daily_sale_items i JOIN rider_daily_sales s ON i.rider_daily_sale_id=s.id WHERE s.branch_id=? AND MONTH(s.date)=? AND YEAR(s.date)=?";
$cupsParams = [$branchId, $month, $year];
if($riderId) { $cupsQuery .= " AND s.rider_id=?"; $cupsParams[] = $riderId; }
$cupsStmt = $pdo->prepare($cupsQuery); 
$cupsStmt->execute($cupsParams); 
$totalCups = (int)$cupsStmt->fetchColumn();

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    $pagination = null;
} else {
    $pagination = paginate($pdo, $query, $params, 20);
    $sales = $pagination['data'];
}

$pageTitle = 'Laporan Penjualan Rider';

$months = [
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
    '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
    '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
];

$printParams = $_GET;
$printParams['print'] = 1;
$printUrl = '?' . http_build_query($printParams);

// Get rider name if filtered
$selectedRiderName = 'Semua Rider';
if($riderId) {
    foreach($riders as $r) {
        if($r['id'] == $riderId) {
            $selectedRiderName = $r['name'];
            break;
        }
    }
}

ob_start();
?>
<!-- Print Header (Visible only when printing) -->
<div class="print-only" style="display: none; margin-bottom: 20px; text-align: center;">
    <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 50px; width: auto; display: block; margin: 0 auto 8px auto;">
    <h2 style="color: #22c55e; font-size: 1.4rem; font-weight: 800; text-transform: uppercase; margin: 0;">Tether Brew</h2>
    <h3 style="font-size: 1.1rem; font-weight: 700; margin: 4px 0; color: var(--text-primary);">Laporan Penjualan Rider</h3>
    <p style="margin: 4px 0 0; color: #64748b; font-size: 10pt;">
        Cabang: <strong><?= e($branchData['name'] ?? 'Utama') ?></strong> | Periode: <strong><?= $months[$month] ?? '' ?> <?= e($year) ?></strong> | Rider: <strong><?= e($selectedRiderName) ?></strong>
    </p>
    <p style="margin: 2px 0 0; color: #94a3b8; font-size: 9pt;">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
    <hr style="border: none; border-top: 1px solid #cbd5e1; margin: 12px 0;">
</div>

<div class="card print-hide" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:200px; margin-bottom:0;">
                <label class="form-label">Rider</label>
                <select name="rider_id" class="form-input" style="width:100%; height:42px;">
                    <option value="">Semua Rider</option>
                    <?php foreach($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderId==$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:160px; margin-bottom:0;">
                <label class="form-label">Bulan</label>
                <select name="month" class="form-input" style="width:100%; height:42px;">
                    <?php foreach($months as $mVal => $mName): ?>
                        <option value="<?= $mVal ?>" <?= $month==$mVal?'selected':'' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 120px; min-width:110px; margin-bottom:0;">
                <label class="form-label">Tahun</label>
                <input type="number" name="year" class="form-input" value="<?= e($year) ?>" style="width:100%; height:42px;">
            </div>
            <div style="display:flex; gap:8px; margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="height:42px; display:inline-flex; align-items:center; gap:6px; padding:0 20px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg> Filter
                </button>
                <a href="<?= $printUrl ?>" target="_blank" class="btn btn-secondary" style="height:42px; display:inline-flex; align-items:center; gap:6px; padding:0 16px; text-decoration:none;">
                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg> Print
                </a>
                <?php if($riderId || $month != date('m') || $year != date('Y')): ?>
                    <a href="/rider-sales-report" class="btn btn-secondary" style="height:42px; display:inline-flex; align-items:center; padding:0 16px; text-decoration:none;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
    /* Card animated and absolute bg icon effects (matching Jurnal Umum style) */
    .stat-card-animated {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    .stat-card-animated:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }
    .stat-card-animated .stat-icon-bg {
        transition: all 0.3s ease !important;
    }
    .stat-card-animated:hover .stat-icon-bg {
        transform: scale(1.1) rotate(5deg);
        opacity: 0.25 !important;
    }

    @media print {
        .print-hide, .topbar, .sidebar, .sidebar-nav, .sidebar-footer, header, nav {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        body {
            background: #ffffff !important;
            color: #000000 !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        .card {
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
            background: #ffffff !important;
        }
        .stats-grid {
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 10px !important;
        }
        .stat-card {
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            background: #ffffff !important;
            padding: 15px 10px !important;
        }
        .stat-value {
            font-size: 12pt !important;
            color: #000000 !important;
        }
        .stat-label {
            font-size: 8pt !important;
            color: #475569 !important;
        }
        .table-container table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        .table-container th, .table-container td {
            border: 1px solid #cbd5e1 !important;
            padding: 6px 8px !important;
            font-size: 9pt !important;
            color: #000000 !important;
        }
        .table-container th {
            background-color: #f1f5f9 !important;
        }
    }
</style>

<div class="stats-grid" style="margin-bottom:20px;">
    <!-- Card 1: Total Cup Terjual -->
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #3b82f6; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
                <line x1="6" y1="1" x2="6" y2="4"></line>
                <line x1="10" y1="1" x2="10" y2="4"></line>
                <line x1="14" y1="1" x2="14" y2="4"></line>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #3b82f6;">
            <?= number_format($totalCups, 0, ',', '.') ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">Total Cup Terjual</div>
    </div>

    <!-- Card 2: Total Omset -->
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: var(--gradient-gold); color: white;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: white;">
            <?= formatRupiah($totals['t_omset']) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color: white;">Total Omset</div>
    </div>

    <!-- Card 3: Tunai (Teori) -->
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #22c55e; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                <circle cx="12" cy="12" r="2"></circle>
                <path d="M6 12h.01M18 12h.01"></path>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #22c55e;">
            <?= formatRupiah($totals['t_cash']) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">Tunai (Teori)</div>
    </div>

    <!-- Card 4: QRIS -->
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #8b5cf6; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #8b5cf6;">
            <?= formatRupiah($totals['t_qris']) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">QRIS</div>
    </div>

    <!-- Card 5: Total Minus -->
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #ef4444; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #ef4444;">
            <?= formatRupiah($totals['t_minus']) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">Total Minus</div>
    </div>
</div>

<div class="card"><div class="card-body" style="padding:0;">
    <?php if(empty($sales)):?><div style="text-align:center; padding:40px; color:var(--text-muted);"><h3>Tidak ada data laporan</h3></div>
    <?php else:?>
    <div class="table-container"><table><thead><tr><th>Tgl</th><th>Rider</th><th>Omset</th><th>QRIS</th><th>Tunai (Teori)</th><th>Setor Aktual</th><th>Minus</th></tr></thead><tbody>
    <?php foreach($sales as $s): ?>
        <tr>
            <td style="font-size:13px; font-weight:600;"><?= date('d/m/Y', strtotime($s['date'])) ?></td>
            <td><?= e($s['rider_name']) ?></td>
            <td style="font-weight:bold; color:var(--accent-blue);"><?= formatRupiah($s['total_gross_income']) ?></td>
            <td><?= formatRupiah($s['qris_amount']) ?></td>
            <td><?= formatRupiah($s['cash_amount']) ?></td>
            <td style="color:var(--accent-green); font-weight:600;"><?= formatRupiah($s['actual_setor']) ?></td>
            <td style="color:var(--accent-red); font-weight:bold;"><?= $s['minus_amount']>0 ? formatRupiah($s['minus_amount']) : '-' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($pagination): ?>
        <div style="padding:16px;" class="print-hide"><?= render_pagination($pagination) ?></div>
    <?php endif; ?>
    <?php endif; ?>
</div></div>

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
