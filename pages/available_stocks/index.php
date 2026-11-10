<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_bar_admin_owner();

$branchId = activeBranchId();
$filterDate = $_GET['date'] ?? date('Y-m-d');

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND (is_live_brew = 0 OR is_live_brew IS NULL) ORDER BY sort_order")->fetchAll();

// 1. Total Produced pada tanggal tersebut (1 query)
$prodStmt = $pdo->prepare("
    SELECT dpi.product_id, SUM(dpi.quantity_produced) AS qty
    FROM daily_production_items dpi
    INNER JOIN daily_productions dp ON dpi.daily_production_id = dp.id
    WHERE dp.branch_id = ? AND dp.date = ?
    GROUP BY dpi.product_id
");
$prodStmt->execute([$branchId, $filterDate]);
$producedMap = $prodStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Total Spoiled pada tanggal tersebut (1 query)
$spoilStmt = $pdo->prepare("
    SELECT spi.product_id, SUM(spi.quantity) AS qty
    FROM spoiled_product_items spi
    INNER JOIN spoiled_products sp ON spi.spoiled_product_id = sp.id
    WHERE sp.branch_id = ? AND sp.date = ?
    GROUP BY spi.product_id
");
$spoilStmt->execute([$branchId, $filterDate]);
$spoiledMap = $spoilStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Total dibawa Rider pada tanggal tersebut (1 query)
$usedStmt = $pdo->prepare("
    SELECT rsi.product_id, SUM(rsi.stock_out + rsi.stock_added - rsi.stock_return) AS qty
    FROM rider_daily_sale_items rsi
    INNER JOIN rider_daily_sales rs ON rsi.rider_daily_sale_id = rs.id
    WHERE rs.branch_id = ? AND rs.date = ?
    GROUP BY rsi.product_id
");
$usedStmt->execute([$branchId, $filterDate]);
$usedMap = $usedStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stockData = [];
foreach ($products as $p) {
    $pid = $p['id'];
    $produced = (int)($producedMap[$pid] ?? 0);
    $spoiled  = (int)($spoiledMap[$pid] ?? 0);
    $used     = (int)($usedMap[$pid] ?? 0);
    $available = max(0, $produced - $used - $spoiled);

    $stockData[] = [
        'id'        => $pid,
        'name'      => $p['name'],
        'category'  => $p['category'],
        'produced'  => $produced,
        'used'      => $used,
        'spoiled'   => $spoiled,
        'available' => $available,
    ];
}


$totalAvailableStock = array_sum(array_column($stockData, 'available'));

$pageTitle = 'Sisa Stok Tersedia';
ob_start();
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                <label class="form-label">Tampilkan Stok Pada Tanggal</label>
                <input type="date" name="date" class="form-input datepicker" value="<?= e($filterDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height: 42px;">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg> Terapkan Filter
            </button>
            <?php if ($filterDate !== date('Y-m-d')): ?>
                <a href="<?= url('/admin/available-stocks') ?>" class="btn btn-outline-secondary btn-sm" style="height: 42px; line-height: 30px;">Hari Ini</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title" style="display: flex; align-items: center; gap: 8px;">
           
            
              <div style="font-size: 2.2rem; font-weight: 800; color: rgb(34, 197, 94); margin: 12px 0 8px 0; display: flex; align-items: baseline; gap: 4px;">
               <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>  
              Sisa Stok Tersedia         ( <?= $totalAvailableStock; ?> )
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">Pcs</span>
                        </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($stockData)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <h3>Belum Ada Data Produk</h3>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <?php foreach ($stockData as $item): 
                    $available = $item['available'];
                    if ($available == 0) {
                        $bg = 'rgba(239, 68, 68, 0.05)';
                        $border = 'rgba(239, 68, 68, 0.25)';
                        $textCol = '#ef4444';
                    } elseif ($available <= 5) {
                        $bg = 'rgba(245, 158, 11, 0.05)';
                        $border = 'rgba(245, 158, 11, 0.25)';
                        $textCol = '#d97706';
                    } else {
                        $bg = 'rgba(34, 197, 94, 0.05)';
                        $border = 'rgba(34, 197, 94, 0.25)';
                        $textCol = '#16a34a';
                    }
                ?>
                    <div style="background: <?= $bg ?>; border: 1px solid <?= $border ?>; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; transition: transform 0.2s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px;"><?= e($item['name']) ?></div>
                            <span class="badge badge-info" style="font-size: 10px;"><?= e($item['category']) ?></span>
                        </div>
                        <div style="font-size: 2.2rem; font-weight: 800; color: <?= $textCol ?>; margin: 12px 0 8px 0; display: flex; align-items: baseline; gap: 4px;">
                            <?= $item['available'] ?>
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">Pcs</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: auto; display: flex; flex-direction: column; gap: 4px;">
                            <div style="display: flex; justify-content: space-between;"><span>Total Produksi:</span> <strong><?= $item['produced'] ?></strong></div>
                            <div style="display: flex; justify-content: space-between;"><span>Diambil Rider:</span> <strong><?= $item['used'] ?></strong></div>
                            <div style="display: flex; justify-content: space-between;"><span>Produk Basi:</span> <strong><?= $item['spoiled'] ?></strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Detail Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th style="text-align: center;">Total Produksi</th>
                            <th style="text-align: center;">Diambil Rider</th>
                            <th style="text-align: center;">Produk Basi</th>
                            <th style="text-align: center;">Sisa Stok Tersedia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockData as $item): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= e($item['name']) ?></td>
                                <td><span class="badge badge-info"><?= e($item['category']) ?></span></td>
                                <td style="text-align: center; font-weight: 600; color: var(--text-primary);"><?= $item['produced'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: var(--accent-blue);"><?= $item['used'] ?></td>
                                <td style="text-align: center; font-weight: 600; color: var(--accent-red);"><?= $item['spoiled'] ?></td>
                                <td style="text-align: center;">
                                    <?php if ($item['available'] == 0): ?>
                                        <span class="badge badge-danger" style="font-size: 13px; font-weight: 700; padding: 4px 10px;"><?= $item['available'] ?> Pcs</span>
                                    <?php elseif ($item['available'] <= 5): ?>
                                        <span class="badge badge-warning" style="font-size: 13px; font-weight: 700; padding: 4px 10px;"><?= $item['available'] ?> Pcs</span>
                                    <?php else: ?>
                                        <span class="badge badge-success" style="font-size: 13px; font-weight: 700; padding: 4px 10px;"><?= $item['available'] ?> Pcs</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
