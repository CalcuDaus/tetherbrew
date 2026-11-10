<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journals'); csrf_verify();
$pdo->prepare("DELETE FROM journals WHERE id = ? AND branch_id = ?")->execute([$_POST['id'], activeBranchId()]);
redirect_with('/journals', 'success', 'Jurnal berhasil dihapus.');
