<?php
require_once __DIR__ . '/../core/bootstrap.php';

echo "Testing Owner Dashboard:\n";
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'owner';
$_SESSION['active_branch_id'] = 1;
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = 1");
$userStmt->execute();
$GLOBALS['auth_user'] = $userStmt->fetch();

try {
    ob_start();
    include __DIR__ . '/../pages/dashboard/owner.php';
    ob_end_clean();
    echo "Owner dashboard executed successfully!\n";
} catch (Throwable $e) {
    echo "Owner dashboard ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTesting Admin Dashboard:\n";
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'admin';
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = 2");
$userStmt->execute();
$GLOBALS['auth_user'] = $userStmt->fetch();

try {
    ob_start();
    include __DIR__ . '/../pages/dashboard/admin.php';
    ob_end_clean();
    echo "Admin dashboard executed successfully!\n";
} catch (Throwable $e) {
    echo "Admin dashboard ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
