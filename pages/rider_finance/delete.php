<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/rider_finance'); csrf_verify();
$id = $_POST['id']; $branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT f.*, r.name as rider_name FROM rider_finances f JOIN users r ON f.rider_id=r.id WHERE f.id=? AND f.branch_id=?");
$stmt->execute([$id, $branchId]); $f = $stmt->fetch();
if(!$f) {
    redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/rider-finances/kasbon');
}

$redirectUrl = $f['type'] === 'uang_makan' ? '/rider-finances/uang-makan' : '/rider-finances/kasbon';

try {
    $pdo->beginTransaction();
    
    // Cancel related journal
    $catName = $f['type'] === 'kasbon' ? 'Kasbon Rider' : 'Uang Makan Rider';
    $catStmt = $pdo->prepare("SELECT id FROM journal_categories WHERE name=?"); $catStmt->execute([$catName]); $catId = $catStmt->fetchColumn();
    if($catId) {
        $descPrefix = "{$catName}: {$f['rider_name']}%";
        $pdo->prepare("DELETE FROM journals WHERE branch_id=? AND journal_category_id=? AND type='credit' AND amount=? AND description LIKE ? AND DATE(date)=?")
            ->execute([$branchId, $catId, $f['amount'], $descPrefix, $f['date']]);
    }

    $pdo->prepare("DELETE FROM rider_finances WHERE id=?")->execute([$id]);
    $pdo->commit();
    redirect_with($redirectUrl, 'success', 'Transaksi berhasil dihapus beserta jurnalnya.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with($redirectUrl, 'error', 'Gagal: '.$e->getMessage());
}
