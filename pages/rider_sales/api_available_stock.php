<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
header('Content-Type: application/json');

$branchId = activeBranchId();
$date = $_GET['date'] ?? date('Y-m-d');
$riderId = $_GET['rider_id'] ?? null;

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$stockData = [];

// Prepare existing sale if any
$riderSale = null; $riderSaleItems = [];
if ($riderId) {
    $rsStmt = $pdo->prepare("SELECT * FROM rider_daily_sales WHERE branch_id = ? AND date = ? AND rider_id = ?");
    $rsStmt->execute([$branchId, $date, $riderId]); $riderSale = $rsStmt->fetch();
    if($riderSale) {
        $rsiStmt = $pdo->prepare("SELECT * FROM rider_daily_sale_items WHERE rider_daily_sale_id = ?");
        $rsiStmt->execute([$riderSale['id']]);
        foreach($rsiStmt->fetchAll() as $rsi) $riderSaleItems[$rsi['product_id']] = $rsi;
    }
}

foreach ($products as $p) {
    $isLiveBrew = (int)($p['is_live_brew'] ?? 0);
    $produced = 0;
    $spoiled = 0;
    $used = 0;
    $available = 0;

    if (!$isLiveBrew) {
        // Produced (Hari Ini)
        $prodStmt = $pdo->prepare("SELECT SUM(quantity_produced) FROM daily_production_items dpi JOIN daily_productions dp ON dpi.daily_production_id = dp.id WHERE dp.branch_id = ? AND DATE(dp.date) = ? AND dpi.product_id = ?");
        $prodStmt->execute([$branchId, $date, $p['id']]); $produced = (int)$prodStmt->fetchColumn();

        // Spoiled (Hari Ini)
        $spoilStmt = $pdo->prepare("SELECT SUM(quantity) FROM spoiled_product_items spi JOIN spoiled_products sp ON spi.spoiled_product_id = sp.id WHERE sp.branch_id = ? AND DATE(sp.date) = ? AND spi.product_id = ?");
        $spoilStmt->execute([$branchId, $date, $p['id']]); $spoiled = (int)$spoilStmt->fetchColumn();

        // Used (Oleh rider lain pada hari ini)
        $usedQuery = "SELECT SUM(stock_out + stock_added - stock_return) FROM rider_daily_sale_items rsi JOIN rider_daily_sales rs ON rsi.rider_daily_sale_id = rs.id WHERE rs.branch_id = ? AND rsi.product_id = ? AND DATE(rs.date) = ?";
        $usedParams = [$branchId, $p['id'], $date];
        if ($riderId) { 
            $usedQuery .= " AND rs.rider_id != ?"; 
            $usedParams[] = $riderId; 
        }
        
        $usedStmt = $pdo->prepare($usedQuery); $usedStmt->execute($usedParams); $used = (int)$usedStmt->fetchColumn();

        $available = max(0, $produced - $used - $spoiled);
    }

    $riderItem = $riderSaleItems[$p['id']] ?? null;

    $stockData[] = [
        'product_id' => $p['id'],
        'product_name' => $p['name'],
        'price' => $p['price'],
        'is_live_brew' => $isLiveBrew,
        'available' => $available,
        'produced' => $produced,
        'used' => $used,
        'spoiled' => $spoiled,
        'rider_item' => $riderItem
    ];
}

echo json_encode([
    'stockData' => $stockData,
    'riderSale' => $riderSale ? [
        'cash_amount' => $riderSale['cash_amount'],
        'qris_amount' => $riderSale['qris_amount'],
        'actual_setor' => $riderSale['actual_setor'],
        'minus_amount' => $riderSale['minus_amount'],
        'total_gross_income' => $riderSale['total_gross_income'],
        'admin_pemeriksa' => $riderSale['admin_pemeriksa'],
    ] : null
]);
