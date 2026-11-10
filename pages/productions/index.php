<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
$branchId = activeBranchId();
$filterDate = $_GET['date'] ?? '';

$query = "SELECT dp.*, u.name as user_name FROM daily_productions dp LEFT JOIN users u ON dp.user_id = u.id WHERE dp.branch_id = ?";
$params = [$branchId];
if ($filterDate) { $query .= " AND DATE(dp.date) = ?"; $params[] = $filterDate; }
$query .= " ORDER BY dp.date DESC";

$pagination = paginate($pdo, $query, $params, 10);
$productions = $pagination['data'];

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND (is_live_brew = 0 OR is_live_brew IS NULL) ORDER BY sort_order")->fetchAll();

// Pre-fetch items and total items for each production
$allProductionItems = [];
foreach ($productions as &$p) {
    $itemsStmt = $pdo->prepare("SELECT product_id, quantity_produced FROM daily_production_items WHERE daily_production_id = ?");
    $itemsStmt->execute([$p['id']]);
    $items = $itemsStmt->fetchAll();
    $pMap = [];
    $total = 0;
    foreach ($items as $it) {
        $pMap[$it['product_id']] = (int)$it['quantity_produced'];
        $total += (int)$it['quantity_produced'];
    }
    $p['total_items'] = $total;
    $allProductionItems[$p['id']] = $pMap;
}
unset($p);

$pageTitle = 'Riwayat Input Produksi (Stok)';
ob_start();
?>
<!-- Date Filter Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="<?= url('/admin/productions') ?>"
            style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                <label class="form-label" style="font-weight: 600;">Filter Tanggal</label>
                <div class="flatpickr-input-container">
                    <input type="text" name="date" id="filter_date" class="form-input datepicker"
                        value="<?= e($filterDate) ?>" placeholder="Pilih tanggal...">
                </div>
            </div>
            <div class="form-group" style="display: flex; gap: 8px; margin-bottom: 0;">
                <button type="submit" class="btn btn-primary" style="height: 42px;">Filter</button>
                <a href="<?= url('/admin/productions') ?>" class="btn btn-secondary"
                    style="height: 42px; display: flex; align-items: center;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table Header & Action Toolbar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Data Produksi Harian</h3>
    <?php if (auth_user()['role'] !== 'owner'): ?>
    <div style="display: flex; gap: 8px;">
        <form id="deleteAll-productions" method="POST" action="<?= url('/admin/productions/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none; height:36px; padding: 0 14px; border-radius:8px; font-weight:600; display:flex; align-items:center; gap:6px;" onclick="confirmDeleteAll('deleteAll-productions')">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddProductionModal()" class="btn btn-primary flex-center btn-sm" style="gap:0.5rem; height:36px; padding: 0 14px; border-radius:8px;">
            <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg> Input Produksi Baru
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-container" style="width: 100%;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Diinput Oleh</th>
                        <th style="text-align: center;">Total Items Diproduksi</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productions)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Belum ada data produksi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productions as $prod): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($prod['date'])) ?></td>
                                <td><?= e($prod['user_name']) ?></td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?= $prod['total_items'] ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <?php if (auth_user()['role'] !== 'owner'): ?>
                                        <button type="button" onclick='openAddStockModal(<?= json_encode($prod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($allProductionItems[$prod['id']] ?? []) ?>)' class="btn btn-outline-success btn-sm flex-center" title="Tambah Stok Tambahan" style="border-color: #22c55e; color: #22c55e;">
                                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                            Tambah
                                        </button>
                                        <?php endif; ?>
                                        <a href="<?= url('/admin/productions/show?id=' . $prod['id']) ?>" class="btn btn-outline-info btn-sm flex-center" title="Detail">
                                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            Detail
                                        </a>
                                        <?php if (auth_user()['role'] !== 'owner'): ?>
                                        <button type="button" onclick='openEditProductionModal(<?= json_encode($prod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($allProductionItems[$prod['id']] ?? []) ?>)' class="btn btn-outline-primary btn-sm flex-center" title="Edit">
                                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            Edit
                                        </button>
                                        <form method="POST" action="<?= url('/admin/productions/delete') ?>" style="display:inline-block; margin: 0;" data-confirm="Yakin ingin menghapus data ini?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm flex-center" title="Hapus">
                                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                Hapus
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($pagination['last_page']) && $pagination['last_page'] > 1): ?>
            <div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: center;">
                <?= render_pagination($pagination) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL INPUT PRODUKSI BARU -->
<div class="app-modal-overlay" id="addProductionModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Input Stok Produksi Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addProductionModal">✕</button>
        </div>
        <form action="<?= url('/admin/productions/store') ?>" method="POST" id="add-production-form">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <div class="form-group" style="max-width:260px;">
                    <label class="form-label">Tanggal Produksi *</label>
                    <input type="date" name="date" class="form-input datepicker" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div style="font-weight:600; font-size:0.9rem; margin-bottom:10px; color:var(--text-primary);">Jumlah Produksi per Produk:</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:4px;">
                    <?php foreach ($products as $prod): ?>
                        <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; background:rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.85rem; font-weight:600;"><?= e($prod['name']) ?></span>
                            <input type="number" name="items[<?= $prod['id'] ?>]" class="form-input add-prod-input" min="0" value="0" style="width:80px; text-align:center;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px; padding:12px 16px; background:rgba(34,197,94,0.06); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600; color:#22c55e;">Total Items Produksi:</span>
                    <span id="add-total-prod" style="font-size:1.1rem; font-weight:700; color:#22c55e;">0</span>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addProductionModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Produksi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH STOK TAMBAHAN -->
<div class="app-modal-overlay" id="addStockModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Stok Produksi Tambahan
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addStockModal">✕</button>
        </div>
        <form action="<?= url('/admin/productions/store-additional') ?>" method="POST" id="add-stock-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="add_stock_id">
            <div class="app-modal-body">
                <div style="padding:10px 14px; background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2); border-radius:8px; margin-bottom:16px; font-size:0.85rem; color:#166534;">
                    Tanggal Produksi: <strong id="add_stock_date_label"></strong>. Masukkan <strong>angka tambahan</strong> produksi yang ingin ditambahkan ke stok yang sudah ada.
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:4px;">
                    <?php foreach ($products as $prod): ?>
                        <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; background:rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:0.85rem; font-weight:600;"><?= e($prod['name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">Ada: <span id="current_stock_<?= $prod['id'] ?>">0</span></div>
                            </div>
                            <input type="number" name="items[<?= $prod['id'] ?>]" class="form-input" min="0" value="0" placeholder="+0" style="width:80px; text-align:center;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addStockModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Tambahkan ke Stok</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PRODUKSI -->
<div class="app-modal-overlay" id="editProductionModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Stok Produksi
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editProductionModal">✕</button>
        </div>
        <form action="<?= url('/admin/productions/update') ?>" method="POST" id="edit-production-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_prod_id">
            <div class="app-modal-body">
                <div class="form-group" style="max-width:260px;">
                    <label class="form-label">Tanggal Produksi *</label>
                    <input type="date" name="date" id="edit_prod_date" class="form-input datepicker" required>
                </div>
                <div style="font-weight:600; font-size:0.9rem; margin-bottom:10px; color:var(--text-primary);">Jumlah Produksi per Produk:</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:4px;">
                    <?php foreach ($products as $prod): ?>
                        <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; background:rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.85rem; font-weight:600;"><?= e($prod['name']) ?></span>
                            <input type="number" name="items[<?= $prod['id'] ?>]" id="edit_prod_val_<?= $prod['id'] ?>" class="form-input edit-prod-input" min="0" value="0" style="width:80px; text-align:center;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px; padding:12px 16px; background:rgba(34,197,94,0.06); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600; color:#22c55e;">Total Items Produksi:</span>
                    <span id="edit-total-prod" style="font-size:1.1rem; font-weight:700; color:#22c55e;">0</span>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editProductionModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Produksi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddProductionModal() {
    recalcAddProd();
    openModal('addProductionModal');
}

function recalcAddProd() {
    let tot = 0;
    document.querySelectorAll('.add-prod-input').forEach(i => {
        tot += parseInt(i.value) || 0;
    });
    document.getElementById('add-total-prod').textContent = tot;
}

function recalcEditProd() {
    let tot = 0;
    document.querySelectorAll('.edit-prod-input').forEach(i => {
        tot += parseInt(i.value) || 0;
    });
    document.getElementById('edit-total-prod').textContent = tot;
}

document.querySelectorAll('.add-prod-input').forEach(i => i.addEventListener('input', recalcAddProd));
document.querySelectorAll('.edit-prod-input').forEach(i => i.addEventListener('input', recalcEditProd));

function openAddStockModal(prod, items) {
    document.getElementById('add_stock_id').value = prod.id;
    document.getElementById('add_stock_date_label').textContent = prod.date ? prod.date.substring(0, 10) : '';

    <?php foreach ($products as $p): ?>
        const span_<?= $p['id'] ?> = document.getElementById('current_stock_<?= $p['id'] ?>');
        if (span_<?= $p['id'] ?>) {
            span_<?= $p['id'] ?>.textContent = items[<?= $p['id'] ?>] || 0;
        }
    <?php endforeach; ?>

    openModal('addStockModal');
}

function openEditProductionModal(prod, items) {
    document.getElementById('edit_prod_id').value = prod.id;
    document.getElementById('edit_prod_date').value = prod.date ? prod.date.substring(0, 10) : '';

    <?php foreach ($products as $p): ?>
        const val_<?= $p['id'] ?> = document.getElementById('edit_prod_val_<?= $p['id'] ?>');
        if (val_<?= $p['id'] ?>) {
            val_<?= $p['id'] ?>.value = items[<?= $p['id'] ?>] || 0;
        }
    <?php endforeach; ?>

    recalcEditProd();
    openModal('editProductionModal');
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddProductionModal();
    }
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
