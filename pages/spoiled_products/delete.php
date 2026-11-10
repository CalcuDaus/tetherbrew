<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/spoiled-products'); csrf_verify();
$pdo->prepare("DELETE FROM spoiled_products WHERE id = ? AND branch_id = ?")->execute([$_POST['id'], activeBranchId()]);
redirect_with('/admin/spoiled-products', 'success', 'Data produk basi berhasil dihapus!');
