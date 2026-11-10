<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
$branchId = activeBranchId();
$filterDate = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';
$userRole = auth_user()['role'];
$isAdmin = in_array($userRole, ['admin', 'owner']);

$query = "SELECT rs.*, u.name as rider_name, a.name as admin_name, a.role as admin_role,
    (SELECT SUM(stock_sold) FROM rider_daily_sale_items WHERE rider_daily_sale_id = rs.id) as total_cups
    FROM rider_daily_sales rs
    JOIN users u ON rs.rider_id = u.id
    LEFT JOIN users a ON rs.admin_id = a.id
    WHERE rs.branch_id = ?";
$params = [$branchId];

if ($filterDate) { $query .= " AND DATE(rs.date) = ?"; $params[] = $filterDate; }
if ($search) { $query .= " AND u.name LIKE ?"; $params[] = "%{$search}%"; }
$query .= " ORDER BY rs.created_at DESC";

$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    $pagination = null;
} else {
    $pagination = paginate($pdo, $query, $params, 15);
    $sales = $pagination['data'];
}

// Summaries
$summary = null; $confirmation = null; $unverifiedCount = 0;
if ($filterDate) {
    $sumQ = "SELECT SUM(cash_amount) as total_cash, SUM(actual_setor) as total_actual_setor, SUM(qris_amount) as total_qris, SUM(minus_amount) as total_minus, SUM(total_gross_income) as total_omset, COUNT(DISTINCT rider_id) as rider_count FROM rider_daily_sales WHERE branch_id = ? AND DATE(date) = ?";
    $sumP = [$branchId, $filterDate];
    if($search){ $sumQ .= " AND rider_id IN (SELECT id FROM users WHERE name LIKE ?)"; $sumP[] = "%{$search}%"; }
    $sumStmt = $pdo->prepare($sumQ); $sumStmt->execute($sumP); $summary = $sumStmt->fetch();
    
    $cupsStmt = $pdo->prepare("SELECT SUM(stock_sold) FROM rider_daily_sale_items i JOIN rider_daily_sales s ON i.rider_daily_sale_id = s.id WHERE s.branch_id = ? AND DATE(s.date) = ?");
    $cupsStmt->execute([$branchId, $filterDate]); $summary['total_cups'] = $cupsStmt->fetchColumn() ?: 0;

    $confStmt = $pdo->prepare("SELECT c.*, u.name as confirmed_by_name FROM rider_sales_journal_confirmations c LEFT JOIN users u ON c.confirmed_by = u.id WHERE c.branch_id = ? AND DATE(c.date) = ?");
    $confStmt->execute([$branchId, $filterDate]); $confirmation = $confStmt->fetch();

    $unvStmt = $pdo->prepare("SELECT COUNT(*) FROM rider_daily_sales rs LEFT JOIN users a ON rs.admin_id = a.id WHERE rs.branch_id = ? AND DATE(rs.date) = ? AND (rs.admin_id IS NULL OR a.role NOT IN ('admin', 'owner'))");
    $unvStmt->execute([$branchId, $filterDate]); $unverifiedCount = $unvStmt->fetchColumn();
}

$pageTitle = 'Daftar Penjualan Rider';

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
        .print-title h2 { margin: 0; color: #1FBB58; font-size: 16pt; }
        .print-title p { margin: 5px 0 0 0; color: #64748b; font-size: 10pt; }
        
        .topbar, .sidebar, .btn, .btn-primary, .btn-secondary, .filter-card, .search-box, .badge-outline-green, .badge-warning, .btn-delete-outline, .btn-confirm-huge, .confirmation-banner { display: none !important; }
        
        .card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; break-inside: avoid; margin-bottom: 20px !important; border-radius: 8px !important; }
        .table-header-flex { border-bottom: 1px solid #cbd5e1 !important; }
        
        table { width: 100% !important; border-collapse: collapse !important; }
        th { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1 !important; padding: 10px 8px !important; font-weight: bold !important; font-size: 9.5pt !important; }
        td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; font-size: 10pt !important; }
        
        th:last-child, td:last-child, th:nth-child(6), td:nth-child(6) { display: none !important; } /* Hide action & total setoran column */
    }
    .print-title { display: none; }

    /* Styling according to screenshots */
    .filter-card { background: var(--bg-card); border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .filter-title { font-weight: 600; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 16px; display: block; }
    
    .stats-grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-card-custom { background: var(--bg-card); border-radius: 12px; padding: 20px; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .stat-card-custom .icon-bg { position: absolute; right: -10px; top: -10px; font-size: 80px; opacity: 0.05; z-index: 1; pointer-events: none; }
    .stat-card-custom .value { font-size: 1.6rem; font-weight: 800; margin-bottom: 4px; z-index: 2; position: relative; }
    .stat-card-custom .label { font-size: 0.85rem; color: var(--text-muted); z-index: 2; position: relative; }
    
    .stat-green .value { color: #1FBB58; }
    .stat-blue .value { color: #3b82f6; }
    .stat-red .value { color: #ef4444; }
    .stat-purple .value { color: #8b5cf6; }
    
    .stat-solid-green { background: #1FBB58; color: white; }
    .stat-solid-green .value { color: white; }
    .stat-solid-green .label { color: rgba(255,255,255,0.8); }
    
    .table-header-flex { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
    .table-title { font-weight: 600; font-size: 1.1rem; color: var(--text-primary); margin: 0; }
    
    .search-box { position: relative; width: 250px; display: flex; }
    .search-box input { width: 100%; padding: 8px 12px 8px 16px; border: 1px solid var(--border-color); border-radius: 8px 0 0 8px; font-size: 14px; background: var(--bg-main); color: var(--text-primary); border-right: none; outline: none; }
    .search-box input:focus { border-color: #8B5E3C; }
    .search-box button { background: #8B5E3C; color: white; border: none; border-radius: 0 8px 8px 0; padding: 0 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    
    /* Table specific colors */
    .text-green { color: #1FBB58; font-weight: 600; }
    .text-blue { color: #3b82f6; font-weight: 600; }
    .text-purple { color: #8b5cf6; font-weight: 600; }
    .text-red { color: #ef4444; font-weight: 600; }
    .text-gray { color: var(--text-muted); }
    
    .badge-outline-green { border: 1px solid #1FBB58; color: #1FBB58; background: rgba(31,187,88,0.08); border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .badge-outline-green:hover { background: #1FBB58; color: #fff; box-shadow: 0 4px 14px rgba(31,187,88,0.35); transform: translateY(-2px); }
    .badge-outline-green:hover svg { stroke: #ffffff; color: #ffffff; }
    
    .btn-verify-outline { border: 1px solid #f59e0b; color: #f59e0b; background: rgba(245,158,11,0.06); border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-verify-outline:hover { background: #f59e0b; color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,0.3); transform: translateY(-1px); }
    .btn-verify-outline:active { transform: translateY(0) scale(0.97); }
    
    .btn-delete-outline { border: 1px solid #ef4444; color: #ef4444; background: transparent; border-radius: 6px; padding: 4px 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-delete-outline:hover { background: #ef4444; color: #fff; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); transform: translateY(-1px); }
    .btn-delete-outline:active { transform: translateY(0) scale(0.97); }
    
    .btn-edit-outline { border: 1px solid #3b82f6; color: #3b82f6; background: rgba(59,130,246,0.06); border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-edit-outline:hover { background: #3b82f6; color: #fff; box-shadow: 0 4px 12px rgba(59,130,246,0.3); transform: translateY(-1px); }
    .btn-edit-outline:active { transform: translateY(0) scale(0.97); }
    
    .confirmation-banner { background: #e6f4ea; border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border: 1px solid #c3e6cb; }
    .confirmation-text { color: #155724; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .btn-rollback { background: white; border: 1px solid #ef4444; color: #ef4444; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-rollback:hover { background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); transform: translateY(-1px); }
    .btn-rollback:active { transform: translateY(0) scale(0.97); }
    
    .btn-confirm-huge { width: 100%; background: #1FBB58; color: white; border: none; border-radius: 8px; padding: 16px; font-size: 16px; font-weight: 600; cursor: pointer; margin-bottom: 24px; display: flex; justify-content: center; align-items: center; gap: 8px; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-confirm-huge:hover:not([disabled]) { background: #17a34a; box-shadow: 0 6px 20px rgba(31,187,88,0.35); transform: translateY(-2px); }
    .btn-confirm-huge:active:not([disabled]) { transform: translateY(0) scale(0.98); }
    
    /* Table headers uppercase and gray */
    .table-container th { text-transform: uppercase; color: #94a3b8; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
    
    /* Modal Styling */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalScale { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    
    .modal.open { display: flex; animation: fadeIn 0.25s ease-out; }
    .modal.open .modal-content { animation: modalScale 0.25s ease-out; }
    .modal-content { background-color: var(--bg-card); margin: auto; padding: 20px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
</style>

<!-- Print Wrapper -->
<div id="print-area">
    <div class="print-title print-only">
        <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;">
        <h2 style="color: #1FBB58; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Tether Brew</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">Daftar Penjualan Rider — Dicetak: <?= date('d F Y') ?><br>
        <?php if($filterDate) echo "Tanggal: " . date('d F Y', strtotime($filterDate)); else echo "Semua Tanggal"; ?></p>
    </div>

<!-- Filter Section -->
<div class="filter-card">
    <span class="filter-title">Filter Penjualan</span>
    <form method="GET" style="display:flex; gap:12px; align-items:flex-end;">
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label" style="text-transform: uppercase; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: 0.5px;">Tanggal</label>
            <input type="text" name="date" class="form-input datepicker" value="<?= $filterDate ?>" placeholder="Semua Tanggal">
        </div>
        <?php if($userRole === 'bar'): ?>
        <div class="form-group" style="flex: 1.5; min-width: 200px; margin-bottom: 0; position: relative;">
            <label class="form-label" style="text-transform: uppercase; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: 0.5px;">Cari Rider</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Ketik nama rider & tekan Enter..." style="padding-right: 3rem; margin: 0; width: 100%;">
                <button type="submit" style="position: absolute; right: 0; top: 0; bottom: 0; background: transparent; border: none; padding: 0 15px; color: var(--text-muted); cursor: pointer;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            </div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" style="height:42px; background: #1FBB58; border-color: #1FBB58; padding: 0 24px; font-weight: 500;">Filter</button>
        <?php if($filterDate || $search): ?>
        <a href="<?= url('/rider-sales') ?>" class="btn btn-secondary" style="height:42px; padding: 0 24px; font-weight: 500; display: flex; align-items: center;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Summaries Section (Only if date filtered) -->
<?php if($summary && $filterDate && $isAdmin): ?>
    <div class="stats-grid-5">
        <div class="stat-card-custom stat-green">
            <div class="icon-bg"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg></div>
            <div class="value">Rp<br><?= number_format($summary['total_actual_setor'], 0, ',', '.') ?></div>
            <div class="label">Setoran Cash (Fisik)</div>
        </div>
        <div class="stat-card-custom stat-blue">
            <div class="icon-bg"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>
            <div class="value">Rp<br><?= number_format($summary['total_qris'], 0, ',', '.') ?></div>
            <div class="label">Total QRIS</div>
        </div>
        <div class="stat-card-custom stat-red">
            <div class="icon-bg"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg></div>
            <div class="value">Rp <?= number_format($summary['total_minus'], 0, ',', '.') ?></div>
            <div class="label">Total Minus</div>
        </div>
        <div class="stat-card-custom stat-purple">
            <div class="icon-bg"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div class="value"><?= $summary['rider_count'] ?> Rider</div>
            <div class="label">Jumlah Rider</div>
        </div>
        <div class="stat-card-custom stat-solid-green">
            <div class="icon-bg"><svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg></div>
            <div class="value"><?= number_format($summary['total_cups'], 0, ',', '.') ?> Pcs</div>
            <div class="label">Total Cup</div>
        </div>
    </div>

    <!-- Confirmation Status -->
    <?php if($confirmation): ?>
        <div class="confirmation-banner">
            <div class="confirmation-text">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Data penjualan tanggal <strong style="margin-left: 4px; margin-right: 4px;"><?= date('d F Y', strtotime($filterDate)) ?></strong> sudah dikonfirmasi ke Jurnal Umum oleh <strong style="margin-left: 4px;"><?= e($confirmation['confirmed_by_name']) ?></strong>.
            </div>
            <form method="POST" action="<?= url('/rider-sales/rollback-journal') ?>" style="margin: 0;">
                <?= csrf_field() ?><input type="hidden" name="date" value="<?= $filterDate ?>">
                <button type="button" class="btn-rollback" onclick="confirmRollback(this)">
                    <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: text-top;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Batalkan Konfirmasi
                </button>
            </form>
        </div>
    <?php else: ?>
        <button type="button" class="btn-confirm-huge" onclick="openConfirmModal()" <?= $unverifiedCount > 0 ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : '' ?>>
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Konfirmasi ke Jurnal Umum <?= $unverifiedCount > 0 ? "({$unverifiedCount} data belum diperiksa)" : '' ?>
        </button>
    <?php endif; ?>
<?php endif; ?>

<!-- Table Header & Action Toolbar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;" class="print-hide">
    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Riwayat Input Penjualan</h3>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <?php if($isAdmin): ?>
        <form id="deleteAll-riderSales" method="POST" action="<?= url('/rider-sales/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none; display: flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 8px; font-weight: 600;" onclick="confirmDeleteAll('deleteAll-riderSales')">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Hapus Semua
            </button>
        </form>
        <?php endif; ?>
        <a href="?date=<?= $filterDate ?>&search=<?= urlencode($search) ?>&print=1" target="_blank" class="btn btn-secondary btn-sm" style="display: flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 8px; font-weight: 600; text-decoration: none;">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print
        </a>
        <a href="<?= url('/rider-sales/create') ?>" class="btn btn-primary btn-sm" style="display: flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 8px; font-weight: 600; text-decoration: none;">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Input Penjualan Baru
        </a>
    </div>
</div>

<!-- Main Table Card -->
<div class="card" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;">
    <div class="table-header-flex">
        <h4 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary);">Daftar Setoran</h4>
        <form method="GET" class="search-box">
            <?php if($filterDate): ?><input type="hidden" name="date" value="<?= $filterDate ?>"><?php endif; ?>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Ketik & tekan Enter...">
            <button type="submit">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </button>
        </form>
    </div>
    
    <div class="card-body" style="padding:0;">
        <?php if(empty($sales)):?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Tidak ada data penjualan</h3>
            </div>
        <?php else:?>
            <div class="table-container">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 16px;">TANGGAL</th>
                            <th style="padding: 16px;">RIDER</th>
                            <?php if($isAdmin): ?>
                            <th style="padding: 16px; text-align: right;">CASH<br>(TARGET)</th>
                            <th style="padding: 16px; text-align: right;">CASH<br>(SETOR FISIK)</th>
                            <th style="padding: 16px; text-align: right;">QRIS</th>
                            <th style="padding: 16px; text-align: right;">TOTAL<br>SETORAN</th>
                            <th style="padding: 16px; text-align: right;">TOTAL</th>
                            <?php endif; ?>
                            <th style="padding: 16px; text-align: center;">TOTAL<br>CUPS</th>
                            <th style="padding: 16px;">ADMIN<br>PEMERIKSA</th>
                            <th style="padding: 16px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($sales as $s): 
                        $totalSetoran = $s['actual_setor'] + $s['qris_amount'];
                        $total = $s['cash_amount'] + $s['qris_amount'];
                    ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 16px; vertical-align: middle;">
                                <div style="color: var(--text-muted); font-size: 13px;">
                                    <?= date('d M', strtotime($s['date'])) ?><br>
                                    <?= date('Y', strtotime($s['date'])) ?>
                                </div>
                            </td>
                            <td style="padding: 16px; vertical-align: middle;">
                                <div style="font-weight: 600; color: var(--text-primary);">
                                    <?php 
                                        $riderNames = explode(' ', e($s['rider_name']));
                                        echo $riderNames[0] . '<br>' . (isset($riderNames[1]) ? $riderNames[1] : '');
                                    ?>
                                </div>
                            </td>
                            <?php if($isAdmin): ?>
                            <td style="padding: 16px; text-align: right; vertical-align: middle; color: #94a3b8;">
                                <span style="font-size:11px;">Rp</span><br><?= number_format($s['cash_amount'], 0, ',', '.') ?>
                            </td>
                            <td style="padding: 16px; text-align: right; vertical-align: middle;" class="text-green">
                                Rp <?= number_format($s['actual_setor'], 0, ',', '.') ?>
                            </td>
                            <td style="padding: 16px; text-align: right; vertical-align: middle; color: #94a3b8;">
                                <span style="font-size:11px;">Rp</span><br><?= number_format($s['qris_amount'], 0, ',', '.') ?>
                            </td>
                            <td style="padding: 16px; text-align: right; vertical-align: middle;" class="text-blue">
                                Rp<br><?= number_format($totalSetoran, 0, ',', '.') ?>
                            </td>
                            <td style="padding: 16px; text-align: right; vertical-align: middle;" class="text-green">
                                Rp<br><?= number_format($total, 0, ',', '.') ?>
                            </td>
                            <?php endif; ?>
                            <td style="padding: 16px; text-align: center; vertical-align: middle;" class="text-purple">
                                <?= $s['total_cups'] ?> Pcs
                            </td>
                            <td style="padding: 16px; vertical-align: middle; color: #64748b; font-size: 13px;">
                                <?php 
                                    $adminDisplay = $s['admin_pemeriksa'] ?: ($s['admin_name'] ?? '');
                                    if ($adminDisplay) {
                                        $adminNames = explode(' ', e($adminDisplay));
                                        echo $adminNames[0] . '<br>' . (isset($adminNames[1]) ? $adminNames[1] : '');
                                    } else {
                                        echo '<span style="color:var(--text-muted); font-style:italic;">—</span>';
                                    }
                                ?>
                            </td>
                            <td style="padding: 16px; text-align: center; vertical-align: middle;">
                                <div style="display:flex; gap:8px; justify-content: center; align-items: center;">
                                    <?php if($confirmation && $isAdmin): ?>
                                        <button disabled class="badge-outline-green" style="opacity: 0.8; cursor: not-allowed; border: none;">
                                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                            Sudah Dikonfirmasi
                                        </button>
                                    <?php else: ?>
                                        <?php if($isAdmin): ?>
                                            <?php if(in_array($s['admin_role'], ['admin','owner'])): ?>
                                                <a href="<?= url('/rider-sales/edit?id=' . $s['id']) ?>" class="badge-outline-green" style="text-decoration:none;" title="Diperiksa">
                                                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                                    Diperiksa
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= url('/rider-sales/edit?id=' . $s['id']) ?>" class="btn-verify-outline" title="Klik untuk memeriksa data ini">
                                                    <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
                                                    Konfirmasi
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="<?= url('/rider-sales/edit?id=' . $s['id']) ?>" class="btn-edit-outline" title="Edit">
                                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn-delete-outline" onclick="deleteSale(<?= $s['id'] ?>)">
                                            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination): ?>
            <div style="padding:16px;"><?= render_pagination($pagination) ?></div>
            <?php endif; ?>
        <?php endif;?>
    </div>
</div>

<form id="delete-form" method="POST" action="<?= url('/rider-sales/delete') ?>" style="display:none;"><?= csrf_field() ?><input type="hidden" name="id" id="delete-id"></form>

<!-- Modal Konfirmasi Jurnal -->
<div id="confirmJournalModal" class="modal">
    <div class="modal-content" style="max-width: 500px; border-radius: 16px; padding: 30px;">
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 20px; font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Konfirmasi Jurnal Umum</h2>
        <p style="text-align: center; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.5;">
            Apakah Anda yakin ingin mengonfirmasi penjualan rider tanggal <strong style="color: var(--text-primary);"><?= date('d F Y', strtotime($filterDate ?? date('Y-m-d'))) ?></strong> ke Jurnal Umum?
        </p>
        
        <?php if($summary): ?>
        <div style="background: var(--bg-main); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-weight: 500;">
                <span style="color: #64748b;">Setoran Cash (Masuk Jurnal):</span>
                <span class="text-green">Rp <?= number_format($summary['total_actual_setor'], 0, ',', '.') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; font-weight: 500;">
                <span style="color: #64748b;">Total QRIS:</span>
                <span class="text-blue">Rp <?= number_format($summary['total_qris'], 0, ',', '.') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 500;">
                <span style="color: #64748b;">Total Minus:</span>
                <span class="text-red">Rp <?= number_format($summary['total_minus'], 0, ',', '.') ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <p style="font-size: 12px; color: #94a3b8; font-style: italic; margin-bottom: 30px; line-height: 1.5;">
            *Catatan: Nilai minus tidak dicatat ke jurnal sekarang, melainkan akan otomatis tercatat ke Jurnal Umum ketika rider melakukan pembayaran kasbon di masa mendatang.
        </p>
        
        <form method="POST" action="<?= url('/rider-sales/confirm-journal') ?>" style="display: flex; justify-content: center; gap: 16px; margin: 0;">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= $filterDate ?>">
            <button type="button" class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600;" onclick="closeConfirmModal()">Batal</button>
            <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981; padding: 12px 30px; border-radius: 8px; font-weight: 600;">Ya, Konfirmasi</button>
        </form>
    </div>
</div>

<script>
function deleteSale(id) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Yakin hapus data penjualan ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-form').submit();
        }
    });
}

function confirmRollback(btn) {
    Swal.fire({
        title: 'Batalkan Konfirmasi',
        text: 'Yakin batalkan konfirmasi jurnal ini? Jurnal terkait akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tutup',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            btn.closest('form').submit();
        }
    });
}

function openConfirmModal() {
    document.getElementById('confirmJournalModal').classList.add('open');
}

function closeConfirmModal() {
    document.getElementById('confirmJournalModal').classList.remove('open');
}
</script>

</div> <!-- End print-area -->

<?php if ($isPrint): ?>
<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
<?php endif; ?>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
