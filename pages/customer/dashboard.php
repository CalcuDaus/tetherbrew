<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('customer');

$quotes = [
    "Kopi pertama hari ini adalah pelukan dalam cangkir.",
    "Setiap cangkir kopi adalah cerita yang menunggu untuk dicicipi.",
    "Bangun, minum kopi, dan jadikan hari ini luar biasa.",
    "Tidak ada yang tidak mungkin jika kamu punya cukup kopi.",
    "Kopi yang baik, teman yang baik, waktu yang baik.",
    "Awali pagimu dengan senyuman dan secangkir kopi hangat.",
    "Biarkan aroma kopi menginspirasi langkahmu hari ini."
];
$quoteIndex = date('z') % count($quotes);
$dailyQuote = $quotes[$quoteIndex];

$pageTitle = 'Beranda - Tether Brew';
ob_start();
?>
<style>
    .greeting-section {
        padding: 20px 20px 10px 20px;
    }

    .greeting-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .greeting-subtitle {
        font-size: 0.9rem;
        color: #64748b;
        margin-top: 4px;
    }

    /* Quote Card */
    .quote-card {
        background: linear-gradient(135deg, #8b5c2a, #6b4420);
        margin: 15px 20px;
        padding: 20px;
        border-radius: 16px;
        color: white;
        box-shadow: 0 10px 15px -3px rgba(139, 92, 42, 0.3);
        position: relative;
        overflow: hidden;
    }

    .quote-card::after {
        content: '"';
        position: absolute;
        bottom: -20px;
        right: 10px;
        font-size: 6rem;
        font-family: serif;
        opacity: 0.1;
        line-height: 1;
    }

    .quote-text {
        font-style: italic;
        font-size: 1.05rem;
        line-height: 1.5;
        position: relative;
        z-index: 2;
    }

    /* Menu Grid */
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        padding: 20px;
    }

    .menu-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #334155;
    }

    .menu-icon-wrap {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        color: #8b5c2a;
        transition: transform 0.2s;
    }

    .menu-btn:active .menu-icon-wrap {
        transform: scale(0.95);
    }

    .menu-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .menu-btn.disabled .menu-icon-wrap {
        background: #e2e8f0;
        color: #94a3b8;
    }

    .menu-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
    }

    /* Map Section */
    .map-section {
        padding: 0 20px 20px 20px;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 12px;
    }

    #customerMap {
        height: 300px;
        width: 100%;
        border-radius: 16px;
        z-index: 10;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Map Popup Styling */
    .leaflet-popup-content-wrapper {
        border-radius: 12px;
    }

    .cart-popup-title {
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 5px 0;
        font-size: 1.1rem;
    }

    .cart-popup-rider {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    .cart-popup-btn {
        display: block;
        background: #8b5c2a;
        color: white;
        text-align: center;
        padding: 8px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        margin-top: 10px;
    }
</style>

<?php
$userParts = explode(' ', auth_user()['name']);
$firstName = $userParts[0];
?>

<div class="greeting-section">
    <h1 class="greeting-title">Halo, <?= e($firstName) ?>!</h1>
    <div class="greeting-subtitle">Mau ngopi apa hari ini?</div>
</div>

<!-- Daily Quote -->
<div class="quote-card">
    <div class="quote-text"><?= $dailyQuote ?></div>
</div>

<!-- Quick Menus -->
<div class="menu-grid">
    <a href="<?= url('/customer/menu') ?>" class="menu-btn">
        <div class="menu-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
                <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
            </svg>
        </div>
        <span class="menu-label">Order</span>
    </a>

    <a href="#mapSection" class="menu-btn"
        onclick="document.getElementById('mapSection').scrollIntoView({behavior: 'smooth'})">
        <div class="menu-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
        </div>
        <span class="menu-label">Maps</span>
    </a>

    <a href="<?= url('/customer/chats') ?>" class="menu-btn">
        <div class="menu-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="8" cy="21" r="1"></circle>
                <circle cx="19" cy="21" r="1"></circle>
                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
            </svg>
        </div>
        <span class="menu-label">Cart</span>
    </a>

    <a href="javascript:void(0)" class="menu-btn disabled"
        onclick="Swal.fire({toast:true, position:'top-end', icon:'info', title:'Fitur Missions segera hadir!', showConfirmButton:false, timer:3000})">
        <div class="menu-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v20"></path>
                <path d="m17 5-5-3-5 3v14l5 3 5-3Z"></path>
            </svg>
        </div>
        <span class="menu-label">Missions</span>
    </a>
</div>

<!-- Map Section -->
<div id="mapSection" class="map-section">
    <h2 class="section-title">Gerobak & Rider di Sekitar</h2>
    <div id="customerMap"></div>
</div>

<?php
ob_start();
?>
<script>
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function startChatWithRider(riderId, cartId) {
        if (!riderId) {
            window.location.href = '<?= url('/customer/menu') ?>';
            return;
        }
        const fd = new FormData();
        fd.append('rider_id', riderId);
        if (cartId) fd.append('cart_id', cartId);
        try {
            const res = await fetch('<?= url('/api/chat/start') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.conversation && data.conversation.id) {
                window.location.href = '<?= url('/customer/chat_detail') ?>?id=' + data.conversation.id;
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Gagal membuka chat', text: 'Periksa koneksi internet Anda.' });
        }
    }
    window.startChatWithRider = startChatWithRider;

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Map
        const map = L.map('customerMap').setView([3.5952, 98.6722], 13); // Default Medan

        const cartoApiKey = 'cb1_4nyn_1_50043ed39b7a05dd524c65ba';
        L.tileLayer(`https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key=${cartoApiKey}`, {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);

        // Custom Rider Icon
        const riderIcon = L.icon({
            iconUrl: '<?= base_url('assets/images/tether-icon-head.webp') ?>',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });

        // Fetch Carts Data
        fetch('<?= url('/api/carts-map') ?>')
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    const bounds = [];
                    data.forEach(cart => {
                        if (cart.latitude && cart.longitude) {
                            bounds.push([cart.latitude, cart.longitude]);

                            // Build popup
                            let popupHtml = `
                                <div class="cart-popup">
                                    <h3 class="cart-popup-title">${escapeHtml(cart.name)}</h3>
                                    <div class="cart-popup-rider">Rider: ${escapeHtml(cart.rider)}</div>
                                    <div style="display:flex; gap:6px; margin-top:8px;">
                                        <a href="<?= url('/customer/menu') ?>" class="cart-popup-btn" style="flex:1; text-align:center;">Menu</a>
                                        ${cart.rider_id ? `<button type="button" class="cart-popup-btn" style="flex:1; background:var(--accent-green, #16a34a); border:none; cursor:pointer;" onclick="startChatWithRider(${cart.rider_id}, ${cart.id})">Chat</button>` : ''}
                                    </div>
                                </div>
                            `;

                            L.marker([cart.latitude, cart.longitude], { icon: riderIcon })
                                .addTo(map)
                                .bindPopup(popupHtml);
                        }
                    });

                    // Auto fit bounds to visible carts
                    if (bounds.length > 0) {
                        map.fitBounds(bounds, { padding: [30, 30] });
                    }
                } else {
                    const mapEl = document.getElementById('customerMap');
                    if (mapEl) {
                        const note = document.createElement('div');
                        note.style.cssText = 'text-align:center; padding:16px; font-size:13px; color:var(--text-muted); background:var(--bg-card); border-radius:12px; margin-top:10px; border:1px solid var(--border-color);';
                        note.textContent = 'Saat ini belum ada gerobak yang sedang keliling aktif. Cek kembali pada jam operasional ya!';
                        mapEl.parentNode.appendChild(note);
                    }
                }
            })
            .catch(err => console.error("Error loading carts data:", err));
    });
</script>
<?php
$extraScripts = ob_get_clean();
$content = ob_get_clean();
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require __DIR__ . '/../../layouts/customer.php';
?>