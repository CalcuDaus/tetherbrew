<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();

$categories = $pdo->query("SELECT * FROM journal_categories ORDER BY name ASC")->fetchAll();
$pageTitle = 'Kategori Jurnal';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <h3 style="margin:0;">Daftar Kategori Jurnal</h3>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-journalCategories" method="POST" action="<?= url('/journal-categories/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-journalCategories')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddCatModal()" class="btn btn-primary btn-sm">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Kategori
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($categories)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Belum ada kategori</h3>
                <p>Kategori digunakan untuk mengelompokkan pos transaksi jurnal.</p>
                <button type="button" onclick="openAddCatModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Kategori
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th style="text-align:center; width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td style="font-weight:600;"><?= e($c['name']) ?></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick="openEditCatModal(<?= $c['id'] ?>, '<?= addslashes(e($c['name'])) ?>')" title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/journal-categories/delete') ?>" data-confirm="Yakin hapus kategori ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH KATEGORI -->
<div class="app-modal-overlay" id="addCatModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Kategori Jurnal
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addCatModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/journal-categories/store') ?>">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori *</label>
                    <input type="text" name="name" class="form-input" required placeholder="Contoh: Operasional, Bahan Baku, Gaji...">
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addCatModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT KATEGORI -->
<div class="app-modal-overlay" id="editCatModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Kategori Jurnal
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editCatModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/journal-categories/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_cat_id">
            <div class="app-modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori *</label>
                    <input type="text" name="name" id="edit_cat_name" class="form-input" required>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editCatModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Kategori</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCatModal() {
    openModal('addCatModal');
}

function openEditCatModal(id, name) {
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    openModal('editCatModal');
}
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
