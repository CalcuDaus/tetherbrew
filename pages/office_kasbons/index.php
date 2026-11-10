<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$branchId = activeBranchId();
$start = $_GET['start_date'] ?? date('Y-m-01'); $end = $_GET['end_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

$query = "SELECT k.*, u.name as admin_name FROM office_kasbons k LEFT JOIN users u ON k.admin_id = u.id WHERE k.branch_id = ?";
$params = [$branchId];
if($search) { $query .= " AND k.name LIKE ?"; $params[] = "%{$search}%"; }
if($start) { $query .= " AND DATE(k.date) >= ?"; $params[] = $start; }
if($end) { $query .= " AND DATE(k.date) <= ?"; $params[] = $end; }

$sumQuery = "SELECT SUM(amount) as total_kasbon, SUM(paid_amount) as total_paid FROM office_kasbons k WHERE k.branch_id = ?";
$sumParams = [$branchId];
if($search) { $sumQuery .= " AND k.name LIKE ?"; $sumParams[] = "%{$search}%"; }
if($start) { $sumQuery .= " AND DATE(k.date) >= ?"; $sumParams[] = $start; }
if($end) { $sumQuery .= " AND DATE(k.date) <= ?"; $sumParams[] = $end; }
$sumStmt = $pdo->prepare($sumQuery); $sumStmt->execute($sumParams); $totals = $sumStmt->fetch();

$query .= " ORDER BY k.date DESC, k.id DESC";
$isPrint = isset($_GET['print']) && $_GET['print'] == 1;

if ($isPrint) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $kasbons = $stmt->fetchAll();
    $pagination = null;
} else {
    $pagination = paginate($pdo, $query, $params, 10);
    $kasbons = $pagination['data'];
}

$pageTitle = 'Kasbon Office';
$printParams = $_GET;
$printParams['print'] = 1;
$printUrl = '?' . http_build_query($printParams);

ob_start();

$totalKasbon = $totals['total_kasbon'] ?? 0;
$totalTerbayar = $totals['total_paid'] ?? 0;
$totalSisa = $totalKasbon - $totalTerbayar;
?>
<div id="print-area">
    <div class="print-title print-only" style="display: none;">
        <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;">
        <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Tether Brew</h2>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">Laporan Kasbon Office — Dicetak: <?= date('d F Y') ?></p>
    </div>

    <div class="print-summary-row" style="display: none; margin-bottom: 20px;">
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
    </div>
    
    <div class="stats-grid print-hide" style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #ef4444; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #ef4444;">Rp <?= number_format($totalKasbon, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Total Kasbon</div>
        </div>
        
        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #10b981; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #10b981;">Rp <?= number_format($totalTerbayar, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Total Terbayar</div>
        </div>

        <div class="stat-card-animated" style="position: relative; overflow: hidden; z-index: 1;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #f59e0b; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="12" y1="12" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2; color: #f59e0b;">Rp <?= number_format($totalSisa, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Total Sisa</div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card print-hide" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" action="<?= url('/admin/office-kasbon') ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                    <label class="form-label" style="margin-bottom: 5px; display: block; font-weight: 600;">Mulai Tanggal</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="start_date" id="start_date" class="form-input datepicker" value="<?= e($start) ?>" placeholder="Pilih tanggal...">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>
                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                    <label class="form-label" style="margin-bottom: 5px; display: block; font-weight: 600;">Sampai Tanggal</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="end_date" id="end_date" class="form-input datepicker" value="<?= e($end) ?>" placeholder="Pilih tanggal...">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                    <label class="form-label" style="margin-bottom: 5px; display: block; font-weight: 600;">Pencarian</label>
                    <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Keterangan kasbon...">
                </div>
                <div class="form-group" style="display: flex; gap: 8px; margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary" style="height: 42px; background: #22c55e; border-color: #22c55e;">Filter</button>
                    <a href="<?= url('/admin/office-kasbon') ?>" class="btn btn-secondary" style="height: 42px; display: flex; align-items: center; justify-content: center; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary); text-decoration: none;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Header & Action Toolbar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;" class="print-hide">
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Daftar Kasbon Office</h3>
        <div style="display: flex; gap: 8px;">
            <form id="deleteAll-officeKasbons" method="POST" action="<?= url('/admin/office-kasbon/delete-all') ?>" style="margin:0;">
                <?= csrf_field() ?>
                <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none; height: 36px; padding: 0 14px; border-radius:8px; font-weight:600; display:flex; align-items:center; gap:6px;" onclick="confirmDeleteAll('deleteAll-officeKasbons')">
                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Hapus Semua
                </button>
            </form>
            <a href="<?= $printUrl ?>" target="_blank" class="btn btn-secondary btn-sm" style="display:flex; align-items:center; gap:0.5rem; height: 36px; padding: 0 14px; border-radius:8px; text-decoration: none;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Print
            </a>
            <button type="button" onclick="openFinanceModal()" class="btn btn-primary flex-center btn-sm" style="height: 36px; padding: 0 14px; border-radius:8px; display: flex; align-items: center; justify-content: center; gap:0.5rem; white-space: nowrap; background: #22c55e; border-color: #22c55e; cursor: pointer;">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                </svg> Input Kasbon
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-container no-search">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Tanggal</th>
                            <th style="padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Keterangan</th>
                            <th style="padding: 12px 16px; text-align: right; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Kasbon</th>
                            <th style="padding: 12px 16px; text-align: right; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Sisa Tagihan</th>
                            <th style="padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Status</th>
                            <th style="padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Catatan</th>
                            <th style="padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($kasbons)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px;">Belum ada riwayat kasbon.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($kasbons as $finance): $sisa = $finance['amount'] - $finance['paid_amount']; ?>
                                <tr class="kasbon-row">
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; color: var(--text-secondary); font-weight: 500;"><?= date('d/m/Y', strtotime($finance['date'])) ?></td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; color: var(--text-secondary);"><?= e($finance['name']) ?></td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); text-align: right; font-weight: 600; color: #ef4444; font-size: 0.875rem;">Rp <?= number_format($finance['amount'], 0, ',', '.') ?></td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); text-align: right; font-weight: 600; color: #f59e0b; font-size: 0.875rem;">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color);">
                                        <?php if($finance['status'] === 'paid'): ?>
                                            <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; min-width: 80px;">LUNAS</span>
                                        <?php elseif($finance['status'] === 'partial'): ?>
                                            <span style="background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; min-width: 80px;">SEBAGIAN</span>
                                        <?php else: ?>
                                            <span style="background: #dbeafe; color: #1e3a8a; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; min-width: 80px;">BELUM LUNAS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; color: var(--text-secondary);">
                                        <?= e($finance['notes'] ?: '-') ?>
                                    </td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color);">
                                        <div style="display: flex; gap: 5px;">
                                            <?php if($finance['status'] !== 'paid'): ?>
                                                <button type="button" class="btn btn-sm" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: none; padding: 5px 10px; font-weight: 500; cursor: pointer; font-size: 0.75rem;" onclick="openPaymentModal(<?= $finance['id'] ?>, '<?= addslashes(e($finance['name'])) ?>', <?= $sisa ?>)">Bayar</button>
                                            <?php endif; ?>
                                            <form method="POST" action="<?= url('/admin/office-kasbon/delete') ?>" data-confirm="Apakah Anda yakin ingin menghapus data kasbon ini? Semua history jurnal terkait kasbon ini akan terhapus juga." style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $finance['id'] ?>">
                                                <button type="submit" class="btn btn-sm" style="color: #ef4444; background: transparent; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 5px 10px; display: flex; align-items: center; justify-content: center; gap: 4px; cursor: pointer; font-size: 0.75rem;">
                                                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if(!empty($kasbons)): ?>
            <div class="pagination-container print-hide" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 15px 0 0 0; border-top: 1px solid var(--border-color);">
                <?= render_pagination($pagination) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div> <!-- End #print-area -->

<!-- Modal Input Kasbon -->
<div id="financeModal" class="modal-overlay-animate" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card modal-content-animate" style="width: 100%; max-width: 500px; margin: 20px; box-shadow: var(--shadow-lg); border-radius: 12px; overflow: hidden;">
        <div class="card-header" style="background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 16px 20px;">
            <h3 class="card-title" style="margin:0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Input Kasbon Office</h3>
        </div>
        <div class="card-body" style="padding: 20px;">
            <form action="<?= url('/admin/office-kasbon/store') ?>" method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Keterangan / Nama Staff</label>
                    <input type="text" name="name" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" required placeholder="Contoh: Admin Ucok, Beli air minum, dsb...">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Tanggal</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="date" id="date" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" value="<?= date('Y-m-d') ?>" required placeholder="Pilih tanggal...">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Jumlah Uang (Rp)</label>
                    <input type="number" name="amount" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" min="1" required placeholder="0">
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Catatan Tambahan</label>
                    <input type="text" name="notes" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" placeholder="Opsional...">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeFinanceModal()" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background: var(--bg-card); color: var(--text-primary); cursor: pointer; font-weight: 500;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; background: #22c55e; color: white; cursor: pointer; font-weight: 500;">Simpan Kasbon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Payment Kasbon -->
<div id="paymentModal" class="modal-overlay-animate" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card modal-content-animate" style="width: 100%; max-width: 500px; margin: 20px; box-shadow: var(--shadow-lg); border-radius: 12px; overflow: hidden;">
        <div class="card-header" style="background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 16px 20px;">
            <h3 class="card-title" style="margin:0; font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">Bayar Kasbon Office</h3>
        </div>
        <div class="card-body" style="padding: 20px;">
            <form id="paymentForm" action="<?= url('/admin/office-kasbon/payment') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="payment_id">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Pembayaran Untuk</label>
                    <input type="text" id="payment_name" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background: var(--bg-secondary); color: var(--text-secondary);" disabled>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Tanggal Bayar</label>
                    <div class="flatpickr-input-container" style="position: relative;">
                        <input type="text" name="date" id="payment_date" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" value="<?= date('Y-m-d') ?>" required placeholder="Pilih tanggal...">
                        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #94a3b8;" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Jumlah Bayar (Rp) - <small style="color: var(--text-muted); font-weight: 400;">Sisa: <span id="payment_sisa_text">0</span></small></label>
                    <input type="number" name="amount" id="payment_amount" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" min="1" required placeholder="0">
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.875rem;">Catatan</label>
                    <input type="text" name="notes" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.5rem;" placeholder="Opsional...">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closePaymentModal()" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background: var(--bg-card); color: var(--text-primary); cursor: pointer; font-weight: 500;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border: none; border-radius: 0.5rem; background: #3b82f6; color: white; cursor: pointer; font-weight: 500;">Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
            </form>
        </div>
    </div>
</div>

    </div>
</div>

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

        .topbar, .sidebar, .btn, .print-hide, .modal-overlay-animate, #financeModal, #paymentModal, form[method="POST"] { display: none !important; }

        .card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; break-inside: avoid; margin-bottom: 20px !important; border-radius: 8px !important; }
        .card-header { background: transparent !important; border-bottom: 1px solid #cbd5e1 !important; padding: 12px 16px !important; }
        .card-header.print-hide { display: none !important; }
        .card-body { padding: 15px 16px !important; }

        table { width: 100% !important; border-collapse: collapse !important; }
        .kasbon-row { display: table-row !important; }
        th { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1 !important; padding: 10px 8px !important; font-weight: bold !important; font-size: 9.5pt !important; }
        td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; font-size: 10pt !important; }

        /* Hide action column */
        th:last-child, td:last-child { display: none !important; }
    }

    /* Card animated and absolute bg icon effects */
    .stat-card-animated {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        min-height: 140px;
    }
    .light-theme .stat-card-animated { background: white; }
    html:not(.light-theme) .stat-card-animated { background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: none; }
    
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
    .stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
    }
    html:not(.light-theme) .stat-label { color: #94a3b8; }

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
        display: flex;
    }

    .modal-content-animate {
        animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        background: white;
    }
    html:not(.light-theme) .modal-content-animate {
        background: var(--bg-card);
    }
</style>

<script>
    function openFinanceModal() {
        document.getElementById('financeModal').style.display = 'flex';
    }

    function closeFinanceModal() {
        document.getElementById('financeModal').style.display = 'none';
    }

    function openPaymentModal(id, name, sisa) {
        document.getElementById('payment_id').value = id;
        document.getElementById('payment_name').value = name;
        document.getElementById('payment_amount').max = sisa;
        document.getElementById('payment_amount').value = sisa; // Default isi semua sisa
        document.getElementById('payment_sisa_text').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisa);
        document.getElementById('paymentModal').style.display = 'flex';
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    window.addEventListener('click', function(event) {
        const financeModal = document.getElementById('financeModal');
        const paymentModal = document.getElementById('paymentModal');
        
        if (event.target == financeModal) {
            closeFinanceModal();
        }
        if (event.target == paymentModal) {
            closePaymentModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr("#date", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j F Y",
                allowInput: true,
                disableMobile: "true"
            });
            
            flatpickr("#payment_date", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j F Y",
                allowInput: true,
                disableMobile: "true"
            });
            
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j F Y",
                allowInput: true,
                disableMobile: "true"
            });
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
