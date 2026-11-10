<?php
require_once __DIR__ . '/../../core/bootstrap.php'; 
require_auth(); 
require_admin_or_owner();

$branchId = activeBranchId();
$catId = $_GET['category_id'] ?? ''; 
$start = $_GET['start_date'] ?? date('Y-m-01'); 
$end = $_GET['end_date'] ?? date('Y-m-d');

$query = "SELECT j.*, c.name as category_name, u.name as creator_name FROM journals j LEFT JOIN journal_categories c ON j.journal_category_id = c.id LEFT JOIN users u ON j.created_by = u.id WHERE j.branch_id = ?";
$params = [$branchId];

if($catId) { 
    $query .= " AND j.journal_category_id = ?"; 
    $params[] = $catId; 
}
if($start) { 
    $query .= " AND DATE(j.date) >= ?"; 
    $params[] = $start; 
}
if($end) { 
    $query .= " AND DATE(j.date) <= ?"; 
    $params[] = $end; 
}

// Totals
$sumQuery = "SELECT SUM(CASE WHEN type='debit' THEN amount ELSE 0 END) as total_debit, SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as total_credit FROM journals j WHERE j.branch_id = ?";
$sumParams = [$branchId];
if($catId) { $sumQuery .= " AND j.journal_category_id = ?"; $sumParams[] = $catId; }
if($start) { $sumQuery .= " AND DATE(j.date) >= ?"; $sumParams[] = $start; }
if($end) { $sumQuery .= " AND DATE(j.date) <= ?"; $sumParams[] = $end; }
$sumStmt = $pdo->prepare($sumQuery); 
$sumStmt->execute($sumParams); 
$totals = $sumStmt->fetch();


$sumQueryMinus = "SELECT SUM(minus_amount) as total_minus, SUM(minus_paid) as total_paid FROM rider_daily_sales s WHERE s.branch_id=? AND s.minus_amount > 0";
$sumParamsMinus = [$branchId];
// if($riderId) { $sumQueryMinus .= " AND s.rider_id=?"; $sumParamsMinus[] = $riderId; }
// if($status) { $sumQueryMinus .= " AND s.minus_status=?"; $sumParamsMinus[] = $status; }
$sumStmt = $pdo->prepare($sumQueryMinus); 
$sumStmt->execute($sumParamsMinus); 
$totalsMinus = $sumStmt->fetch();

$totalMinus = $totalsMinus['total_minus'] ?? 0;
$totalPaid = $totalsMinus['total_paid'] ?? 0;

$stmtKo = $pdo->prepare("SELECT SUM(amount - paid_amount) FROM office_kasbons WHERE branch_id = ? AND status != 'paid'");
$stmtKo->execute([$branchId]);
$totalOfficeKasbon = (float)$stmtKo->fetchColumn();

$stmtRk = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE branch_id=? AND type='kasbon'");
$stmtRk->execute([$branchId]);
$totalRiderKasbonNet = (float)$stmtRk->fetchColumn();

$stmtRkPayroll = $pdo->prepare("SELECT SUM(kasbon_deducted) FROM payroll_records WHERE branch_id=? AND status='confirmed'");
$stmtRkPayroll->execute([$branchId]);
$totalRiderKasbonDeducted = (float)$stmtRkPayroll->fetchColumn();

$totalRiderKasbonBelumDibayar = $totalRiderKasbonNet - $totalRiderKasbonDeducted;


$totalDebit = $totals['total_debit'] ?? 0;
$totalCredit = $totals['total_credit'] ?? 0;
$balance = $totalDebit - $totalCredit;

$query .= " ORDER BY j.date DESC, j.id DESC";

$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $journals = $stmt->fetchAll();
    $pagination = null;
} else {
    $pagination = paginate($pdo, $query, $params, 15);
    $journals = $pagination['data'];
}

$categories = $pdo->query("SELECT * FROM journal_categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Jurnal Umum';

$printParams = $_GET;
$printParams['print'] = 1;
$printUrl = '?' . http_build_query($printParams);

ob_start();
?>
    <div id="print-area">
        <div class="print-title print-only" style="display: none;">
            <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;">
            <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Tether Brew</h2>
            <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">Laporan Jurnal Umum — Dicetak: <?= date('d F Y') ?></p>
        </div>

        <?php // Print Only Summary (2 Rows x 3 Columns) ?>
        <div class="journal-summary-row" style="display: none; margin-bottom: 20px;">
            <div class="summary-print-card">
                <div class="summary-print-label">Total Pemasukan (Debit)</div>
                <div class="summary-print-val" style="color: #16a34a;">
                    <?= formatRupiah($totalDebit) ?>
                </div>
            </div>
            <div class="summary-print-card">
                <div class="summary-print-label">Total Pengeluaran (Kredit)</div>
                <div class="summary-print-val" style="color: #dc2626;">
                    <?= formatRupiah($totalCredit) ?>
                </div>
            </div>
            <div class="summary-print-card">
                <div class="summary-print-label">Saldo Saat Ini</div>
                <div class="summary-print-val" style="color: #0f172a;">
                    <?= formatRupiah($balance) ?>
                </div>
            </div>
            <div class="summary-print-card">
                <div class="summary-print-label">Minus Rider (Belum Dibayar)</div>
                <div class="summary-print-val" style="color: #d97706;">
                    <?= formatRupiah($totalMinus - $totalPaid) ?>
                </div>
            </div>
            <div class="summary-print-card">
                <div class="summary-print-label">Kasbon Office (Belum Dibayar)</div>
                <div class="summary-print-val" style="color: #7c3aed;">
                    <?= formatRupiah($totalOfficeKasbon) ?>
                </div>
            </div>
            <div class="summary-print-card">
                <div class="summary-print-label">Kasbon Rider (Belum Dibayar)</div>
                <div class="summary-print-val" style="color: #db2777;">
                    <?= formatRupiah($totalRiderKasbonBelumDibayar) ?>
                </div>
            </div>
        </div>

        <div class="stats-grid print-hide mb-4" style="margin-bottom: 20px;">
        <?php // Card Debit ?>
        <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #22c55e; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #22c55e;">
                <?= formatRupiah($totalDebit) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2;">Total Pemasukan (Debit)</div>
            </div>

        <?php // Card Kredit ?>
        <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #ef4444; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #ef4444;">
                <?= formatRupiah($totalCredit) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2;">Total Pengeluaran (Kredit)</div>
            </div>

        <?php // Card Saldo ?>
        <div class="stat-card stat-card-animated"
            style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: var(--gradient-gold); color: white;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="M2 10h20"></path>
                    <path d="M16 14h2"></path>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: white;">
                <?= formatRupiah($balance) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color:white">Saldo Saat Ini</div>
            </div>

        <?php // Card Minus Rider ?>
        <div class="stat-card stat-card-animated"
            style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: white;">
                <?= formatRupiah($totalMinus - $totalPaid) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color:white">Minus Rider (Belum Dibayar)</div>
            </div>

        <?php // Card Kasbon Office ?>
        <div class="stat-card stat-card-animated"
            style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: white;">
                <?= formatRupiah($totalOfficeKasbon) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color:white">Kasbon Office (Belum Dibayar)</div>
            </div>
            
        <?php // Card Kasbon Rider ?>
        <div class="stat-card stat-card-animated"
            style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white;">
            <div class="stat-icon-bg"
                style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.25; color: white; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: white;">
                <?= formatRupiah($totalRiderKasbonBelumDibayar) ?>
            </div>
            <div class="stat-label" style="position: relative; z-index: 2; opacity: 0.9; color:white">Kasbon Rider (Belum Dibayar)</div>
            </div>
            </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header print-hide" style="padding-bottom: 0;">
            <form method="GET" action="<?= url('/journals') ?>"
                style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px;">
                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                    <label class="form-label">Mulai Tanggal</label>
                    <div class="flatpickr-input-container" style="position:relative;">
                        <input type="text" name="start_date" id="start_date" class="form-input datepicker" value="<?= $start ?>"
                            placeholder="Pilih tanggal...">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); pointer-events:none; color:var(--text-muted);">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                            <label class="form-label">Sampai Tanggal</label>
                            <div class="flatpickr-input-container" style="position:relative;">
                        <input type="text" name="end_date" id="end_date" class="form-input datepicker" value="<?= $end ?>"
                            placeholder="Pilih tanggal...">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); pointer-events:none; color:var(--text-muted);">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        </div>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-input">
                                <option value="">Semua Kategori</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $catId == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; gap: 8px; margin-bottom: 0;">
                            <button type="submit" class="btn btn-primary" style="height: 42px;">Filter</button>
                            <a href="<?= url('/journals') ?>" class="btn btn-secondary" style="height: 42px; display: flex; align-items: center;">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Header & Action Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;" class="print-hide">
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Daftar Jurnal Umum</h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <form id="deleteAll-journals" method="POST" action="<?= url('/journals/delete-all') ?>" style="margin:0;">
                        <?= csrf_field() ?>
                        <button type="button" class="btn btn-sm flex-center" style="background-color:#ef4444; color:white; border:none; height: 36px; gap:0.5rem; white-space: nowrap; padding: 0 14px; border-radius: 8px; font-weight:600;" onclick="confirmDeleteAll('deleteAll-journals')">
                            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
                        </button>
                    </form>

                    <a href="<?= $printUrl ?>" target="_blank" class="btn btn-secondary btn-sm flex-center"
                        style="height: 36px; padding: 0 14px; border-radius: 8px; gap:0.5rem; text-decoration: none;">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>
                        </svg> Print
                    </a>

                    <button type="button" onclick="openImportModal()" class="btn btn-secondary btn-sm flex-center"
                        style="height: 36px; padding: 0 14px; border-radius: 8px; gap:0.5rem; white-space: nowrap; background: #10b981; color: white; border-color: #10b981;">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg> Import Excel
                    </button>

                    <button type="button" onclick="openJournalModal()" class="btn btn-primary btn-sm flex-center"
                        style="height: 36px; padding: 0 14px; border-radius: 8px; gap:0.5rem; white-space: nowrap;">
                        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg> Input Jurnal
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card">
                <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Tanggal</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Kategori</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Keterangan</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Tipe</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-align: right; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Jumlah (Rp)</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Diinput Oleh</th>
                            <th style="background-color: var(--bg-secondary); color: var(--text-primary); padding: 10px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 600;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="journal-table-body">
                        <?php if(empty($journals)): ?>
                            <tr id="no-data-row">
                                <td colspan="7" style="text-align: center; padding: 20px;">Belum ada data jurnal.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($journals as $journal): ?>
                                <tr class="journal-row">
                                    <td style="padding: 12px 10px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary);"><?= date('d/m/Y', strtotime($journal['date'])) ?></td>
                                    <td style="padding: 12px 10px;">
                                        <?php if($journal['category_name']): ?>
                                            <span
                                                style="display: inline-block; padding: 4px 10px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 6px; font-size: 0.85rem; font-weight: 600;"><?= e($journal['category_name']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 10px; font-size: 0.9rem; color: var(--text-secondary);"><?= e($journal['description']) ?></td>
                                    <td style="padding: 12px 10px;">
                                        <?php if($journal['type'] === 'debit'): ?>
                                            <span style="color: #22c55e; font-weight: 600; font-size: 0.9rem;">Debit (Masuk)</span>
                                        <?php else: ?>
                                            <span style="color: #ef4444; font-weight: 600; font-size: 0.9rem;">Kredit (Keluar)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; padding: 12px 10px; font-weight: 600; font-size: 0.9rem; color: var(--text-primary);"><?= formatRupiah($journal['amount']) ?></td>
                                    <td style="padding: 12px 10px; font-size: 0.9rem; color: var(--text-secondary);"><?= e($journal['creator_name']) ?></td>
                                    <td style="padding: 12px 10px;">
                                        <form method="POST" action="<?= url('/journals/delete') ?>"
                                            onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $journal['id'] ?>">
                                            <button type="submit" class="btn-delete-outline" title="Hapus">
                                                <svg class="icon-two-tone" width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php // Pagination Controls ?>
            <?php if($pagination): ?>
            <div class="pagination-container print-hide"
                style="margin-top: 20px; padding: 15px 0px; border-top: 1px solid #e2e8f0;">
                <?= render_pagination($pagination) ?>
            </div>
            <?php endif; ?>

            </div>
            </div>
            </div>

    <!-- Modal Input Jurnal -->
    <div id="journalModal" class="modal-overlay-animate"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="card modal-content-animate"
            style="width: 100%; max-width: 500px; margin: 20px; box-shadow: var(--shadow-lg);">
            <div class="card-header" style="border-bottom: 1px solid #e2e8f0; padding: 15px 20px;">
                <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 600;">Tambah Data Jurnal</h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <form action="<?= url('/journals/store') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Tanggal</label>
                        <div class="flatpickr-input-container" style="position:relative;">
                            <input type="date" name="date" id="modal_date" class="form-input datepicker" value="<?= date('Y-m-d') ?>" required
                                placeholder="Pilih tanggal..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Tipe Transaksi</label>
                        <select name="type" class="form-input" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                            <option value="debit">Debit (Pemasukan)</option>
                            <option value="credit">Kredit (Pengeluaran)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Kategori</label>
                        <select name="journal_category_id" class="form-input" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                            <option value="">Pilih Kategori (Opsional)...</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Keterangan / Catatan</label>
                        <input type="text" name="description" class="form-input" placeholder="Contoh: Beli gula, Pendapatan lain-lain..." required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Jumlah Uang (Rp)</label>
                        <input type="number" name="amount" class="form-input" placeholder="0" min="0" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <button type="button" onclick="closeJournalModal()" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Jurnal</button>
                    </div>
                    </form>
                    </div>
                    </div>
                    </div>

    <!-- Modal Import Excel -->
    <div id="importModal" class="modal-overlay-animate"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="card modal-content-animate"
            style="width: 100%; max-width: 500px; margin: 20px; box-shadow: var(--shadow-lg);">
            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 15px 20px;">
                <h3 class="card-title" style="margin: 0; font-size: 1.1rem; font-weight: 600;">Import Data Jurnal via Excel</h3>
            </div>
            <div class="card-body" style="padding: 20px;">
                <div style="margin-bottom: 20px;">
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 10px;">
                        Gunakan file template Excel berikut agar format data sesuai dengan sistem.
                    </p>
                    <a href="<?= url('/journals/template') ?>" class="btn btn-secondary" style="font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg> Download Template (.csv)
                    </a>
                </div>

                <form action="<?= url('/journals/import') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Upload File (Excel/CSV)</label>
                        <input type="file" name="file" class="form-input" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required style="width: 100%; padding: 8px; border: 1px dashed #cbd5e1; border-radius: 6px;">
                        <small style="color: #64748b; font-size: 0.8rem; margin-top: 5px; display: block;">Maksimal ukuran file: 10MB.</small>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <button type="button" onclick="closeImportModal()" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">Proses Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<style>
    @media print {
        @page {
            margin: 15mm;
        }

        body {
            font-size: 11pt;
        }

        body * {
            visibility: hidden;
        }

        #print-area,
        #print-area * {
            visibility: visible;
        }

        #print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        .print-title {
            display: block !important;
            text-align: center;
            padding-top: 20px;
            padding-bottom: 15px;
            border-bottom: 2px dashed #cbd5e1;
            margin-bottom: 20px !important;
        }

        .print-title h2 {
            margin: 0;
            color: #22c55e;
            font-size: 16pt;
        }

        .print-title p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 10pt;
        }

        .topbar,
        .sidebar,
        .btn,
        .print-hide,
        #journalModal,
        form[method="POST"],
        .modal-overlay-animate {
            display: none !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            break-inside: avoid;
            margin-bottom: 20px !important;
            border-radius: 8px !important;
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid #cbd5e1 !important;
            padding: 12px 16px !important;
        }

        .card-header.print-hide {
            display: none !important;
        }

        .card-body {
            padding: 15px 16px !important;
        }

        /* Summary cards styling for print (2 rows x 3 columns) */
        .journal-summary-row {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 12px 14px !important;
            margin-bottom: 20px !important;
        }
        .journal-summary-row > div,
        .summary-print-card {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            color: #1e293b !important;
            padding: 12px 14px !important;
            text-align: center !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
        }
        .summary-print-label {
            font-size: 8.5pt !important;
            font-weight: 700 !important;
            color: #64748b !important;
            margin-bottom: 6px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.4px !important;
            line-height: 1.25 !important;
        }
        .summary-print-val {
            font-size: 13.5pt !important;
            font-weight: 800 !important;
            line-height: 1.2 !important;
        }

        /* Table print styles */
        table { width: 100% !important; border-collapse: collapse !important; }
        /* FORCE all rows to display when printing regardless of pagination */
        .journal-row { display: table-row !important; }
        th { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1 !important; padding: 10px 8px !important; font-weight: bold !important; font-size: 9.5pt !important; }
        td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; font-size: 10pt !important; }
        tfoot td { border-bottom: none !important; border-top: 2px solid #cbd5e1 !important; }

        /* Hide action column */
        th:last-child, td:last-child { display: none !important; }
    }

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
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
        border: 1px solid var(--border-color);
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if(typeof flatpickr !== 'undefined') {
            const commonConfig = {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d F Y",
                allowInput: true,
                disableMobile: "true"
            };

            const startPicker = flatpickr("#start_date", {
                ...commonConfig,
                onChange: function (selectedDates, dateStr, instance) {
                    endPicker.set('minDate', dateStr);
                }
            });

            const endPicker = flatpickr("#end_date", {
                ...commonConfig,
                minDate: document.getElementById('start_date').value || null
            });

            flatpickr("#modal_date", commonConfig);
        }
    });

    function openJournalModal() {
        document.getElementById('journalModal').style.display = 'flex';
    }

    function closeJournalModal() {
        document.getElementById('journalModal').style.display = 'none';
    }

    window.addEventListener('click', function (event) {
        const modal = document.getElementById('journalModal');
        const importModal = document.getElementById('importModal');
        if (event.target == modal) {
            closeJournalModal();
        }
        if (event.target == importModal) {
            closeImportModal();
        }
    });
    
    function openImportModal() {
        document.getElementById('importModal').style.display = 'flex';
    }

    function closeImportModal() {
        document.getElementById('importModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
            openJournalModal();
        }
    });
</script>

<?php if ($isPrint): ?>
<style>
    /* Clean screen view when print=1 is opened */
    .topbar, .sidebar, .sidebar-backdrop, .btn, .print-hide, #journalModal, form[method="POST"], .modal-overlay-animate {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .page-content {
        padding: 24px !important;
        max-width: 1040px !important;
        margin: 0 auto !important;
    }
    .print-title {
        display: block !important;
        text-align: center;
        padding-top: 10px;
        padding-bottom: 15px;
        border-bottom: 2px dashed #cbd5e1;
        margin-bottom: 20px !important;
    }
    .journal-summary-row {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 14px !important;
        margin-bottom: 20px !important;
    }
    .summary-print-card {
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        color: #1e293b !important;
        padding: 14px 16px !important;
        text-align: center !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
    }
    .summary-print-label {
        font-size: 8.5pt !important;
        font-weight: 700 !important;
        color: #64748b !important;
        margin-bottom: 6px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.4px !important;
        line-height: 1.25 !important;
    }
    .summary-print-val {
        font-size: 13.5pt !important;
        font-weight: 800 !important;
        line-height: 1.2 !important;
    }
    th:last-child, td:last-child { display: none !important; }
</style>
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
