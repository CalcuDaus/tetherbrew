<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journals'); csrf_verify();
$pdo->prepare("INSERT INTO journals (branch_id, journal_category_id, date, type, amount, description, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())")
    ->execute([activeBranchId(), $_POST['journal_category_id']?:null, $_POST['date'], $_POST['type'], $_POST['amount'], $_POST['description'], auth_id()]);
redirect_with('/journals', 'success', 'Jurnal berhasil ditambahkan.');
