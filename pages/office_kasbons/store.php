<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/office-kasbon'); csrf_verify();

$branchId = activeBranchId(); $date = $_POST['date']; $name = $_POST['name']; $amount = $_POST['amount']; $notes = $_POST['notes'];

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO office_kasbons (branch_id, admin_id, name, date, amount, status, notes, created_at, updated_at) VALUES (?,?,?,?,?,'unpaid',?,NOW(),NOW())")
        ->execute([$branchId, auth_id(), $name, $date, $amount, $notes]);
    
    $pdo->commit();
    redirect_with('/admin/office-kasbon', 'success', 'Data Kasbon berhasil disimpan.');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/admin/office-kasbon', 'error', 'Gagal: '.$e->getMessage());
}
