<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_owner();

$pagination = paginate($pdo, "SELECT * FROM branches ORDER BY id DESC", [], 10);
$branches = $pagination['data'];
$pageTitle = 'Kelola Cabang';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:16px;">
    <form id="deleteAll-branches" method="POST" action="<?= url('/branches/delete-all') ?>" style="margin:0;">
        <?= csrf_field() ?>
        <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-branches')">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
        </button>
    </form>
    <button type="button" onclick="openAddBranchModal()" class="btn btn-primary btn-sm">
        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Cabang
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($branches)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Belum Ada Cabang</h3>
                <button type="button" onclick="openAddBranchModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Cabang
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th>Telepon</th>
                            <th>Status</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr>
                            <td>#<?= $b['id'] ?></td>
                            <td><span class="badge badge-info"><?= e($b['code']) ?></span></td>
                            <td style="font-weight:600;"><?= e($b['name']) ?></td>
                            <td><?= e(truncate($b['address'] ?? '-', 40)) ?></td>
                            <td><?= e($b['phone'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $b['is_active'] ? 'active' : 'inactive' ?>"><?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <?php if ($b['is_active'] && activeBranchId() != $b['id']): ?>
                                        <form method="POST" action="<?= url('/branches/switch') ?>" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn-edit-outline" title="Pindah Cabang">
                                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.15em; margin-right:3px;"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg> Pindah
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn-edit-outline" onclick='openEditBranchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/branches/delete') ?>" onsubmit="return confirmForceDelete(event, this, '<?= e($b['name']) ?>')" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-delete-outline" title="Hapus Paksa">
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

<!-- MODAL TAMBAH CABANG -->
<div class="app-modal-overlay" id="addBranchModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Cabang Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addBranchModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/branches/store') ?>">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Cabang *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Contoh: Tether Brew Bandung">
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Cabang *</label>
                    <input type="text" name="code" class="form-input" value="<?= e(old('code')) ?>" required placeholder="BDG-01">
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="address" class="form-input" rows="3" placeholder="Alamat cabang..."><?= e(old('address')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Telepon</label>
                    <input type="text" name="phone" class="form-input" value="<?= e(old('phone')) ?>" placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked style="width:18px; height:18px;"> Status Aktif
                    </label>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addBranchModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Cabang</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT CABANG -->
<div class="app-modal-overlay" id="editBranchModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Cabang
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editBranchModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/branches/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_branch_id" value="<?= e($oldId) ?>">
            <div class="app-modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Cabang *</label>
                    <input type="text" name="name" id="edit_branch_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Cabang *</label>
                    <input type="text" name="code" id="edit_branch_code" class="form-input" value="<?= e(old('code')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap</label>
                    <textarea name="address" id="edit_branch_address" class="form-input" rows="3"><?= e(old('address')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Telepon</label>
                    <input type="text" name="phone" id="edit_branch_phone" class="form-input" value="<?= e(old('phone')) ?>">
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" id="edit_branch_is_active" value="1" style="width:18px; height:18px;"> Status Aktif
                    </label>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editBranchModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Cabang</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddBranchModal() {
    openModal('addBranchModal');
}

function openEditBranchModal(b) {
    document.getElementById('edit_branch_id').value = b.id;
    document.getElementById('edit_branch_name').value = b.name || '';
    document.getElementById('edit_branch_code').value = b.code || '';
    document.getElementById('edit_branch_address').value = b.address || '';
    document.getElementById('edit_branch_phone').value = b.phone || '';
    document.getElementById('edit_branch_is_active').checked = !!parseInt(b.is_active);
    openModal('editBranchModal');
}

function confirmForceDelete(event, form, branchName) {
    event.preventDefault();
    Swal.fire({
        title: 'Hapus Paksa Cabang?',
        html: `Anda akan menghapus cabang <b>${branchName}</b> beserta <b>SELURUH DATA</b> yang terkait (transaksi, inventaris, pengguna, jurnal, dll).<br><br>Ketik nama cabang <b>${branchName}</b> untuk melanjutkan:`,
        icon: 'warning',
        input: 'text',
        inputAttributes: {
            autocapitalize: 'off'
        },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus Paksa',
        cancelButtonText: 'Batal',
        preConfirm: (inputValue) => {
            if (inputValue !== branchName) {
                Swal.showValidationMessage(`Nama cabang tidak cocok.`);
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}

// Auto open on validation errors or query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddBranchModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($branches as $b): ?>
            if (<?= (int)$b['id'] ?> === targetId) {
                openEditBranchModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }

    <?php if (!empty($errors)): ?>
        <?php if ($oldId): ?>
            openModal('editBranchModal');
        <?php else: ?>
            openModal('addBranchModal');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
