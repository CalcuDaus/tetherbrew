<?php
require_once __DIR__ . '/../../core/bootstrap.php'; 
require_auth(); 
require_admin_or_owner();

$branchId = activeBranchId();
$branchData = activeBranch();
$riderId = $_GET['rider_id'] ?? ''; 
$status = $_GET['status'] ?? '';
$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); 
$riders->execute([$branchId]); 
$riders = $riders->fetchAll();

$query = "SELECT s.*, r.name as rider_name FROM rider_daily_sales s JOIN users r ON s.rider_id=r.id WHERE s.branch_id=? AND s.minus_amount > 0";
$params = [$branchId];
if($riderId) { $query .= " AND s.rider_id=?"; $params[] = $riderId; }
if($status) { $query .= " AND s.minus_status=?"; $params[] = $status; }
$query .= " ORDER BY s.date DESC";

$sumQuery = "SELECT SUM(minus_amount) as total_minus, SUM(minus_paid) as total_paid FROM rider_daily_sales s WHERE s.branch_id=? AND s.minus_amount > 0";
$sumParams = [$branchId];
if($riderId) { $sumQuery .= " AND s.rider_id=?"; $sumParams[] = $riderId; }
if($status) { $sumQuery .= " AND s.minus_status=?"; $sumParams[] = $status; }
$sumStmt = $pdo->prepare($sumQuery); 
$sumStmt->execute($sumParams); 
$totals = $sumStmt->fetch();

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $minuses = $stmt->fetchAll();
    $pagination = null;
} else {
    $pagination = paginate($pdo, $query, $params, 15);
    $minuses = $pagination['data'];
}

$selectedRiderName = 'Semua Rider';
if($riderId) {
    foreach($riders as $r) {
        if($r['id'] == $riderId) {
            $selectedRiderName = $r['name'];
            break;
        }
    }
}

$statusLabels = [
    'unpaid' => 'Belum Dibayar',
    'partial' => 'Dibayar Sebagian',
    'paid' => 'Lunas'
];
$selectedStatusLabel = $status ? ($statusLabels[$status] ?? $status) : 'Semua Status';

$printParams = $_GET;
$printParams['print'] = 1;
$printUrl = '?' . http_build_query($printParams);

$pageTitle = 'Riwayat Minus Rider';
ob_start();
?>
<!-- Print Header (Visible only when printing) -->
<div class="print-only" style="display: none; margin-bottom: 20px; text-align: center;">
    <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 50px; width: auto; display: block; margin: 0 auto 8px auto;">
    <h2 style="color: #22c55e; font-size: 1.4rem; font-weight: 800; text-transform: uppercase; margin: 0;">Tether Brew</h2>
    <h3 style="font-size: 1.1rem; font-weight: 700; margin: 4px 0; color: var(--text-primary);">Laporan Riwayat Minus Rider</h3>
    <p style="margin: 4px 0 0; color: #64748b; font-size: 10pt;">
        Cabang: <strong><?= e($branchData['name'] ?? 'Utama') ?></strong> | Rider: <strong><?= e($selectedRiderName) ?></strong> | Status: <strong><?= e($selectedStatusLabel) ?></strong>
    </p>
    <p style="margin: 2px 0 0; color: #94a3b8; font-size: 9pt;">Dicetak pada: <?= date('d/m/Y H:i') ?></p>
    <hr style="border: none; border-top: 1px solid #cbd5e1; margin: 12px 0;">
</div>

<!-- Print Only Summary -->
<div class="minus-summary-print print-only" style="display: none; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
    <div style="display: flex; background: var(--bg-secondary);">
        <div style="flex: 1; padding: 12px; text-align: center; border-right: 1px solid #e2e8f0;">
            <div style="font-size: 9pt; color: #64748b; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Total Minus</div>
            <div style="font-size: 13pt; font-weight: bold; color: #ef4444;">
                <?= formatRupiah($totals['total_minus'] ?? 0) ?>
            </div>
        </div>
        <div style="flex: 1; padding: 12px; text-align: center; border-right: 1px solid #e2e8f0;">
            <div style="font-size: 9pt; color: #64748b; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Total Terbayar</div>
            <div style="font-size: 13pt; font-weight: bold; color: #22c55e;">
                <?= formatRupiah($totals['total_paid'] ?? 0) ?>
            </div>
        </div>
        <div style="flex: 1; padding: 12px; text-align: center;">
            <div style="font-size: 9pt; color: #64748b; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;">Total Sisa Minus</div>
            <div style="font-size: 13pt; font-weight: bold; color: #d97706;">
                <?= formatRupiah(($totals['total_minus'] ?? 0) - ($totals['total_paid'] ?? 0)) ?>
            </div>
        </div>
    </div>
</div>

<div class="card print-hide" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="<?= url('/rider-minus') ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:220px; max-width:320px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Rider</label>
                <select name="rider_id" class="form-input" style="width:100%; height:42px;">
                    <option value="">Semua Rider</option>
                    <?php foreach($riders as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $riderId==$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:180px; max-width:260px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Status</label>
                <select name="status" class="form-input" style="width:100%; height:42px;">
                    <option value="">Semua Status</option>
                    <option value="unpaid" <?= $status=='unpaid'?'selected':'' ?>>Belum Dibayar</option>
                    <option value="partial" <?= $status=='partial'?'selected':'' ?>>Dibayar Sebagian</option>
                    <option value="paid" <?= $status=='paid'?'selected':'' ?>>Lunas</option>
                </select>
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
                <?php if($riderId || $status): ?>
                    <a href="<?= url('/rider-minus') ?>" class="btn btn-outline-secondary" style="height:42px; display:inline-flex; align-items:center; padding:0 16px; text-decoration:none;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
    /* Card animated and absolute bg icon effects */
    .stat-card-animated {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    .stat-card-animated:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }
    .stat-card-animated .stat-icon-bg {
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stat-card-animated:hover .stat-icon-bg {
        transform: scale(1.1) rotate(-5deg);
        opacity: 0.25 !important;
    }

    @media print {
        .print-only { display: block !important; }
        .minus-summary-print { display: block !important; }
        .print-hide { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        th, td { border: 1px solid #cbd5e1 !important; padding: 6px 10px !important; }
        th { background: #f8fafc !important; color: #1e293b !important; }
    }
</style>

<div class="stats-grid print-hide" style="margin-bottom:20px;">
    <?php // Card Minus ?>
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #ef4444; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"></line>
                <polyline points="5 12 12 5 19 12"></polyline>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #ef4444;">
            <?= formatRupiah($totals['total_minus'] ?? 0) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">Total Minus</div>
    </div>

    <?php // Card Terbayar ?>
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #22c55e; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <polyline points="19 12 12 19 5 12"></polyline>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: #22c55e;">
            <?= formatRupiah($totals['total_paid'] ?? 0) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2;">Total Terbayar (via Gaji)</div>
    </div>

    <?php // Card Sisa ?>
    <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: var(--gradient-gold); color: white;">
        <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                <path d="M2 10h20"></path>
                <path d="M16 14h2"></path>
            </svg>
        </div>
        <div class="stat-value" style="position: relative; z-index: 2; color: white;">
            <?= formatRupiah(($totals['total_minus']??0)-($totals['total_paid']??0)) ?>
        </div>
        <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color:white">Total Sisa Minus</div>
    </div>
</div>

<div class="card"><div class="card-body" style="padding:0;">
    <?php if(empty($minuses)):?>
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
            <h3>Belum ada data minus</h3>
        </div>
    <?php else:?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tgl Penjualan</th>
                        <th>Rider</th>
                        <th>Total Minus</th>
                        <th>Terbayar</th>
                        <th>Sisa Hutang</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($minuses as $m): $sisa = $m['minus_amount'] - $m['minus_paid']; ?>
                        <tr>
                            <td style="font-size:13px; font-weight:600;"><?= date('d/m/Y', strtotime($m['date'])) ?></td>
                            <td><?= e($m['rider_name']) ?></td>
                            <td style="color:var(--accent-red); font-weight:bold;"><?= formatRupiah($m['minus_amount']) ?></td>
                            <td style="color:var(--accent-green);"><?= formatRupiah($m['minus_paid']) ?></td>
                            <td style="font-weight:bold;"><?= formatRupiah($sisa) ?></td>
                            <td>
                                <?php if($m['minus_status']==='paid'): ?><span class="badge badge-success">Lunas</span>
                                <?php elseif($m['minus_status']==='partial'): ?><span class="badge badge-warning">Sebagian</span>
                                <?php else: ?><span class="badge badge-danger">Belum Dibayar</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
