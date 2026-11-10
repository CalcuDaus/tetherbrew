// =============================================
// Helper: escapeHtml
// =============================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// =============================================
// Navbar scroll effect
// =============================================
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
});

// Mobile toggle
const mobileToggle = document.getElementById('mobile-toggle');
const navbarMenu = document.getElementById('navbar-menu');
if (mobileToggle && navbarMenu) {
    mobileToggle.addEventListener('click', () => {
        navbarMenu.classList.toggle('mobile-open');
    });
    // Close mobile menu on link click
    navbarMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navbarMenu.classList.remove('mobile-open');
        });
    });
}

// =============================================
// Initialize map
// =============================================
const map = L.map('map', { attributionControl: false }).setView([3.5952, 98.6722], 13);

const cartoApiKey = 'cb1_4nyn_1_50043ed39b7a05dd524c65ba';
const darkTiles = `https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=${cartoApiKey}`;
const lightTiles = `https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key=${cartoApiKey}`;

let activeTileLayer = L.tileLayer((localStorage.getItem('theme') || 'light') === 'light' ? lightTiles : darkTiles, {
    maxZoom: 19
}).addTo(map);

window.addEventListener('theme-changed', (e) => {
    map.removeLayer(activeTileLayer);
    activeTileLayer = L.tileLayer(e.detail.theme === 'light' ? lightTiles : darkTiles, {
        maxZoom: 19
    }).addTo(map);
});

function formatRupiah(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

// =============================================
// USER LOCATION (for distance estimation)
// =============================================
let userLatLng = null;
let userMarker = null;

const userIcon = L.divIcon({
    className: 'user-location-marker',
    html: '<div class="user-marker-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>',
    iconSize: [36, 36],
    iconAnchor: [18, 18]
});

function locateNearestCart() {
    const btn = document.getElementById('locate-me-btn');
    const label = document.getElementById('locate-me-label');
    if (!('geolocation' in navigator)) {
        alert('Browser Anda tidak mendukung fitur lokasi GPS.');
        return;
    }
    if (btn) btn.disabled = true;
    if (label) label.textContent = 'Mencari GPS...';

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            userLatLng = L.latLng(pos.coords.latitude, pos.coords.longitude);
            if (userMarker) {
                userMarker.setLatLng(userLatLng);
            } else {
                userMarker = L.marker([pos.coords.latitude, pos.coords.longitude], { icon: userIcon, zIndexOffset: 1000 })
                    .addTo(map)
                    .bindTooltip('Lokasi Anda', {
                        permanent: true, direction: 'bottom', offset: [0, 6],
                        className: 'user-tooltip'
                    });
            }

            if (typeof window.__refreshCartDistances === 'function') {
                window.__refreshCartDistances(true);
            } else {
                map.setView(userLatLng, 15);
            }

            if (btn) btn.disabled = false;
            if (label) label.textContent = 'Terdekat Ditemukan';
        },
        () => {
            if (btn) btn.disabled = false;
            if (label) label.textContent = 'Cari Terdekat';
            alert('Izin lokasi ditolak atau sinyal GPS belum aktif. Silakan aktifkan lokasi di browser Anda.');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
}
window.locateNearestCart = locateNearestCart;

function getDistanceText(cartLat, cartLng) {
    if (!userLatLng) return null;
    const cartPos = L.latLng(cartLat, cartLng);
    const meters = userLatLng.distanceTo(cartPos);
    if (meters < 1000) return Math.round(meters) + ' m';
    return (meters / 1000).toFixed(1) + ' km';
}

function getDistanceMeters(cartLat, cartLng) {
    if (!userLatLng) return null;
    const cartPos = L.latLng(cartLat, cartLng);
    return userLatLng.distanceTo(cartPos);
}

function estimateETA(meters) {
    if (meters === null) return '';
    if (meters < 500) return 'Saya sudah di lokasi';
    if (meters < 1000) return '± 5 menit';
    if (meters < 2000) return '± 10 menit';
    if (meters < 5000) return '± 15 menit';
    if (meters < 10000) return '± 30 menit';
    return 'Lebih dari 30 menit';
}

// =============================================
// ORDER PANEL LOGIC
// =============================================
let currentOrderCart = null;
let orderItems = [];
const disabledMenuItems = ['cold brew'];

const orderPanel = document.getElementById('order-panel');
const orderBackdrop = document.getElementById('order-backdrop');
const orderSheet = document.getElementById('order-sheet');
const orderCartName = document.getElementById('order-cart-name');
const orderRiderName = document.getElementById('order-rider-name');
const orderDistance = document.getElementById('order-distance');
const orderMenuList = document.getElementById('order-menu-list');
const orderNotes = document.getElementById('order-notes');
const orderTotalPrice = document.getElementById('order-total-price');
const orderItemCount = document.getElementById('order-item-count');
const orderWaBtn = document.getElementById('order-wa-btn');
const orderWaBtnText = document.getElementById('order-wa-btn-text');
const orderNavBtn = document.getElementById('order-nav-btn');
const orderLocBtn = document.getElementById('order-loc-btn');
const orderLocBtnText = document.getElementById('order-loc-btn-text');
const orderCloseBtn = document.getElementById('order-close-btn');
const orderNoRiderAlert = document.getElementById('order-no-rider-alert');

// Cegah intersep sentuhan / scroll Leaflet agar scrolling pada mobile sheet lancar
if (window.L && L.DomEvent && orderSheet) {
    L.DomEvent.disableScrollPropagation(orderSheet);
    L.DomEvent.disableClickPropagation(orderSheet);
}

function openOrderPanel(cart) {
    if (!cart) return;
    currentOrderCart = cart;
    const menuSource = (cart.menu && cart.menu.length > 0) ? cart.menu : (allMenuItems || []);
    orderItems = menuSource.map(m => ({ ...m, qty: 0 }));

    orderCartName.textContent = cart.name || 'Gerobak';
    orderRiderName.textContent = 'Rider: ' + (cart.rider || 'Tidak ada rider');
    orderNotes.value = '';

    const hasRider = !!(cart.whatsapp && cart.rider && cart.rider !== 'Tidak ada rider' && cart.rider.trim() !== '-');

    // Alert banner jika gerobak belum memiliki rider
    if (orderNoRiderAlert) {
        if (!hasRider) {
            orderNoRiderAlert.innerHTML = `
                <svg class="icon-two-tone" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Gerobak ini sedang tidak memiliki rider aktif. Pesanan dan kirim lokasi via WhatsApp belum tersedia.</span>
            `;
            orderNoRiderAlert.style.display = 'flex';
        } else {
            orderNoRiderAlert.style.display = 'none';
        }
    }

    // Distance estimation
    const dist = getDistanceText(cart.latitude, cart.longitude);
    if (dist) {
        orderDistance.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-right:4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ± ${dist} dari lokasi Anda`;
        orderDistance.style.display = 'flex';
    } else {
        orderDistance.style.display = 'none';
    }

    // Navigation button
    if (cart.latitude && cart.longitude) {
        orderNavBtn.href = `https://www.google.com/maps/dir/?api=1&destination=${cart.latitude},${cart.longitude}`;
        orderNavBtn.style.display = 'inline-flex';
    } else {
        orderNavBtn.style.display = 'none';
    }

    // Location share button setup
    if (hasRider) {
        orderLocBtn.classList.remove('disabled');
        if (orderLocBtnText) orderLocBtnText.textContent = userLatLng ? 'Kirim Lokasi' : 'Kirim Lokasi';
    } else {
        orderLocBtn.classList.add('disabled');
        if (orderLocBtnText) orderLocBtnText.textContent = 'Kirim Lokasi';
    }

    renderOrderMenu();
    updateOrderTotal();

    orderPanel.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeOrderPanel() {
    orderPanel.classList.remove('open');
    document.body.style.overflow = '';
    currentOrderCart = null;
}

orderCloseBtn.addEventListener('click', closeOrderPanel);
orderBackdrop.addEventListener('click', closeOrderPanel);

function renderOrderMenu() {
    if (orderItems.length === 0) {
        orderMenuList.innerHTML = '<div class="order-empty">Belum ada menu tersedia di gerobak ini.</div>';
        return;
    }
    orderMenuList.innerHTML = orderItems.map((item, i) => {
        const isDisabled = disabledMenuItems.includes(item.name.toLowerCase());
        return `
            <div class="order-menu-row${isDisabled ? ' order-menu-disabled' : ''}">
                <div class="order-menu-info">
                    <span class="order-menu-name">${item.name}</span>
                    <span class="order-menu-price">${formatRupiah(item.price)}</span>
                </div>
                <div class="order-qty-controls">
                    <button class="order-qty-btn" onclick="changeQty(${i}, -1)" ${item.qty <= 0 || isDisabled ? 'disabled' : ''}>−</button>
                    <span class="order-qty-value">${item.qty}</span>
                    <button class="order-qty-btn" onclick="changeQty(${i}, 1)" ${isDisabled ? 'disabled' : ''}>+</button>
                </div>
            </div>
        `;
    }).join('');
}

function changeQty(index, delta) {
    const item = orderItems[index];
    if (disabledMenuItems.includes(item.name.toLowerCase())) return;
    const newQty = item.qty + delta;
    if (newQty < 0) return;
    item.qty = newQty;
    renderOrderMenu();
    updateOrderTotal();
}

function updateOrderTotal() {
    const selected = orderItems.filter(i => i.qty > 0);
    const total = selected.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const count = selected.reduce((sum, i) => sum + i.qty, 0);

    orderTotalPrice.textContent = formatRupiah(total);
    orderItemCount.textContent = count + ' item dipilih';

    const hasRider = !!(currentOrderCart?.whatsapp && currentOrderCart?.rider && currentOrderCart?.rider !== 'Tidak ada rider' && currentOrderCart?.rider.trim() !== '-');

    if (!hasRider) {
        orderWaBtn.classList.add('disabled', 'no-rider');
        orderWaBtn.href = '#';
        if (orderWaBtnText) orderWaBtnText.textContent = 'Pesan via WhatsApp (Rider Belum Tersedia)';
    } else if (count > 0) {
        orderWaBtn.classList.remove('disabled', 'no-rider');
        orderWaBtn.href = generateWhatsAppLink();
        if (orderWaBtnText) orderWaBtnText.textContent = 'Pesan via WhatsApp';
    } else {
        orderWaBtn.classList.add('disabled');
        orderWaBtn.classList.remove('no-rider');
        orderWaBtn.href = '#';
        if (orderWaBtnText) orderWaBtnText.textContent = 'Pesan via WhatsApp';
    }
}

if (orderWaBtn) {
    orderWaBtn.addEventListener('click', function(e) {
        if (!currentOrderCart) return;
        const hasRider = !!(currentOrderCart.whatsapp && currentOrderCart.rider && currentOrderCart.rider !== 'Tidak ada rider' && currentOrderCart.rider.trim() !== '-');
        if (!hasRider) {
            e.preventDefault();
            alert('Maaf, gerobak ini sedang tidak memiliki rider aktif sehingga belum dapat menerima pesanan via WhatsApp.');
            return;
        }
        const selected = orderItems.filter(i => i.qty > 0);
        if (selected.length === 0) {
            e.preventDefault();
            alert('Silakan pilih minimal 1 menu minuman terlebih dahulu sebelum mengirim pesanan via WhatsApp.');
            return;
        }
    });
}

if (orderLocBtn) {
    orderLocBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!currentOrderCart) return;

        const hasRider = !!(currentOrderCart.whatsapp && currentOrderCart.rider && currentOrderCart.rider !== 'Tidak ada rider' && currentOrderCart.rider.trim() !== '-');
        if (!hasRider) {
            alert('Maaf, gerobak ini sedang tidak memiliki rider aktif untuk menerima lokasi.');
            return;
        }

        if (userLatLng) {
            const link = generateLocationShareLink();
            window.open(link, '_blank');
            return;
        }

        if (!('geolocation' in navigator)) {
            alert('Browser Anda tidak mendukung fitur lokasi GPS.');
            return;
        }

        const origText = orderLocBtnText ? orderLocBtnText.textContent : 'Kirim Lokasi';
        if (orderLocBtnText) orderLocBtnText.textContent = 'Mencari GPS...';
        orderLocBtn.classList.add('loading');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                userLatLng = L.latLng(pos.coords.latitude, pos.coords.longitude);
                if (userMarker) {
                    userMarker.setLatLng(userLatLng);
                } else if (window.map) {
                    userMarker = L.marker([pos.coords.latitude, pos.coords.longitude], { icon: userIcon, zIndexOffset: 1000 })
                        .addTo(map)
                        .bindTooltip('Lokasi Anda', {
                            permanent: true, direction: 'bottom', offset: [0, 6],
                            className: 'user-tooltip'
                        });
                }

                if (typeof window.__refreshCartDistances === 'function') {
                    window.__refreshCartDistances(false);
                }

                const dist = getDistanceText(currentOrderCart.latitude, currentOrderCart.longitude);
                if (dist) {
                    orderDistance.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-right:4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ± ${dist} dari lokasi Anda`;
                    orderDistance.style.display = 'flex';
                }

                orderLocBtn.classList.remove('loading');
                if (orderLocBtnText) orderLocBtnText.textContent = origText;

                const link = generateLocationShareLink();
                window.open(link, '_blank');
            },
            (err) => {
                orderLocBtn.classList.remove('loading');
                if (orderLocBtnText) orderLocBtnText.textContent = origText;
                alert('Tidak dapat mendeteksi lokasi. Pastikan izin lokasi (GPS) pada browser/perangkat Anda sudah diaktifkan.');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    });
}

function generateOrderText() {
    if (!currentOrderCart) return '';
    const selected = orderItems.filter(i => i.qty > 0);
    if (selected.length === 0) return '';

    const total = selected.reduce((sum, i) => sum + (i.price * i.qty), 0);
    const notes = orderNotes.value.trim();

    const meters = getDistanceMeters(currentOrderCart.latitude, currentOrderCart.longitude);
    const eta = estimateETA(meters);

    let msg = `Halo Tether Brew *${currentOrderCart.name}*,\nSaya mau pesan:\n\n`;
    selected.forEach(item => {
        const sub = item.price * item.qty;
        msg += ` ${item.name} x${item.qty} = ${formatRupiah(sub)}\n`;
    });
    msg += `\n *Total: ${formatRupiah(total)}*`;
    if (eta) msg += `\n *Estimasi saya sampai: ${eta}*`;
    if (notes) msg += `\n\n Catatan: ${notes}`;
    if (userLatLng) {
        msg += `\n\n Lokasi saya: https://maps.google.com/?q=${userLatLng.lat},${userLatLng.lng}`;
    }
    msg += `\n\nMohon konfirmasi ketersediaannya dan jangan kemana-mana dulu ya! `;
    return msg;
}

function generateWhatsAppLink() {
    if (!currentOrderCart) return '#';
    const phone = currentOrderCart.whatsapp;
    const msg = generateOrderText();
    return `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
}

function generateLocationShareLink() {
    if (!currentOrderCart || !userLatLng) return '#';
    const phone = currentOrderCart.whatsapp;
    const dist = getDistanceText(currentOrderCart.latitude, currentOrderCart.longitude);
    const mapsLink = `https://maps.google.com/?q=${userLatLng.lat},${userLatLng.lng}`;

    let msg = `Halo Tether Brew *${currentOrderCart.name}*,\n\n`;
    msg += `Saya tertarik untuk pesan kopi. Ini lokasi saya:\n`;
    msg += ` ${mapsLink}\n\n`;
    if (dist) msg += `Jarak saya ke gerobak Anda: ± ${dist}\n\n`;
    msg += `Apakah memungkinkan untuk mendekat ke lokasi saya? Terima kasih.`;

    return `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
}

// Update WA link on notes change
orderNotes.addEventListener('input', updateOrderTotal);

// =============================================
// MENU & MAP DATA
// =============================================
let allMenuItems = Array.isArray(window.__catalogMenus) ? window.__catalogMenus : [];
let allCartsData = [];

// Determine base URL for API calls
const scriptTag = document.querySelector('script[src*="landing.js"]');
let apiBaseUrl = '';
if (scriptTag) {
    const src = scriptTag.getAttribute('src');
    // Extract base from e.g. http://localhost/tether-brew/assets/js/landing.js
    const idx = src.indexOf('/assets/js/landing.js');
    if (idx > 0) {
        apiBaseUrl = src.substring(0, idx);
    }
}

// Determine image base URL
const imgBaseUrl = apiBaseUrl;

// Pre-render menus immediately from PHP catalog
const statMenusEl = document.getElementById('stat-menus');
if (statMenusEl) statMenusEl.textContent = allMenuItems.length;
renderMenu('semua');

document.querySelectorAll('.menu-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.menu-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const cat = tab.dataset.category || 'semua';
        renderMenu(cat);
    });
});

fetch(apiBaseUrl + '/api/carts-map')
    .then(r => r.json())
    .then(carts => {
        allCartsData = carts;

        // Stats
        const statCartsEl = document.getElementById('stat-carts');
        if (statCartsEl) statCartsEl.textContent = carts.length;

        if (allMenuItems.length === 0) {
            let menuSet = new Map();
            carts.forEach(c => c.menu.forEach(m => {
                if (!menuSet.has(m.name)) menuSet.set(m.name, m);
            }));
            allMenuItems = Array.from(menuSet.values());
            if (statMenusEl) statMenusEl.textContent = menuSet.size;
            renderMenu('semua');
        }

        const cartCountLabel = document.getElementById('cart-count-label');
        if (cartCountLabel) cartCountLabel.textContent = carts.length + ' gerobak aktif';

        const markers = {};
        const brewIcon = L.icon({
            iconUrl: imgBaseUrl + '/assets/images/custom_icon_maps.webp',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });

        function renderCartsOnMap(carts, initial = false) {
            const bounds = [];
            carts.forEach(cart => {
                const latlng = [cart.latitude, cart.longitude];
                bounds.push(latlng);

                const distText = getDistanceText(cart.latitude, cart.longitude);
                const popupContent = `
                    <div class="popup-title">${escapeHtml(cart.name)}</div>
                    <div class="popup-rider">${escapeHtml(cart.rider)}${distText ? ' · <svg class="icon-two-tone" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:2px; margin-left:4px; margin-top:-2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>' + distText : ''}</div>
                    <div class="popup-menu-title">${cart.menu.length} menu tersedia</div>
                    <div class="popup-updated">Diperbarui: ${escapeHtml(cart.updated_at)}</div>
                    <div class="popup-contact-wrapper">
                        <button class="popup-order-btn" onclick="openOrderPanel(window.__cartsData.find(c=>c.id===${cart.id}))"><svg class="icon-two-tone" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-0.2em;margin-right:4px;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg> Pesan dari Gerobak Ini</button>
                    </div>
                `;

                if (markers[cart.id]) {
                    markers[cart.id].setLatLng(latlng).setPopupContent(popupContent);
                    if (distText) {
                        markers[cart.id].unbindTooltip();
                        markers[cart.id].bindTooltip(distText, {
                            permanent: true, direction: 'bottom', offset: [0, 4],
                            className: 'distance-tooltip'
                        });
                    }
                } else {
                    const m = L.marker(latlng, { icon: brewIcon })
                        .addTo(map)
                        .bindPopup(popupContent, { maxWidth: 280 });
                    if (distText) {
                        m.bindTooltip(distText, {
                            permanent: true, direction: 'bottom', offset: [0, 4],
                            className: 'distance-tooltip'
                        });
                    }
                    markers[cart.id] = m;
                }
            });

            // Store globally for popup button access
            window.__cartsData = carts;

            if (initial && bounds.length > 0) map.fitBounds(bounds, { padding: [50, 50] });
        }

        window.__refreshCartDistances = function(panToNearest) {
            renderCartsOnMap(allCartsData, false);
            renderCartCards(allCartsData);
            if (panToNearest && userLatLng) {
                if (lastSortedCarts.length > 0) {
                    const nearest = lastSortedCarts[0];
                    map.fitBounds([userLatLng, [nearest.latitude, nearest.longitude]], { padding: [60, 60], maxZoom: 16 });
                    if (markers[nearest.id]) markers[nearest.id].openPopup();
                } else {
                    map.setView(userLatLng, 14);
                }
            }
        };

        // =============================================
        // GEROBAK PAGINATION
        // =============================================
        const CARTS_PER_PAGE = 5;
        let currentCartPage = 1;
        let lastSortedCarts = [];

        function renderCartCards(carts) {
            const grid = document.getElementById('cart-grid');
            const paginationEl = document.getElementById('cart-pagination');

            if (carts.length === 0) {
                document.getElementById('cart-count-label').textContent = 'Gerobak sedang istirahat / persiapan';
                const dot = document.getElementById('map-live-dot');
                if (dot) dot.style.background = '#f59e0b';
                grid.innerHTML = `
                    <div class="gerobak-hours-banner">
                        <h3>Armada Gerobak Sedang Bersiap / Istirahat</h3>
                        <p>Saat ini belum ada rider yang mengaktifkan titik lokasi keliling. Gerobak Tether Brew beroperasi setiap hari mulai pagi hingga sore di titik-titik strategis Kota Medan. Anda tetap dapat melihat katalog menu di atas atau memesan paket kopi untuk acara/kantor!</p>
                        <a href="https://wa.me/6282379742368?text=${encodeURIComponent('Halo Tether Brew! Saya ingin tanya jadwal gerobak / pesan kopi untuk acara.')}" target="_blank" class="gerobak-hours-cta">
                            Hubungi Admin via WhatsApp
                        </a>
                    </div>
                `;
                paginationEl.innerHTML = '';
                return;
            }

            const dot = document.getElementById('map-live-dot');
            if (dot) dot.style.background = '';
            document.getElementById('cart-count-label').textContent = carts.length + ' gerobak aktif · live';

            let sortedCarts = [...carts];
            if (typeof userLatLng !== 'undefined' && userLatLng) {
                sortedCarts.sort((a, b) => {
                    const distA = getDistanceMeters(a.latitude, a.longitude);
                    const distB = getDistanceMeters(b.latitude, b.longitude);
                    return (distA === null ? Infinity : distA) - (distB === null ? Infinity : distB);
                });
            }
            lastSortedCarts = sortedCarts;

            const totalPages = Math.ceil(sortedCarts.length / CARTS_PER_PAGE);
            if (currentCartPage > totalPages) currentCartPage = totalPages;
            if (currentCartPage < 1) currentCartPage = 1;

            const startIdx = (currentCartPage - 1) * CARTS_PER_PAGE;
            const pageCarts = sortedCarts.slice(startIdx, startIdx + CARTS_PER_PAGE);

            grid.innerHTML = pageCarts.map(cart => {
                const distText = getDistanceText(cart.latitude, cart.longitude);
                return `
                <div class="gerobak-card" onclick="map.setView([${cart.latitude},${cart.longitude}],16); document.getElementById('maps').scrollIntoView({behavior:'smooth'});">
                    <div class="gerobak-card-top">
                        <div class="gerobak-card-name"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.25em; margin-right:4px;"><path d="M6 9l1.5 11.5A2 2 0 0 0 9.5 22h5a2 2 0 0 0 2-1.5L18 9" /><line x1="4" y1="9" x2="20" y2="9" /><path d="M5 9 A 7 5 0 0 1 19 9" /><line x1="12" y1="4" x2="14" y2="0" /></svg> ${escapeHtml(cart.name)}</div>
                        <div class="gerobak-badge">Aktif</div>
                    </div>
                    <div class="gerobak-card-rider"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:4px; margin-top:-2px;"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>${escapeHtml(cart.rider)} · <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:2px; margin-left:4px; margin-top:-2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>${escapeHtml(cart.updated_at)}${distText ? ' · <svg class="icon-two-tone" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:2px; margin-left:4px; margin-top:-2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ' + distText : ''}</div>
                    <div class="gerobak-card-actions" onclick="event.stopPropagation()">
                        <button class="btn-cart-wa" onclick="openOrderPanel(window.__cartsData.find(c=>c.id===${cart.id}))"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-0.2em; margin-right:4px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg> Pesan</button>
                    </div>
                </div>
            `}).join('');

            // Render pagination
            renderCartPagination(totalPages);
        }

        function renderCartPagination(totalPages) {
            const paginationEl = document.getElementById('cart-pagination');
            if (totalPages <= 1) {
                paginationEl.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `<button class="pagination-btn pagination-prev ${currentCartPage === 1 ? 'disabled' : ''}" onclick="goToCartPage(${currentCartPage - 1})" ${currentCartPage === 1 ? 'disabled' : ''}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>`;

            // Page numbers with ellipsis
            const pages = getPaginationRange(currentCartPage, totalPages);
            pages.forEach(p => {
                if (p === '...') {
                    html += `<span class="pagination-ellipsis">…</span>`;
                } else {
                    html += `<button class="pagination-btn pagination-num ${p === currentCartPage ? 'active' : ''}" onclick="goToCartPage(${p})">${p}</button>`;
                }
            });

            // Next button
            html += `<button class="pagination-btn pagination-next ${currentCartPage === totalPages ? 'disabled' : ''}" onclick="goToCartPage(${currentCartPage + 1})" ${currentCartPage === totalPages ? 'disabled' : ''}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>`;

            // Page info
            html += `<div class="pagination-info">${lastSortedCarts.length} gerobak</div>`;

            paginationEl.innerHTML = html;
        }

        function getPaginationRange(current, total) {
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
            const pages = [];
            if (current <= 3) {
                pages.push(1, 2, 3, 4, '...', total);
            } else if (current >= total - 2) {
                pages.push(1, '...', total - 3, total - 2, total - 1, total);
            } else {
                pages.push(1, '...', current - 1, current, current + 1, '...', total);
            }
            return pages;
        }

        window.goToCartPage = function(page) {
            const totalPages = Math.ceil(lastSortedCarts.length / CARTS_PER_PAGE);
            if (page < 1 || page > totalPages) return;
            currentCartPage = page;
            renderCartCards(allCartsData);
            document.getElementById('gerobak').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        // Initial load
        renderCartsOnMap(carts, true);
        renderCartCards(carts);

        function refreshCartsMap() {
            if (document.hidden) return;
            fetch(apiBaseUrl + '/api/carts-map?_t=' + Date.now(), { cache: 'no-store' })
                .then(r => r.json())
                .then(carts => {
                    allCartsData = carts;
                    const statCartsEl = document.getElementById('stat-carts');
                    if (statCartsEl) statCartsEl.textContent = carts.length;
                    renderCartsOnMap(carts);
                    renderCartCards(carts);
                })
                .catch(err => console.warn('Refresh failed:', err));
        }

        setInterval(refreshCartsMap, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshCartsMap();
        });
    })
    .catch(err => {
        console.error('Initial load failed:', err);
        document.getElementById('cart-count-label').textContent = 'Gagal memuat data';
    });

function renderMenu(category) {
    const grid = document.getElementById('menu-grid');
    if (!grid) return;
    const items = category === 'semua' ? allMenuItems : allMenuItems.filter(m => m.category === category);

    if (items.length === 0) {
        grid.innerHTML = '<div class="empty-state-text">Tidak ada menu</div>';
        return;
    }

    grid.innerHTML = items.map(m => {
        const isDisabled = disabledMenuItems.includes(m.name.toLowerCase());
        const desc = m.description ? escapeHtml(m.description) : (m.category === 'kopi' ? 'Minuman kopi spesial dengan racikan khas Tether Brew' : 'Minuman segar pilihan non-kopi yang cocok untuk bersantai');
        return `
        <div class="menu-card${isDisabled ? ' menu-card-disabled' : ''}">
            <div class="menu-card-top">
                <div class="menu-card-bg-circle"></div>
                <div class="menu-card-hero">
                    <img src="${m.image ? imgBaseUrl + '/uploads/image/' + m.image : imgBaseUrl + '/uploads/image/kopi-tether-new.webp'}" alt="${escapeHtml(m.name)}" loading="lazy">
                </div>
                <svg class="menu-card-wave" viewBox="0 0 1000 100" preserveAspectRatio="none"><path  d="M0,50 C300,120 700,-20 1000,50 L1000,105 L0,105 Z"></path></svg>
            </div>
            <div class="menu-card-info">
                <div class="menu-card-name">${escapeHtml(m.name)}</div>
                <div class="menu-card-desc">${desc}</div>
                <div class="menu-card-bottom">
                    <span class="menu-card-price">${formatRupiah(m.price)}</span>
                    <a href="#maps" class="menu-card-btn">Pesan</a>
                </div>
            </div>
        </div>
    `;
    }).join('');
}


// =============================================
// BOTTOM NAV: Active state on scroll (mobile)
// =============================================
const bottomNavItems = document.querySelectorAll('.bottom-nav-item');
const sectionIds = ['home', 'maps', 'menu', 'about', 'contact'];

function updateBottomNav() {
    let current = 'home';
    for (const id of sectionIds) {
        const section = document.getElementById(id);
        if (section) {
            const rect = section.getBoundingClientRect();
            if (rect.top <= window.innerHeight / 2) {
                current = id;
            }
        }
    }
    bottomNavItems.forEach(item => {
        item.classList.toggle('active', item.dataset.section === current);
    });
}

window.addEventListener('scroll', updateBottomNav, { passive: true });

// Expose functions globally for inline onclick handlers
window.openOrderPanel = openOrderPanel;
window.changeQty = changeQty;
