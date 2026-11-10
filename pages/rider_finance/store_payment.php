<?php
require_once __DIR__ . '/../../core/bootstrap.php'; 
require_auth(); 
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/rider-finances/kasbon');
}

csrf_verify();

$branchId = activeBranchId();
$date = $_POST['date'];
$riderId = $_POST['rider_id'];
$amount = (float)$_POST['amount'];
$notes = trim($_POST['notes'] ?? '');

$redirectUrl = '/rider-finances/kasbon';

if ($amount <= 0) {
    redirect_with($redirectUrl, 'error', 'Nominal pembayaran harus lebih dari 0.');
}

// Convert amount to negative for payment
$negAmount = -$amount;

$rider = $pdo->prepare("SELECT name FROM users WHERE id=?");
$rider->execute([$riderId]);
$riderName = $rider->fetchColumn();

try {
    $pdo->beginTransaction();

    // 1. Insert negative amount to rider_finances
    $pdo->prepare("INSERT INTO rider_finances (branch_id, admin_id, rider_id, date, type, amount, reference_cups, notes, created_at, updated_at) VALUES (?,?,?,?,'kasbon',?,NULL,?,NOW(),NOW())")
        ->execute([$branchId, auth_id(), $riderId, $date, $negAmount, $notes ? "Pembayaran: {$notes}" : "Pembayaran Kasbon via Cash"]);

    $pdo->commit();
    redirect_with($redirectUrl, 'success', 'Pembayaran kasbon berhasil dicatat.');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with($redirectUrl, 'error', 'Gagal: '.$e->getMessage());
}
