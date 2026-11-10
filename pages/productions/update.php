<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/productions'); csrf_verify();
$id = $_POST['id']; $date = $_POST['date']; $items = $_POST['items'] ?? []; $branchId = activeBranchId();

$check = $pdo->prepare("SELECT id FROM daily_productions WHERE branch_id = ? AND date = ? AND id != ?"); $check->execute([$branchId, $date, $id]);
if($check->fetch()) redirect_with("/admin/productions/edit?id={$id}", 'error', 'Data produksi untuk tanggal tersebut sudah ada!');

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE daily_productions SET date = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?")->execute([$date, $id, $branchId]);
    $pdo->prepare("DELETE FROM daily_production_items WHERE daily_production_id = ?")->execute([$id]);

    $itemStmt = $pdo->prepare("INSERT INTO daily_production_items (daily_production_id, product_id, quantity_produced, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
    foreach ($items as $productId => $qty) {
        if((int)$qty > 0) $itemStmt->execute([$id, $productId, (int)$qty]);
    }
    $pdo->commit();
    redirect_with('/admin/productions', 'success', 'Data produksi berhasil diperbarui!');
} catch (Exception $e) {
    $pdo->rollBack(); redirect_with("/admin/productions/edit?id={$id}", 'error', 'Gagal: '.$e->getMessage());
}
