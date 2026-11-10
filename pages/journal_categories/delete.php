<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journal_categories'); csrf_verify();
$id = $_POST['id'];
$check = $pdo->prepare("SELECT id FROM journals WHERE journal_category_id = ? LIMIT 1"); $check->execute([$id]);
if($check->fetch()) redirect_with('/journal_categories', 'error', 'Kategori tidak dapat dihapus karena masih digunakan dalam jurnal.');

$pdo->prepare("DELETE FROM journal_categories WHERE id = ?")->execute([$id]);
redirect_with('/journal_categories', 'success', 'Kategori berhasil dihapus.');
