<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
$branchId = activeBranchId();

$cartsStmt = $pdo->prepare("SELECT c.*, u.name as rider_name FROM carts c LEFT JOIN users u ON c.user_id = u.id WHERE c.branch_id = ? ORDER BY c.name");
$cartsStmt->execute([$branchId]); $carts = $cartsStmt->fetchAll();

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
$selectedCartId = $_GET['cart_id'] ?? null;
$selectedCart = null; $inventories = [];

if ($selectedCartId) {
    $scStmt = $pdo->prepare("SELECT * FROM carts WHERE id = ? AND branch_id = ?"); $scStmt->execute([$selectedCartId, $branchId]); $selectedCart = $scStmt->fetch();
    if ($selectedCart) {
        $invStmt = $pdo->prepare("SELECT i.*, p.name as product_name FROM inventories i JOIN products p ON i.product_id = p.id WHERE i.cart_id = ? ORDER BY p.sort_order, p.name");
        $invStmt->execute([$selectedCartId]); $inventories = $invStmt->fetchAll();
    }
}
$pageTitle = 'Kelola Stok';
ob_start();
?>
<div style="display:grid; grid-template-columns:300px 1fr; gap:20px;">
    <div class="card">
        <div class="card-header"><div class="card-title">Pilih Gerobak</div></div>
        <div class="card-body" style="padding:0;">
            <?php foreach ($carts as $c): ?>
                <a href="/inventories?cart_id=<?= $c['id'] ?>" style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid var(--border-color); text-decoration:none; color:var(--text-primary); transition:background 0.2s; <?= $selectedCartId == $c['id'] ? 'background:rgba(34,197,94,0.1); font-weight:600;' : '' ?>" onmouseover="this.style.background='var(--bg-card-hover)'" onmouseout="this.style.background='<?= $selectedCartId == $c['id'] ? 'rgba(34,197,94,0.1)' : '' ?>'">
                    <div><?= e($c['name']) ?><div style="font-size:11px; color:var(--text-muted);"><?= e($c['rider_name'] ?? 'Tanpa rider') ?></div></div>
                    <span class="badge badge-<?= $c['status'] ?>" style="font-size:10px;"><?= ucfirst($c['status']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (empty($carts)): ?><p style="padding:20px; color:var(--text-muted); text-align:center;">Belum ada gerobak</p><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <?php if ($selectedCart): ?>
            <div class="card-header"><div class="card-title">Stok: <?= e($selectedCart['name']) ?></div></div>
            <div class="card-body">
                <form method="POST" action="<?= url('/inventories/update') ?>">
                    <?= csrf_field() ?><input type="hidden" name="cart_id" value="<?= $selectedCart['id'] ?>">
                    <div class="table-container"><table><thead><tr><th>Produk</th><th>Stok Saat Ini</th></tr></thead><tbody>
                    <?php
                    $invMap = []; foreach ($inventories as $inv) $invMap[$inv['product_id']] = $inv['stock'];
                    foreach ($products as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?> <span style="color:var(--text-muted); font-size:12px;">(<?= e($p['category']) ?>)</span></td>
                            <td><input type="hidden" name="stocks[<?= $p['id'] ?>][product_id]" value="<?= $p['id'] ?>"><input type="number" name="stocks[<?= $p['id'] ?>][stock]" class="form-input" value="<?= $invMap[$p['id']] ?? 0 ?>" min="0" style="width:120px;"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <div style="margin-top:16px; text-align:right;"><button type="submit" class="btn btn-primary"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.15em; margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Simpan Stok</button></div>
                </form>
            </div>
        <?php else: ?>
            <div class="card-body" style="text-align:center; padding:60px; color:var(--text-muted);">
                <div style="margin-bottom:12px; color:var(--text-muted); opacity:0.6;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
                <h3>Pilih Gerobak</h3>
                <p>Pilih gerobak dari daftar di sebelah kiri untuk mengelola stok.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
