<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/productions'); csrf_verify();
$pdo->prepare("DELETE FROM daily_productions WHERE id = ? AND branch_id = ?")->execute([$_POST['id'], activeBranchId()]);
redirect_with('/admin/productions', 'success', 'Data produksi berhasil dihapus!');
