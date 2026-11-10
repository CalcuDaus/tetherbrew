<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_auth();
require_admin_or_owner();

header('Content-Type: application/json');

$riderId = $_GET['rider_id'] ?? null;
$date = $_GET['date'] ?? null;
$branchId = activeBranchId();

if (!$riderId || !$date) {
    echo json_encode(['cups' => 0]);
    exit;
}

try {
    // Cari total cup yang terjual (stock_sold) oleh rider pada tanggal tersebut
    $stmt = $pdo->prepare("
        SELECT SUM(i.stock_sold) as cups
        FROM rider_daily_sale_items i
        JOIN rider_daily_sales s ON i.rider_daily_sale_id = s.id
        WHERE s.branch_id = ? AND s.rider_id = ? AND DATE(s.date) = ?
    ");
    $stmt->execute([$branchId, $riderId, $date]);
    $cups = (int) $stmt->fetchColumn();

    echo json_encode(['cups' => $cups]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'cups' => 0]);
}
