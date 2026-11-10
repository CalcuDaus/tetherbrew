<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
$branchId = activeBranchId();
$filterDate = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND (is_live_brew = 0 OR is_live_brew IS NULL) ORDER BY sort_order")->fetchAll();
$planData = [];

$stmt = $pdo->prepare("SELECT rsi.product_id, SUM(rsi.stock_sold) as total_sold FROM rider_daily_sale_items rsi JOIN rider_daily_sales rs ON rsi.rider_daily_sale_id = rs.id JOIN users a ON rs.admin_id = a.id WHERE rs.branch_id = ? AND DATE(rs.date) = ? AND a.role IN ('admin', 'owner', 'bar') GROUP BY rsi.product_id");
$stmt->execute([$branchId, $filterDate]);
$soldMap = []; foreach($stmt->fetchAll() as $row) $soldMap[$row['product_id']] = $row['total_sold'];

foreach ($products as $p) {
    $planData[] = ['name' => $p['name'], 'category' => $p['category'], 'sold' => $soldMap[$p['id']] ?? 0];
}

$pageTitle = 'Rencana Produksi';
ob_start();
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="<?= url('/admin/production-plan') ?>" style="display:flex; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;"><label class="form-label">Basis Tanggal Penjualan</label><input type="date" name="date" class="form-input datepicker" value="<?= $filterDate ?>"></div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:42px;">Lihat Rencana</button>
        </form>
    </div>
</div>

<div class="card"><div class="card-header"><div class="card-title">Estimasi Rencana Produksi (Berdasarkan terjual tanggal <?= date('d M Y', strtotime($filterDate)) ?>)</div></div><div class="card-body" style="padding:0;">
    <div class="table-container"><table><thead><tr><th>Produk</th><th>Kategori</th><th>Rencana Produksi (Cup)</th></tr></thead><tbody>
    <?php foreach($planData as $p): ?>
        <tr>
            <td style="font-weight:600;"><?= e($p['name']) ?></td>
            <td><span class="badge badge-info"><?= e($p['category']) ?></span></td>
            <td style="font-size:18px; font-weight:700; color:var(--accent-blue);"><?= $p['sold'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
