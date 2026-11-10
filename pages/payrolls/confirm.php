<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/payroll/history'); csrf_verify();
$id = $_POST['id']; $branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT p.*, r.name as rider_name FROM payroll_records p JOIN users r ON p.rider_id=r.id WHERE p.id=? AND p.branch_id=?");
$stmt->execute([$id, $branchId]); $p = $stmt->fetch();
if(!$p || $p['status']==='confirmed') redirect_with("/payroll/show?id={$id}", 'error', 'Data tidak valid atau sudah dikonfirmasi.');

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE payroll_records SET status='confirmed', confirmed_at=NOW(), confirmed_by=? WHERE id=?")->execute([auth_id(), $id]);

    $getCat = function($name) use ($pdo) {
        $cStmt=$pdo->prepare("SELECT id FROM journal_categories WHERE name=?"); $cStmt->execute([$name]); $catId=$cStmt->fetchColumn();
        if(!$catId){$pdo->prepare("INSERT INTO journal_categories(name,created_at,updated_at) VALUES (?,NOW(),NOW())")->execute([$name]); return $pdo->lastInsertId();}
        return $catId;
    };

    $date = date('Y-m-d'); $periodeLabel = date('d/m/Y',strtotime($p['period_start'])).' - '.date('d/m/Y',strtotime($p['period_end']));
    $descParams = [$branchId, $date, auth_id()];
    $insertJ = "INSERT INTO journals (branch_id,date,created_by,created_at,updated_at,journal_category_id,type,amount,description) VALUES (?,?,?,NOW(),NOW(),?,?,?,?)";

    // Gaji -> Kredit
    $pdo->prepare($insertJ)->execute([...$descParams, $getCat('Gaji Rider'), 'credit', $p['gross_income'], "Gaji rider {$p['rider_name']} periode {$periodeLabel}"]);
    
    // Minus -> Debit
    if($p['minus_deducted'] > 0) $pdo->prepare($insertJ)->execute([...$descParams, $getCat('Pembayaran Minus Rider'), 'debit', $p['minus_deducted'], "Pembayaran minus rider {$p['rider_name']} periode {$periodeLabel}"]);

    // Minus Distribution (deducted from sales + paid cash)
    $deducted = $p['minus_deducted'] + $p['minus_paid_cash'];
    if($deducted > 0) {
        $upStmt = $pdo->prepare("SELECT * FROM rider_daily_sales WHERE rider_id=? AND minus_status IN ('unpaid','partial') ORDER BY date ASC");
        $upStmt->execute([$p['rider_id']]);
        foreach($upStmt->fetchAll() as $sale) {
            if($deducted <= 0) break;
            $rem = $sale['minus_amount'] - $sale['minus_paid'];
            if($rem <= $deducted) {
                $pdo->prepare("UPDATE rider_daily_sales SET minus_paid=minus_amount, minus_status='paid' WHERE id=?")->execute([$sale['id']]);
                $deducted -= $rem;
            } else {
                $pdo->prepare("UPDATE rider_daily_sales SET minus_paid=minus_paid+?, minus_status='partial' WHERE id=?")->execute([$deducted, $sale['id']]);
                $deducted = 0;
            }
        }
    }

    $pdo->commit();
    redirect_with("/payroll/show?id={$id}", 'success', 'Slip gaji berhasil dikonfirmasi dan dicatat ke Jurnal.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with("/payroll/show?id={$id}", 'error', 'Gagal: '.$e->getMessage());
}
