<?php
require_once __DIR__ . '/../core/bootstrap.php';

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, name, email, role, is_active, branch_id FROM users ORDER BY id DESC LIMIT 10")->fetchAll();
foreach ($users as $u) {
    echo "ID: {$u['id']} | Name: {$u['name']} | Role: {$u['role']} | Active: {$u['is_active']} | Branch: {$u['branch_id']}\n";
}

echo "\n=== CARTS ===\n";
$carts = $pdo->query("SELECT id, name, branch_id, user_id, status FROM carts ORDER BY id ASC LIMIT 10")->fetchAll();
foreach ($carts as $c) {
    echo "Cart ID: {$c['id']} | Name: {$c['name']} | User ID: " . ($c['user_id'] ?? 'NULL') . " | Status: {$c['status']}\n";
}
