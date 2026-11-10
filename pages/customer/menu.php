<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('customer');

// Get active products
$products = $pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY sort_order, name ASC")->fetchAll();

// Get active carts with riders
$activeCarts = $pdo->query("
    SELECT c.id, c.name, c.user_id AS rider_id, u.name AS rider_name, u.whatsapp
    FROM carts c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.status = 'active' AND u.is_active = 1
    ORDER BY c.name ASC
")->fetchAll();

$pageTitle = 'Menu - Tether Brew';
ob_start();
?>
<style>
    .menu-header {
        padding: 20px;
        background: white;
        position: sticky;
        top: 0;
        z-index: 40;
        border-bottom: 1px solid #f1f5f9;
    }
    .menu-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .products-container {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .product-card {
        display: flex;
        background: white;
        border-radius: 16px;
        padding: 15px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        gap: 15px;
    }

    .product-img {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        object-fit: cover;
        background: #f8fafc;
    }

    .product-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .product-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: #1e293b;
        margin: 0 0 4px 0;
    }

    .product-desc {
        font-size: 0.8rem;
        color: #64748b;
        margin: 0 0 8px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-price {
        font-weight: 700;
        color: #8b5c2a;
        font-size: 0.95rem;
    }

    .order-btn {
        background: #8b5c2a;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
    }
</style>

<div class="menu-header">
    <h1 class="menu-title">Menu Pilihan</h1>
</div>

<div class="products-container">
    <?php if(empty($products)): ?>
        <div style="text-align: center; padding: 40px 20px; color: #64748b;">
            Belum ada produk yang tersedia.
        </div>
    <?php else: ?>
        <?php foreach($products as $product): ?>
            <div class="product-card">
                <?php if(!empty($product['image'])): ?>
                    <img src="<?= url('/uploads/image/' . e($product['image'])) ?>" alt="<?= e($product['name']) ?>" class="product-img">
                <?php else: ?>
                    <div class="product-img" style="display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <h3 class="product-name"><?= e($product['name']) ?></h3>
                    <p class="product-desc"><?= e($product['description'] ?: 'Kopi nikmat spesial dari Tether Brew.') ?></p>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="product-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></div>
                        <button class="order-btn" onclick="orderFromCustomerMenu(<?= htmlspecialchars(json_encode($product['name']), ENT_QUOTES, 'UTF-8') ?>)">Pesan</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
const activeCarts = <?= json_encode($activeCarts, JSON_UNESCAPED_UNICODE) ?>;

async function orderFromCustomerMenu(productName) {
    if (!activeCarts || activeCarts.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Gerobak Sedang Istirahat',
            text: 'Saat ini belum ada rider yang aktif keliling. Silakan cek peta beranda atau hubungi WhatsApp Admin untuk pesanan khusus!',
            showCancelButton: true,
            confirmButtonText: 'Chat Admin WA',
            cancelButtonText: 'Tutup'
        }).then(result => {
            if (result.isConfirmed) {
                const msg = encodeURIComponent(`Halo Tether Brew! Saya ingin memesan ${productName} atau tanya jadwal gerobak terdekat.`);
                window.open(`https://wa.me/6282379742368?text=${msg}`, '_blank');
            }
        });
        return;
    }

    const inputOptions = {};
    activeCarts.forEach(c => {
        inputOptions[c.id] = `${c.name} (${c.rider_name})`;
    });

    const { value: selectedCartId } = await Swal.fire({
        title: 'Pilih Gerobak',
        text: `Pilih gerobak tujuan untuk memesan ${productName}:`,
        input: 'select',
        inputOptions: inputOptions,
        inputPlaceholder: '-- Pilih Gerobak --',
        showCancelButton: true,
        confirmButtonText: 'Lanjut ke Chat',
        cancelButtonText: 'Batal',
        inputValidator: (value) => {
            if (!value) return 'Silakan pilih salah satu gerobak!';
        }
    });

    if (selectedCartId) {
        const cart = activeCarts.find(c => String(c.id) === String(selectedCartId));
        if (!cart || !cart.rider_id) return;

        Swal.fire({
            title: 'Menghubungkan ke Rider...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const fd = new FormData();
        fd.append('rider_id', cart.rider_id);
        fd.append('cart_id', cart.id);

        try {
            const res = await fetch('<?= url('/api/chat/start') ?>', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.conversation && data.conversation.id) {
                window.location.href = '<?= url('/customer/chat_detail') ?>?id=' + data.conversation.id;
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.error || 'Gagal memulai percakapan.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Koneksi Terganggu', text: 'Periksa koneksi internet Anda.' });
        }
    }
}
</script>

<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../layouts/customer.php';
?>
