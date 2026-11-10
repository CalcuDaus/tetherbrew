<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_owner();
if ($_SERVER['REQUEST_METHOD']!=='POST') redirect('/branches'); csrf_verify();
$id=$_POST['id'];

try {
    $pdo->beginTransaction();

    // 1. Journals
    $pdo->prepare("DELETE FROM journals WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM journal_categories WHERE branch_id=?")->execute([$id]);

    // 2. Rider Finances & Sales & Payroll
    $pdo->prepare("DELETE FROM rider_finances WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM rider_daily_sales WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM payroll_records WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM rider_sales_journal_confirmations WHERE branch_id=?")->execute([$id]);

    // 3. Office Kasbons
    $pdo->prepare("DELETE FROM office_kasbons WHERE branch_id=?")->execute([$id]);

    // 4. Production & Spoiled
    $pdo->prepare("DELETE FROM daily_productions WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM spoiled_products WHERE branch_id=?")->execute([$id]);

    // 5. Transactions & Inventories
    $pdo->prepare("DELETE FROM transactions WHERE branch_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM inventories WHERE branch_id=?")->execute([$id]);

    // 6. Carts
    $pdo->prepare("DELETE FROM carts WHERE branch_id=?")->execute([$id]);

    // 7. App Settings
    $pdo->prepare("DELETE FROM app_settings WHERE branch_id=?")->execute([$id]);

    // 8. Users
    $pdo->prepare("DELETE FROM users WHERE branch_id=?")->execute([$id]);

    // 9. Branch
    $pdo->prepare("DELETE FROM branches WHERE id=?")->execute([$id]);

    // If active branch session was this branch, clear it
    if (activeBranchId() == $id) {
        unset($_SESSION['active_branch_id']);
    }

    $pdo->commit();
    redirect_with('/branches','success','Cabang beserta seluruh datanya berhasil dihapus paksa!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with('/branches','error','Gagal menghapus cabang: ' . $e->getMessage());
}
