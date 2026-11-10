<?php
/**
 * Owner Dashboard
 * Sumber: DashboardController.php#ownerDashboard
 */
$branchId = activeBranchId();

// Riders for this branch
$riders = $pdo->prepare("SELECT * FROM users WHERE role = 'rider' AND branch_id = ?");
$riders->execute([$branchId]);
$riders = $riders->fetchAll();
$totalRiders = count($riders);

// Month revenue from RiderDailySale
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$monthRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(total_gross_income), 0) as total FROM rider_daily_sales WHERE branch_id = ? AND date BETWEEN ? AND ?");
$monthRevenueStmt->execute([$branchId, $monthStart, $monthEnd]);
$monthRevenue = $monthRevenueStmt->fetch()['total'];

$monthCupsStmt = $pdo->prepare("
    SELECT COALESCE(SUM(rdsi.stock_sold), 0) as total 
    FROM rider_daily_sale_items rdsi 
    JOIN rider_daily_sales rds ON rdsi.rider_daily_sale_id = rds.id 
    WHERE rds.branch_id = ? AND rds.date BETWEEN ? AND ?
");
$monthCupsStmt->execute([$branchId, $monthStart, $monthEnd]);
$monthCups = $monthCupsStmt->fetch()['total'];

$todayRevenueStmt = $pdo->prepare("SELECT COALESCE(SUM(total_gross_income), 0) as total FROM rider_daily_sales WHERE branch_id = ? AND date = CURDATE()");
$todayRevenueStmt->execute([$branchId]);
$todayRevenue = $todayRevenueStmt->fetch()['total'];

// Rider Performance (30 days)
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$today = date('Y-m-d');
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
$riderPerformance = array_map(fn($r) => [
    'id'      => $r['id'],
    'name'    => $r['name'],
    'cups'    => (int)$r['cups'],
    'revenue' => (int)$r['revenue'],
], $perfStmt->fetchAll());

// Daily revenue trend (14 days)
$dailyRevenueStmt = $pdo->prepare("
    SELECT date, SUM(total_gross_income) as revenue, COUNT(*) as count
    FROM rider_daily_sales
    WHERE branch_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY date ORDER BY date
");
$dailyRevenueStmt->execute([$branchId]);
$dailyRevenue = $dailyRevenueStmt->fetchAll();

// Payment method distribution
$monthSalesStmt = $pdo->prepare("SELECT COALESCE(SUM(cash_amount), 0) as total_cash, COALESCE(SUM(qris_amount), 0) as total_qris FROM rider_daily_sales WHERE branch_id = ? AND date BETWEEN ? AND ?");
$monthSalesStmt->execute([$branchId, $monthStart, $monthEnd]);
$paymentData = $monthSalesStmt->fetch();
$totalCash = $paymentData['total_cash'];
$totalQris = $paymentData['total_qris'];

// Top products (30 days)
$topProductsStmt = $pdo->prepare("
    SELECT rdsi.product_id, p.name, SUM(rdsi.stock_sold) as total_sold
    FROM rider_daily_sale_items rdsi
    JOIN rider_daily_sales rds ON rdsi.rider_daily_sale_id = rds.id
    JOIN products p ON rdsi.product_id = p.id
    WHERE rds.branch_id = ? AND rds.date BETWEEN ? AND ?
    GROUP BY rdsi.product_id, p.name ORDER BY total_sold DESC LIMIT 8
");
$topProductsStmt->execute([$branchId, $thirtyDaysAgo, $today]);
$topProducts = $topProductsStmt->fetchAll();

// Journal summary
$journalStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit,
        COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit
    FROM journals
    WHERE branch_id = ? AND date BETWEEN ? AND ?
");
$journalStmt->execute([$branchId, $monthStart, $monthEnd]);
$jRow = $journalStmt->fetch();
$journalDebit = $jRow['total_debit'];
$journalCredit = $jRow['total_credit'];
$journalBalance = $journalDebit - $journalCredit;

$pageTitle = 'Dashboard Owner';

ob_start();
?>

    <div class="stats-grid">
        <div class="stat-card" style="display: flex; flex-direction: column; justify-content: center;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Metode Pembayaran
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="width: 70px;">
                    <?php if($totalCash + $totalQris > 0): ?>
                        <div id="payment-donut-chart"></div>
                    <?php else: ?>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Tidak ada</div>
                    <?php endif; ?>
                </div>
                <div
                    style="display: flex; flex-direction: column; gap: 4px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span
                            style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #8b5c2a;"></span>
                        Cash
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span
                            style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #22c55e;"></span>
                        QRIS
                    </div>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #22c55e; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="8" />
                    <line x1="12" x2="12" y1="16" y2="8" />
                    <line x1="10" x2="14" y1="10" y2="10" />
                    <line x1="10" x2="14" y1="14" y2="14" />
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2;">Rp <?= number_format($monthRevenue, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Pendapatan Bulan Ini</div>
        </div>
        <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #8b5c2a; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1" />
                    <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" />
                    <line x1="6" x2="6" y1="2" y2="4" />
                    <line x1="10" x2="10" y1="2" y2="4" />
                    <line x1="14" x2="14" y1="2" y2="4" />
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2;"><?= number_format($monthCups, 0, ',', '.') ?> <small style="font-size:0.6em;opacity:0.7;">cup</small></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Cup Terjual Bulan Ini</div>
        </div>
        <div class="stat-card stat-card-animated" style="position: relative; overflow: hidden; z-index: 1; padding-top: 40px;">
            <div class="stat-icon-bg" style="position: absolute; top: -25px; left: -20px; width: 130px; height: 130px; opacity: 0.15; color: #3b82f6; z-index: -1;">
                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2" />
                    <line x1="16" x2="16" y1="2" y2="6" />
                    <line x1="8" x2="8" y1="2" y2="6" />
                    <line x1="3" x2="21" y1="10" y2="10" />
                </svg>
            </div>
            <div class="stat-value" style="position: relative; z-index: 2;">Rp <?= number_format($todayRevenue, 0, ',', '.') ?></div>
            <div class="stat-label" style="position: relative; z-index: 2;">Pendapatan Hari Ini</div>
        </div>
    </div>


    <div class="mt-2-custom">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-0.25em;">
                        <path d="M3 3v18h18" />
                        <path d="M18 17V9" />
                        <path d="M13 17V5" />
                        <path d="M8 17v-3" />
                    </svg>
                    Performance Rider (30 Hari)
                </h3>
            </div>
            <div class="card-body">
                <?php if(count($riderPerformance) > 0): ?>
                    <div id="rider-performance-chart"></div>
                <?php else: ?>
                    <div class="empty-state p-5-custom">
                        <div class="empty-state-text">Belum ada data penjualan rider</div>
                    </div>
                <?php endif; ?>
                </div>
                </div>
                </div>

    <div class="grid-2 mt-2-custom">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-0.25em;">
                        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                        <polyline points="16 7 22 7 22 13" />
                    </svg>
                    Tren Pendapatan (14 Hari)
                </h3>
                </div>
            <div class="card-body">
                <?php if(count($dailyRevenue) > 0): ?>
                    <div id="revenue-trend-chart"></div>
                <?php else: ?>
                    <div class="empty-state p-5-custom">
                        <div class="empty-state-text">Belum ada data pendapatan</div>
                    </div>
                <?php endif; ?>
            </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-0.25em;">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
                            <path d="M4 22h16" />
                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
                        </svg>
                        Produk Terlaris (30 Hari)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if(count($topProducts) > 0): ?>
                    <div id="top-products-chart"></div>
                <?php else: ?>
                        <div class="empty-state p-5-custom">
                            <div class="empty-state-text">Belum ada data produk</div>
                        </div>
                    <?php endif; ?>
                    </div>
                    </div>
                    </div>

                    <div class="card mt-5-custom" id="rider-performance-section">
                        <div class="card-header print-hide">
                            <h3 class="card-title">
                                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-0.25em;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                                Daftar Rider & Performance
                            </h3>
                        </div>
                        <div class="card-body card-body-no-padding">
                            
                            <div class="print-title" style="display: none;">
                                <h2>Laporan Performa Rider</h2>
                                <p>Dicetak pada: <?= date('d/m/Y H:i') ?> WIB</p>
                            </div>

                            <?php if(count($riderPerformance) > 0): ?>
                                            <div class="table-container" id="rider-performance-table-container">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Nama Rider</th>
                                                            <th style="text-align: right;">Cup Terjual (30hr)</th>
                                                            <th style="text-align: right;">Pendapatan (30hr)</th>
                                                            <th style="text-align: center;">Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="owner-rider-table-body">
                                                        <?php foreach($riderPerformance as $i => $rp): ?>
                                                            <tr class="owner-rider-row">
                                                                <td>
                                                                    <?php if($i === 0): ?>
                                                                        <span
                                                                            style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#d4a24e,#8b5c2a);color:white;font-weight:700;font-size:0.8rem;"><?= $i + 1 ?></span>
                                                                    <?php elseif($i === 1): ?>
                                                                        <span
                                                                            style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#94a3b8,#64748b);color:white;font-weight:700;font-size:0.8rem;"><?= $i + 1 ?></span>
                                                                    <?php elseif($i === 2): ?>
                                                                        <span
                                                                            style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#d97706,#a16207);color:white;font-weight:700;font-size:0.8rem;"><?= $i + 1 ?></span>
                                                                    <?php else: ?>
                                                                        <span style="color:var(--text-muted);"><?= $i + 1 ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-primary-semi" style="font-weight: 600;"><?= e($rp['name']) ?></td>
                                                                <td style="text-align: right;">
                                                                    <span style="font-weight:600;"><?= number_format($rp['cups'], 0, ',', '.') ?></span>
                                                                    <small style="color:var(--text-muted);"> cup</small>
                                                                </td>
                                                                <td style="text-align: right;">
                                                                    <span class="text-gold-semi" style="font-weight:600;">Rp
                                                                        <?= number_format($rp['revenue'], 0, ',', '.') ?></span>
                                                                </td>
                                                                <td style="text-align: center;">
                                                                    <a href="<?= url('/owner/rider-performance?rider_id=' . $rp['id']) ?>"
                                                                        class="btn btn-sm btn-primary" style="gap:0.3rem;font-size:0.8rem;">
                                                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                                            <circle cx="12" cy="12" r="3" />
                                                                        </svg>
                                                                        Detail
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                </div>
                                <div class="pagination-container print-hide" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                                        Menampilkan <span id="rider-page-info"></span> dari <?= count($riderPerformance) ?> rider
                                    </div>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="changeRiderPage(-1)" id="rider-prev-btn" class="btn btn-sm" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary);">Sebelumnya</button>
                                        <div id="rider-page-numbers" style="display: flex; gap: 5px;"></div>
                                        <button onclick="changeRiderPage(1)" id="rider-next-btn" class="btn btn-sm" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary);">Selanjutnya</button>
                                    </div>
                                </div>
                            <?php else: ?>
                <div class="empty-state p-5-custom">
                    <div class="empty-state-text">Belum ada data rider</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php
$content = ob_get_clean();

$extraScripts = '
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const isLight = document.documentElement.classList.contains("light-theme");
        const textColor = isLight ? "#475569" : "#94a3b8";
        const gridColor = isLight ? "#e2e8f0" : "#1e293b";

        const brownPrimary = "#8b5c2a";
        const brownLight = "#c49a5c";
        const greenPrimary = "#22c55e";
        const greenLight = "#4ade80";
        const brownGradient = ["#8b5c2a", "#a16b35", "#b87d42", "#c49a5c", "#d4b07a"];
        const themeColors = ["#8b5c2a", "#22c55e", "#c49a5c", "#4ade80", "#d4a24e", "#16a34a", "#a16207", "#15803d"];

        // Chart 1: Rider Performance
        ' . (count($riderPerformance) > 0 ? '
        const riderData = ' . json_encode($riderPerformance) . ';
        new ApexCharts(document.querySelector("#rider-performance-chart"), {
            series: [{ name: "Cup Terjual", data: riderData.map(r => r.cups) }],
            chart: { type: "bar", height: 350, fontFamily: "Poppins, sans-serif", background: "transparent", toolbar: { show: false } },
            theme: { mode: isLight ? "light" : "dark" },
            plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: "end", columnWidth: "55%", distributed: true } },
            colors: riderData.map((_, i) => i % 2 === 0 ? brownPrimary : greenPrimary),
            fill: { type: "gradient", gradient: { shade: "dark", type: "vertical", shadeIntensity: 0.3, opacityFrom: 1, opacityTo: 0.85, stops: [0, 100] } },
            dataLabels: { enabled: false },
            xaxis: { categories: riderData.map(r => r.name), labels: { style: { colors: textColor, fontWeight: 600 } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: val => val + " cup", style: { colors: textColor } } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            tooltip: { theme: "dark", y: { formatter: function (val, { dataPointIndex }) { return val + " cup — Rp " + riderData[dataPointIndex].revenue.toLocaleString("id-ID"); } } },
            legend: { show: false }
        }).render();' : '') . '

        // Chart 2: Payment Donut
        ' . (($totalCash + $totalQris > 0) ? '
        new ApexCharts(document.querySelector("#payment-donut-chart"), {
            series: [' . $totalCash . ', ' . $totalQris . '],
            chart: { type: "donut", height: 70, fontFamily: "Poppins, sans-serif", background: "transparent", sparkline: { enabled: true } },
            theme: { mode: isLight ? "light" : "dark" },
            labels: ["Cash", "QRIS"],
            colors: [brownPrimary, greenPrimary],
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: "60%" } } },
            dataLabels: { enabled: false },
            tooltip: { theme: "dark", y: { formatter: val => "Rp " + val.toLocaleString("id-ID") } }
        }).render();' : '') . '

        // Chart 3: Revenue Trend
        ' . (count($dailyRevenue) > 0 ? '
        const dailyData = ' . json_encode($dailyRevenue) . ';
        const translatedDays = { "Monday": "Sen", "Tuesday": "Sel", "Wednesday": "Rab", "Thursday": "Kam", "Friday": "Jum", "Saturday": "Sab", "Sunday": "Min" };
        new ApexCharts(document.querySelector("#revenue-trend-chart"), {
            series: [{ name: "Pendapatan", data: dailyData.map(d => parseInt(d.revenue)) }],
            chart: { type: "area", height: 320, fontFamily: "Poppins, sans-serif", background: "transparent", toolbar: { show: false }, sparkline: { enabled: false } },
            theme: { mode: isLight ? "light" : "dark" },
            colors: [brownPrimary],
            fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
            stroke: { curve: "smooth", width: 3 },
            markers: { size: 5, colors: [brownPrimary], strokeColors: isLight ? "#fff" : "#1e293b", strokeWidth: 2, hover: { size: 7 } },
            xaxis: {
                categories: dailyData.map(d => {
                    const dayName = new Date(d.date).toLocaleDateString("en-US", { weekday: "long" });
                    const dateStr = new Date(d.date).toLocaleDateString("id-ID", { day: "numeric", month: "short" });
                    return (translatedDays[dayName] || dayName.substring(0, 3)) + " " + dateStr;
                }),
                labels: { style: { colors: textColor, fontSize: "11px" }, rotate: -45, rotateAlways: dailyData.length > 7 },
                axisBorder: { show: false }, axisTicks: { show: false }
            },
            yaxis: { labels: { formatter: val => "Rp" + (val / 1000).toLocaleString("id-ID") + "k", style: { colors: textColor } } },
            grid: { borderColor: gridColor, strokeDashArray: 4 },
            tooltip: { theme: "dark", y: { formatter: val => "Rp " + val.toLocaleString("id-ID") } }
        }).render();' : '') . '

        // Chart 4: Top Products
        ' . (count($topProducts) > 0 ? '
        const productData = ' . json_encode($topProducts) . ';
        new ApexCharts(document.querySelector("#top-products-chart"), {
            series: [{ name: "Terjual", data: productData.map(p => parseInt(p.total_sold)) }],
            chart: { type: "bar", height: Math.max(250, productData.length * 50), fontFamily: "Poppins, sans-serif", background: "transparent", toolbar: { show: false } },
            theme: { mode: isLight ? "light" : "dark" },
            plotOptions: { bar: { borderRadius: 8, horizontal: true, barHeight: "60%", distributed: true, dataLabels: { position: "top" } } },
            colors: themeColors,
            dataLabels: { enabled: true, formatter: val => val + " cup", offsetX: 30, style: { colors: [textColor], fontSize: "12px", fontWeight: 600 } },
            xaxis: { categories: productData.map(p => p.name ? p.name : "Unknown"), labels: { formatter: val => val + " cup", style: { colors: textColor } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontWeight: 600, fontSize: "12px" } } },
            grid: { borderColor: gridColor, strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: "dark", y: { formatter: val => val + " cup terjual" } },
            legend: { show: false }
        }).render();' : '') . '

        window.addEventListener("theme-changed", function () { location.reload(); });

        const rRowsPerPage = 10;
        let rCurrentPage = 1;
        const rRows = document.querySelectorAll(".owner-rider-row");
        
        if (rRows.length > 0) {
            const rTotalPages = Math.ceil(rRows.length / rRowsPerPage);

            function displayRiderRows() {
                const start = (rCurrentPage - 1) * rRowsPerPage;
                const end = start + rRowsPerPage;
                rRows.forEach((row, index) => {
                    if (index >= start && index < end) { row.style.display = ""; } else { row.style.display = "none"; }
                });
                const pageInfo = document.getElementById("rider-page-info");
                if (pageInfo) { const endRow = Math.min(end, rRows.length); pageInfo.textContent = `${start + 1} - ${endRow}`; }
                const prevBtn = document.getElementById("rider-prev-btn");
                const nextBtn = document.getElementById("rider-next-btn");
                if (prevBtn) { prevBtn.disabled = rCurrentPage === 1; prevBtn.style.opacity = rCurrentPage === 1 ? "0.5" : "1"; }
                if (nextBtn) { nextBtn.disabled = rCurrentPage === rTotalPages; nextBtn.style.opacity = rCurrentPage === rTotalPages ? "0.5" : "1"; }
                renderRiderPageNumbers();
            }

            function renderRiderPageNumbers() {
                const container = document.getElementById("rider-page-numbers");
                if (!container) return;
                container.innerHTML = "";
                let startPage = Math.max(1, rCurrentPage - 2);
                let endPage = Math.min(rTotalPages, startPage + 4);
                if (endPage - startPage < 4) { startPage = Math.max(1, endPage - 4); }
                for (let i = startPage; i <= endPage; i++) {
                    const btn = document.createElement("button");
                    btn.type = "button"; btn.className = "btn btn-sm"; btn.textContent = i;
                    if (i === rCurrentPage) {
                        btn.style.background = "#8b5c2a"; btn.style.color = "white"; btn.style.border = "1px solid #8b5c2a";
                    } else {
                        btn.style.background = "white"; btn.style.color = "#475569"; btn.style.border = "1px solid #cbd5e1";
                    }
                    btn.onclick = (e) => { e.preventDefault(); rCurrentPage = i; displayRiderRows(); };
                    container.appendChild(btn);
                }
            }

            window.changeRiderPage = function(step) {
                rCurrentPage += step;
                if (rCurrentPage < 1) rCurrentPage = 1;
                if (rCurrentPage > rTotalPages) rCurrentPage = rTotalPages;
                displayRiderRows();
            };

            displayRiderRows();
        }
    });
</script>
<style>
    .stats-grid { gap: 16px; }
    .stat-card-animated { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; }
    .stat-card-animated:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important; }
    .stat-card-animated .stat-icon-bg { transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }
    .stat-card-animated:hover .stat-icon-bg { transform: scale(1.1) rotate(-5deg); opacity: 0.25 !important; }

    @media print {
        @page { margin: 15mm; }
        body { font-size: 11pt; background: white !important; }
        body * { visibility: hidden; }
        #rider-performance-section, #rider-performance-section * { visibility: visible; }
        #rider-performance-section { position: absolute; left: 0; top: 0; width: 100%; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
        .print-title { display: block !important; text-align: center; padding-bottom: 15px; border-bottom: 2px solid #cbd5e1; margin-bottom: 20px !important; }
        .print-title h2 { margin: 0; color: #8b5c2a; font-size: 16pt; }
        .print-title p { margin: 5px 0 0 0; color: #64748b; font-size: 10pt; }
        .print-hide, form[method="GET"], .pagination-container { display: none !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        .owner-rider-row { display: table-row !important; }
        th { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1 !important; padding: 10px 8px !important; font-weight: bold !important; font-size: 9.5pt !important; }
        td { border-bottom: 1px solid #e2e8f0 !important; padding: 8px !important; font-size: 10pt !important; }
        th:last-child, td:last-child { display: none !important; }
    }
</style>
';

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
require __DIR__ . '/../../layouts/app.php';
