<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/productions');
csrf_verify();

$id = $_POST['id'];
$items = $_POST['items'] ?? [];
$note = $_POST['note'] ?? '';
$branchId = activeBranchId();

// Verify production belongs to this branch
$check = $pdo->prepare("SELECT id FROM daily_productions WHERE id = ? AND branch_id = ?");
$check->execute([$id, $branchId]);
if (!$check->fetch()) redirect_with('/admin/productions', 'error', 'Data produksi tidak ditemukan.');

if (empty(trim($note))) redirect_with("/admin/productions/add-stock?id={$id}", 'error', 'Catatan penambahan stok wajib diisi.');

try {
    $pdo->beginTransaction();
    $addedItemsData = [];
    $hasAddedItems = false;
    
    foreach ($items as $productId => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) continue;
        
        $hasAddedItems = true;
        $addedItemsData[$productId] = $qty;

        // Check if item already exists
        $existStmt = $pdo->prepare("SELECT id, quantity_produced FROM daily_production_items WHERE daily_production_id = ? AND product_id = ?");
        $existStmt->execute([$id, $productId]);
        $existing = $existStmt->fetch();

        if ($existing) {
            // Update existing: add to current quantity
            $newQty = $existing['quantity_produced'] + $qty;
            $pdo->prepare("UPDATE daily_production_items SET quantity_produced = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newQty, $existing['id']]);
        } else {
            // Insert new item
            $pdo->prepare("INSERT INTO daily_production_items (daily_production_id, product_id, quantity_produced, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())")
                ->execute([$id, $productId, $qty]);
        }
    }

    if ($hasAddedItems) {
        $pdo->prepare("INSERT INTO daily_production_additions (daily_production_id, user_id, note, items_data, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$id, auth_user()['id'], $note, json_encode($addedItemsData)]);
    }

    $pdo->prepare("UPDATE daily_productions SET updated_at = NOW() WHERE id = ?")->execute([$id]);
    $pdo->commit();
    redirect_with('/admin/productions', 'success', 'Tambahan stok produksi berhasil disimpan!');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with("/admin/productions/add-stock?id={$id}", 'error', 'Gagal: ' . $e->getMessage());
}
