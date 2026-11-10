<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();

$branchId = activeBranchId();
$pagination = paginate($pdo, "SELECT u.*, c.name as cart_name FROM users u LEFT JOIN carts c ON c.user_id = u.id WHERE u.role = 'rider' AND u.branch_id = ? ORDER BY u.id DESC", [$branchId], 10);
$riders = $pagination['data'];
$pageTitle = 'Kelola Rider';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div style="display:flex; justify-content:flex-end; gap:8px; margin-bottom:16px;">
    <form id="deleteAll-riders" method="POST" action="<?= url('/riders/delete-all') ?>" style="margin:0;">
        <?= csrf_field() ?>
        <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-riders')">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
        </button>
    </form>
    <button type="button" onclick="openAddRiderModal()" class="btn btn-primary btn-sm">
        <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Rider
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($riders)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <div style="font-size:48px;">
                    <svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a3.5 3.5 0 1 0-7 0"></path><path d="M12 17.5V14l-4-4 4-3.5 5.5 3.5"></path></svg>
                </div>
                <h3>Belum Ada Rider</h3>
                <button type="button" onclick="openAddRiderModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Rider
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>WhatsApp</th>
                            <th>Gerobak</th>
                            <th>Status</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($riders as $r): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <svg class="icon-two-tone" width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="18.5" cy="17.5" r="3.5"></circle><path d="M15 6a3.5 3.5 0 1 0-7 0"></path><path d="M12 17.5V14l-4-4 4-3.5 5.5 3.5"></path></svg> 
                                <?= e($r['name']) ?>
                            </td>
                            <td><?= e($r['email']) ?></td>
                            <td><?= e($r['whatsapp'] ?? '-') ?></td>
                            <td><?= e($r['cart_name'] ?? 'Belum ditugaskan') ?></td>
                            <td>
                                <?php if ($r['is_active']): ?>
                                    <span style="display: inline-block; padding: 4px 8px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">Aktif</span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 4px 8px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">Resign / Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditRiderModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <?php if ($r['is_active']): ?>
                                        <a href="<?= url('/riders/resign?id=' . $r['id']) ?>" class="btn-secondary" style="padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; border: 1px solid #f59e0b; color: #d97706; text-decoration:none;" title="Proses Resign">
                                            Proses Resign
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" action="<?= url('/riders/delete') ?>" data-confirm="Yakin hapus rider ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
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

<!-- MODAL TAMBAH RIDER -->
<div class="app-modal-overlay" id="addRiderModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Rider Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addRiderModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/riders/store') ?>">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Contoh: Budi Santoso">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Akun *</label>
                    <input type="email" name="email" class="form-input" value="<?= e(old('email')) ?>" required placeholder="budi@tetherbrew.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="whatsapp" class="form-input" value="<?= e(old('whatsapp')) ?>" placeholder="628123456789">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required placeholder="Min 6 karakter">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password *</label>
                        <input type="password" name="password_confirmation" class="form-input" required placeholder="Ulangi password">
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addRiderModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Rider</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT RIDER -->
<div class="app-modal-overlay" id="editRiderModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Rider
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editRiderModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/riders/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_rider_id" value="<?= e($oldId) ?>">
            <div class="app-modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="name" id="edit_rider_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Akun *</label>
                    <input type="email" name="email" id="edit_rider_email" class="form-input" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="whatsapp" id="edit_rider_whatsapp" class="form-input" value="<?= e(old('whatsapp')) ?>">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-input" placeholder="Kosongkan jika tidak ubah">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-input" placeholder="Ulangi jika ubah">
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editRiderModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Rider</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddRiderModal() {
    openModal('addRiderModal');
}

function openEditRiderModal(rider) {
    document.getElementById('edit_rider_id').value = rider.id;
    document.getElementById('edit_rider_name').value = rider.name || '';
    document.getElementById('edit_rider_email').value = rider.email || '';
    document.getElementById('edit_rider_whatsapp').value = rider.whatsapp || '';
    openModal('editRiderModal');
}

// Auto open on validation errors or query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddRiderModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($riders as $r): ?>
            if (<?= (int)$r['id'] ?> === targetId) {
                openEditRiderModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }

    <?php if (!empty($errors)): ?>
        <?php if ($oldId): ?>
            openModal('editRiderModal');
        <?php else: ?>
            openModal('addRiderModal');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
