<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_bar_admin_owner();
$user = auth_user();
$isBar = ($user['role'] === 'bar');
$isAdmin = in_array($user['role'], ['admin', 'owner']);
$riders = $pdo->prepare("SELECT id, name FROM users WHERE role='rider' AND branch_id=?"); $riders->execute([activeBranchId()]); $riders = $riders->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$pageTitle = 'Input Penjualan Rider Baru';
ob_start();

$rows = [
    'stock_out' => 'Produk Keluar',
    'stock_added' => 'Produk Tambahan',
    'stock_return' => 'Produk Retur',
    'stock_sold' => 'Produk Laku',
];

$finReadonly = $isBar ? 'readonly tabindex="-1"' : '';
$finStyle = $isBar ? 'background: rgba(0,0,0,0.05); cursor: not-allowed; color: var(--text-muted);' : '';
?>
<div class="card mb-4" style="max-width: 1400px; margin: 0 auto; background: var(--bg-card);">
    <?php if($isBar): ?>
        <div class="card-header" style="border-bottom: 2px solid var(--border-color); padding: 20px; display: block;">
            <div id="stock-cards-container" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <div style="color: var(--text-muted); font-size: 0.9rem; padding: 5px;">Memuat data stok...</div>
            </div>
        </div>
    <?php else: ?>
        <div class="card-header" style="text-align: center; border-bottom: 2px solid var(--border-color); padding: 20px;">
            <h2 style="font-weight: 700; color: var(--text-primary); margin: 0; font-size: 1.5rem; letter-spacing: 1px;">TETHER BREW OFFICE</h2>
        </div>
    <?php endif; ?>

    <div class="card-body">
        <form action="<?= url('/rider-sales/store') ?>" method="POST" id="sales-form">
            <?= csrf_field() ?>

            <!-- Top Row: Date & Rider -->
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px;">
                <div class="form-group" style="flex: 1; min-width: 250px;">
                    <label class="form-label" style="font-weight: 600; text-transform: uppercase;">TANGGAL</label>
                    <div class="flatpickr-input-container">
                        <input type="text" name="date" id="date" class="form-input" value="<?= date('Y-m-d') ?>" required placeholder="Pilih tanggal..." style="height: 48px; font-size: 1rem;">
                        <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                </div>
                <div class="form-group" style="flex: 1; min-width: 250px;">
                    <label class="form-label" style="font-weight: 600; text-transform: uppercase;">NAMA RIDER</label>
                    <select name="rider_id" class="form-input" required style="height: 48px; font-size: 1rem;">
                        <option value="">-- Pilih Rider --</option>
                        <?php foreach($riders as $rider): ?>
                            <option value="<?= $rider['id'] ?>"><?= e($rider['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Matrix Table -->
            <div class="table-container no-search" style="margin-bottom: 40px; border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto;">
                <table class="table" style="min-width: 1400px; margin: 0; white-space: nowrap;">
                    <thead style="background: rgba(0,0,0,0.02);">
                        <tr>
                            <th style="padding: 15px; font-weight: 600; border-right: 1px solid var(--border-color); position: sticky; left: 0; background: var(--bg-card); z-index: 10;">Nama Produk</th>
                            <?php foreach($products as $product): 
                                $isLive = !empty($product['is_live_brew']);
                            ?>
                                <th style="padding: 15px; font-weight: 600; text-align: center; border-right: 1px solid var(--border-color); min-width: 110px; <?= $isLive ? 'background: rgba(245, 158, 11, 0.03);' : '' ?>">
                                    <div><?= e($product['name']) ?></div>
                                    <?php if($isLive): ?>
                                        <span style="display:inline-block; font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(245, 158, 11, 0.15); color:#d97706; font-weight:700; margin-top:4px; border:1px solid rgba(245, 158, 11, 0.3);">Live Brew</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $key => $label): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 15px; font-weight: 600; border-right: 1px solid var(--border-color); position: sticky; left: 0; background: var(--bg-card); z-index: 10;">
                                    <?= $label ?>
                                </td>
                                <?php foreach($products as $product):
                                    $isLive = !empty($product['is_live_brew']);
                                    $isReadonly = false;
                                    
                                    if ($isLive) {
                                        if ($key === 'stock_sold') {
                                            $isReadonly = false;
                                            $style = 'width: 80px; text-align: center; margin: 0 auto; padding: 6px; font-size: 0.95rem; background: var(--bg-card); border: 2px solid #22c55e; font-weight: 700; color: var(--accent-green); border-radius: 6px;';
                                        } else {
                                            $isReadonly = true;
                                            $style = 'width: 80px; text-align: center; margin: 0 auto; padding: 6px; font-size: 0.95rem; background: rgba(0,0,0,0.03); cursor: not-allowed; color: #94a3b8; border-color: rgba(0,0,0,0.06);';
                                        }
                                    } else {
                                        if ($key === 'stock_sold') $isReadonly = true;
                                        if (($key === 'stock_out' || $key === 'stock_added') && $isAdmin) $isReadonly = true;
                                        if ($key === 'stock_return' && $isBar) $isReadonly = true;
                                        
                                        $style = 'width: 80px; text-align: center; margin: 0 auto; padding: 6px; font-size: 0.95rem;';
                                        if ($key === 'stock_sold') {
                                            $style .= ' background: rgba(34,197,94,0.08); font-weight: 700; cursor: not-allowed;';
                                        } elseif ($isReadonly) {
                                            $style .= ' background: rgba(0,0,0,0.05); cursor: not-allowed; color: var(--text-muted);';
                                        }
                                    }
                                ?>
                                    <td style="padding: 8px; text-align: center; border-right: 1px solid var(--border-color); <?= $isLive ? 'background: rgba(245, 158, 11, 0.02);' : '' ?>">
                                        <input type="number" 
                                               name="items[<?= $product['id'] ?>][<?= $key ?>]" 
                                               class="form-input item-input <?= $key ?>"
                                               data-product-id="<?= $product['id'] ?>"
                                               data-price="<?= $product['price'] ?>"
                                               data-is-live-brew="<?= $isLive ? '1' : '0' ?>"
                                               min="0" 
                                               placeholder="<?= ($isLive && $key !== 'stock_sold') ? '-' : '0' ?>"
                                               <?= $isReadonly ? 'readonly tabindex="-1"' : '' ?>
                                               style="<?= $style ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--border-color); background: rgba(0,0,0,0.03);">
                            <td style="padding: 12px 15px; font-weight: 700; border-right: 1px solid var(--border-color); position: sticky; left: 0; background: var(--bg-card); z-index: 10;">
                                Total Uang
                            </td>
                            <?php foreach($products as $product): ?>
                                <td style="padding: 12px 8px; text-align: center; border-right: 1px solid var(--border-color); font-weight: 700; color: #22c55e;">
                                    <span class="total-uang-cell" data-product-id="<?= $product['id'] ?>" data-price="<?= $product['price'] ?>">
                                        Rp 0
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Summary Section -->
            <div style="margin-bottom: 30px; padding: 25px; background: rgba(0,0,0,0.02); border: 1px solid var(--border-color); border-radius: 12px;">
                <h4 style="margin: 0 0 20px; font-weight: 700; font-size: 1.2rem; letter-spacing: 1px;">TOTAL</h4>
                
                <!-- Row 1: CASH & QRIS -->
                <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600;">CASH</label>
                        <input type="number" name="cash_amount" id="cash-amount" class="form-input" <?= $finReadonly ?> style="height: 44px; font-size: 1rem; font-weight: 600; <?= $finStyle ?>">
                    </div>
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600;">QRIS</label>
                        <input type="number" name="qris_amount" class="form-input" <?= $finReadonly ?> style="height: 44px; font-size: 1rem; font-weight: 600; <?= $finStyle ?>">
                    </div>
                </div>

                <!-- Row 1.5: Actual Setor & Minus -->
                <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600;">Actual Setor</label>
                        <input type="number" name="actual_setor" id="actual-setor" class="form-input" <?= !$isBar ? 'required' : '' ?> <?= $finReadonly ?> style="height: 44px; font-size: 1rem; font-weight: 600; <?= !$isBar ? 'border-color: #3b82f6;' : '' ?> <?= $finStyle ?>">
                    </div>
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600; color: #ef4444;">Minus</label>
                        <input type="number" name="minus_amount" id="minus-amount" class="form-input" readonly
                               style="height: 44px; font-size: 1rem; font-weight: 600; background: rgba(239,68,68,0.08); color: #ef4444; cursor: not-allowed;">
                    </div>
                </div>
                
                <!-- Row 2: TOTAL & Total Cup -->
                <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600;">TOTAL</label>
                        <input type="number" name="total_gross_income" id="total-gross-income" class="form-input" readonly
                               style="height: 44px; font-size: 1.1rem; font-weight: 700; background: rgba(34,197,94,0.08); cursor: not-allowed;">
                    </div>
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label" style="font-weight: 600; color: #8b5c2a;">Total Cup Terjual</label>
                        <input type="number" id="total-cup" class="form-input" readonly
                               style="height: 44px; font-size: 1.1rem; font-weight: 700; background: rgba(139,92,42,0.08); color: #8b5c2a; cursor: not-allowed;">
                    </div>
                </div>
            </div>

            <!-- Admin Pemeriksa & Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
                <div class="form-group" style="flex: 1; max-width: 300px; margin: 0;">
                    <label class="form-label" style="font-weight: 600;">Nama Admin Pemeriksa</label>
                    <input type="text" name="admin_pemeriksa" class="form-input" value="<?= e($user['name']) ?>" <?= $finReadonly ?> style="height: 48px; font-size: 1rem; <?= $finStyle ?>">
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <button type="reset" class="btn btn-secondary" style="height: 48px; padding: 0 30px; font-size: 1rem; font-weight: 600;">Reset</button>
                    <button type="submit" class="btn btn-primary" style="height: 48px; padding: 0 40px; font-size: 1rem; font-weight: 600;">Submit</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentStockData = [];

    const stockContainer = document.getElementById('stock-cards-container');
    const dateInput = document.getElementById('date');
    const riderSelect = document.querySelector('select[name="rider_id"]');

    async function fetchAvailableStock() {
        if (!stockContainer) return;
        
        const date = dateInput.value;
        const riderId = riderSelect ? riderSelect.value : '';
        
        try {
            stockContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.9rem; padding: 5px;">Memuat data stok...</div>';
            
            const response = await fetch(`<?= url('/rider-sales/available-stock') ?>?date=${date}&rider_id=${riderId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            const stockData = Array.isArray(data) ? data : (data.stockData || []);
            const riderSale = data.riderSale || null;
            
            currentStockData = stockData;
            stockContainer.innerHTML = '';
            
            if (stockData.length === 0) {
                stockContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.9rem; padding: 5px;">Tidak ada data stok produksi.</div>';
                return;
            }
            
            stockData.forEach(item => {
                const card = document.createElement('div');
                card.className = 'stock-card';
                
                if (item.is_live_brew) {
                    card.style.cssText = `
                        background: rgba(245, 158, 11, 0.05);
                        border: 1px dashed rgba(245, 158, 11, 0.3);
                        border-radius: 10px;
                        padding: 12px 16px;
                        min-width: 170px;
                        flex: 1;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.01);
                        transition: all 0.2s ease;
                    `;
                    card.innerHTML = `
                        <div style="font-size: 0.72rem; font-weight: 700; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px;">${item.product_name}</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #d97706; margin: 4px 0;">
                            Live Brew
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); border-top: 1px dashed rgba(245, 158, 11, 0.2); padding-top: 5px; margin-top: 5px;">
                            <span>Diseduh di jalan</span>
                        </div>
                    `;
                } else {
                    let bg = 'rgba(34, 197, 94, 0.04)';
                    let border = 'rgba(34, 197, 94, 0.15)';
                    let text = '#22c55e';
                    
                    if (item.available === 0) {
                        bg = 'rgba(239, 68, 68, 0.04)';
                        border = 'rgba(239, 68, 68, 0.15)';
                        text = '#ef4444';
                    } else if (item.available < 5) {
                        bg = 'rgba(245, 158, 11, 0.04)';
                        border = 'rgba(245, 158, 11, 0.15)';
                        text = '#f59e0b';
                    }
                    
                    card.style.cssText = `
                        background: ${bg};
                        border: 1px solid ${border};
                        border-radius: 10px;
                        padding: 12px 16px;
                        min-width: 170px;
                        flex: 1;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.01);
                        transition: all 0.2s ease;
                    `;
                    
                    card.innerHTML = `
                        <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">${item.product_name}</div>
                        <div style="font-size: 1.6rem; font-weight: 800; color: ${text}; margin: 4px 0; display: flex; align-items: baseline; gap: 4px;">
                            ${item.available}
                            <span style="font-size: 0.8rem; font-weight: 500; color: var(--text-muted);">Pcs</span>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); border-top: 1px dashed var(--border-color); padding-top: 5px; margin-top: 5px; display: flex; justify-content: space-between;">
                            <span>Prod: <strong>${item.produced}</strong></span>
                            <span>Out: <strong>${item.used}</strong></span>
                            <span>Basi: <strong>${item.spoiled}</strong></span>
                        </div>
                    `;
                }

                stockContainer.appendChild(card);
                
                // Prefill inputs
                const formElement = document.getElementById('sales-form');
                if (formElement && riderId) {
                    const stockOutInput = formElement.querySelector(`.item-input.stock_out[data-product-id="${item.product_id}"]`);
                    const stockAddedInput = formElement.querySelector(`.item-input.stock_added[data-product-id="${item.product_id}"]`);
                    const stockReturnInput = formElement.querySelector(`.item-input.stock_return[data-product-id="${item.product_id}"]`);
                    const stockSoldInput = formElement.querySelector(`.item-input.stock_sold[data-product-id="${item.product_id}"]`);
                    
                    if (item.rider_item) {
                        if(stockOutInput) stockOutInput.value = item.rider_item.stock_out;
                        if(stockAddedInput) stockAddedInput.value = item.rider_item.stock_added;
                        if(stockReturnInput) stockReturnInput.value = item.rider_item.stock_return;
                        if(stockSoldInput) stockSoldInput.value = item.rider_item.stock_sold;
                    } else {
                        if(stockOutInput) stockOutInput.value = '';
                        if(stockAddedInput) stockAddedInput.value = '';
                        if(stockReturnInput) stockReturnInput.value = '';
                        if(stockSoldInput) stockSoldInput.value = '';
                    }
                    if (typeof recalcProduct === 'function') {
                        recalcProduct(item.product_id);
                    }
                }
            });

            const formElement = document.getElementById('sales-form');
            if (formElement && riderId) {
                const cashInput = document.getElementById('cash-amount');
                const qrisInput = document.querySelector('input[name="qris_amount"]');
                const actualSetorInput = document.getElementById('actual-setor');
                const minusInput = document.getElementById('minus-amount');
                const adminInput = document.querySelector('input[name="admin_pemeriksa"]');

                if (riderSale) {
                    if(cashInput) cashInput.value = riderSale.cash_amount > 0 ? riderSale.cash_amount : '';
                    if(qrisInput) qrisInput.value = riderSale.qris_amount > 0 ? riderSale.qris_amount : '';
                    if(actualSetorInput) actualSetorInput.value = riderSale.actual_setor > 0 ? riderSale.actual_setor : '';
                    if(minusInput) minusInput.value = riderSale.minus_amount > 0 ? riderSale.minus_amount : '';
                    if(adminInput && riderSale.admin_pemeriksa) adminInput.value = riderSale.admin_pemeriksa;
                } else {
                    if(cashInput) cashInput.value = '';
                    if(qrisInput) qrisInput.value = '';
                    if(actualSetorInput) actualSetorInput.value = '';
                    if(minusInput) minusInput.value = '';
                    if(adminInput) adminInput.value = '<?= e($user['name']) ?>';
                }
                
                if (typeof recalcGrandTotal === 'function') {
                    recalcGrandTotal();
                }
            }
        } catch (error) {
            console.error('Error fetching stock:', error);
            stockContainer.innerHTML = '<div style="color: #ef4444; font-size: 0.9rem; padding: 5px;">Gagal memuat data stok.</div>';
        }
    }

    // For non-bar (admin/owner), also fetch stock data on date/rider change to prefill matrix
    <?php if(!$isBar): ?>
    async function fetchAndPrefill() {
        const date = dateInput.value;
        const riderId = riderSelect ? riderSelect.value : '';
        if (!date || !riderId) return;
        
        try {
            const response = await fetch(`<?= url('/rider-sales/available-stock') ?>?date=${date}&rider_id=${riderId}`);
            if (!response.ok) return;
            const data = await response.json();
            
            const stockData = Array.isArray(data) ? data : (data.stockData || []);
            const riderSale = data.riderSale || null;
            currentStockData = stockData;
            
            const formElement = document.getElementById('sales-form');
            stockData.forEach(item => {
                const stockOutInput = formElement.querySelector(`.item-input.stock_out[data-product-id="${item.product_id}"]`);
                const stockAddedInput = formElement.querySelector(`.item-input.stock_added[data-product-id="${item.product_id}"]`);
                const stockReturnInput = formElement.querySelector(`.item-input.stock_return[data-product-id="${item.product_id}"]`);
                const stockSoldInput = formElement.querySelector(`.item-input.stock_sold[data-product-id="${item.product_id}"]`);
                
                if (item.rider_item) {
                    if(stockOutInput) stockOutInput.value = item.rider_item.stock_out;
                    if(stockAddedInput) stockAddedInput.value = item.rider_item.stock_added;
                    if(stockReturnInput) stockReturnInput.value = item.rider_item.stock_return;
                    if(stockSoldInput) stockSoldInput.value = item.rider_item.stock_sold;
                } else {
                    if(stockOutInput) stockOutInput.value = '';
                    if(stockAddedInput) stockAddedInput.value = '';
                    if(stockReturnInput) stockReturnInput.value = '';
                    if(stockSoldInput) stockSoldInput.value = '';
                }
                recalcProduct(item.product_id);
            });

            const cashInput = document.getElementById('cash-amount');
            const qrisInput = document.querySelector('input[name="qris_amount"]');
            const actualSetorInput = document.getElementById('actual-setor');
            const minusInput = document.getElementById('minus-amount');
            const adminInput = document.querySelector('input[name="admin_pemeriksa"]');

            if (riderSale) {
                if(cashInput) cashInput.value = riderSale.cash_amount > 0 ? riderSale.cash_amount : '';
                if(qrisInput) qrisInput.value = riderSale.qris_amount > 0 ? riderSale.qris_amount : '';
                if(actualSetorInput) actualSetorInput.value = riderSale.actual_setor > 0 ? riderSale.actual_setor : '';
                if(minusInput) minusInput.value = riderSale.minus_amount > 0 ? riderSale.minus_amount : '';
                if(adminInput && riderSale.admin_pemeriksa) adminInput.value = riderSale.admin_pemeriksa;
            } else {
                if(cashInput) cashInput.value = '';
                if(qrisInput) qrisInput.value = '';
                if(actualSetorInput) actualSetorInput.value = '';
                if(minusInput) minusInput.value = '';
                if(adminInput) adminInput.value = '<?= e($user['name']) ?>';
            }
            recalcGrandTotal();
        } catch(e) { console.error(e); }
    }
    <?php endif; ?>

    flatpickr("#date", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "j F Y",
        allowInput: true,
        disableMobile: "true",
        onChange: function(selectedDates, dateStr, instance) {
            <?php if($isBar): ?>
            fetchAvailableStock();
            <?php else: ?>
            fetchAndPrefill();
            <?php endif; ?>
        }
    });

    if (riderSelect) {
        riderSelect.addEventListener('change', () => {
            <?php if($isBar): ?>
            fetchAvailableStock();
            <?php else: ?>
            fetchAndPrefill();
            <?php endif; ?>
        });
    }
    
    // Initial fetch
    <?php if($isBar): ?>
    fetchAvailableStock();
    <?php endif; ?>

    const form = document.getElementById('sales-form');

    // Prevent scroll-wheel changing number input values
    form.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('wheel', (e) => e.preventDefault());
    });

    function getVal(productId, rowClass) {
        const input = form.querySelector(`.item-input.${rowClass}[data-product-id="${productId}"]`);
        return input ? (parseInt(input.value) || 0) : 0;
    }

    function recalcProduct(productId) {
        const soldInput = form.querySelector(`.item-input.stock_sold[data-product-id="${productId}"]`);
        const isLiveBrew = soldInput && soldInput.dataset.isLiveBrew === '1';
        
        let stockSold = 0;
        if (isLiveBrew) {
            stockSold = soldInput ? (parseInt(soldInput.value) || 0) : 0;
        } else {
            const stockOut = getVal(productId, 'stock_out');
            const stockAdded = getVal(productId, 'stock_added');
            const stockReturn = getVal(productId, 'stock_return');

            stockSold = Math.max(0, stockOut + stockAdded - stockReturn);
            if (soldInput) {
                soldInput.value = stockSold;
            }
        }

        const totalUangCell = document.querySelector(`.total-uang-cell[data-product-id="${productId}"]`);
        if (totalUangCell) {
            const price = parseFloat(totalUangCell.dataset.price) || 0;
            const totalUang = price * stockSold;
            totalUangCell.textContent = 'Rp ' + totalUang.toLocaleString('id-ID');
        }
    }

    function recalcGrandTotal() {
        let grandTotal = 0;
        let totalCups = 0;
        document.querySelectorAll('.total-uang-cell').forEach(cell => {
            const price = parseFloat(cell.dataset.price) || 0;
            const productId = cell.dataset.productId;
            const stockSold = getVal(productId, 'stock_sold');
            grandTotal += price * stockSold;
            totalCups += stockSold;
        });
        const totalInput = document.getElementById('total-gross-income');
        if (totalInput) {
            totalInput.value = grandTotal;
            if (typeof autoCalcPayment === 'function') {
                autoCalcPayment('total');
            }
        }
        const totalCupInput = document.getElementById('total-cup');
        if (totalCupInput) {
            totalCupInput.value = totalCups;
        }
    }

    form.querySelectorAll('.item-input.stock_out, .item-input.stock_added, .item-input.stock_return, .item-input.stock_sold').forEach(input => {
        input.addEventListener('input', () => {
            const isLiveBrew = input.dataset.isLiveBrew === '1';
            <?php if($isBar): ?>
                if (!isLiveBrew && (input.classList.contains('stock_out') || input.classList.contains('stock_added'))) {
                    const productId = input.dataset.productId;
                    const stockOut = getVal(productId, 'stock_out');
                    const stockAdded = getVal(productId, 'stock_added');
                    const totalInput = stockOut + stockAdded;

                    const stockInfo = currentStockData.find(item => item.product_id == productId);
                    if (stockInfo) {
                        const maxAvailable = stockInfo.available;
                        if (totalInput > maxAvailable) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Stok Tidak Mencukupi!',
                                    text: `Stok Tersedia Hanya ${maxAvailable} Pcs. Anda menginput melebihi batas.`,
                                    icon: 'error',
                                    confirmButtonText: 'Oke',
                                    customClass: { confirmButton: 'btn btn-primary' },
                                    buttonsStyling: false
                                });
                            } else {
                                alert(`Stok Tidak Mencukupi! Stok Tersedia Hanya ${maxAvailable} Pcs.`);
                            }

                            if (input.classList.contains('stock_out')) {
                                input.value = Math.min(stockOut, maxAvailable);
                            } else {
                                const currentStockOut = getVal(productId, 'stock_out');
                                input.value = Math.max(0, maxAvailable - currentStockOut);
                            }
                        }
                    }
                }
            <?php endif; ?>

            recalcProduct(input.dataset.productId);
            recalcGrandTotal();
        });
    });

    // Payment and Minus Calculation
    const cashInput = document.getElementById('cash-amount');
    const qrisInput = document.querySelector('input[name="qris_amount"]');
    const totalInput = document.getElementById('total-gross-income');
    const actualSetorInput = document.getElementById('actual-setor');
    const minusInput = document.getElementById('minus-amount');

    let lastEditedPayment = 'cash'; // Default priority to cash

    function calcMinus() {
        const cash = parseFloat(cashInput ? cashInput.value : 0) || 0;
        const actual = parseFloat(actualSetorInput ? actualSetorInput.value : 0) || 0;
        const minus = Math.max(0, cash - actual);
        if (minusInput) minusInput.value = minus;
    }

    function autoCalcPayment(source) {
        const total = parseFloat(totalInput ? totalInput.value : 0) || 0;
        
        if (source === 'cash') {
            lastEditedPayment = 'cash';
            const cash = parseFloat(cashInput.value) || 0;
            if (qrisInput) qrisInput.value = Math.max(0, total - cash);
        } else if (source === 'qris') {
            lastEditedPayment = 'qris';
            const qris = parseFloat(qrisInput.value) || 0;
            if (cashInput) cashInput.value = Math.max(0, total - qris);
        } else if (source === 'total') {
            if (lastEditedPayment === 'cash') {
                const cash = parseFloat(cashInput ? cashInput.value : 0) || 0;
                if (qrisInput) qrisInput.value = Math.max(0, total - cash);
            } else {
                const qris = parseFloat(qrisInput ? qrisInput.value : 0) || 0;
                if (cashInput) cashInput.value = Math.max(0, total - qris);
            }
        }
        calcMinus();
    }

    if (cashInput) {
        cashInput.addEventListener('input', () => autoCalcPayment('cash'));
    }
    if (qrisInput) {
        qrisInput.addEventListener('input', () => autoCalcPayment('qris'));
    }
    if (actualSetorInput) {
        actualSetorInput.addEventListener('input', calcMinus);
    }
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../layouts/app.php';
