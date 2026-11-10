<?php
/**
 * Rider Dashboard
 * Sumber: DashboardController.php#riderDashboard
 */
$user = auth_user();

// Get rider's cart
$cartStmt = $pdo->prepare("SELECT * FROM carts WHERE user_id = ?");
$cartStmt->execute([$user['id']]);
$cart = $cartStmt->fetch();

$todayTransactions = [];
$todayRevenue = 0;
$todayCount = 0;
$inventories = [];

if ($cart) {
    // Today's transactions
    $txStmt = $pdo->prepare("
        SELECT t.*, GROUP_CONCAT(CONCAT(p.name, ' x', ti.qty) SEPARATOR ', ') as items_summary
        FROM transactions t
        LEFT JOIN transaction_items ti ON t.id = ti.transaction_id
        LEFT JOIN products p ON ti.product_id = p.id
        WHERE t.cart_id = ? AND DATE(t.created_at) = CURDATE()
        GROUP BY t.id ORDER BY t.created_at DESC
    ");
    $txStmt->execute([$cart['id']]);
    $todayTransactions = $txStmt->fetchAll();
    
    $todayRevenue = array_sum(array_column($todayTransactions, 'total_price'));
    $todayCount = count($todayTransactions);
    
    // Current inventory
    $invStmt = $pdo->prepare("SELECT i.*, p.name, p.price FROM inventories i JOIN products p ON i.product_id = p.id WHERE i.cart_id = ? ORDER BY p.sort_order, p.name");
    $invStmt->execute([$cart['id']]);
    $inventories = $invStmt->fetchAll();
    
    // Location
    $locStmt = $pdo->prepare("SELECT * FROM cart_locations WHERE cart_id = ? ORDER BY updated_at DESC LIMIT 1");
    $locStmt->execute([$cart['id']]);
    $location = $locStmt->fetch();
}

$pageTitle = 'Dashboard Rider';

ob_start();
?>

<?php if (!$cart): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 40px;">
            <div style="margin-bottom: 16px; color: var(--text-muted); opacity: 0.6;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <h3>Belum Ada Gerobak</h3>
            <p style="color: var(--text-muted);">Anda belum ditugaskan ke gerobak. Hubungi admin.</p>
        </div>
    </div>
<?php else: ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div>
        <div class="stat-value"><?= e($cart['name']) ?></div>
        <div class="stat-label">Gerobak — <span style="color: <?= $cart['status'] === 'active' ? 'var(--accent-green)' : 'var(--accent-red)' ?>"><?= ucfirst($cart['status']) ?></span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
        <div class="stat-value"><?= formatRupiah($todayRevenue) ?></div>
        <div class="stat-label"><?= $todayCount ?> transaksi hari ini</div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="<?= url('/rider/pos') ?>" class="btn btn-primary" style="flex: 1; justify-content: center; min-width: 140px;">POS Kasir</a>
    <a href="<?= url('/rider/transactions') ?>" class="btn btn-secondary" style="flex: 1; justify-content: center; min-width: 140px;">Riwayat</a>
    <a href="<?= url('/rider/chat') ?>" class="btn btn-secondary" style="flex: 1; justify-content: center; min-width: 140px;">Chat</a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <!-- Stok Saat Ini -->
    <div class="card">
        <div class="card-header"><div class="card-title">Stok Saat Ini</div></div>
        <div class="card-body">
            <?php if (empty($inventories)): ?>
                <p style="color: var(--text-muted); text-align: center;">Belum ada stok</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Produk</th><th>Stok</th><th>Harga</th></tr></thead>
                        <tbody>
                        <?php foreach ($inventories as $inv): ?>
                            <tr>
                                <td><?= e($inv['name']) ?></td>
                                <td><strong><?= $inv['stock'] ?></strong></td>
                                <td><?= formatRupiah($inv['price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaksi Hari Ini -->
    <div class="card">
        <div class="card-header"><div class="card-title">Transaksi Hari Ini</div></div>
        <div class="card-body">
            <?php if (empty($todayTransactions)): ?>
                <p style="color: var(--text-muted); text-align: center;">Belum ada transaksi</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Waktu</th><th>Items</th><th>Total</th><th>Bayar</th></tr></thead>
                        <tbody>
                        <?php foreach ($todayTransactions as $tx): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($tx['created_at'])) ?></td>
                                <td style="font-size: 12px;"><?= e(truncate($tx['items_summary'] ?? '-', 30)) ?></td>
                                <td><?= formatRupiah($tx['total_price']) ?></td>
                                <td><span class="badge badge-<?= $tx['payment_method'] === 'cash' ? 'success' : 'info' ?>"><?= strtoupper($tx['payment_method']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- GPS Location Update -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header"><div class="card-title">Update Lokasi GPS</div></div>
    <div class="card-body">
        <p style="color: var(--text-muted); margin-bottom: 12px;">
            <?php if (isset($location)): ?>
                Terakhir update: <?= diffForHumans($location['updated_at']) ?>
            <?php else: ?>
                Belum ada data lokasi
            <?php endif; ?>
        </p>
        <button id="updateLocationBtn" class="btn btn-primary" onclick="updateLocation()">
            <svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.15em; margin-right:4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Perbarui Lokasi Saya
        </button>
        <span id="locationStatus" style="margin-left: 12px; color: var(--text-muted);"></span>
    </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();

$extraScripts = '<script>
function updateLocation() {
    var statusEl = document.getElementById("locationStatus");
    statusEl.textContent = "Mengambil lokasi...";
    
    if (!navigator.geolocation) {
        statusEl.textContent = "Browser tidak mendukung GPS";
        return;
    }
    
    navigator.geolocation.getCurrentPosition(function(pos) {
        var data = new FormData();
        data.append("latitude", pos.coords.latitude);
        data.append("longitude", pos.coords.longitude);
        data.append("_token", document.querySelector("meta[name=csrf-token]").content);
        
        fetch("/rider/location/live", { method: "POST", body: data })
            .then(r => r.json())
            .then(d => { statusEl.textContent = d.success ? "Lokasi berhasil diperbarui!" : "Gagal memperbarui lokasi."; })
            .catch(e => { statusEl.textContent = "Error: " + e.message; });
    }, function(err) {
        statusEl.textContent = "Gagal: " + err.message;
    }, { enableHighAccuracy: true });
}

// Auto update location every 5 minutes
setInterval(function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            var data = new FormData();
            data.append("latitude", pos.coords.latitude);
            data.append("longitude", pos.coords.longitude);
            data.append("_token", document.querySelector("meta[name=csrf-token]").content);
            fetch("/rider/location/live", { method: "POST", body: data }).catch(e => {});
        }, function() {}, { enableHighAccuracy: true });
    }
}, 300000);
</script>';

require __DIR__ . '/../../layouts/app.php';
