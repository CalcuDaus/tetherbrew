<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/payroll/history'); csrf_verify();
$id = $_POST['id']; $branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT p.*, r.name as rider_name FROM payroll_records p JOIN users r ON p.rider_id=r.id WHERE p.id=? AND p.branch_id=?");
$stmt->execute([$id, $branchId]); $p = $stmt->fetch();
if(!$p || $p['status']!=='confirmed') redirect_with("/payroll/show?id={$id}", 'error', 'Data tidak valid.');
if((time() - strtotime($p['confirmed_at'])) > (2 * 24 * 3600)) redirect_with("/payroll/show?id={$id}", 'error', 'Hanya dapat membatalkan maksimal 2 hari setelah konfirmasi.');

try {
    $pdo->beginTransaction();
    $periodeLabel = date('d/m/Y',strtotime($p['period_start'])).' - '.date('d/m/Y',strtotime($p['period_end']));
    $descs = [
        "Gaji rider {$p['rider_name']} periode {$periodeLabel}",
        "Pembayaran kasbon rider {$p['rider_name']} periode {$periodeLabel}",
        "Pembayaran minus rider {$p['rider_name']} periode {$periodeLabel}",
        "Uang makan rider {$p['rider_name']} periode {$periodeLabel}",
        "Kelebihan uang makan rider {$p['rider_name']} periode {$periodeLabel}"
    ];
    
    $in = str_repeat('?,', count($descs) - 1) . '?';
    $delJ = $pdo->prepare("DELETE FROM journals WHERE branch_id=? AND DATE(date)=? AND description IN ($in)");
    $params = array_merge([$branchId, date('Y-m-d', strtotime($p['confirmed_at']))], $descs);
    $delJ->execute($params);

    $deducted = $p['minus_deducted'] + $p['minus_paid_cash'];
    if($deducted > 0) {
        $upStmt = $pdo->prepare("SELECT * FROM rider_daily_sales WHERE rider_id=? AND minus_paid > 0 ORDER BY date DESC");
        $upStmt->execute([$p['rider_id']]);
        foreach($upStmt->fetchAll() as $sale) {
            if($deducted <= 0) break;
            if($sale['minus_paid'] <= $deducted) {
                $deducted -= $sale['minus_paid'];
                $pdo->prepare("UPDATE rider_daily_sales SET minus_paid=0, minus_status='unpaid' WHERE id=?")->execute([$sale['id']]);
            } else {
                $pdo->prepare("UPDATE rider_daily_sales SET minus_paid=minus_paid-?, minus_status='partial' WHERE id=?")->execute([$deducted, $sale['id']]);
                $deducted = 0;
            }
        }
    }

    $pdo->prepare("UPDATE payroll_records SET status='draft', confirmed_at=NULL, confirmed_by=NULL WHERE id=?")->execute([$id]);

    $pdo->commit();
    redirect_with("/payroll/show?id={$id}", 'success', 'Konfirmasi dibatalkan. Jurnal telah dihapus.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with("/payroll/show?id={$id}", 'error', 'Gagal: '.$e->getMessage());
}
