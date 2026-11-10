<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journal_categories'); csrf_verify();
$name = trim($_POST['name']);
$check = $pdo->prepare("SELECT id FROM journal_categories WHERE name = ?"); $check->execute([$name]);
if($check->fetch()) redirect_with('/journal_categories', 'error', 'Nama kategori sudah digunakan.');

$pdo->prepare("INSERT INTO journal_categories (name, created_at, updated_at) VALUES (?,NOW(),NOW())")->execute([$name]);
redirect_with('/journal_categories', 'success', 'Kategori berhasil ditambahkan.');
