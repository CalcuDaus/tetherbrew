<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
csrf_verify();
header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
if (!$id) { echo json_encode(['success' => false]); exit; }

$stmt = $pdo->prepare("SELECT status, user_id FROM carts WHERE id = ? AND branch_id = ?");
$stmt->execute([$id, activeBranchId()]);
$cart = $stmt->fetch();
if (!$cart) { echo json_encode(['success' => false, 'message' => 'Gerobak tidak ditemukan.']); exit; }

$newStatus = $cart['status'] === 'active' ? 'inactive' : 'active';
if ($newStatus === 'active' && empty($cart['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Gerobak tidak dapat diaktifkan karena belum memiliki rider. Silakan edit dan tugaskan rider terlebih dahulu.'
    ]);
    exit;
}
$pdo->prepare("UPDATE carts SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
clear_carts_map_cache();

echo json_encode(['success' => true, 'status' => $newStatus, 'message' => 'Status berhasil diubah ke ' . ucfirst($newStatus)]);
