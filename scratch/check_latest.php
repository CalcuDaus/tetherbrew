<?php
require_once __DIR__ . '/../core/bootstrap.php';

echo "=== LATEST USERS ===\n";
foreach($pdo->query("SELECT id, name, role, is_active, created_at, updated_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll() as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== INACTIVE RIDERS ===\n";
foreach($pdo->query("SELECT id, name, role, is_active FROM users WHERE role = 'rider' AND is_active = 0")->fetchAll() as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== LATEST RIDER FINANCES ===\n";
foreach($pdo->query("SELECT * FROM rider_finances ORDER BY id DESC LIMIT 5")->fetchAll() as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== LATEST RIDER DAILY SALES ===\n";
foreach($pdo->query("SELECT * FROM rider_daily_sales ORDER BY id DESC LIMIT 5")->fetchAll() as $r) {
    echo json_encode($r) . "\n";
}
