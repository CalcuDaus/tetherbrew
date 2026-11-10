<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_admin_or_owner();

if (!isset($_GET['id'])) {
    redirect('/riders');
}

$id = $_GET['id'];
$branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND branch_id = ? AND role = 'rider'");
$stmt->execute([$id, $branchId]);
$rider = $stmt->fetch();

if (!$rider || !$rider['is_active']) {
    redirect_with('/riders', 'error', 'Rider tidak ditemukan atau sudah resign.');
}

// Hitung total kasbon
$tkStmt = $pdo->prepare("SELECT SUM(amount) FROM rider_finances WHERE rider_id=? AND type='kasbon'");
$tkStmt->execute([$id]); 
$totalKasbonAllTime = (int)$tkStmt->fetchColumn();

$tkdStmt = $pdo->prepare("SELECT SUM(kasbon_deducted) FROM payroll_records WHERE rider_id=? AND status='confirmed'");
$tkdStmt->execute([$id]); 
$totalKasbonDeducted = (int)$tkdStmt->fetchColumn();

$outstandingKasbon = max(0, $totalKasbonAllTime - $totalKasbonDeducted);

// Hitung total minus
$tmStmt = $pdo->prepare("SELECT SUM(minus_amount) as total_minus, SUM(minus_paid) as total_paid FROM rider_daily_sales WHERE rider_id=? AND minus_amount > 0 AND minus_status != 'paid'");
$tmStmt->execute([$id]); 
$minusData = $tmStmt->fetch();
$outstandingMinus = max(0, ($minusData['total_minus'] ?? 0) - ($minusData['total_paid'] ?? 0));

$pageTitle = 'Proses Resign Rider';
ob_start();
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Proses Resign: <?= e($rider['name']) ?></h3>
    </div>
    <div class="card-body">
        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="margin: 0; color: #b45309; font-weight: 500; font-size: 0.95rem;">
                <strong>Perhatian:</strong> Memproses resign akan menonaktifkan akun rider ini. Sisa hutang kasbon dan minus akan diputihkan (write-off) secara sistem agar tidak muncul lagi sebagai tagihan, namun <strong>tidak</strong> akan dimasukkan ke Jurnal Umum (kerugian).
            </p>
        </div>

        <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-secondary); font-weight: 500;">Nama Rider</td>
                    <td style="padding: 12px 0; text-align: right; font-weight: 600; color: var(--text-primary);"><?= e($rider['name']) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-secondary); font-weight: 500;">Sisa Kasbon Belum Lunas</td>
                    <td style="padding: 12px 0; text-align: right; font-weight: 600; color: #ef4444;"><?= formatRupiah($outstandingKasbon) ?></td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 0; color: var(--text-secondary); font-weight: 500;">Sisa Minus Belum Dibayar</td>
                    <td style="padding: 12px 0; text-align: right; font-weight: 600; color: #f59e0b;"><?= formatRupiah($outstandingMinus) ?></td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; color: var(--text-primary); font-weight: 600; font-size: 1.1rem;">Total Hutang (Write-off)</td>
                    <td style="padding: 12px 0; text-align: right; font-weight: 700; color: #dc2626; font-size: 1.1rem;">
                        <?= formatRupiah($outstandingKasbon + $outstandingMinus) ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <form method="POST" action="<?= url('/riders/resign-process') ?>" onsubmit="return confirm('Apakah Anda yakin ingin memproses resign rider ini? Tindakan ini tidak dapat dibatalkan.');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $rider['id'] ?>">
            <input type="hidden" name="outstanding_kasbon" value="<?= $outstandingKasbon ?>">
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <a href="<?= url('/riders') ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444; color: white;">
                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Konfirmasi Resign
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../layouts/app.php'; 
?>
