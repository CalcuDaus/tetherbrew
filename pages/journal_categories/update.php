<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journal_categories'); csrf_verify();
$id = $_POST['id']; $name = trim($_POST['name']);
$check = $pdo->prepare("SELECT id FROM journal_categories WHERE name = ? AND id != ?"); $check->execute([$name, $id]);
if($check->fetch()) redirect_with('/journal_categories', 'error', 'Nama kategori sudah digunakan.');

$pdo->prepare("UPDATE journal_categories SET name = ?, updated_at = NOW() WHERE id = ?")->execute([$name, $id]);
redirect_with('/journal_categories', 'success', 'Kategori berhasil diperbarui.');
