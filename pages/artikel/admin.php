<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();

$search = $_GET['search'] ?? '';

$query = "SELECT a.*, u.name as author_name FROM artikels a JOIN users u ON a.user_id = u.id";
$params = [];
if ($search) {
    $query .= " WHERE a.title LIKE ? OR a.category LIKE ?";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$query .= " ORDER BY a.created_at DESC";

$pagination = paginate($pdo, $query, $params, 10);
$artikels = $pagination['data'];

$pageTitle = 'Kelola Artikel (Admin)';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0; flex:1;">
                <label class="form-label">Cari Artikel</label>
                <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Judul / Kategori">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:42px;"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Cari</button>
            <?php if ($search): ?><a href="<?= url('/artikel/admin') ?>" class="btn btn-outline-secondary btn-sm" style="height:42px; line-height:30px;">Reset</a><?php endif; ?>
        </form>
    </div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <a href="<?= url('/artikel') ?>" class="btn btn-outline-primary btn-sm" target="_blank"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg> Lihat Halaman Publik</a>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-artikels" method="POST" action="<?= url('/admin/artikel/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-artikels')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddArtikelModal()" class="btn btn-primary btn-sm">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Artikel Baru
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($artikels)): ?>
            <div style="text-align:center; padding:40px; color:var(--text-muted);">
                <h3>Belum ada artikel</h3>
                <button type="button" onclick="openAddArtikelModal()" class="btn btn-primary" style="margin-top:16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Artikel Baru
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Penulis</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($artikels as $a): ?>
                        <tr>
                            <td>
                                <?php if (!empty($a['cover_image'])): ?>
                                    <img src="<?= e(artikel_cover_url($a['cover_image'])) ?>" style="width:60px; height:40px; object-fit:cover; border-radius:4px;">
                                <?php else: ?>
                                    <div style="width:60px; height:40px; background:#e5e7eb; border-radius:4px; text-align:center; line-height:40px; font-size:10px; color:var(--text-muted);">No Img</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight:600; max-width:250px;"><?= e($a['title']) ?></td>
                            <td><span class="badge badge-info"><?= e($a['category']) ?></span></td>
                            <td>
                                <?php if ($a['is_published']): ?>
                                    <span class="badge badge-success">Terbit (<?= date('d/m/Y', strtotime($a['published_at'])) ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($a['author_name']) ?></td>
                            <td>
                                <div style="display:flex; gap:6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditArtikelModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg class="icon-two-tone" width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/artikel/delete') ?>" data-confirm="Yakin hapus artikel ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-delete-outline" title="Hapus">
                                            <svg class="icon-two-tone" width="1.1em" height="1.1em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
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

<!-- MODAL TAMBAH ARTIKEL -->
<div class="app-modal-overlay" id="addArtikelModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Artikel Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addArtikelModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/admin/artikel/store') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <div class="form-group">
                    <label class="form-label">Judul Artikel *</label>
                    <input type="text" name="title" class="form-input" required placeholder="Judul artikel atau berita">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori *</label>
                    <input type="text" name="category" class="form-input" placeholder="Tips Bisnis, Berita, Event, Edukasi..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ringkasan (Excerpt) *</label>
                    <textarea name="excerpt" class="form-input" rows="2" placeholder="Singkat untuk cuplikan dan SEO preview..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Isi Artikel (HTML Diizinkan) *</label>
                    <textarea name="content" class="form-input" rows="8" placeholder="Tulis konten artikel di sini..." required></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Gambar Cover (Max 2MB)</label>
                        <input type="file" name="cover_image" class="form-input" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimasi Waktu Baca (Menit)</label>
                        <input type="number" name="read_time" class="form-input" placeholder="Opsional (Otomatis jika kosong)" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_published" value="1" checked style="width:18px; height:18px;"> Langsung Terbitkan (Publish)
                    </label>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addArtikelModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Artikel</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT ARTIKEL -->
<div class="app-modal-overlay" id="editArtikelModal">
    <div class="app-modal app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Artikel
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editArtikelModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/admin/artikel/update') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_artikel_id">
            <div class="app-modal-body">
                <div class="form-group">
                    <label class="form-label">Judul Artikel *</label>
                    <input type="text" name="title" id="edit_artikel_title" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori *</label>
                    <input type="text" name="category" id="edit_artikel_category" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ringkasan (Excerpt) *</label>
                    <textarea name="excerpt" id="edit_artikel_excerpt" class="form-input" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Isi Artikel (HTML Diizinkan) *</label>
                    <textarea name="content" id="edit_artikel_content" class="form-input" rows="8" required></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Gambar Cover Baru (Max 2MB)</label>
                        <input type="file" name="cover_image" class="form-input" accept="image/jpeg,image/png,image/webp">
                        <div id="edit_artikel_cover_preview" style="margin-top: 8px; display: none; align-items: center; gap: 10px;">
                            <img id="edit_artikel_cover_thumb" src="" alt="Cover" style="height: 40px; width: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-color);">
                            <span style="font-size: 11px; color: var(--text-muted);">Cover saat ini</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimasi Waktu Baca (Menit)</label>
                        <input type="number" name="read_time" id="edit_artikel_read_time" class="form-input" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_published" id="edit_artikel_is_published" value="1" style="width:18px; height:18px;"> Terbitkan (Publish)
                    </label>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editArtikelModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Artikel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddArtikelModal() {
    openModal('addArtikelModal');
}

function openEditArtikelModal(a) {
    document.getElementById('edit_artikel_id').value = a.id;
    document.getElementById('edit_artikel_title').value = a.title || '';
    document.getElementById('edit_artikel_category').value = a.category || '';
    document.getElementById('edit_artikel_excerpt').value = a.excerpt || '';
    document.getElementById('edit_artikel_content').value = a.content || '';
    document.getElementById('edit_artikel_read_time').value = a.read_time || '';
    document.getElementById('edit_artikel_is_published').checked = !!parseInt(a.is_published);

    const preview = document.getElementById('edit_artikel_cover_preview');
    const thumb = document.getElementById('edit_artikel_cover_thumb');
    if (a.cover_image) {
        thumb.src = '<?= base_url('uploads/') ?>' + a.cover_image.replace(/^\/+/, '');
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }

    openModal('editArtikelModal');
}

// Auto open on query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddArtikelModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($artikels as $a): ?>
            if (<?= (int)$a['id'] ?> === targetId) {
                openEditArtikelModal(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
