<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/carts');
csrf_verify();
$pdo->prepare("DELETE FROM carts WHERE id = ? AND branch_id = ?")->execute([$_POST['id'], activeBranchId()]);
clear_carts_map_cache();
redirect_with('/carts', 'success', 'Gerobak berhasil dihapus!');
