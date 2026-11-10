<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');

$cartStmt = $pdo->prepare("SELECT * FROM carts WHERE user_id = ?");
$cartStmt->execute([auth_id()]);
$cart = $cartStmt->fetch();

if (!$cart) {
    $pageTitle = 'POS Kasir';
    ob_start();
    echo "<div class='card' style='text-align:center; padding:48px 20px;'>
            <div style='display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:var(--bg-secondary); color:var(--text-muted); margin-bottom:12px;'>
                <svg width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><circle cx='9' cy='21' r='1'/><circle cx='20' cy='21' r='1'/><path d='M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6'/></svg>
            </div>
            <h3 style='color:var(--text-primary); margin-bottom:8px;'>Belum Ada Gerobak Aktif</h3>
            <p style='color:var(--text-muted);'>Anda belum ditugaskan ke gerobak manapun. Silakan hubungi Admin cabang Anda.</p>
          </div>";
    $content = ob_get_clean();
    require __DIR__ . '/../../layouts/app.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT i.product_id, i.stock, p.name, p.price, p.category, p.image
    FROM inventories i
    INNER JOIN products p ON i.product_id = p.id
    WHERE i.cart_id = ? AND i.stock > 0 AND p.is_active = 1
    ORDER BY p.sort_order ASC, p.name ASC
");
$stmt->execute([$cart['id']]);
$inventories = $stmt->fetchAll();

$pageTitle = 'POS Kasir — ' . $cart['name'];
ob_start();
?>
<style>
    .pos-status-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 14px;
    }
    .pos-net-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        background: rgba(34, 197, 94, 0.12);
        color: #16a34a;
        border: 1px solid rgba(34, 197, 94, 0.28);
    }
    html:not(.light-theme) .pos-net-pill { color: #4ade80; }
    .pos-net-pill.offline {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
        border-color: rgba(245, 158, 11, 0.35);
    }
    html:not(.light-theme) .pos-net-pill.offline { color: #fbbf24; }
    .pos-net-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }
    .pos-queue-banner {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 14px;
        border-radius: 12px;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.35);
        color: var(--text-primary);
        font-size: 13px;
    }
    .pos-queue-banner.show { display: flex; flex-wrap: wrap; }

    .pos-filter-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-bottom: 14px;
        scrollbar-width: none;
    }
    .pos-filter-tabs::-webkit-scrollbar { display: none; }
    .pos-filter-tab {
        padding: 8px 16px;
        border-radius: 999px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
        transition: all 0.18s ease;
    }
    .pos-filter-tab.active {
        background: var(--accent-green);
        color: #ffffff;
        border-color: var(--accent-green);
    }

    .pos-layout {
        display: grid;
        grid-template-columns: 1fr 370px;
        gap: 20px;
        align-items: start;
    }
    .pos-products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }
    .pos-product-card {
        position: relative;
        background: var(--bg-card);
        border: 1.5px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
        cursor: pointer;
        user-select: none;
        transition: transform 0.12s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        display: flex;
        flex-direction: column;
    }
    .pos-product-card:active { transform: scale(0.97); }
    .pos-product-card.in-cart {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.18);
    }
    .pos-product-card.out-of-stock {
        opacity: 0.4;
        pointer-events: none;
    }
    .pos-product-thumb {
        position: relative;
        height: 112px;
        background: var(--bg-secondary);
        overflow: hidden;
    }
    .pos-product-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .pos-qty-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        min-width: 28px;
        height: 28px;
        padding: 0 8px;
        border-radius: 999px;
        background: var(--accent-green);
        color: #ffffff;
        font-weight: 800;
        font-size: 13px;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
    }
    .pos-product-card.in-cart .pos-qty-badge { display: inline-flex; }
    .pos-stock-pill {
        position: absolute;
        bottom: 6px;
        left: 6px;
        padding: 2px 8px;
        border-radius: 6px;
        background: rgba(12, 18, 16, 0.75);
        color: #ffffff;
        font-size: 11px;
        font-weight: 600;
    }
    .pos-product-body {
        padding: 10px 12px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }
    .pos-product-name {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.25;
    }
    .pos-product-price {
        font-size: 14px;
        font-weight: 800;
        color: var(--accent-green);
    }
    .pos-card-stepper {
        display: none;
        align-items: center;
        justify-content: space-between;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed var(--border-color);
    }
    .pos-product-card.in-cart .pos-card-stepper { display: flex; }
    .pos-step-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        font-size: 18px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .pos-step-btn.plus {
        background: var(--accent-green);
        border-color: var(--accent-green);
        color: #ffffff;
    }

    /* Panel Keranjang & Pembayaran */
    .pos-cart-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 95;
    }
    .pos-cart-panel {
        position: sticky;
        top: 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
    }
    .pos-cart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
    }
    .pos-cart-close-mobile {
        display: none;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
    }
    .pos-cart-items {
        max-height: 240px;
        overflow-y: auto;
        padding: 12px 16px;
    }
    .pos-pay-methods {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
    }
    .pos-pay-btn {
        padding: 10px 12px;
        border-radius: 10px;
        border: 1.5px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-secondary);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        font-family: inherit;
        text-align: center;
    }
    .pos-pay-btn.active {
        border-color: var(--accent-green);
        background: rgba(34, 197, 94, 0.12);
        color: var(--accent-green);
    }
    .pos-quick-cash {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
        margin-bottom: 10px;
    }
    .pos-cash-chip {
        padding: 6px 10px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }
    .pos-cash-chip:active, .pos-cash-chip.active {
        background: var(--accent-green);
        border-color: var(--accent-green);
        color: #ffffff;
    }
    .pos-change-box {
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid rgba(34, 197, 94, 0.25);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .pos-change-box.insufficient {
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }

    /* Sticky Bottom Bar khusus Mobile */
    .pos-mobile-bar {
        display: none;
    }

    /* Modal Struk */
    .pos-receipt-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 120;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .pos-receipt-overlay.open { display: flex; }
    .pos-receipt-card {
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 18px;
        width: 100%;
        max-width: 380px;
        padding: 24px 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
    }

    @media (max-width: 900px) {
        .pos-layout {
            grid-template-columns: 1fr;
            padding-bottom: 92px;
        }
        .pos-products-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .pos-product-thumb { height: 100px; }
        .pos-cart-backdrop.open { display: block; }
        .pos-cart-panel {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            top: auto;
            z-index: 100;
            border-radius: 20px 20px 0 0;
            max-height: 88vh;
            overflow-y: auto;
            transform: translateY(105%);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.35);
        }
        .pos-cart-panel.open { transform: translateY(0); }
        .pos-cart-close-mobile { display: inline-block; }
        .pos-mobile-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 90;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 10px 16px calc(12px + env(safe-area-inset-bottom));
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        }
    }
</style>

<div class="pos-status-bar">
    <div id="pos-net-pill" class="pos-net-pill">
        <span class="pos-net-dot"></span>
        <span id="pos-net-text">Online · Siap Transaksi</span>
    </div>
    <a href="<?= url('/rider/transactions') ?>" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Riwayat Hari Ini
    </a>
</div>

<div id="pos-queue-banner" class="pos-queue-banner">
    <div>
        <strong style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
            <span id="pos-queue-count">0</span> Transaksi Menunggu Sinyal
        </strong>
        <div style="font-size:12px; color:var(--text-muted);">Tersimpan aman di HP ini dan akan dikirim otomatis saat internet kembali stabil.</div>
    </div>
    <button type="button" id="pos-sync-btn" onclick="syncOfflineQueue(true)" class="btn btn-primary btn-sm">Sinkronkan Sekarang</button>
</div>

<div class="pos-filter-tabs">
    <button type="button" class="pos-filter-tab active" onclick="filterCategory('all', this)">Semua Menu</button>
    <button type="button" class="pos-filter-tab" onclick="filterCategory('kopi', this)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>Coffee
    </button>
    <button type="button" class="pos-filter-tab" onclick="filterCategory('non-kopi', this)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v7a6 6 0 0 1-6 6H9a6 6 0 0 1-6-6V8z"/><line x1="9" y1="2" x2="9" y2="5"/><line x1="13" y1="2" x2="13" y2="5"/></svg>Non-Coffee
    </button>
</div>

<div class="pos-layout">
    <!-- Grid Produk -->
    <div>
        <div class="pos-products-grid" id="pos-products-grid">
            <?php if (empty($inventories)): ?>
                <div class="card" style="grid-column: 1 / -1; text-align:center; padding:40px 20px;">
                    <h3 style="color:var(--text-primary); margin-bottom:6px;">Stok Gerobak Kosong</h3>
                    <p style="color:var(--text-muted);">Silakan minta Admin untuk mengisi stok gerobak Anda terlebih dahulu.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($inventories as $inv):
                $pid = (int)$inv['product_id'];
                $catSlug = strpos(strtolower((string)$inv['category']), 'non') !== false ? 'non-kopi' : 'kopi';
                $imgUrl = !empty($inv['image'])
                    ? base_url('uploads/image/' . $inv['image'])
                    : base_url('uploads/image/kopi-tether-new.webp');
            ?>
                <div class="pos-product-card"
                     id="prod-card-<?= $pid ?>"
                     data-product-id="<?= $pid ?>"
                     data-category="<?= $catSlug ?>"
                     onclick="tapProductCard(<?= $pid ?>)">
                    <div class="pos-product-thumb">
                        <img src="<?= e($imgUrl) ?>" alt="<?= e($inv['name']) ?>" loading="lazy">
                        <span class="pos-qty-badge" id="prod-badge-<?= $pid ?>">0</span>
                        <span class="pos-stock-pill">Stok: <strong id="prod-stock-<?= $pid ?>"><?= (int)$inv['stock'] ?></strong></span>
                    </div>
                    <div class="pos-product-body">
                        <div class="pos-product-name"><?= e($inv['name']) ?></div>
                        <div class="pos-product-price"><?= formatRupiah($inv['price']) ?></div>
                        <div class="pos-card-stepper" onclick="event.stopPropagation()">
                            <button type="button" class="pos-step-btn" onclick="updateQty(<?= $pid ?>, -1)">−</button>
                            <span id="prod-step-qty-<?= $pid ?>" style="font-weight:800; font-size:14px;">0</span>
                            <button type="button" class="pos-step-btn plus" onclick="updateQty(<?= $pid ?>, 1)">+</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Backdrop & Panel Keranjang / Checkout -->
    <div class="pos-cart-backdrop" id="pos-cart-backdrop" onclick="toggleMobileCart(false)"></div>
    <div class="pos-cart-panel" id="pos-cart-panel">
        <div class="pos-cart-header">
            <div style="display:flex; align-items:center; gap:8px;">
                <strong style="font-size:16px; color:var(--text-primary); display:inline-flex; align-items:center; gap:6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Keranjang Kasir
                </strong>
                <button type="button" onclick="clearCart()" id="pos-clear-btn" style="display:none; font-size:12px; color:#ef4444; background:none; border:none; cursor:pointer; font-weight:600;">Kosongkan</button>
            </div>
            <button type="button" class="pos-cart-close-mobile" onclick="toggleMobileCart(false)" aria-label="Tutup">✕</button>
        </div>

        <div class="pos-cart-items" id="cart-items">
            <div style="text-align:center; padding:24px 12px; color:var(--text-muted); font-size:13px;">Ketuk produk untuk menambahkan ke keranjang</div>
        </div>

        <div style="padding:16px; border-top:1px dashed var(--border-color); background:var(--bg-secondary);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <span style="font-size:14px; color:var(--text-secondary); font-weight:600;">Total Tagihan (<span id="cart-total-cups">0</span> cup)</span>
                <span id="cart-total" style="font-size:20px; font-weight:800; color:var(--accent-green);">Rp 0</span>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" style="font-size:12px;">Metode Pembayaran</label>
                <div class="pos-pay-methods">
                    <button type="button" class="pos-pay-btn active" id="pay-btn-cash" onclick="selectPaymentMethod('cash')" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
                        Tunai (Cash)
                    </button>
                    <button type="button" class="pos-pay-btn" id="pay-btn-qris" onclick="selectPaymentMethod('qris')" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        QRIS
                    </button>
                </div>
            </div>

            <!-- Kalkulator Uang Tunai & Kembalian -->
            <div id="cash-calc-section">
                <label class="form-label" style="font-size:12px;">Uang Diterima (Kosongkan = Uang Pas)</label>
                <input type="number" id="cash-received-input" class="form-input" inputmode="numeric" placeholder="Contoh: 50000" oninput="updateChangeDisplay()">
                <div class="pos-quick-cash">
                    <button type="button" class="pos-cash-chip" onclick="setQuickCash('exact')">Uang Pas</button>
                    <button type="button" class="pos-cash-chip" onclick="setQuickCash(10000)">10rb</button>
                    <button type="button" class="pos-cash-chip" onclick="setQuickCash(20000)">20rb</button>
                    <button type="button" class="pos-cash-chip" onclick="setQuickCash(50000)">50rb</button>
                    <button type="button" class="pos-cash-chip" onclick="setQuickCash(100000)">100rb</button>
                </div>
                <div id="pos-change-box" class="pos-change-box">
                    <span>Kembalian:</span>
                    <strong id="pos-change-amount" style="font-size:16px;">Rp 0</strong>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <input type="text" id="pos-notes-input" class="form-input" placeholder="Catatan opsional (misal: Less sugar / Less ice)">
            </div>

            <button type="button" id="pos-submit-btn" onclick="submitPosOrder()" class="btn btn-primary" style="width:100%; height:48px; font-size:16px; font-weight:700; justify-content:center; display:inline-flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Proses Bayar Sekarang
            </button>
        </div>
    </div>
</div>

<!-- Sticky Bottom Bar Mobile -->
<div class="pos-mobile-bar" id="pos-mobile-bar">
    <div onclick="toggleMobileCart(true)" style="cursor:pointer; flex:1;">
        <div style="font-size:12px; color:var(--text-muted);"><span id="mobile-bar-cups">0</span> Cup dipilih</div>
        <div id="mobile-bar-total" style="font-size:18px; font-weight:800; color:var(--accent-green);">Rp 0</div>
    </div>
    <button type="button" class="btn btn-primary" style="height:44px; padding:0 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;" onclick="toggleMobileCart(true)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Bayar
    </button>
</div>

<!-- Modal Struk Digital Ringkas -->
<div class="pos-receipt-overlay" id="pos-receipt-modal">
    <div class="pos-receipt-card">
        <div style="text-align:center; margin-bottom:16px;">
            <div id="receipt-icon" style="margin-bottom:8px; display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; border-radius:50%; background:rgba(16,185,129,0.12); color:#10b981;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3 id="receipt-title" style="margin:0; font-size:18px;">Transaksi Berhasil!</h3>
            <div id="receipt-meta" style="font-size:12px; color:var(--text-muted); margin-top:4px;"></div>
        </div>
        <div id="receipt-items" style="border-top:1px dashed var(--border-color); border-bottom:1px dashed var(--border-color); padding:12px 0; margin-bottom:12px; font-size:13px;"></div>
        <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:700; margin-bottom:6px;">
            <span>Total</span>
            <span id="receipt-total">Rp 0</span>
        </div>
        <div id="receipt-cash-rows" style="font-size:13px; color:var(--text-secondary); margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span>Tunai Diterima</span>
                <span id="receipt-cash">Rp 0</span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:15px; color:var(--accent-green); padding-top:4px;">
                <span>Kembalian</span>
                <span id="receipt-change">Rp 0</span>
            </div>
        </div>
        <button type="button" class="btn btn-primary" style="width:100%; height:44px; justify-content:center; font-weight:700;" onclick="closeReceiptModal()">
            Transaksi Baru
        </button>
    </div>
</div>

<script>
const POS_STORE_URL = <?= json_encode(url('/rider/pos/store')) ?>;
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const OFFLINE_QUEUE_KEY = 'tb_pos_offline_queue_v1';

// Katalog awal dari PHP
const catalog = {};
<?php foreach ($inventories as $inv): ?>
catalog[<?= (int)$inv['product_id'] ?>] = {
    id: <?= (int)$inv['product_id'] ?>,
    name: <?= json_encode($inv['name'], JSON_UNESCAPED_UNICODE) ?>,
    price: <?= (float)$inv['price'] ?>,
    stock: <?= (int)$inv['stock'] ?>
};
<?php endforeach; ?>

let cart = {};
let paymentMethod = 'cash';
let isSubmitting = false;
let isSyncingQueue = false;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatMoney(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(num || 0));
}

function getCartTotals() {
    let total = 0, cups = 0;
    for (const id in cart) {
        total += cart[id].price * cart[id].qty;
        cups += cart[id].qty;
    }
    return { total, cups };
}

function tapProductCard(id) {
    updateQty(id, 1);
}

function updateQty(id, delta) {
    const prod = catalog[id];
    if (!prod) return;
    const current = cart[id] ? cart[id].qty : 0;
    const next = current + delta;

    if (next > prod.stock) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top', icon: 'warning', title: 'Stok ' + prod.name + ' tersisa ' + prod.stock, showConfirmButton: false, timer: 1800 });
        }
        return;
    }

    if (next <= 0) {
        delete cart[id];
    } else {
        cart[id] = { id: prod.id, name: prod.name, price: prod.price, qty: next };
    }
    renderPosState();
}

function clearCart() {
    cart = {};
    document.getElementById('cash-received-input').value = '';
    document.getElementById('pos-notes-input').value = '';
    renderPosState();
}

function filterCategory(cat, btn) {
    document.querySelectorAll('.pos-filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.pos-product-card').forEach(card => {
        const match = (cat === 'all' || card.dataset.category === cat);
        const pid = parseInt(card.dataset.productId, 10);
        const hasStock = catalog[pid] && catalog[pid].stock > 0;
        card.style.display = (match && hasStock) ? '' : 'none';
    });
}

function selectPaymentMethod(method) {
    paymentMethod = method;
    document.getElementById('pay-btn-cash').classList.toggle('active', method === 'cash');
    document.getElementById('pay-btn-qris').classList.toggle('active', method === 'qris');
    document.getElementById('cash-calc-section').style.display = method === 'cash' ? 'block' : 'none';
    updateChangeDisplay();
}

function setQuickCash(val) {
    const { total } = getCartTotals();
    const input = document.getElementById('cash-received-input');
    input.value = val === 'exact' ? (total > 0 ? total : '') : val;
    updateChangeDisplay();
}

function updateChangeDisplay() {
    const { total } = getCartTotals();
    const raw = document.getElementById('cash-received-input').value.trim();
    const box = document.getElementById('pos-change-box');
    const amountEl = document.getElementById('pos-change-amount');

    if (total === 0 || raw === '') {
        box.classList.remove('insufficient');
        box.firstElementChild.textContent = 'Kembalian (Uang Pas):';
        amountEl.textContent = 'Rp 0';
        return;
    }

    const cash = parseFloat(raw) || 0;
    const diff = cash - total;
    if (diff < 0) {
        box.classList.add('insufficient');
        box.firstElementChild.textContent = 'Kurang Bayar:';
        amountEl.textContent = formatMoney(Math.abs(diff));
    } else {
        box.classList.remove('insufficient');
        box.firstElementChild.textContent = 'Kembalian:';
        amountEl.textContent = formatMoney(diff);
    }
}

function toggleMobileCart(open) {
    const panel = document.getElementById('pos-cart-panel');
    const backdrop = document.getElementById('pos-cart-backdrop');
    if (open) {
        panel.classList.add('open');
        backdrop.classList.add('open');
    } else {
        panel.classList.remove('open');
        backdrop.classList.remove('open');
    }
}

function renderPosState() {
    // Update kartu produk
    for (const id in catalog) {
        const card = document.getElementById('prod-card-' + id);
        const badge = document.getElementById('prod-badge-' + id);
        const stepQty = document.getElementById('prod-step-qty-' + id);
        const stockEl = document.getElementById('prod-stock-' + id);
        if (!card) continue;

        const qty = cart[id] ? cart[id].qty : 0;
        const stock = catalog[id].stock;
        stockEl.textContent = stock;

        if (stock <= 0) {
            card.classList.add('out-of-stock');
            card.style.display = 'none';
        } else {
            card.classList.remove('out-of-stock');
        }

        if (qty > 0) {
            card.classList.add('in-cart');
            badge.textContent = qty;
            stepQty.textContent = qty;
        } else {
            card.classList.remove('in-cart');
            badge.textContent = '0';
            stepQty.textContent = '0';
        }
    }

    // Update daftar keranjang
    const container = document.getElementById('cart-items');
    const { total, cups } = getCartTotals();
    let html = '';

    for (const id in cart) {
        const item = cart[id];
        html += `<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border-color);">
            <div style="padding-right:8px;">
                <div style="font-weight:700; font-size:13px; color:var(--text-primary);">${escapeHtml(item.name)}</div>
                <div style="font-size:12px; color:var(--text-muted);">${formatMoney(item.price)} × ${item.qty} = <strong>${formatMoney(item.price * item.qty)}</strong></div>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <button type="button" class="pos-step-btn" onclick="updateQty(${item.id}, -1)">−</button>
                <span style="font-weight:700; min-width:20px; text-align:center;">${item.qty}</span>
                <button type="button" class="pos-step-btn plus" onclick="updateQty(${item.id}, 1)">+</button>
            </div>
        </div>`;
    }

    container.innerHTML = html || '<div style="text-align:center; padding:24px 12px; color:var(--text-muted); font-size:13px;">Ketuk produk untuk menambahkan ke keranjang</div>';
    document.getElementById('pos-clear-btn').style.display = cups > 0 ? 'inline-block' : 'none';
    document.getElementById('cart-total').textContent = formatMoney(total);
    document.getElementById('cart-total-cups').textContent = cups;
    document.getElementById('mobile-bar-cups').textContent = cups;
    document.getElementById('mobile-bar-total').textContent = formatMoney(total);
    updateChangeDisplay();
}

function getOfflineQueue() {
    try {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function saveOfflineQueue(queue) {
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    updateQueueBanner();
}

function updateQueueBanner() {
    const queue = getOfflineQueue();
    const banner = document.getElementById('pos-queue-banner');
    document.getElementById('pos-queue-count').textContent = queue.length;
    banner.classList.toggle('show', queue.length > 0);
}

function updateNetworkStatusUI() {
    const pill = document.getElementById('pos-net-pill');
    const text = document.getElementById('pos-net-text');
    if (navigator.onLine) {
        pill.classList.remove('offline');
        text.textContent = 'Online · Siap Transaksi';
    } else {
        pill.classList.add('offline');
        text.textContent = 'Mode Offline · Transaksi Disimpan di HP';
    }
}

function applyStocksFromServer(stocksMap) {
    if (!stocksMap) return;
    for (const id in catalog) {
        catalog[id].stock = typeof stocksMap[id] !== 'undefined' ? parseInt(stocksMap[id], 10) : 0;
    }
    renderPosState();
}

function showReceiptModal(receipt) {
    document.getElementById('receipt-icon').innerHTML = receipt.offline
        ? '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>'
        : '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    document.getElementById('receipt-title').textContent = receipt.offline ? 'Tersimpan di Antrean Offline' : 'Transaksi Berhasil!';
    document.getElementById('receipt-meta').textContent = (receipt.transaction_id ? '#' + receipt.transaction_id + ' · ' : '') + receipt.created_at + ' · ' + receipt.payment_method.toUpperCase();
    document.getElementById('receipt-items').innerHTML = receipt.items.map(i =>
        `<div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span>${escapeHtml(i.name)} × ${i.qty}</span>
            <span>${formatMoney(i.price * i.qty)}</span>
        </div>`
    ).join('');
    document.getElementById('receipt-total').textContent = formatMoney(receipt.total_price);

    const cashRows = document.getElementById('receipt-cash-rows');
    if (receipt.payment_method === 'cash') {
        cashRows.style.display = 'block';
        document.getElementById('receipt-cash').textContent = formatMoney(receipt.cash_received);
        document.getElementById('receipt-change').textContent = formatMoney(receipt.change_amount);
    } else {
        cashRows.style.display = 'none';
    }

    document.getElementById('pos-receipt-modal').classList.add('open');
}

function closeReceiptModal() {
    document.getElementById('pos-receipt-modal').classList.remove('open');
}

async function submitPosOrder() {
    if (isSubmitting) return;
    const { total, cups } = getCartTotals();
    if (cups === 0) {
        Swal.fire({ toast: true, position: 'top', icon: 'info', title: 'Pilih minimal 1 menu terlebih dahulu', showConfirmButton: false, timer: 1800 });
        return;
    }

    const rawCash = document.getElementById('cash-received-input').value.trim();
    const cashReceived = paymentMethod === 'cash' ? (rawCash === '' ? total : parseFloat(rawCash)) : null;

    if (paymentMethod === 'cash' && (isNaN(cashReceived) || cashReceived < total)) {
        Swal.fire({ toast: true, position: 'top', icon: 'error', title: 'Uang diterima kurang dari total belanja!', showConfirmButton: false, timer: 2200 });
        return;
    }

    const itemsPayload = Object.values(cart).map(i => ({
        product_id: i.id,
        name: i.name,
        price: i.price,
        qty: i.qty
    }));
    const payload = {
        _token: CSRF_TOKEN,
        client_tx_id: 'tx_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8),
        payment_method: paymentMethod,
        cash_received: cashReceived,
        notes: document.getElementById('pos-notes-input').value.trim(),
        items: itemsPayload,
        queued_at: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
    };

    const btn = document.getElementById('pos-submit-btn');
    isSubmitting = true;
    btn.disabled = true;
    btn.innerHTML = 'Memproses...';

    // Fungsi simpan ke antrean offline saat sinyal mati / timeout
    const enqueueOffline = () => {
        const q = getOfflineQueue();
        q.push(payload);
        saveOfflineQueue(q);

        // Kurangi stok lokal agar tidak oversell saat offline
        itemsPayload.forEach(i => {
            if (catalog[i.product_id]) {
                catalog[i.product_id].stock = Math.max(0, catalog[i.product_id].stock - i.qty);
            }
        });
        clearCart();
        toggleMobileCart(false);
        showReceiptModal({
            offline: true,
            transaction_id: null,
            created_at: 'Offline · ' + payload.queued_at,
            payment_method: payload.payment_method,
            items: itemsPayload,
            total_price: total,
            cash_received: cashReceived,
            change_amount: paymentMethod === 'cash' ? (cashReceived - total) : 0
        });
    };

    if (!navigator.onLine) {
        enqueueOffline();
        isSubmitting = false;
        btn.disabled = false;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polyline points="20 6 9 17 4 12"/></svg>Proses Bayar Sekarang';
        return;
    }

    try {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 10000);

        const res = await fetch(POS_STORE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload),
            signal: controller.signal
        });
        clearTimeout(timer);

        const data = await res.json();
        if (!res.ok || !data.success) {
            // Error validasi server (misal stok kurang / sesi habis) -> JANGAN masukkan offline queue
            Swal.fire({ icon: 'error', title: 'Gagal Memproses', text: data.error || 'Terjadi kesalahan validasi.' });
            if (res.status === 419) setTimeout(() => window.location.reload(), 1200);
        } else {
            applyStocksFromServer(data.stocks);
            clearCart();
            toggleMobileCart(false);
            showReceiptModal({
                offline: false,
                transaction_id: data.transaction_id,
                created_at: data.created_at,
                payment_method: payload.payment_method,
                items: itemsPayload,
                total_price: data.total_price,
                cash_received: paymentMethod === 'cash' ? cashReceived : 0,
                change_amount: paymentMethod === 'cash' ? (cashReceived - data.total_price) : 0
            });
        }
    } catch (networkErr) {
        // Jaringan terputus di tengah jalan -> simpan aman ke Offline Queue dengan client_tx_id yang sama!
        enqueueOffline();
    } finally {
        isSubmitting = false;
        btn.disabled = false;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polyline points="20 6 9 17 4 12"/></svg>Proses Bayar Sekarang';
    }
}

async function syncOfflineQueue(manual = false) {
    if (isSyncingQueue || !navigator.onLine) return;
    let queue = getOfflineQueue();
    if (queue.length === 0) return;

    isSyncingQueue = true;
    const syncBtn = document.getElementById('pos-sync-btn');
    if (syncBtn) { syncBtn.disabled = true; syncBtn.textContent = 'Menyinkronkan...'; }

    const remaining = [];
    let syncedCount = 0;

    for (const tx of queue) {
        try {
            const res = await fetch(POS_STORE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(tx)
            });
            const data = await res.json();
            if (res.ok && data.success) {
                syncedCount++;
                if (data.stocks) applyStocksFromServer(data.stocks);
            } else if (res.status === 422) {
                // Stok server ternyata sudah habis, beritahu Rider dan keluarkan dari antrean macet
                Swal.fire({ icon: 'warning', title: 'Transaksi Offline Ditolak Server', text: data.error || 'Stok tidak mencukupi.' });
            } else {
                remaining.push(tx);
            }
        } catch (err) {
            remaining.push(tx);
        }
    }

    saveOfflineQueue(remaining);
    isSyncingQueue = false;
    if (syncBtn) { syncBtn.disabled = false; syncBtn.textContent = 'Sinkronkan Sekarang'; }

    if (syncedCount > 0 && (manual || remaining.length === 0)) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: syncedCount + ' transaksi offline berhasil disinkronkan!', showConfirmButton: false, timer: 2500 });
    }
}

window.addEventListener('online', () => { updateNetworkStatusUI(); syncOfflineQueue(false); });
window.addEventListener('offline', updateNetworkStatusUI);
document.addEventListener('DOMContentLoaded', () => {
    updateNetworkStatusUI();
    updateQueueBanner();
    syncOfflineQueue(false);
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
