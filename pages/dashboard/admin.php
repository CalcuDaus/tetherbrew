<?php
/**
 * Admin Dashboard
 * Sumber: DashboardController.php#adminDashboard
 */
$branchId = activeBranchId();

// Stats
$totalCarts = $pdo->prepare("SELECT COUNT(*) as cnt FROM carts WHERE branch_id = ?");
$totalCarts->execute([$branchId]);
$totalCarts = $totalCarts->fetch()['cnt'];

$activeCarts = $pdo->prepare("SELECT COUNT(*) as cnt FROM carts WHERE branch_id = ? AND status = 'active'");
$activeCarts->execute([$branchId]);
$activeCarts = $activeCarts->fetch()['cnt'];

$totalProducts = $pdo->query("SELECT COUNT(*) as cnt FROM products")->fetch()['cnt'];

// Revenue from transactions
$branchCartIds = $pdo->prepare("SELECT id FROM carts WHERE branch_id = ?");
$branchCartIds->execute([$branchId]);
$cartIds = array_column($branchCartIds->fetchAll(), 'id');
$cartIdsStr = implode(',', $cartIds ?: [0]);

$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');
$monthStartTs = date('Y-m-01 00:00:00');
$monthEndTs   = date('Y-m-t 23:59:59');

$todayStats = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total, COUNT(*) as cnt FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at BETWEEN '{$todayStart}' AND '{$todayEnd}'")->fetch();
$todayRevenue = $todayStats['total'];
$todayTransactions = $todayStats['cnt'];
$weekRevenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetch()['total'];
$monthRevenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at BETWEEN '{$monthStartTs}' AND '{$monthEndTs}'")->fetch()['total'];

// Top products (30 days)
$topProducts = $pdo->query("
    SELECT ti.product_id, p.name, SUM(ti.qty) as total_sold, SUM(ti.subtotal) as total_revenue
    FROM transaction_items ti
    JOIN products p ON ti.product_id = p.id
    JOIN transactions t ON ti.transaction_id = t.id
    WHERE t.cart_id IN ({$cartIdsStr}) AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY ti.product_id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

// Daily revenue chart (7 days)
$dailyRevenue = $pdo->query("
    SELECT DATE(created_at) as date, SUM(total_price) as revenue, COUNT(*) as count
    FROM transactions
    WHERE cart_id IN ({$cartIdsStr}) AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at) ORDER BY date
")->fetchAll();

$avgTransaction = $todayTransactions > 0 ? $todayRevenue / $todayTransactions : 0;

$recentTransactions = $pdo->query("
    SELECT t.*, u.name as user_name, c.name as cart_name 
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN carts c ON t.cart_id = c.id
    WHERE t.cart_id IN ({$cartIdsStr})
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard Admin';
$activeBranchData = activeBranch();
if ($activeBranchData) {
    $pageTitle .= ' — ' . $activeBranchData['name'];
}

ob_start();
?>

<?php
$hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$dateString = $hari[date('l')] . ', ' . formatTanggal(date('Y-m-d'), 'd F Y');
?>

<?php // Welcome Header ?>
<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px 0;">Dashboard Admin <?php if($activeBranchData): ?><span style="color: var(--accent-gold); font-weight: 600;">(<?= e($activeBranchData['name']) ?>)</span><?php endif; ?></h2>
        <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">Pantau aktivitas operasional dan performa penjualan hari ini.</p>
    </div>
    <div style="font-size: 0.9rem; color: var(--text-muted); background: var(--bg-card); padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-color);">
        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.15em; margin-right: 6px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= $dateString ?>
    </div>
</div>

<?php // Top Metrics Grid ?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <?php // Card 1: Pendapatan Hari Ini ?>
    <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Pendapatan Hari Ini</div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(34, 197, 94, 0.1); color: #22c55e; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/><path d="M16 14h2"/></svg>
            </div>
        </div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary);">Rp <?= number_format($todayRevenue, 0, ',', '.') ?></div>
    </div>

    <?php // Card 2: Transaksi Hari Ini ?>
    <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Transaksi Hari Ini</div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
        </div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary);"><?= number_format($todayTransactions, 0, ',', '.') ?> <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Struk</span></div>
    </div>

    <?php // Card 3: Rata-rata Transaksi ?>
    <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Rata-rata Penjualan</div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(168, 85, 247, 0.1); color: #a855f7; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
        </div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary);">Rp <?= number_format($avgTransaction, 0, ',', '.') ?></div>
    </div>

    <?php // Card 4: Gerobak Aktif ?>
    <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Gerobak Aktif</div>
            <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(234, 179, 8, 0.1); color: #eab308; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary);"><?= $activeCarts ?> <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">/ <?= $totalCarts ?> Total</span></div>
    </div>
</div>

<?php // Middle Section: Chart & Revenue Summary ?>
<style>
    @media (min-width: 992px) {
        .dashboard-middle-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        .dashboard-bottom-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
    }
    @media (max-width: 991px) {
        .dashboard-middle-grid, .dashboard-bottom-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
    }
</style>

<div class="dashboard-middle-grid" style="margin-bottom: 24px;">
    <?php // Chart Card ?>
    <div class="card" style="margin: 0; display: flex; flex-direction: column; border-radius: 10px; overflow: hidden;">
        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 16px 20px; background: transparent;">
            <h3 class="card-title" style="font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0; color: var(--text-primary);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                Grafik Pendapatan 7 Hari
            </h3>
        </div>
        <div class="card-body" style="flex: 1; padding: 20px;">
            <?php if(count($dailyRevenue) > 0): ?>
                <div id="revenue-chart" style="width: 100%; min-height: 280px;"></div>
            <?php else: ?>
                <div style="height: 100%; min-height: 280px; display: flex; flex-direction: column; justify-content: center; align-items: center; color: var(--text-muted); background: rgba(0,0,0,0.01); border-radius: 8px; border: 1px dashed var(--border-color);">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5; margin-bottom: 12px;"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    <span style="font-size: 0.95rem;">Belum ada data pendapatan</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // Revenue Summary Column ?>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; border-left: 4px solid #3b82f6;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Akumulasi Minggu Ini</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Rp <?= number_format($weekRevenue, 0, ',', '.') ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Total pendapatan 7 hari terakhir</div>
        </div>
        <div class="card" style="margin: 0; padding: 20px; border-radius: 10px; border-left: 4px solid #22c55e;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Akumulasi Bulan Ini</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Rp <?= number_format($monthRevenue, 0, ',', '.') ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Total selama bulan berjalan</div>
        </div>
        
        <div class="card" style="margin: 0; padding: 20px; flex: 1; border-radius: 10px; display: flex; flex-direction: column; justify-content: center; align-items: center; background: rgba(59, 130, 246, 0.03); border: 1px dashed rgba(59, 130, 246, 0.3); text-align: center;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>
            </div>
            <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 4px;">Kelola Laporan Keuangan</div>
            <a href="<?= url('/journals') ?>" style="font-size: 0.85rem; color: #3b82f6; text-decoration: none; font-weight: 500;">Buka Jurnal Umum &rarr;</a>
        </div>
    </div>
</div>

<?php // Bottom Section: Top Products & Recent Transactions ?>
<div class="dashboard-bottom-grid">
    <?php // Top Products ?>
    <div class="card" style="margin: 0; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column;">
        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 16px 20px; background: transparent;">
            <h3 class="card-title" style="font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0; color: var(--text-primary);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Terlaris (30 Hari)
            </h3>
        </div>
        <div class="card-body" style="padding: 0; flex: 1;">
            <?php if(count($topProducts) > 0): ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: rgba(0,0,0,0.02);">
                        <tr>
                            <th style="padding: 12px 20px; text-align: left; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Produk</th>
                            <th style="padding: 12px 20px; text-align: right; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($topProducts as $item): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 14px 20px; font-weight: 500; color: var(--text-primary);"><?= e($item['name']) ?></td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: var(--text-primary);"><?= $item['total_sold'] ?> <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 400;">cup</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5; margin-bottom: 12px;"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    <div style="font-size: 0.95rem;">Belum ada data penjualan</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // Recent Transactions ?>
    <div class="card" style="margin: 0; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column;">
        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding: 16px 20px; background: transparent; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0; color: var(--text-primary);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Transaksi Terbaru
            </h3>
            <a href="<?= url('/transactions') ?>" style="font-size: 0.85rem; font-weight: 600; color: #3b82f6; text-decoration: none;">Lihat Semua</a>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <?php if(count($recentTransactions) > 0): ?>
                <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
                    <thead style="background: rgba(0,0,0,0.02);">
                        <tr>
                            <th style="padding: 12px 20px; text-align: left; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Waktu</th>
                            <th style="padding: 12px 20px; text-align: left; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Rider / Gerobak</th>
                            <th style="padding: 12px 20px; text-align: right; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Total</th>
                            <th style="padding: 12px 20px; text-align: center; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color);">Bayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentTransactions as $tx): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 14px 20px; font-size: 0.9rem; color: var(--text-secondary);">
                                    <?= diffForHumans($tx['created_at']) ?>
                                </td>
                                <td style="padding: 14px 20px;">
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;"><?= e($tx['user_name'] ?? '-') ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($tx['cart_name'] ?? '-') ?></div>
                                </td>
                                <td style="padding: 14px 20px; text-align: right; font-weight: 600; color: var(--text-primary);">
                                    Rp <?= number_format($tx['total_price'], 0, ',', '.') ?>
                                </td>
                                <td style="padding: 14px 20px; text-align: center;">
                                    <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; 
                                        background: <?= $tx['payment_method'] === 'qris' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(34, 197, 94, 0.1)' ?>;
                                        color: <?= $tx['payment_method'] === 'qris' ? '#3b82f6' : '#16a34a' ?>;">
                                        <?= e($tx['payment_method']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5; margin-bottom: 12px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <div style="font-size: 0.95rem;">Belum ada transaksi</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$extraScripts = '';
if(count($dailyRevenue) > 0) {
    $extraScripts .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const dailyData = ' . json_encode($dailyRevenue) . ';
        const translatedDays = {
            "Monday": "Sen", "Tuesday": "Sel", "Wednesday": "Rab", "Thursday": "Kam",
            "Friday": "Jum", "Saturday": "Sab", "Sunday": "Min"
        };

        const series = [{
            name: "Pendapatan",
            data: dailyData.map(item => item.revenue)
        }];

        const categories = dailyData.map(item => {
            const dayName = new Date(item.date).toLocaleDateString("en-US", { weekday: "long" });
            return translatedDays[dayName] || dayName.substring(0, 3);
        });

        const isLightTheme = document.documentElement.classList.contains("light-theme") || localStorage.getItem("theme") !== "dark";

        const options = {
            series: series,
            chart: {
                type: "bar",
                height: 280,
                fontFamily: "Inter, sans-serif",
                background: "transparent",
                toolbar: { show: false },
                parentHeightOffset: 0
            },
            theme: {
                mode: isLightTheme ? "light" : "dark"
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: "45%",
                    dataLabels: {
                        position: "top",
                    },
                }
            },
            colors: ["#3b82f6"], 
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: isLightTheme ? "#64748b" : "#94a3b8",
                        fontWeight: 500,
                        fontSize: "12px"
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return "Rp" + (val/1000).toLocaleString("id-ID") + "k";
                    },
                    style: {
                        colors: isLightTheme ? "#64748b" : "#94a3b8",
                        fontSize: "11px"
                    }
                }
            },
            grid: {
                borderColor: isLightTheme ? "#f1f5f9" : "#1e293b",
                strokeDashArray: 4,
                padding: { top: 0, right: 0, bottom: 0, left: 10 }
            },
            tooltip: {
                theme: isLightTheme ? "light" : "dark",
                y: {
                    formatter: function (val) {
                        return "Rp " + val.toLocaleString("id-ID");
                    }
                }
            },
            legend: { show: false }
        };

        const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
        chart.render();
        
        window.addEventListener("theme-changed", (e) => {
            const newMode = e.detail.theme;
            const newColors = newMode === "light" ? "#64748b" : "#94a3b8";
            const newGrid = newMode === "light" ? "#f1f5f9" : "#1e293b";
            const labelColor = newMode === "light" ? "#475569" : "#cbd5e1";
            
            chart.updateOptions({
                theme: { mode: newMode },
                grid: { borderColor: newGrid },
                xaxis: { labels: { style: { colors: newColors } } },
                yaxis: { labels: { style: { colors: newColors } } },
                dataLabels: { style: { colors: [labelColor] } }
            });
        });
    });
    </script>';
}

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
require __DIR__ . '/../../layouts/app.php';
