<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_bar_admin_owner();

$branchId = activeBranchId();
$filterDate = $_GET['date'] ?? '';

$query = "SELECT sp.*, u.name as user_name FROM spoiled_products sp LEFT JOIN users u ON sp.user_id = u.id WHERE sp.branch_id = ?";
$params = [$branchId];
if ($filterDate) {
    $query .= " AND DATE(sp.date) = ?";
    $params[] = $filterDate;
}
$query .= " ORDER BY sp.date DESC";

$pagination = paginate($pdo, $query, $params, 10);
$spoiledProducts = $pagination['data'];

$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND (is_live_brew = 0 OR is_live_brew IS NULL) ORDER BY sort_order")->fetchAll();

$pageTitle = 'Data Produk Basi';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" action="<?= url('/admin/spoiled-products') ?>" style="display:flex; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0; flex:1;">
                <label class="form-label">Filter Tanggal</label>
                <input type="date" name="date" class="form-input datepicker" value="<?= e($filterDate) ?>">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm" style="height:42px;"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Filter</button>
            <?php if ($filterDate): ?><a href="<?= url('/admin/spoiled-products') ?>" class="btn btn-outline-secondary btn-sm" style="height:42px; line-height:30px;">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">Data Produk Basi</h3>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-spoiled" method="POST" action="<?= url('/admin/spoiled-products/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none; height:36px; padding: 0 14px; border-radius:8px; font-weight:600; display:flex; align-items:center; gap:6px;" onclick="confirmDeleteAll('deleteAll-spoiled')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddSpoiledModal()" class="btn btn-primary btn-sm flex-center" style="height:36px; padding: 0 14px; border-radius:8px; gap:0.5rem;">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Input Produk Basi
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($spoiledProducts)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Tidak ada data produk basi</h3>
                <button type="button" onclick="openAddSpoiledModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Input Produk Basi
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Diinput Oleh</th>
                            <th>Total Item</th>
                            <th>Detail Basi</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($spoiledProducts as $sp):
                        $itemsStmt = $pdo->prepare("SELECT spi.*, prod.name FROM spoiled_product_items spi JOIN products prod ON spi.product_id=prod.id WHERE spi.spoiled_product_id=?");
                        $itemsStmt->execute([$sp['id']]);
                        $items = $itemsStmt->fetchAll();
                        $totalItems = array_sum(array_column($items, 'quantity'));
                        $itemMap = [];
                        foreach ($items as $it) {
                            $itemMap[$it['product_id']] = (int)$it['quantity'];
                        }
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?= date('d M Y', strtotime($sp['date'])) ?></td>
                            <td><?= e($sp['user_name']) ?></td>
                            <td><span class="badge badge-warning"><?= $totalItems ?> cup</span></td>
                            <td style="font-size:12px; color:var(--text-muted);">
                                <?php
                                $descParts = [];
                                foreach ($items as $i) {
                                    if ($i['quantity'] > 0) $descParts[] = e($i['name']) . ' (' . $i['quantity'] . ')';
                                }
                                echo !empty($descParts) ? implode(', ', $descParts) : '-';
                                ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditSpoiledModal(<?= json_encode($sp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($itemMap) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/admin/spoiled-products/delete') ?>" data-confirm="Yakin hapus data produk basi tanggal ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $sp['id'] ?>">
                                        <button type="submit" class="btn-delete-outline" title="Hapus">
                                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:16px;"><?= render_pagination($pagination) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH PRODUK BASI -->
<div class="app-modal-overlay" id="addSpoiledModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Input Produk Basi
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addSpoiledModal">✕</button>
        </div>
        <form action="<?= url('/admin/spoiled-products/store') ?>" method="POST" id="add-spoiled-form">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <div class="form-group" style="max-width:260px;">
                    <label class="form-label">Tanggal Basi *</label>
                    <input type="date" name="date" class="form-input datepicker" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div style="font-weight:600; font-size:0.9rem; margin-bottom:10px; color:var(--text-primary);">Jumlah Produk Basi (Cup):</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:4px;">
                    <?php foreach ($products as $prod): ?>
                        <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; background:rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.85rem; font-weight:600;"><?= e($prod['name']) ?></span>
                            <input type="number" name="items[<?= $prod['id'] ?>]" class="form-input add-spoiled-input" min="0" value="0" style="width:80px; text-align:center;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px; padding:12px 16px; background:rgba(239,68,68,0.06); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600; color:#ef4444;">Total Cup Basi:</span>
                    <span id="add-total-spoiled" style="font-size:1.1rem; font-weight:700; color:#ef4444;">0 cup</span>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addSpoiledModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Produk Basi</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PRODUK BASI -->
<div class="app-modal-overlay" id="editSpoiledModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Produk Basi
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editSpoiledModal">✕</button>
        </div>
        <form action="<?= url('/admin/spoiled-products/update') ?>" method="POST" id="edit-spoiled-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_spoiled_id">
            <div class="app-modal-body">
                <div class="form-group" style="max-width:260px;">
                    <label class="form-label">Tanggal Basi *</label>
                    <input type="date" name="date" id="edit_spoiled_date" class="form-input datepicker" required>
                </div>
                <div style="font-weight:600; font-size:0.9rem; margin-bottom:10px; color:var(--text-primary);">Jumlah Produk Basi (Cup):</div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; max-height:360px; overflow-y:auto; padding:4px;">
                    <?php foreach ($products as $prod): ?>
                        <div style="padding:10px; border:1px solid var(--border-color); border-radius:8px; background:rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.85rem; font-weight:600;"><?= e($prod['name']) ?></span>
                            <input type="number" name="items[<?= $prod['id'] ?>]" id="edit_prod_<?= $prod['id'] ?>" class="form-input edit-spoiled-input" min="0" value="0" style="width:80px; text-align:center;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px; padding:12px 16px; background:rgba(239,68,68,0.06); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600; color:#ef4444;">Total Cup Basi:</span>
                    <span id="edit-total-spoiled" style="font-size:1.1rem; font-weight:700; color:#ef4444;">0 cup</span>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editSpoiledModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Produk Basi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddSpoiledModal() {
    recalcAddSpoiled();
    openModal('addSpoiledModal');
}

function recalcAddSpoiled() {
    let tot = 0;
    document.querySelectorAll('.add-spoiled-input').forEach(i => {
        tot += parseInt(i.value) || 0;
    });
    document.getElementById('add-total-spoiled').textContent = tot + ' cup';
}

function recalcEditSpoiled() {
    let tot = 0;
    document.querySelectorAll('.edit-spoiled-input').forEach(i => {
        tot += parseInt(i.value) || 0;
    });
    document.getElementById('edit-total-spoiled').textContent = tot + ' cup';
}

document.querySelectorAll('.add-spoiled-input').forEach(i => i.addEventListener('input', recalcAddSpoiled));
document.querySelectorAll('.edit-spoiled-input').forEach(i => i.addEventListener('input', recalcEditSpoiled));

function openEditSpoiledModal(sp, itemMap) {
    document.getElementById('edit_spoiled_id').value = sp.id;
    document.getElementById('edit_spoiled_date').value = sp.date ? sp.date.substring(0, 10) : '';

    <?php foreach ($products as $prod): ?>
        const input_<?= $prod['id'] ?> = document.getElementById('edit_prod_<?= $prod['id'] ?>');
        if (input_<?= $prod['id'] ?>) {
            input_<?= $prod['id'] ?>.value = itemMap[<?= $prod['id'] ?>] || 0;
        }
    <?php endforeach; ?>

    recalcEditSpoiled();
    openModal('editSpoiledModal');
}

// Auto open on query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddSpoiledModal();
    }
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
