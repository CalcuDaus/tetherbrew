<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/carts');
csrf_verify();

$id = $_POST['id']; $errors = validate($_POST, ['name' => 'required|string|max:255', 'status' => 'required|in:active,inactive,closed']);

if (($_POST['status'] ?? '') === 'active' && empty($_POST['user_id'])) {
    $errors['user_id'] = ['Gerobak tidak dapat diaktifkan tanpa rider. Silakan pilih rider terlebih dahulu atau atur status ke Inactive.'];
}

if (!empty($errors)) redirect_back_with_errors($errors);

$pdo->prepare("UPDATE carts SET name=?, description=?, user_id=?, status=?, updated_at=NOW() WHERE id=? AND branch_id=?")
    ->execute([trim($_POST['name']), $_POST['description'] ?? null, $_POST['user_id'] ?: null, $_POST['status'], $id, activeBranchId()]);

if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
    $pdo->prepare("INSERT INTO cart_locations (cart_id, latitude, longitude, created_at, updated_at) VALUES (?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude), updated_at=NOW()")
        ->execute([$id, $_POST['latitude'], $_POST['longitude']]);
}

clear_carts_map_cache();
redirect_with('/carts', 'success', 'Gerobak berhasil diperbarui!');
