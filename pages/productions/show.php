<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
$id = $_GET['id'] ?? null;
if (!$id) redirect('/admin/productions');

$stmt = $pdo->prepare("SELECT dp.*, u.name as user_name FROM daily_productions dp LEFT JOIN users u ON dp.user_id = u.id WHERE dp.id = ? AND dp.branch_id = ?");
$stmt->execute([$id, activeBranchId()]);
$production = $stmt->fetch();
if (!$production) redirect('/admin/productions');

// Get branch name
$branch = activeBranch();
$branchName = $branch ? $branch['name'] : 'Pusat';

// Get items
$itemsStmt = $pdo->prepare("SELECT dpi.*, p.name as product_name FROM daily_production_items dpi JOIN products p ON dpi.product_id = p.id WHERE dpi.daily_production_id = ? ORDER BY p.sort_order, p.name");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Get additions history
$additionsStmt = $pdo->prepare("SELECT dpa.*, u.name as user_name FROM daily_production_additions dpa LEFT JOIN users u ON dpa.user_id = u.id WHERE dpa.daily_production_id = ? ORDER BY dpa.created_at ASC");
$additionsStmt->execute([$id]);
$additions = $additionsStmt->fetchAll();

// Calculate total additions for each product
$totalAdditionsByProduct = [];
foreach ($additions as $addition) {
    $itemsData = json_decode($addition['items_data'], true) ?: [];
    foreach ($itemsData as $pId => $qty) {
        if (!isset($totalAdditionsByProduct[$pId])) $totalAdditionsByProduct[$pId] = 0;
        $totalAdditionsByProduct[$pId] += (int)$qty;
    }
}

// Map product names for JSON items_data
$allProducts = $pdo->query("SELECT id, name FROM products")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Detail Produksi Harian';
ob_start();
?>
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .print-area, .print-area * {
            visibility: visible;
        }
        .print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0 !important;
            padding: 10px !important;
            background: #ffffff !important;
            color: #1e293b !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print, .topbar, .sidebar, nav, header, footer {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
        }
    }
</style>
<div class="card mb-4 print-area" style="max-width: 900px; margin: 0 auto; background: var(--bg-card);">
    <div class="card-header" style="display: block; text-align: center; border-bottom: 2px dashed #cbd5e1; padding: 25px 20px; position: relative;">
        <img src="<?= url('/assets/images/tether-icon-head.webp') ?>" alt="Logo Tether Brew" style="height: 60px; width: auto; display: block; margin: 0 auto 10px auto;" onerror="this.src='<?= base_url('assets/images/tether-icon-head.webp') ?>'; this.onerror=null;">
        <h2 style="color: #22c55e; font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 0;">TETHER BREW</h2>
        <h3 style="font-weight: 700; color: var(--text-primary); margin: 6px 0 0; font-size: 1.25rem; letter-spacing: 0.5px; text-transform: uppercase;">DETAIL STOK PRODUKSI</h3>
        <p style="margin: 5px 0 0; color: #64748b; font-size: 10pt;">Tether Brew Office — Cabang <?= e($branchName) ?></p>
        <p style="margin: 3px 0 0; color: #94a3b8; font-size: 9pt;">Dicetak pada: <?= date('d F Y H:i') ?></p>
    </div>

    <div class="card-body" style="padding: 30px;">
        <!-- Info Section -->
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
            <div>
                <p style="margin: 0 0 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Tanggal Produksi</p>
                <p style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-primary);"><?= formatTanggal($production['date'], 'd F Y') ?></p>
            </div>
            <div>
                <p style="margin: 0 0 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Diinput Oleh</p>
                <p style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-primary);"><?= e($production['user_name']) ?></p>
            </div>
            <div>
                <p style="margin: 0 0 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase;">Cabang</p>
                <p style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text-primary);"><?= e($branchName) ?></p>
            </div>
        </div>

        <!-- Table Section -->
        <h4 style="font-weight: 700; margin-bottom: 15px; color: var(--text-primary); font-size: 1.2rem;">Rincian Produk</h4>
        <div class="table-container no-search" style="border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
            <table class="table" style="margin: 0; width: 100%;">
                <thead style="background: rgba(0,0,0,0.03);">
                    <tr>
                        <th style="padding: 15px; font-weight: 700; text-align: left; border-bottom: 2px solid var(--border-color);">No</th>
                        <th style="padding: 15px; font-weight: 700; text-align: left; border-bottom: 2px solid var(--border-color);">Nama Produk</th>
                        <th style="padding: 15px; font-weight: 700; text-align: center; border-bottom: 2px solid var(--border-color);">Jumlah Awal Diproduksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total = 0; ?>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="3" style="padding: 20px; text-align: center; color: var(--text-muted);">Tidak ada data produk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $index => $item): ?>
                            <?php 
                                $addedQty = $totalAdditionsByProduct[$item['product_id']] ?? 0;
                                $initialQty = $item['quantity_produced'] - $addedQty;
                                $total += $initialQty; 
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 15px; color: var(--text-primary);"><?= $index + 1 ?></td>
                                <td style="padding: 15px; font-weight: 600; color: var(--text-primary);"><?= e($item['product_name']) ?></td>
                                <td style="padding: 15px; text-align: center; font-weight: 600; color: #3b82f6;">
                                    <span style="background: rgba(59,130,246,0.1); padding: 5px 15px; border-radius: 20px;">
                                        <?= number_format($initialQty, 0, ',', '.') ?> Cup
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(0,0,0,0.02);">
                        <td colspan="2" style="padding: 15px; text-align: right; font-weight: 800; font-size: 1.1rem;">Total Keseluruhan :</td>
                        <td style="padding: 15px; text-align: center; font-weight: 800; font-size: 1.2rem; color: #22c55e;"><?= number_format($total, 0, ',', '.') ?> Cup</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($additions)): ?>
        <h4 style="font-weight: 700; margin-bottom: 15px; margin-top: 30px; color: var(--text-primary); font-size: 1.2rem;">Riwayat Tambahan Produksi Hari Ini</h4>
        <div class="table-container no-search" style="border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
            <table class="table" style="margin: 0; width: 100%;">
                <thead style="background: rgba(34, 197, 94, 0.05);">
                    <tr>
                        <th style="padding: 15px; font-weight: 700; text-align: left; border-bottom: 2px solid var(--border-color); width: 15%;">Waktu & Oleh</th>
                        <th style="padding: 15px; font-weight: 700; text-align: left; border-bottom: 2px solid var(--border-color); width: 45%;">Detail Tambahan Item</th>
                        <th style="padding: 15px; font-weight: 700; text-align: left; border-bottom: 2px solid var(--border-color); width: 40%;">Catatan / Penjelasan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($additions as $addition): ?>
                        <?php 
                            $itemsData = json_decode($addition['items_data'], true) ?: []; 
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px; vertical-align: top;">
                                <div style="font-weight: 700; color: #166534; margin-bottom: 4px;"><?= date('H:i', strtotime($addition['created_at'])) ?> WIB</div>
                                <div style="color: var(--text-muted); font-size: 0.9rem;">Oleh: <?= e($addition['user_name']) ?></div>
                            </td>
                            <td style="padding: 15px; vertical-align: top;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($itemsData as $pId => $qty): ?>
                                        <?php $pName = $allProducts[$pId] ?? 'Produk ' . $pId; ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--border-color); padding-bottom: 6px;">
                                            <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;"><?= e($pName) ?></span>
                                            <span style="background: rgba(59,130,246,0.1); padding: 4px 12px; border-radius: 20px; color: #3b82f6; font-weight: 600; font-size: 0.85rem;">
                                                <?= number_format($qty, 0, ',', '.') ?> Cup
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td style="padding: 15px; vertical-align: top; color: var(--text-primary); font-size: 0.95rem;">
                                <span style="font-style: italic;">"<?= nl2br(e($addition['note'])) ?>"</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Grand Total Section -->
        <div style="background: rgba(14, 165, 233, 0.05); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: 12px; padding: 25px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4 style="margin: 0; font-weight: 800; color: #0369a1; font-size: 1.25rem;">Total Akhir Produksi Hari Ini</h4>
                <p style="margin: 5px 0 0; color: var(--text-muted); font-size: 0.95rem;">(Total Jumlah Awal + Seluruh Tambahan Stok)</p>
            </div>
            <?php 
                $grandTotal = 0;
                foreach ($items as $itm) {
                    $grandTotal += $itm['quantity_produced'];
                }
            ?>
            <div style="font-size: 2rem; font-weight: 800; color: #0ea5e9;">
                <?= number_format($grandTotal, 0, ',', '.') ?> <span style="font-size: 1.2rem; color: #0284c7;">Cup</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="no-print" style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 40px;">
            <a href="<?= url('/admin/productions') ?>" class="btn btn-secondary" style="height: 44px; padding: 0 25px; display: flex; align-items: center; font-weight: 600; gap: 8px;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Kembali
            </a>
            <button onclick="window.print()" class="btn btn-primary" style="height: 44px; padding: 0 25px; display: flex; align-items: center; font-weight: 600; gap: 8px; background-color: #0ea5e9; border-color: #0ea5e9;">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print Out
            </button>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
