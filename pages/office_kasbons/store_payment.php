<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/office-kasbon'); csrf_verify();

$branchId = activeBranchId(); $id = $_POST['id']; $date = $_POST['date']; $amount = $_POST['amount']; $notes = $_POST['notes'];

$stmt = $pdo->prepare("SELECT * FROM office_kasbons WHERE id = ? AND branch_id = ?"); $stmt->execute([$id, $branchId]); $k = $stmt->fetch();
if(!$k) redirect('/admin/office-kasbon');

$sisa = $k['amount'] - $k['paid_amount'];
if($amount > $sisa) redirect_with('/admin/office-kasbon', 'error', 'Jumlah bayar melebihi sisa kasbon!');

$newPaid = $k['paid_amount'] + $amount;
$status = $newPaid >= $k['amount'] ? 'paid' : 'partial';

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO office_kasbon_payments (office_kasbon_id, admin_id, date, amount, notes, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())")
        ->execute([$id, auth_id(), $date, $amount, $notes]);
        
    $pdo->prepare("UPDATE office_kasbons SET paid_amount = ?, status = ?, updated_at = NOW() WHERE id = ?")->execute([$newPaid, $status, $id]);
    
    $pdo->commit();
    redirect_with('/admin/office-kasbon', 'success', 'Pembayaran berhasil disimpan.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/admin/office-kasbon', 'error', 'Gagal: '.$e->getMessage());
}
