<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM products";
$params = [];
if ($search) {
    $query .= " WHERE name LIKE ? OR category LIKE ? OR description LIKE ?";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}
$query .= " ORDER BY sort_order ASC, id DESC";

$pagination = paginate($pdo, $query, $params, 10);
$products = $pagination['data'];
$pageTitle = 'Kelola Produk';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Cari produk..." style="width: 250px;">
        <button type="submit" class="btn btn-secondary btn-sm"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
        <?php if ($search): ?><a href="<?= url('/products') ?>" class="btn btn-outline-secondary btn-sm" style="display: flex; align-items: center;">Reset</a><?php endif; ?>
    </form>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-products" method="POST" action="<?= url('/products/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-products')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddProductModal()" class="btn btn-primary btn-sm">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Produk
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($products)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <div style="font-size:48px; margin-bottom:12px;">
                    <svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                </div>
                <h3>Belum Ada Produk</h3>
                <button type="button" onclick="openAddProductModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Produk
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Urutan</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td>
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?= base_url('uploads/image/' . e($p['image'])) ?>" alt="<?= e($p['name']) ?>" style="height: 38px; width: 38px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                <?php else: ?>
                                    <div style="height: 38px; width: 38px; background: rgba(0,0,0,0.05); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: var(--text-muted); border: 1px solid var(--border-color);">No Img</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600;">
                                <?= e($p['name']) ?>
                                <?php if (!empty($p['is_live_brew'])): ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.72rem; padding: 2px 6px; margin-left: 6px; border-radius: 4px;">Live Brew</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= e($p['category']) ?></span></td>
                            <td><?= formatRupiah($p['price']) ?></td>
                            <td><span class="badge badge-<?= $p['is_active'] ? 'active' : 'inactive' ?>"><?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td><?= $p['sort_order'] ?></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditProductModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/products/delete') ?>" data-confirm="Yakin hapus produk ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

<!-- MODAL TAMBAH PRODUK -->
<div class="app-modal-overlay" id="addProductModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Produk Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addProductModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/products/store') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Produk *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Es Kopi Susu / Kopi Pasir">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Deskripsi varian / rasa produk..."><?= e(old('description')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Foto Produk (JPG/PNG/WebP, maks 5MB)</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Harga *</label>
                        <input type="number" name="price" class="form-input" value="<?= e(old('price')) ?>" required min="0" placeholder="15000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori *</label>
                        <input type="text" name="category" class="form-input" value="<?= e(old('category')) ?>" required placeholder="coffee / non-coffee">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Urutan Tampilan</label>
                        <input type="number" name="sort_order" class="form-input" value="<?= e(old('sort_order', 0)) ?>" min="0">
                    </div>
                    <div class="form-group" style="display:flex; flex-direction:column; gap:8px; padding-top:15px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" <?= old('is_active', '1') ? 'checked' : '' ?> style="width:18px; height:18px;"> Status Aktif
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#d97706; font-weight:600; font-size:0.85rem;">
                            <input type="checkbox" name="is_live_brew" value="1" <?= old('is_live_brew') ? 'checked' : '' ?> style="width:18px; height:18px;"> Live Brew
                        </label>
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addProductModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PRODUK -->
<div class="app-modal-overlay" id="editProductModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Produk
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editProductModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/products/update') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_product_id" value="<?= e($oldId) ?>">
            <div class="app-modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Produk *</label>
                    <input type="text" name="name" id="edit_product_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" id="edit_product_description" class="form-input" rows="3"><?= e(old('description')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Foto Produk (JPG/PNG/WebP, maks 5MB)</label>
                    <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
                    <div id="edit_product_image_preview" style="margin-top: 8px; display: none; align-items: center; gap: 10px;">
                        <img id="edit_product_image_thumb" src="" alt="Thumbnail" style="height: 50px; width: 50px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color);">
                        <span id="edit_product_image_name" style="font-size: 12px; color: var(--text-muted);"></span>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Harga *</label>
                        <input type="number" name="price" id="edit_product_price" class="form-input" value="<?= e(old('price')) ?>" required min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori *</label>
                        <input type="text" name="category" id="edit_product_category" class="form-input" value="<?= e(old('category')) ?>" required>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Urutan Tampilan</label>
                        <input type="number" name="sort_order" id="edit_product_sort_order" class="form-input" value="<?= e(old('sort_order', 0)) ?>" min="0">
                    </div>
                    <div class="form-group" style="display:flex; flex-direction:column; gap:8px; padding-top:15px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="is_active" id="edit_product_is_active" value="1" style="width:18px; height:18px;"> Status Aktif
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#d97706; font-weight:600; font-size:0.85rem;">
                            <input type="checkbox" name="is_live_brew" id="edit_product_is_live_brew" value="1" style="width:18px; height:18px;"> Live Brew
                        </label>
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editProductModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Produk</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddProductModal() {
    openModal('addProductModal');
}

function openEditProductModal(product) {
    document.getElementById('edit_product_id').value = product.id;
    document.getElementById('edit_product_name').value = product.name || '';
    document.getElementById('edit_product_description').value = product.description || '';
    document.getElementById('edit_product_price').value = product.price || '';
    document.getElementById('edit_product_category').value = product.category || '';
    document.getElementById('edit_product_sort_order').value = product.sort_order ?? 0;
    document.getElementById('edit_product_is_active').checked = !!parseInt(product.is_active);
    document.getElementById('edit_product_is_live_brew').checked = !!parseInt(product.is_live_brew);

    const imgPreview = document.getElementById('edit_product_image_preview');
    const imgThumb = document.getElementById('edit_product_image_thumb');
    const imgName = document.getElementById('edit_product_image_name');
    if (product.image) {
        imgThumb.src = '<?= base_url('uploads/image/') ?>' + product.image;
        imgName.textContent = product.image;
        imgPreview.style.display = 'flex';
    } else {
        imgPreview.style.display = 'none';
    }

    openModal('editProductModal');
}

// Auto open modal on validation errors or query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddProductModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($products as $p): ?>
            if (<?= (int)$p['id'] ?> === targetId) {
                openEditProductModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }

    <?php if (!empty($errors)): ?>
        <?php if ($oldId): ?>
            openModal('editProductModal');
        <?php else: ?>
            openModal('addProductModal');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
