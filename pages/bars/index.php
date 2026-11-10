<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
$branchId = activeBranchId();
$pagination = paginate($pdo, "SELECT * FROM users WHERE role='bar' AND branch_id=? ORDER BY id DESC", [$branchId], 10);
$bars = $pagination['data'];
$errors = validation_errors();
$pageTitle = 'Kelola Bar';
$oldId = old('id');

ob_start();
?>
<style>
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1050;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active {
        display: flex;
        animation: modalFadeIn 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .modal-card {
        background: var(--bg-card);
        border-radius: var(--radius-md, 12px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 520px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        animation: modalScaleUp 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    .modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-close-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 1.25rem;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .modal-close-btn:hover {
        background: rgba(0, 0, 0, 0.06);
        color: var(--text-primary);
    }
    .modal-body {
        padding: 20px;
    }
    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 16px 20px;
        border-top: 1px solid var(--border-color);
        background: var(--bg-card-hover, rgba(0,0,0,0.01));
    }

    @keyframes modalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes modalScaleUp {
        from { opacity: 0; transform: scale(0.95) translateY(-10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h3 style="margin:0;">Akun Bar / Barista</h3>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-bars" method="POST" action="<?= url('/bars/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-bars')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button onclick="openAddModal()" class="btn btn-primary btn-sm"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Bar</button>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if(empty($bars)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Belum ada bar</h3>
                <p style="margin-top:8px;">Klik tombol "Tambah Bar" untuk menambahkan akun barista.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($bars as $b): ?>
                        <tr>
                            <td style="font-weight:600;"><?= e($b['name']) ?></td>
                            <td><?= e($b['email']) ?></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <button type="button" class="btn-delete-outline" onclick="deleteBar(<?= $b['id'] ?>, '<?= e($b['name']) ?>')" title="Hapus">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus
                                    </button>
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

<!-- MODAL TAMBAH BAR -->
<div class="modal-overlay" id="addBarModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Bar</div>
            <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
        </div>
        <form method="POST" action="<?= url('/bars/store') ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" placeholder="Masukkan nama barista / bar" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" value="<?= e(old('email')) ?>" placeholder="contoh@tetherbrew.com" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" placeholder="Minimal 6 karakter" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeAddModal()" class="btn btn-secondary btn-sm">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT BAR -->
<div class="modal-overlay" id="editBarModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title"><svg width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.15em; margin-right:4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Bar</div>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST" action="<?= url('/bars/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_bar_id" value="<?= e($oldId) ?>">
            <div class="modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama *</label>
                    <input type="text" name="name" id="edit_bar_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" id="edit_bar_email" class="form-input" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-input" placeholder="Kosongkan jika tidak ingin mengubah password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary btn-sm">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden delete form -->
<form id="delete-bar-form" method="POST" action="<?= url('/bars/delete') ?>" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="delete-bar-id">
</form>

<script>
function openAddModal() {
    document.getElementById('addBarModal').classList.add('active');
    setTimeout(() => {
        const input = document.querySelector('#addBarModal input[name="name"]');
        if (input) input.focus();
    }, 100);
}

function closeAddModal() {
    document.getElementById('addBarModal').classList.remove('active');
}

function openEditModal(bar) {
    document.getElementById('edit_bar_id').value = bar.id;
    document.getElementById('edit_bar_name').value = bar.name;
    document.getElementById('edit_bar_email').value = bar.email;
    document.getElementById('editBarModal').classList.add('active');
    setTimeout(() => {
        const input = document.getElementById('edit_bar_name');
        if (input) input.focus();
    }, 100);
}

function closeEditModal() {
    document.getElementById('editBarModal').classList.remove('active');
}

function deleteBar(id, name) {
    Swal.fire({
        title: 'Hapus Bar?',
        html: 'Yakin ingin menghapus akun bar <strong>' + name + '</strong>?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-bar-id').value = id;
            document.getElementById('delete-bar-form').submit();
        }
    });
}

// Close modals when clicking backdrop
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Auto-open modal on validation errors or query params
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddModal();
    }
});

<?php if (!empty($errors)): ?>
    <?php if ($oldId): ?>
        document.getElementById('editBarModal').classList.add('active');
    <?php else: ?>
        document.getElementById('addBarModal').classList.add('active');
    <?php endif; ?>
<?php endif; ?>
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
