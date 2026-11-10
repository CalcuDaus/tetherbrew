<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$type = $_POST['type'] ?? 'kasbon';
$redirectUrl = $type === 'uang_makan' ? '/rider-finances/uang-makan' : '/rider-finances/kasbon';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($redirectUrl); csrf_verify();
$branchId = activeBranchId(); $date = $_POST['date']; $riderId = $_POST['rider_id']; $amount = $_POST['amount']; $notes = $_POST['notes'];
$reference_cups = isset($_POST['reference_cups']) && $_POST['reference_cups'] !== '' ? (int)$_POST['reference_cups'] : null;

$rider = $pdo->prepare("SELECT name FROM users WHERE id=?"); $rider->execute([$riderId]); $riderName = $rider->fetchColumn();

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO rider_finances (branch_id, admin_id, rider_id, date, type, amount, reference_cups, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$branchId, auth_id(), $riderId, $date, $type, $amount, $reference_cups, $notes]);

    $pdo->commit();
    redirect_with($redirectUrl, 'success', 'Transaksi berhasil disimpan.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with($redirectUrl, 'error', 'Gagal: '.$e->getMessage());
}
