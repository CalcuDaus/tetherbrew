<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$id = $_GET['id'] ?? null; if(!$id) redirect('/payroll/history');

$stmt = $pdo->prepare("SELECT p.*, r.name as rider_name, a.name as admin_name, c.name as conf_name FROM payroll_records p JOIN users r ON p.rider_id=r.id JOIN users a ON p.admin_id=a.id LEFT JOIN users c ON p.confirmed_by=c.id WHERE p.id=? AND p.branch_id=?");
$stmt->execute([$id, activeBranchId()]); $p = $stmt->fetch();
if(!$p) redirect('/payroll/history');

$pageTitle = 'Detail Slip Gaji';
ob_start();
?>
<div class="card" style="max-width:800px; margin:0 auto;" id="printableArea">
    <div class="card-header print-hide" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="card-title">Slip Gaji: <?= e($p['rider_name']) ?></div>
        <div>
            <?php if($p['status']==='draft'): ?><span class="badge badge-warning">DRAFT</span>
            <?php else: ?><span class="badge badge-success">CONFIRMED</span><?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="payslip-container" style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: 'Poppins', sans-serif;">
            <!-- Header Logo & Title -->
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px dashed var(--border-color); padding-bottom: 20px;">
                <img src="<?= base_url('assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;" onerror="this.src='<?= base_url('assets/images/tether-icon-head.webp') ?>'; this.onerror=null;">
                <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">TETHER BREW</h2>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">
                    Fee Mitra Rider (<?= ucfirst($p['type']) ?>)
                </p>
                <p style="margin: 5px 0 0; color: var(--text-primary); font-weight: 600;">
                    Periode: <?= date('d/m/Y', strtotime($p['period_start'])) ?> - <?= date('d/m/Y', strtotime($p['period_end'])) ?>
                </p>
            </div>

            <!-- Metadata Info -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                <div>
                    <p style="margin: 0 0 5px;"><strong>Nama Rider:</strong> <?= e($p['rider_name']) ?></p>
                    <p style="margin: 0 0 5px;"><strong>ID Rider:</strong> #<?= str_pad($p['rider_id'], 4, '0', STR_PAD_LEFT) ?></p>
                    <p style="margin: 0 0 5px;"><strong>Dibuat Oleh:</strong> <?= e($p['admin_name']) ?></p>
                    <?php if($p['status']==='confirmed'): ?>
                        <p style="margin: 0 0 5px;"><strong>Dikonfirmasi Oleh:</strong> <?= e($p['conf_name']) ?> <small style="color: #64748b;">(<?= date('d/m/Y H:i', strtotime($p['confirmed_at'])) ?>)</small></p>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0 0 5px;"><strong>Total Cup Terjual:</strong> <?= number_format($p['total_cups'], 0, ',', '.') ?> Cup</p>
                    <p style="margin: 0 0 5px;"><strong>Status:</strong> 
                        <?php if($p['status']==='draft'): ?>
                            <span style="color: #d97706; font-weight: 700;">DRAFT</span>
                        <?php else: ?>
                            <span style="color: #16a34a; font-weight: 700;">CONFIRMED</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Table Breakdown -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background: var(--bg-secondary); border-top: 2px solid var(--border-color); border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Keterangan</th>
                        <th style="padding: 12px; text-align: right; font-weight: 600;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">Gaji Bonus Penjualan <small>(<?= number_format($p['total_cups'], 0, ',', '.') ?> Cup)</small></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right;">Rp <?= number_format($p['gross_income'], 0, ',', '.') ?></td>
                    </tr>

                    <?php if($p['uang_makan_adjustment'] > 0): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #22c55e;">Sisa Uang Makan</td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #22c55e;">+ Rp <?= number_format($p['uang_makan_adjustment'], 0, ',', '.') ?></td>
                        </tr>
                    <?php elseif($p['uang_makan_adjustment'] < 0): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Kelebihan Uang Makan</td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format(abs($p['uang_makan_adjustment']), 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Kasbon</td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format($p['kasbon_deducted'], 0, ',', '.') ?></td>
                    </tr>
                    <?php if($p['kasbon_paid_cash'] > 0): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #64748b;">Pembayaran Kasbon (via Cash) <small>(Tidak memotong gaji)</small></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #64748b;">Rp <?= number_format($p['kasbon_paid_cash'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #ef4444;">Potongan Minus Penjualan</td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #ef4444;">- Rp <?= number_format($p['minus_deducted'], 0, ',', '.') ?></td>
                    </tr>
                    <?php if($p['minus_paid_cash'] > 0): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); color: #64748b;">Pembayaran Minus (via Cash) <small>(Tidak memotong gaji)</small></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-color); text-align: right; color: #64748b;">Rp <?= number_format($p['minus_paid_cash'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="background: var(--bg-secondary);">
                        <td style="padding: 15px 12px; font-weight: 700; font-size: 16px;">TAKE HOME PAY</td>
                        <td style="padding: 15px 12px; text-align: right; font-weight: 700; font-size: 18px; color: <?= $p['net_income'] >= 0 ? 'var(--text-primary)' : '#ef4444' ?>;">Rp <?= number_format($p['net_income'], 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if($p['notes']): ?>
                <div style="background: #fefce8; border: 1px solid #fef08a; padding: 12px; border-radius: 8px; margin-bottom: 30px;">
                    <p style="margin: 0 0 5px; font-size: 0.85rem; font-weight: 700; color: #854d0e;">Catatan:</p>
                    <p style="margin: 0; font-size: 0.85rem; color: #854d0e;"><?= e($p['notes']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Signature Section -->
            <div style="display: flex; justify-content: space-between; margin-top: 50px;">
                <div style="text-align: center;">
                    <p style="margin-bottom: 60px;">Penerima (Rider),</p>
                    <p style="font-weight: 600;">( <?= e($p['rider_name']) ?> )</p>
                </div>
                <div style="text-align: center;">
                    <p style="margin-bottom: 60px;">Mengetahui (Admin),</p>
                    <p style="font-weight: 600;">( <?= e($p['admin_name']) ?> )</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="max-width:800px; margin:20px auto; display:flex; justify-content:space-between;" class="no-print">
    <a href="<?= url('/payroll/history') ?>" class="btn btn-secondary">
        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg> Kembali
    </a>
    <div style="display:flex; gap:10px;">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg> Cetak
        </button>
        <?php if($p['status']==='draft'): ?>
            <form method="POST" action="<?= url('/payroll/confirm') ?>" onsubmit="return confirm('Konfirmasi dan catat ke Jurnal?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-primary">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg> Konfirmasi Slip Gaji
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= url('/payroll/rollback') ?>" onsubmit="return confirm('Batal konfirmasi dan hapus dari Jurnal?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg> Batal Konfirmasi
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<style>
@media print {
    .print-hide, .no-print, .topbar, .sidebar, nav, header, footer { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    .page-content { padding: 0 !important; }
    .card { box-shadow: none !important; border: none !important; margin: 0 !important; }
    .card-header { display: none !important; }
    .card-body { padding: 0 !important; }
}
</style>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
