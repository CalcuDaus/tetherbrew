<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();

$branchId = activeBranchId();
$search = $_GET['search'] ?? '';
$query = "SELECT u.*, b.name as branch_name FROM users u LEFT JOIN branches b ON u.branch_id=b.id";
$params = [];
$conditions = [];

if ($branchId) {
    $conditions[] = "(u.branch_id=? OR u.branch_id IS NULL)";
    $params[] = $branchId;
}

if ($search) {
    $conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.role LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY u.id DESC";
$pagination = paginate($pdo, $query, $params, 10);
$accounts = $pagination['data'];
$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY name ASC")->fetchAll();
$pageTitle = 'Kelola Akun';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <form method="GET" style="display:flex; gap:8px;">
        <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Cari akun..." style="width:250px;">
        <button type="submit" class="btn btn-secondary btn-sm"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
        <?php if ($search): ?><a href="<?= url('/accounts') ?>" class="btn btn-outline-secondary btn-sm" style="display: flex; align-items: center;">Reset</a><?php endif; ?>
    </form>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-accounts" method="POST" action="<?= url('/accounts/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-accounts')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddAccountModal()" class="btn btn-primary btn-sm">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Akun
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($accounts)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Belum ada akun</h3>
                <button type="button" onclick="openAddAccountModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Akun
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Cabang</th>
                            <th>WhatsApp</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $a): ?>
                        <tr>
                            <td style="font-weight:600;"><?= e($a['name']) ?></td>
                            <td><?= e($a['email']) ?></td>
                            <td><span class="badge badge-<?= $a['role']==='owner'?'active':($a['role']==='admin'?'info':'warning') ?>"><?= ucfirst($a['role']) ?></span></td>
                            <td><?= e($a['branch_name'] ?? '-') ?></td>
                            <td><?= e($a['whatsapp'] ?? '-') ?></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditAccountModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <?php if ($a['id'] !== auth_id()): ?>
                                        <form method="POST" action="<?= url('/accounts/delete') ?>" data-confirm="Yakin hapus akun ini?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn-delete-outline" title="Hapus">
                                                <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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

<!-- MODAL TAMBAH AKUN -->
<div class="app-modal-overlay" id="addAccountModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Akun Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addAccountModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/accounts/store') ?>">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Nama Pengguna">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Akun *</label>
                    <input type="email" name="email" class="form-input" value="<?= e(old('email')) ?>" required placeholder="user@tetherbrew.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="whatsapp" class="form-input" value="<?= e(old('whatsapp')) ?>" placeholder="628xxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Role Akun *</label>
                    <select name="role" id="add_account_role" class="form-input" required onchange="toggleBranchField('add')">
                        <option value="rider" <?= old('role')=='rider'?'selected':'' ?>>Rider</option>
                        <option value="admin" <?= old('role')=='admin'?'selected':'' ?>>Admin</option>
                        <option value="bar" <?= old('role')=='bar'?'selected':'' ?>>Bar</option>
                        <option value="owner" <?= old('role')=='owner'?'selected':'' ?>>Owner</option>
                    </select>
                </div>
                <div class="form-group" id="add_branch_group">
                    <label class="form-label">Pilih Cabang (Khusus Admin, Rider, & Bar) *</label>
                    <select name="branch_id" id="add_branch_id" class="form-input" required>
                        <option value="">— Pilih Cabang —</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (old('branch_id') == $b['id'] || count($branches) === 1) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="color:var(--text-secondary); font-size:0.85rem; margin-top:4px;">Catatan: Owner tidak terikat cabang tunggal.</div>
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
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addAccountModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT AKUN -->
<div class="app-modal-overlay" id="editAccountModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Akun
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editAccountModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/accounts/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_account_id" value="<?= e($oldId) ?>">
            <div class="app-modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="name" id="edit_account_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Akun *</label>
                    <input type="email" name="email" id="edit_account_email" class="form-input" value="<?= e(old('email')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="whatsapp" id="edit_account_whatsapp" class="form-input" value="<?= e(old('whatsapp')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Role Akun *</label>
                    <select name="role" id="edit_account_role" class="form-input" required onchange="toggleBranchField('edit')">
                        <option value="rider">Rider</option>
                        <option value="admin">Admin</option>
                        <option value="bar">Bar</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div class="form-group" id="edit_branch_group">
                    <label class="form-label">Pilih Cabang (Khusus Admin, Rider, & Bar) *</label>
                    <select name="branch_id" id="edit_branch_id" class="form-input">
                        <option value="">— Pilih Cabang —</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editAccountModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleBranchField(mode) {
    const roleSelect = document.getElementById(mode === 'add' ? 'add_account_role' : 'edit_account_role');
    const branchGroup = document.getElementById(mode === 'add' ? 'add_branch_group' : 'edit_branch_group');
    const branchInput = document.getElementById(mode === 'add' ? 'add_branch_id' : 'edit_branch_id');

    if (!roleSelect || !branchGroup || !branchInput) return;

    if (roleSelect.value === 'owner') {
        branchGroup.style.display = 'none';
        branchInput.removeAttribute('required');
        branchInput.value = '';
    } else {
        branchGroup.style.display = 'block';
        branchInput.setAttribute('required', 'required');
    }
}

function openAddAccountModal() {
    toggleBranchField('add');
    openModal('addAccountModal');
}

function openEditAccountModal(account) {
    document.getElementById('edit_account_id').value = account.id;
    document.getElementById('edit_account_name').value = account.name || '';
    document.getElementById('edit_account_email').value = account.email || '';
    document.getElementById('edit_account_whatsapp').value = account.whatsapp || '';
    document.getElementById('edit_account_role').value = account.role || 'rider';
    document.getElementById('edit_branch_id').value = account.branch_id || '';
    toggleBranchField('edit');
    openModal('editAccountModal');
}

// Auto open on validation errors or query params
document.addEventListener('DOMContentLoaded', () => {
    toggleBranchField('add');
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddAccountModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($accounts as $a): ?>
            if (<?= (int)$a['id'] ?> === targetId) {
                openEditAccountModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }

    <?php if (!empty($errors)): ?>
        <?php if ($oldId): ?>
            openModal('editAccountModal');
        <?php else: ?>
            openModal('addAccountModal');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
