<?php
require_once __DIR__ . '/../core/bootstrap.php';

$branchId = 1;
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$today = date('Y-m-d');

$start = microtime(true);
$perfStmt = $pdo->prepare("
    SELECT u.id, u.name,
           COALESCE(SUM(rds.total_gross_income), 0) AS revenue,
           COALESCE(SUM(items_agg.cups), 0) AS cups
    FROM users u
    LEFT JOIN rider_daily_sales rds
        ON rds.rider_id = u.id AND rds.branch_id = ? AND rds.date BETWEEN ? AND ?
    LEFT JOIN (
        SELECT rider_daily_sale_id, SUM(stock_sold) AS cups
        FROM rider_daily_sale_items
        GROUP BY rider_daily_sale_id
    ) items_agg ON items_agg.rider_daily_sale_id = rds.id
    WHERE u.role = 'rider' AND u.branch_id = ?
    GROUP BY u.id, u.name
    ORDER BY cups DESC, revenue DESC
");
$perfStmt->execute([$branchId, $thirtyDaysAgo, $today, $branchId]);
$res = $perfStmt->fetchAll();
$time = microtime(true) - $start;

echo "Query took: " . round($time * 1000, 2) . " ms. Count: " . count($res) . "\n";
echo "Total items in rider_daily_sale_items: " . $pdo->query("SELECT count(*) FROM rider_daily_sale_items")->fetchColumn() . "\n";
echo "Total items in rider_daily_sales: " . $pdo->query("SELECT count(*) FROM rider_daily_sales")->fetchColumn() . "\n";
