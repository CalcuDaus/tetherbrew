<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/productions'); csrf_verify();
$date = $_POST['date']; $items = $_POST['items'] ?? []; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT id FROM daily_productions WHERE branch_id = ? AND date = ?"); $check->execute([$branchId, $date]);
if($check->fetch()) redirect_with('/admin/productions/create', 'error', 'Data produksi untuk tanggal tersebut sudah ada! Edit data yang sudah ada.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO daily_productions (branch_id, user_id, date, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    $stmt->execute([$branchId, auth_id(), $date]); $productionId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO daily_production_items (daily_production_id, product_id, quantity_produced, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    foreach ($items as $productId => $qty) {
        if((int)$qty > 0) $itemStmt->execute([$productionId, $productId, (int)$qty]);
    }
    $pdo->commit();
    redirect_with('/admin/productions', 'success', 'Data produksi berhasil disimpan!');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with('/admin/productions/create', 'error', 'Gagal: '.$e->getMessage());
}
