<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$branchId=activeBranchId();
$cartFilter = $_GET['cart_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

$query = "SELECT t.*, c.name as cart_name, u.name as rider_name
    FROM transactions t
    JOIN carts c ON t.cart_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.branch_id = ? AND DATE(t.created_at) BETWEEN ? AND ?";
$params = [$branchId, $dateFrom, $dateTo];

if ($cartFilter) { $query .= " AND t.cart_id = ?"; $params[] = $cartFilter; }
if ($search) { $query .= " AND (c.name LIKE ? OR u.name LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
$query .= " ORDER BY t.created_at DESC";

$pagination = paginate($pdo, $query, $params, 15);
$transactions = $pagination['data'];

$cartsStmt = $pdo->prepare("SELECT id, name FROM carts WHERE branch_id = ? ORDER BY name");
$cartsStmt->execute([$branchId]); $carts = $cartsStmt->fetchAll();

// Summary
$summaryQuery = "SELECT COUNT(*) as total_count, COALESCE(SUM(t.total_price),0) as total_revenue,
    COALESCE(SUM(CASE WHEN t.payment_method='cash' THEN t.total_price ELSE 0 END),0) as cash_total,
    COALESCE(SUM(CASE WHEN t.payment_method='qris' THEN t.total_price ELSE 0 END),0) as qris_total
    FROM transactions t JOIN carts c ON t.cart_id = c.id WHERE c.branch_id = ? AND DATE(t.created_at) BETWEEN ? AND ?";
$summaryParams = [$branchId, $dateFrom, $dateTo];
if ($cartFilter) { $summaryQuery .= " AND t.cart_id = ?"; $summaryParams[] = $cartFilter; }
$summaryStmt = $pdo->prepare($summaryQuery); $summaryStmt->execute($summaryParams); $summary = $summaryStmt->fetch();

$pageTitle = 'Riwayat Transaksi';
ob_start();
?>
<!-- Filter -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="<?= url('/transactions') ?>" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="flex:1; min-width:150px; max-width:200px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Dari</label>
                <input type="date" name="date_from" class="form-input datepicker" value="<?= $dateFrom ?>" style="height:42px;">
            </div>
            <div class="form-group" style="flex:1; min-width:150px; max-width:200px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Sampai</label>
                <input type="date" name="date_to" class="form-input datepicker" value="<?= $dateTo ?>" style="height:42px;">
            </div>
            <div class="form-group" style="flex:1; min-width:180px; max-width:240px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Gerobak</label>
                <select name="cart_id" class="form-input" style="width:100%; height:42px;">
                    <option value="">Semua Gerobak</option>
                    <?php foreach($carts as $c):?>
                        <option value="<?= $c['id']?>" <?= $cartFilter==$c['id']?'selected':''?>><?= e($c['name'])?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:180px; max-width:240px; margin-bottom:0;">
                <label class="form-label" style="font-weight:600;">Cari</label>
                <input type="text" name="search" class="form-input" value="<?= e($search)?>" placeholder="Nama..." style="height:42px;">
            </div>
            <div style="display:flex; gap:8px; margin-bottom:0;">
                <button type="submit" class="btn btn-primary" style="height:42px; display:inline-flex; align-items:center; gap:6px; padding:0 20px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg> Filter
                </button>
                <?php if($dateFrom != date('Y-m-01') || $dateTo != date('Y-m-d') || $cartFilter || $search): ?>
                    <a href="<?= url('/transactions') ?>" class="btn btn-outline-secondary" style="height:42px; display:inline-flex; align-items:center; padding:0 16px;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card"><div class="stat-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></div><div class="stat-value"><?= number_format($summary['total_count'])?></div><div class="stat-label">Total Transaksi</div></div>
    <div class="stat-card"><div class="stat-icon gold"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div class="stat-value"><?= formatRupiah($summary['total_revenue'])?></div><div class="stat-label">Total Revenue</div></div>
    <div class="stat-card"><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg></div><div class="stat-value"><?= formatRupiah($summary['cash_total'])?></div><div class="stat-label">Cash</div></div>
    <div class="stat-card"><div class="stat-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div><div class="stat-value"><?= formatRupiah($summary['qris_total'])?></div><div class="stat-label">QRIS</div></div>
</div>

<!-- Table -->
<div class="card"><div class="card-body" style="padding:0;">
    <?php if(empty($transactions)):?><div style="text-align:center; padding:40px; color:var(--text-muted);"><h3>Tidak ada transaksi</h3></div>
    <?php else:?>
    <div class="table-container"><table><thead><tr><th>ID</th><th>Waktu</th><th>Gerobak</th><th>Rider</th><th>Total</th><th>Bayar</th><th>Detail</th></tr></thead><tbody>
    <?php foreach($transactions as $t):
        $itemsStmt=$pdo->prepare("SELECT ti.*, p.name FROM transaction_items ti JOIN products p ON ti.product_id=p.id WHERE ti.transaction_id=?");
        $itemsStmt->execute([$t['id']]); $items=$itemsStmt->fetchAll();
    ?>
        <tr>
            <td>#<?= $t['id']?></td>
            <td style="font-size:12px;"><?= date('d/m H:i', strtotime($t['created_at']))?></td>
            <td><?= e($t['cart_name'])?></td>
            <td><?= e($t['rider_name']??'-')?></td>
            <td style="font-weight:600;"><?= formatRupiah($t['total_price'])?></td>
            <td><span class="badge badge-<?= $t['payment_method']==='cash'?'success':'info'?>"><?= strtoupper($t['payment_method'])?></span></td>
            <td style="font-size:11px; color:var(--text-muted);"><?php foreach($items as $i) echo e($i['name']).' x'.$i['qty'].', '; ?></td>
        </tr>
    <?php endforeach;?></tbody></table></div>
    <div style="padding:16px;"><?= render_pagination($pagination)?></div>
    <?php endif;?>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
