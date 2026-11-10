<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_admin_or_owner();

$branchId = activeBranchId();
$search = $_GET['search'] ?? '';

$query = "SELECT c.*, u.name as rider_name, cl.latitude, cl.longitude, cl.updated_at as location_updated
    FROM carts c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN cart_locations cl ON cl.cart_id = c.id
    WHERE c.branch_id = ?";
$params = [$branchId];

if ($search) {
    $query .= " AND (c.name LIKE ? OR c.status LIKE ? OR u.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$query .= " ORDER BY c.id DESC";

$pagination = paginate($pdo, $query, $params, 10);
$carts = $pagination['data'];

$ridersStmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'rider' AND branch_id = ? ORDER BY name ASC");
$ridersStmt->execute([$branchId]);
$riders = $ridersStmt->fetchAll();

$pageTitle = 'Kelola Gerobak';
$errors = validation_errors();
$oldId = old('id');

ob_start();
?>
<style>
    .live-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #22c55e;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        animation: pulseLive 2s infinite;
        margin-right: 6px;
    }
    @keyframes pulseLive {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .leaflet-popup-content-wrapper {
        background: var(--bg-card, #142320) !important;
        color: var(--text-color, #e8f5f0) !important;
        border-radius: 14px !important;
        border: 1px solid var(--border-color, #1e3a30) !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.35) !important;
    }
    .leaflet-popup-tip { background: var(--bg-card, #142320) !important; }
    .leaflet-popup-content { margin: 14px 16px !important; font-family: 'Segoe UI', system-ui, sans-serif !important; }
    .popup-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; color: var(--text-color, #f8fafc); }
    .popup-rider { font-size: 12px; color: var(--text-muted, #94a3b8); margin-bottom: 6px; }
    .popup-menu-title { font-size: 11px; font-weight: 700; color: #22c55e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .popup-updated { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.08)); font-size: 11px; color: var(--text-muted, #94a3b8); }
</style>

<!-- Map -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="card-title" style="display:flex; align-items:center;">
            <span class="live-dot"></span> Live Tracking Gerobak
        </div>
        <div style="font-size:12px; color:var(--text-muted);" id="cart-map-count">
            Memuat posisi gerobak...
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div id="admin-carts-map" style="height: 420px; border-radius: 0 0 12px 12px; overflow: hidden;"></div>
    </div>
</div>

<!-- Search & Add -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" class="form-input" value="<?= e($search) ?>" placeholder="Cari gerobak..." style="width: 250px;">
        <button type="submit" class="btn btn-secondary btn-sm"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
        <?php if ($search): ?><a href="<?= url('/carts') ?>" class="btn btn-outline-secondary btn-sm" style="display: flex; align-items: center;">Reset</a><?php endif; ?>
    </form>
    <div style="display:flex; gap:8px;">
        <form id="deleteAll-carts" method="POST" action="<?= url('/carts/delete-all') ?>" style="margin:0;">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-sm" style="background-color:#ef4444; color:white; border:none;" onclick="confirmDeleteAll('deleteAll-carts')">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg> Hapus Semua
            </button>
        </form>
        <button type="button" onclick="openAddCartModal()" class="btn btn-primary btn-sm">
            <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Gerobak
        </button>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($carts)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <div style="margin-bottom: 12px; color: var(--text-muted); opacity: 0.6;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </div>
                <h3>Belum Ada Gerobak</h3>
                <p>Mulai tambahkan gerobak pertama Anda</p>
                <button type="button" onclick="openAddCartModal()" class="btn btn-primary" style="margin-top: 16px;">
                    <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Gerobak
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Rider</th>
                            <th>Status</th>
                            <th>Lokasi</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($carts as $cart): ?>
                        <tr>
                            <td>#<?= $cart['id'] ?></td>
                            <td style="font-weight: 600;"><?= e($cart['name']) ?></td>
                            <td><?= e($cart['rider_name'] ?? '-') ?></td>
                            <td>
                                <button class="badge badge-<?= $cart['status'] ?>" onclick="toggleStatus(this, <?= $cart['id'] ?>)" style="cursor:pointer; border:none; font-family:inherit;">
                                    <?= ucfirst($cart['status']) ?>
                                </button>
                            </td>
                            <td>
                                <?php if ($cart['latitude']): ?>
                                    <span style="color: var(--accent-green); font-size: 12px; display:inline-flex; align-items:center; gap:3px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?= number_format($cart['latitude'], 4) ?>, <?= number_format($cart['longitude'], 4) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">Belum diset</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; justify-content:center;">
                                    <button type="button" class="btn-edit-outline" onclick='openEditCartModal(<?= json_encode($cart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                                    </button>
                                    <form method="POST" action="<?= url('/carts/delete') ?>" data-confirm="Yakin hapus gerobak ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $cart['id'] ?>">
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
            <div style="padding: 16px;"><?= render_pagination($pagination) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL TAMBAH GEROBAK -->
<div class="app-modal-overlay" id="addCartModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Gerobak Baru
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="addCartModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/carts/store') ?>">
            <?= csrf_field() ?>
            <div class="app-modal-body">
                <?php if (!empty($errors) && !$oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Gerobak *</label>
                    <input type="text" name="name" class="form-input" value="<?= e(old('name')) ?>" required placeholder="Gerobak Kopi Pak Budi">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Deskripsi posisi / operasional gerobak..."><?= e(old('description')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Tugaskan Rider</label>
                    <select name="user_id" class="form-input">
                        <option value="">-- Pilih Rider --</option>
                        <?php foreach($riders as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= old('user_id') == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-input" required>
                        <option value="inactive" <?= old('status', 'inactive') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="active" <?= old('status') == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= old('status') == 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">* Status <strong>Active</strong> mewajibkan gerobak memiliki Rider.</small>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-input" value="<?= e(old('latitude')) ?>" placeholder="3.5952">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-input" value="<?= e(old('longitude')) ?>" placeholder="98.6722">
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="addCartModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Gerobak</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT GEROBAK -->
<div class="app-modal-overlay" id="editCartModal">
    <div class="app-modal">
        <div class="app-modal-header">
            <h3 class="app-modal-title">
                <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Gerobak
            </h3>
            <button type="button" class="app-modal-close" data-close-modal="editCartModal">✕</button>
        </div>
        <form method="POST" action="<?= url('/carts/update') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit_cart_id" value="<?= e($oldId) ?>">
            <div class="app-modal-body">
                <?php if (!empty($errors) && $oldId): ?>
                    <?= render_errors($errors) ?>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Gerobak *</label>
                    <input type="text" name="name" id="edit_cart_name" class="form-input" value="<?= e(old('name')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" id="edit_cart_description" class="form-input" rows="3"><?= e(old('description')) ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Tugaskan Rider</label>
                    <select name="user_id" id="edit_cart_user_id" class="form-input">
                        <option value="">-- Pilih Rider --</option>
                        <?php foreach($riders as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" id="edit_cart_status" class="form-input" required>
                        <option value="inactive">Inactive</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 4px; font-size: 11px;">* Status <strong>Active</strong> mewajibkan gerobak memiliki Rider.</small>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" id="edit_cart_latitude" class="form-input" value="<?= e(old('latitude')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" id="edit_cart_longitude" class="form-input" value="<?= e(old('longitude')) ?>">
                    </div>
                </div>
            </div>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-close-modal="editCartModal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Gerobak</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCartModal() {
    openModal('addCartModal');
}

function openEditCartModal(cart) {
    document.getElementById('edit_cart_id').value = cart.id;
    document.getElementById('edit_cart_name').value = cart.name || '';
    document.getElementById('edit_cart_description').value = cart.description || '';
    document.getElementById('edit_cart_user_id').value = cart.user_id || '';
    document.getElementById('edit_cart_status').value = cart.status || 'inactive';
    document.getElementById('edit_cart_latitude').value = cart.latitude || '';
    document.getElementById('edit_cart_longitude').value = cart.longitude || '';
    openModal('editCartModal');
}

// Auto open on validation errors or query params
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('create') === '1' || urlParams.get('action') === 'create') {
        openAddCartModal();
    } else if (urlParams.get('edit_id')) {
        const targetId = parseInt(urlParams.get('edit_id'));
        <?php foreach ($carts as $c): ?>
            if (<?= (int)$c['id'] ?> === targetId) {
                openEditCartModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
            }
        <?php endforeach; ?>
    }

    <?php if (!empty($errors)): ?>
        <?php if ($oldId): ?>
            openModal('editCartModal');
        <?php else: ?>
            openModal('addCartModal');
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php
$content = ob_get_clean();
$extraScripts = '<script>
const map = L.map("admin-carts-map", { attributionControl: false }).setView([3.5952, 98.6722], 13);

const darkTiles = `https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=cb1_4nyn_1_50043ed39b7a05dd524c65ba`;
const lightTiles = `https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key=cb1_4nyn_1_50043ed39b7a05dd524c65ba`;

const currentTheme = (localStorage.getItem("theme") || "light");
let activeTileLayer = L.tileLayer(currentTheme === "light" ? lightTiles : darkTiles, {
    maxZoom: 19
}).addTo(map);

window.addEventListener("theme-changed", (e) => {
    map.removeLayer(activeTileLayer);
    activeTileLayer = L.tileLayer(e.detail.theme === "light" ? lightTiles : darkTiles, {
        maxZoom: 19
    }).addTo(map);
});

const brewIcon = L.icon({
    iconUrl: "' . url('/assets/images/custom_icon_maps.webp') . '",
    iconSize: [40, 40],
    iconAnchor: [20, 40],
    popupAnchor: [0, -40]
});

function escapeHtml(text) {
    if(!text) return "";
    return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

fetch("' . url('/api/carts-map') . '").then(r=>r.json()).then(carts=>{
    const bounds = [];
    let countActive = 0;
    carts.forEach(c=>{
        if(c.latitude && c.longitude){
            bounds.push([c.latitude, c.longitude]);
            countActive++;
            const statusBadge = (c.status === "active" || c.status === 1) 
                ? "<span style=\'background:rgba(34,197,94,0.15); color:#22c55e; padding:2px 8px; border-radius:10px; font-weight:700; font-size:10px;\'>Aktif</span>"
                : "<span style=\'background:rgba(239,68,68,0.15); color:#ef4444; padding:2px 8px; border-radius:10px; font-weight:700; font-size:10px;\'>Inactive</span>";
            
            const popupContent = `
                <div class="popup-title">${escapeHtml(c.name)}</div>
                <div class="popup-rider">Rider: <strong>${escapeHtml(c.rider || "-")}</strong> · ${statusBadge}</div>
                <div class="popup-menu-title">${c.menu ? c.menu.length : 0} Menu Tersedia</div>
                <div class="popup-updated">Diperbarui: ${c.updated_at || "-"}</div>
            `;
            
            L.marker([c.latitude, c.longitude], { icon: brewIcon })
                .addTo(map)
                .bindPopup(popupContent, { maxWidth: 280 });
        }
    });
    
    document.getElementById("cart-map-count").textContent = countActive + " gerobak terpantau di peta";
    if(bounds.length) map.fitBounds(bounds, {padding:[50,50]});
}).catch(e=>console.log(e));

async function toggleStatus(btn, cartId) {
    var orig = btn.textContent; btn.textContent = "..."; btn.disabled = true;
    try {
        var fd = new FormData(); fd.append("id", cartId); fd.append("_token", document.querySelector("meta[name=csrf-token]").content);
        var r = await fetch("' . url('/carts/toggle-status') . '", {method:"POST", body:fd});
        var d = await r.json();
        if(d.success){ 
            btn.textContent = d.status.charAt(0).toUpperCase()+d.status.slice(1); 
            btn.className="badge badge-"+d.status; 
        } else {
            btn.textContent = orig;
            if(window.Swal) {
                Swal.fire({
                    icon: "warning",
                    title: "Perhatian",
                    text: d.message || "Gagal mengubah status gerobak.",
                    confirmButtonColor: "#22c55e"
                });
            } else {
                alert(d.message || "Gagal mengubah status gerobak.");
            }
        }
    } catch(e){ 
        btn.textContent = orig; 
    }
    btn.disabled = false;
}
</script>';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require __DIR__ . '/../../layouts/app.php';
