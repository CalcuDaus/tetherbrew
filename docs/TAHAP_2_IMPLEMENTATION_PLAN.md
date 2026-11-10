# Dokumen Rencana Implementasi — Tahap 2 (Perombakan UX Mobile-First, PWA, Rider POS Offline-Ready, & Dark Mode)

> **INSTRUKSI KHUSUS UNTUK MODEL AI EKSEKUTOR (WAJIB DIBACA SEBELUM MENULIS KODE):**
> Dokumen ini adalah spesifikasi teknis presisi tinggi untuk **Tahap 2**. Pastikan **Tahap 1** (`docs/TAHAP_1_IMPLEMENTATION_PLAN.md`) **SUDAH selesai dikerjakan terlebih dahulu** sebelum mengeksekusi dokumen ini. Kerjakan **Task 1 sampai Task 6 secara berurutan** tanpa berimprovisasi di luar instruksi yang tertulis.

---

## 0. Prasyarat, Konteks Lingkungan, & Pantangan Keras (Guardrails)

### Informasi Lingkungan Lokal (Laragon Windows)
- **Root Proyek:** `c:\laragon\www\tetherbrew`
- **PHP CLI Binary:** `C:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe`
- **MySQL CLI Binary:** `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- **Database Lokal:** `tetherbrew` (host `127.0.0.1`, user `root`, password kosong `""`)
- **URL Lokal Aktif:** `http://tetherbrew.test`

### Kondisi Kode Pasca-Tahap 1 (Wajib Dipahami!)
1. `.htaccess` dari Tahap 1 **memblokir semua file `.json` publik** (`RewriteRule \.(md|sql|json|lock|log)$ - [F,L,NC]`).
2. `layouts/app.php` dan `layouts/customer.php` dari Tahap 1 **sudah memiliki interceptor `window.fetch`** yang otomatis menyuntikkan header `X-CSRF-TOKEN` dari `<meta name="csrf-token">` pada semua request `POST` same-origin.
3. `layouts/app.php` dari Tahap 1 **TIDAK memuat `jQuery`, `Select2`, `AlpineJS`, `Leaflet`, maupun `ApexCharts`** pada akun ber-role `rider` dan `bar`.
4. Helper `clear_carts_map_cache()` sudah tersedia di `core/helpers.php`.

### 8 Pantangan Keras Tahap 2 (DILARANG DILANGGAR!)
1. **DILARANG KERAS memakai TailwindCSS** (`cdn.tailwindcss.com` maupun utility class Tailwind). Gunakan **100% Vanilla CSS** sesuai variabel tema proyek (`var(--bg-card)`, `var(--text-primary)`, dll.).
2. **DILARANG memakai nama file `manifest.json`!** Karena `.htaccess` memblokir ekstensi `.json` dengan `403 Forbidden`, **WAJIB gunakan nama standar W3C `manifest.webmanifest`** sesuai **Task 1**.
3. **DILARANG meng-cache halaman HTML/PHP atau response `/api/*` di dalam Service Worker (`sw.js`)!** Meng-cache HTML terautentikasi akan membocorkan data antar sesi dan membuat `<meta name="csrf-token">` basi (`419 Sesi Kedaluwarsa`). `sw.js` **HANYA** boleh meng-cache aset statis (`/assets/*`, `/uploads/image/*`, dan `/offline.html`).
4. **DILARANG memakai `$()` / `jQuery` di halaman Rider atau Bar** (`pages/rider/*`), karena jQuery sengaja tidak dimuat untuk role tersebut. Wajib gunakan **100% Vanilla JavaScript**.
5. **DILARANG mengubah warna putih/hitam yang berada di dalam blok `@media print { ... }`** saat memperbaiki Dark Mode di **Task 5**, agar cetakan laporan di kertas tetap berlatar putih bersih.
6. **DILARANG menonaktifkan tombol submit pada `<form method="GET">`** (form filter laporan/pencarian) atau menonaktifkan tombol sebelum dialog konfirmasi `data-confirm` disetujui user (**Task 4**).
7. **JANGAN mengubah kredensial production** di `config/database.php` maupun `config/app.php`.
8. **WAJIB menjalankan `php.exe -l <file>`** setiap selesai mengedit file `.php`.

---

## Task 1: Implementasi Progressive Web App (PWA) Installable & Aman

### Tujuan
Membuat website utama, Customer App, dan Backoffice/Rider POS dapat di-install ke layar utama HP (*Add to Home Screen*) layaknya aplikasi native, lengkap dengan ikon `192x192` & `512x512`, fallback `/offline.html`, serta Service Worker aman untuk aset statis.

### 1.1 Generate Ikon PWA (`pwa-192.png` & `pwa-512.png`) via PHP GD
Jalankan skrip satu kali lewat CLI PHP (misalnya melalui file sementara di luar root proyek lalu hapus) menggunakan `C:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe` untuk membuat 2 file PNG berlatar putih-hijau atau transparan rapi dari `assets/images/tether-icon-head.webp`:
- `c:\laragon\www\tetherbrew\assets\images\pwa-192.png` (`192x192` px)
- `c:\laragon\www\tetherbrew\assets\images\pwa-512.png` (`512x512` px)

Kode PHP CLI yang dapat dijalankan:
```php
<?php
$srcFile = 'c:/laragon/www/tetherbrew/assets/images/tether-icon-head.webp';
$src = imagecreatefromwebp($srcFile);
$sw = imagesx($src);
$sh = imagesy($src);

foreach ([192, 512] as $size) {
    $dst = imagecreatetruecolor($size, $size);
    // Latar belakang hijau gelap khas Tether Brew (#0c1210) agar tampil elegan sebagai maskable & any icon
    $bg = imagecolorallocate($dst, 12, 18, 16);
    imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
    imagealphablending($dst, true);

    $pad = (int)round($size * 0.12);
    $inner = $size - ($pad * 2);
    // Proporsional di tengah kanvas
    $scale = min($inner / $sw, $inner / $sh);
    $nw = (int)round($sw * $scale);
    $nh = (int)round($sh * $scale);
    $dx = (int)round(($size - $nw) / 2);
    $dy = (int)round(($size - $nh) / 2);

    imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
    imagepng($dst, "c:/laragon/www/tetherbrew/assets/images/pwa-{$size}.png", 8);
    imagedestroy($dst);
}
imagedestroy($src);
echo "PWA icons generated OK\n";
```

### 1.2 Perbarui `.htaccess` untuk MIME `.webmanifest` & No-Cache `sw.js`
Pada `c:\laragon\www\tetherbrew\.htaccess`, tambahkan blok berikut di bagian paling bawah file:

```apache

# 9. Dukungan PWA Manifest & Larangan Cache Browser pada Service Worker
AddType application/manifest+json .webmanifest

<IfModule mod_headers.c>
    <FilesMatch "^(sw\.js|manifest\.webmanifest)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
    </FilesMatch>
</IfModule>
```

### 1.3 Buat File `/manifest.webmanifest` di Root Proyek
Buat file `c:\laragon\www\tetherbrew\manifest.webmanifest`:

```json
{
  "name": "Tether Brew — Kopi Keliling Medan",
  "short_name": "Tether Brew",
  "description": "Temukan gerobak kopi keliling Tether Brew terdekat secara realtime di Medan sekitarnya. Kopi segar mulai Rp 8.000!",
  "start_url": "/?source=pwa",
  "scope": "/",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#0c1210",
  "theme_color": "#16a34a",
  "lang": "id-ID",
  "icons": [
    {
      "src": "/assets/images/pwa-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/images/pwa-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "shortcuts": [
    {
      "name": "Cari Gerobak Terdekat",
      "short_name": "Peta Gerobak",
      "url": "/#maps",
      "icons": [{ "src": "/assets/images/pwa-192.png", "sizes": "192x192" }]
    },
    {
      "name": "POS Kasir Rider",
      "short_name": "POS Rider",
      "url": "/rider/pos",
      "icons": [{ "src": "/assets/images/pwa-192.png", "sizes": "192x192" }]
    }
  ]
}
```

### 1.4 Buat Halaman Fallback Statis `/offline.html` di Root Proyek
Buat file `c:\laragon\www\tetherbrew\offline.html` (100% mandiri dengan inline CSS tanpa request eksternal):

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c1210">
    <title>Sedang Offline — Tether Brew</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0c1210;
            color: #d1d9d5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
        }
        .card {
            background: #142320;
            border: 1px solid #1e3a30;
            border-radius: 20px;
            padding: 36px 24px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: rgba(34, 197, 94, 0.12);
            color: #22c55e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        h1 { font-size: 1.35rem; margin-bottom: 10px; color: #ffffff; }
        p { font-size: 0.92rem; color: #9ab8a9; line-height: 1.6; margin-bottom: 24px; }
        .btn {
            display: inline-block;
            width: 100%;
            padding: 13px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">☕</div>
        <h1>Koneksi Internet Terputus</h1>
        <p>Sepertinya sinyal Anda sedang tidak tersedia. Periksa koneksi data seluler atau Wi-Fi Anda, lalu coba muat ulang halaman.</p>
        <button class="btn" onclick="window.location.reload()">Muat Ulang Halaman</button>
    </div>
</body>
</html>
```

### 1.5 Buat Service Worker Aman `/sw.js` di Root Proyek
Buat file `c:\laragon\www\tetherbrew\sw.js`:
- **ATURAN KEAMANAN:** Jangan pernah menyimpan dokumen HTML (`request.mode === 'navigate'`) atau endpoint `/api/*` ke dalam Cache Storage. Hanya cache file statis di `/assets/` dan `/uploads/image/`, serta `/offline.html`.

```javascript
const CACHE_NAME = 'tetherbrew-static-v2';
const PRECACHE_URLS = [
    '/offline.html',
    '/assets/images/pwa-192.png',
    '/assets/images/pwa-512.png',
    '/assets/images/tether-icon-head.webp'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => (key !== CACHE_NAME ? caches.delete(key) : Promise.resolve()))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // 1. Abaikan semua request non-GET (POST, PUT, DELETE, dll.)
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // 2. Abaikan request lintas domain (CDN, tile peta Carto/OSM, Google Fonts)
    if (url.origin !== self.location.origin) return;

    // 3. Navigasi halaman HTML: WAJIB Network-Only (jangan pernah cache sesi/CSRF!),
    //    fallback ke /offline.html hanya jika jaringan benar-benar mati.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // 4. Endpoint API dinamis (/api/*): WAJIB Network-Only tanpa cache SW
    if (url.pathname.startsWith('/api/')) {
        return;
    }

    // 5. Aset statis lokal (/assets/* dan /uploads/image/*): Stale-While-Revalidate
    const isStaticAsset =
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/uploads/image/');

    if (isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(req).then((cachedResponse) => {
                    const networkFetch = fetch(req)
                        .then((networkResponse) => {
                            if (networkResponse && networkResponse.status === 200) {
                                cache.put(req, networkResponse.clone());
                            }
                            return networkResponse;
                        })
                        .catch(() => cachedResponse);

                    return cachedResponse || networkFetch;
                })
            )
        );
    }
});
```

### 1.6 Daftarkan Manifest & Service Worker di `index.php`, `layouts/app.php`, dan `layouts/customer.php`
1. **Di `<head>` ketiga file** (`index.php`, `layouts/app.php`, dan `layouts/customer.php`), pastikan terdapat tag PWA berikut:
   ```html
   <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
   <meta name="theme-color" content="#16a34a">
   <link rel="icon" type="image/webp" href="<?= base_url('assets/images/tether-icon-head.webp') ?>">
   <link rel="apple-touch-icon" href="<?= base_url('assets/images/pwa-192.png') ?>">
   ```
   *(Pada `index.php` baris 173–175 yang sudah punya `favicon` & `theme-color`, cukup perbarui `apple-touch-icon` ke `pwa-192.png` dan tambahkan `<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">`).*

2. **Di bagian sebelum `</body>` pada ketiga file** (`index.php`, `layouts/app.php`, dan `layouts/customer.php`), tambahkan registrasi Service Worker ringan:
   ```html
   <script>
       if ('serviceWorker' in navigator) {
           window.addEventListener('load', function() {
               navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function() {});
           });
       }
   </script>
   ```

### Verifikasi Task 1
- `curl.exe -s -I http://tetherbrew.test/manifest.webmanifest` → HTTP `200` dengan `Content-Type: application/manifest+json`.
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/sw.js` → `200`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/offline.html` → `200`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/assets/images/pwa-192.png` → `200`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/assets/images/pwa-512.png` → `200`

---

## Task 2: Perombakan Total Mobile-First Rider POS + Kalkulator Kembalian + Offline Queue

### Tujuan
Mengubah `/rider/pos` dari grid desktop kaku `1fr 350px` menjadi mesin kasir mobile-first sekelas aplikasi kasir modern: nyaman dipakai satu jempol di layar HP `360px–412px` di bawah terik matahari, dilengkapi filter kategori, foto produk `.webp`, tombol pecahan uang tunai & kalkulator kembalian otomatis, checkout AJAX instan + struk digital, serta **Antrean Offline (`localStorage`) bebas duplikasi (`client_tx_id`)** saat sinyal 4G Rider putus-nyambung.

### 2.1 Buat & Jalankan Migrasi SQL `sql/migration_phase2.sql`
Buat file `c:\laragon\www\tetherbrew\sql\migration_phase2.sql`:

```sql
-- =====================================================
-- MIGRATION TAHAP 2: IDEMPOTENCY KEY & INFO KEMBALIAN POS
-- Aman untuk MySQL 8.x (Lokal) & MariaDB 11.x (Production)
-- Seluruh kolom baru bersifat NULLABLE (100% kompatibel data lama)
-- =====================================================

ALTER TABLE `transactions`
    ADD COLUMN `client_tx_id` VARCHAR(64) NULL DEFAULT NULL AFTER `notes`,
    ADD COLUMN `cash_received` DECIMAL(12,2) NULL DEFAULT NULL AFTER `client_tx_id`,
    ADD COLUMN `change_amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `cash_received`,
    ADD UNIQUE KEY `transactions_client_tx_id_unique` (`client_tx_id`);
```

Jalankan ke database lokal:
```powershell
cmd /c "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root tetherbrew < c:\laragon\www\tetherbrew\sql\migration_phase2.sql"
```

### 2.2 Tulis Ulang `pages/rider/pos_store.php` (Dukung AJAX JSON + Idempotensi `client_tx_id` + Fallback Form)
Ganti seluruh isi `c:\laragon\www\tetherbrew\pages\rider\pos_store.php` menjadi kode berikut:

```php
<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');

$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    redirect('/rider/pos');
}

csrf_verify();

// Dukung payload JSON maupun form-urlencoded biasa
$rawBody = file_get_contents('php://input');
$jsonBody = (!empty($rawBody) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
    ? json_decode($rawBody, true)
    : null;

$cartDataRaw   = $jsonBody['items'] ?? ($_POST['cart_data'] ?? '');
$cartData      = is_array($cartDataRaw) ? $cartDataRaw : json_decode((string)$cartDataRaw, true);
$paymentMethod = $jsonBody['payment_method'] ?? ($_POST['payment_method'] ?? '');
$notes         = trim((string)($jsonBody['notes'] ?? ($_POST['notes'] ?? ''))) ?: null;
$clientTxId    = trim((string)($jsonBody['client_tx_id'] ?? ($_POST['client_tx_id'] ?? ''))) ?: null;
if ($clientTxId !== null) {
    $clientTxId = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $clientTxId), 0, 64) ?: null;
}
$cashReceivedInput = $jsonBody['cash_received'] ?? ($_POST['cash_received'] ?? null);

$respondError = function (string $msg, int $status = 422) use ($wantsJson) {
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    redirect_with('/rider/pos', 'error', $msg);
};

if (empty($cartData) || !is_array($cartData) || !in_array($paymentMethod, ['cash', 'qris'], true)) {
    $respondError('Data keranjang atau metode pembayaran tidak valid.');
}

$cartStmt = $pdo->prepare("SELECT id, branch_id, name FROM carts WHERE user_id = ?");
$cartStmt->execute([auth_id()]);
$cart = $cartStmt->fetch();
if (!$cart) {
    $respondError('Anda belum ditugaskan ke gerobak manapun.', 403);
}

// Helper untuk mengambil sisa stok terbaru gerobak agar UI POS selalu sinkron
$fetchCurrentStocks = function () use ($pdo, $cart) {
    $s = $pdo->prepare("SELECT product_id, stock FROM inventories WHERE cart_id = ?");
    $s->execute([$cart['id']]);
    $map = [];
    foreach ($s->fetchAll() as $row) {
        $map[(int)$row['product_id']] = (int)$row['stock'];
    }
    return $map;
};

// Cek Idempotensi: jika client_tx_id sudah pernah berhasil disimpan sebelumnya, jangan potong stok 2x!
if ($clientTxId !== null) {
    $dupStmt = $pdo->prepare("SELECT id, total_price, payment_method, cash_received, change_amount, created_at FROM transactions WHERE client_tx_id = ? AND user_id = ? LIMIT 1");
    $dupStmt->execute([$clientTxId, auth_id()]);
    $existingTx = $dupStmt->fetch();
    if ($existingTx) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'        => true,
                'duplicate'      => true,
                'transaction_id' => (int)$existingTx['id'],
                'total_price'    => (float)$existingTx['total_price'],
                'payment_method' => $existingTx['payment_method'],
                'cash_received'  => $existingTx['cash_received'] !== null ? (float)$existingTx['cash_received'] : null,
                'change_amount'  => $existingTx['change_amount'] !== null ? (float)$existingTx['change_amount'] : null,
                'created_at'     => date('d M Y H:i', strtotime($existingTx['created_at'])),
                'stocks'         => $fetchCurrentStocks(),
            ]);
            exit;
        }
        redirect_with('/rider/pos', 'success', 'Transaksi #' . $existingTx['id'] . ' sudah tercatat sebelumnya.');
    }
}

try {
    $pdo->beginTransaction();
    $totalPrice = 0;
    $itemsToInsert = [];

    $prodStmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_active = 1");
    $invStmt  = $pdo->prepare("UPDATE inventories SET stock = stock - ?, updated_at = NOW() WHERE cart_id = ? AND product_id = ? AND stock >= ?");

    foreach ($cartData as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) {
            throw new Exception('Jumlah item tidak valid.');
        }

        $prodStmt->execute([$pid]);
        $product = $prodStmt->fetch();
        if (!$product) {
            throw new Exception('Salah satu produk tidak ditemukan atau sudah nonaktif.');
        }

        $price    = (float)$product['price'];
        $subtotal = $price * $qty;
        $totalPrice += $subtotal;

        $invStmt->execute([$qty, $cart['id'], $pid, $qty]);
        if ($invStmt->rowCount() === 0) {
            throw new Exception('Stok tidak mencukupi untuk menu "' . $product['name'] . '".');
        }

        $itemsToInsert[] = [
            'product_id' => $pid,
            'name'       => $product['name'],
            'qty'        => $qty,
            'price'      => $price,
            'subtotal'   => $subtotal,
        ];
    }

    $cashReceived = null;
    $changeAmount = null;
    if ($paymentMethod === 'cash') {
        $parsedCash = is_numeric($cashReceivedInput) ? (float)$cashReceivedInput : $totalPrice;
        if ($parsedCash < $totalPrice) {
            throw new Exception('Uang tunai diterima lebih kecil dari total belanja.');
        }
        $cashReceived = $parsedCash;
        $changeAmount = $parsedCash - $totalPrice;
    }

    $transStmt = $pdo->prepare("
        INSERT INTO transactions
            (cart_id, user_id, branch_id, total_price, payment_method, notes, client_tx_id, cash_received, change_amount, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $transStmt->execute([
        $cart['id'],
        auth_id(),
        $cart['branch_id'],
        $totalPrice,
        $paymentMethod,
        $notes,
        $clientTxId,
        $cashReceived,
        $changeAmount,
    ]);
    $transId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO transaction_items (transaction_id, product_id, qty, price, subtotal, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    foreach ($itemsToInsert as $i) {
        $itemStmt->execute([$transId, $i['product_id'], $i['qty'], $i['price'], $i['subtotal']]);
    }

    $pdo->commit();

    if (function_exists('clear_carts_map_cache')) {
        clear_carts_map_cache();
    }

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'        => true,
            'duplicate'      => false,
            'transaction_id' => $transId,
            'total_price'    => $totalPrice,
            'payment_method' => $paymentMethod,
            'cash_received'  => $cashReceived,
            'change_amount'  => $changeAmount,
            'created_at'     => date('d M Y H:i'),
            'items'          => $itemsToInsert,
            'stocks'         => $fetchCurrentStocks(),
        ]);
        exit;
    }

    redirect_with('/rider/pos', 'success', 'Transaksi #' . $transId . ' berhasil! Total: Rp ' . number_format($totalPrice, 0, ',', '.'));
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $respondError('Gagal memproses transaksi: ' . $e->getMessage(), 422);
}
```

### 2.3 Tulis Ulang `pages/rider/pos.php` (Mobile-First 2-Kolom, Kalkulator Kembalian, Struk Modal, & Offline Queue)
Ganti seluruh isi `c:\laragon\www\tetherbrew\pages\rider\pos.php` menjadi kode berikut (100% Vanilla CSS + Vanilla JS tanpa jQuery):

```php
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
            <div style='font-size:40px; margin-bottom:12px;'>🛒</div>
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
    <a href="<?= url('/rider/transactions') ?>" class="btn btn-secondary btn-sm">🧾 Riwayat Hari Ini</a>
</div>

<div id="pos-queue-banner" class="pos-queue-banner">
    <div>
        <strong>📡 <span id="pos-queue-count">0</span> Transaksi Menunggu Sinyal</strong>
        <div style="font-size:12px; color:var(--text-muted);">Tersimpan aman di HP ini dan akan dikirim otomatis saat internet kembali stabil.</div>
    </div>
    <button type="button" id="pos-sync-btn" onclick="syncOfflineQueue(true)" class="btn btn-primary btn-sm">Sinkronkan Sekarang</button>
</div>

<div class="pos-filter-tabs">
    <button type="button" class="pos-filter-tab active" onclick="filterCategory('all', this)">Semua Menu</button>
    <button type="button" class="pos-filter-tab" onclick="filterCategory('kopi', this)">☕ Coffee</button>
    <button type="button" class="pos-filter-tab" onclick="filterCategory('non-kopi', this)">🍵 Non-Coffee</button>
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
                <strong style="font-size:16px; color:var(--text-primary);">🛒 Keranjang Kasir</strong>
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
                    <button type="button" class="pos-pay-btn active" id="pay-btn-cash" onclick="selectPaymentMethod('cash')">💵 Tunai (Cash)</button>
                    <button type="button" class="pos-pay-btn" id="pay-btn-qris" onclick="selectPaymentMethod('qris')">📱 QRIS</button>
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

            <button type="button" id="pos-submit-btn" onclick="submitPosOrder()" class="btn btn-primary" style="width:100%; height:48px; font-size:16px; font-weight:700; justify-content:center;">
                ✅ Proses Bayar Sekarang
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
    <button type="button" class="btn btn-primary" style="height:44px; padding:0 20px; font-weight:700;" onclick="toggleMobileCart(true)">
        🛒 Bayar
    </button>
</div>

<!-- Modal Struk Digital Ringkas -->
<div class="pos-receipt-overlay" id="pos-receipt-modal">
    <div class="pos-receipt-card">
        <div style="text-align:center; margin-bottom:16px;">
            <div id="receipt-icon" style="font-size:36px; margin-bottom:6px;">✅</div>
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
    document.getElementById('receipt-icon').textContent = receipt.offline ? '📡' : '✅';
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
    btn.textContent = '⏳ Memproses...';

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
        btn.textContent = '✅ Proses Bayar Sekarang';
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
                'X-Requested-With': 'XMLHttpRequest'
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
        btn.textContent = '✅ Proses Bayar Sekarang';
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
                    'X-Requested-With': 'XMLHttpRequest'
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
```

### 2.4 Hapus Query N+1 & Tampilkan Info Kembalian di `pages/rider/transactions.php`
Ganti seluruh isi `c:\laragon\www\tetherbrew\pages\rider\transactions.php` agar 15 baris transaksi tidak memicu 15 query `SELECT` terpisah (cukup 1 query `WHERE ti.transaction_id IN (...)`):

```php
<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_role('rider');

$cartStmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
$cartStmt->execute([auth_id()]);
$cart = $cartStmt->fetch();

$transactions = [];
$itemsByTx = [];
$pagination = null;

if ($cart) {
    $pagination = paginate($pdo, "SELECT * FROM transactions WHERE cart_id = ? ORDER BY id DESC", [$cart['id']], 15);
    $transactions = $pagination['data'];

    if (!empty($transactions)) {
        $txIds = array_map(fn($t) => (int)$t['id'], $transactions);
        $placeholders = implode(',', array_fill(0, count($txIds), '?'));
        $itemsStmt = $pdo->prepare("
            SELECT ti.transaction_id, ti.qty, p.name
            FROM transaction_items ti
            INNER JOIN products p ON ti.product_id = p.id
            WHERE ti.transaction_id IN ({$placeholders})
        ");
        $itemsStmt->execute($txIds);
        foreach ($itemsStmt->fetchAll() as $row) {
            $itemsByTx[(int)$row['transaction_id']][] = e($row['name']) . ' (' . (int)$row['qty'] . ')';
        }
    }
}

$pageTitle = 'Riwayat Transaksi Saya';
$headerActions = '<a href="' . url('/rider/pos') . '" class="btn btn-primary btn-sm">← Kembali ke POS</a>';
ob_start();
?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($transactions)): ?>
            <div style="text-align:center; padding:48px 20px; color:var(--text-muted);">
                <h3>Belum ada transaksi</h3>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Waktu</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Detail Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t):
                            $tid = (int)$t['id'];
                            $itemSummary = implode(', ', $itemsByTx[$tid] ?? []);
                        ?>
                            <tr>
                                <td style="font-weight:700;">#<?= $tid ?></td>
                                <td style="font-size:12px; white-space:nowrap;"><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
                                <td style="font-weight:700; color:var(--accent-green); white-space:nowrap;">
                                    <?= formatRupiah($t['total_price']) ?>
                                    <?php if ($t['payment_method'] === 'cash' && $t['change_amount'] !== null && (float)$t['change_amount'] > 0): ?>
                                        <div style="font-size:11px; font-weight:500; color:var(--text-muted);">Kembali: <?= formatRupiah($t['change_amount']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $t['payment_method'] === 'cash' ? 'success' : 'info' ?>">
                                        <?= strtoupper(e($t['payment_method'])) ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color:var(--text-secondary);"><?= $itemSummary ?: '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:16px;"><?= render_pagination($pagination) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
```

---

## Task 3: Hapus `cdn.tailwindcss.com`, Geolocation On-Demand, Banner Jam Operasional, & Penyempurnaan Customer App

### Tujuan
1. Menghapus beban `cdn.tailwindcss.com` (~330 KB render-blocking JS) dari `index.php` agar 100% patuh pada aturan Vanilla CSS proyek.
2. Menghentikan permintaan izin GPS mendadak (*unsolicited geolocation prompt*) saat pengunjung baru membuka halaman depan, dan menggantinya dengan tombol interaktif **"📍 Cari Terdekat"** di atas peta.
3. Menampilkan informasi **Jam Operasional & Pesanan Event/Kantor** yang ramah ketika sedang tidak ada gerobak aktif (`carts.length === 0`).
4. Menghubungkan tombol **"Pesan / Chat Rider"** di Customer App (`pages/customer/dashboard.php` & `pages/customer/menu.php`) langsung ke percakapan in-app (`/api/chat/start` → `/customer/chat_detail?id=...`) alih-alih sekadar menampilkan toast kosong.

### 3.1 Hapus `cdn.tailwindcss.com` & Tambahkan Tombol `"📍 Cari Terdekat"` di `index.php`
1. Pada `c:\laragon\www\tetherbrew\index.php`:
   - **HAPUS** baris `<script src="https://cdn.tailwindcss.com"></script>` di `<head>`.
   - Pada bagian `<div class="map-wrapper">` (sekitar baris 354–360), tambahkan tombol aksi lokasi di samping overlay peta:
     ```html
     <div class="map-wrapper">
         <div class="map-overlay">
             <div class="dot" id="map-live-dot"></div>
             <span id="cart-count-label">Memuat data...</span>
         </div>
         <button type="button" id="locate-me-btn" class="map-locate-btn" onclick="locateNearestCart()">
             <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
             <span id="locate-me-label">Cari Terdekat</span>
         </button>
         <div id="map"></div>
     </div>
     ```

### 3.2 Tambahkan Reset Dasar Ringan & Style `.map-locate-btn` + Empty State di `assets/css/landing.css`
1. Di bagian atas `c:\laragon\www\tetherbrew\assets\css\landing.css` (tepat di bawah `*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }`), tambahkan 4 baris reset pengganti Preflight agar penghapusan `cdn.tailwindcss.com` tidak mengubah tampilan elemen dasar:
   ```css
   button, input, select, textarea { font-family: inherit; font-size: 100%; color: inherit; }
   button { background: none; border: 0; cursor: pointer; }
   a { color: inherit; text-decoration: none; }
   img, video { max-width: 100%; height: auto; }
   ```
2. Di bawah rule `.map-overlay .dot { ... }` (sekitar baris 352 `assets/css/landing.css`), tambahkan CSS untuk `.map-locate-btn` dan `.gerobak-hours-banner`:
   ```css
   .map-locate-btn {
       position: absolute; top: 16px; right: 16px; z-index: 999;
       background: linear-gradient(135deg, #22c55e, #16a34a);
       color: #ffffff;
       border: none;
       border-radius: var(--radius-md);
       padding: 10px 14px;
       font-size: 13px;
       font-weight: 700;
       display: inline-flex;
       align-items: center;
       gap: 6px;
       box-shadow: 0 6px 20px rgba(22, 163, 74, 0.38);
       cursor: pointer;
       transition: transform 0.2s ease, opacity 0.2s ease;
   }
   .map-locate-btn:hover { transform: translateY(-1px); opacity: 0.94; }
   .map-locate-btn:disabled { opacity: 0.65; cursor: wait; }

   .gerobak-hours-banner {
       grid-column: 1 / -1;
       background: var(--bg-card);
       border: 1px solid var(--border-color);
       border-radius: var(--radius-lg);
       padding: 32px 24px;
       text-align: center;
       max-width: 640px;
       margin: 0 auto;
   }
   .gerobak-hours-banner h3 {
       font-size: 18px;
       font-weight: 700;
       color: var(--text-primary);
       margin-bottom: 8px;
   }
   .gerobak-hours-banner p {
       font-size: 14px;
       color: var(--text-secondary);
       line-height: 1.6;
       margin-bottom: 16px;
   }
   .gerobak-hours-cta {
       display: inline-flex;
       align-items: center;
       gap: 8px;
       padding: 10px 20px;
       border-radius: 10px;
       background: linear-gradient(135deg, #22c55e, #16a34a);
       color: #ffffff;
       font-size: 13px;
       font-weight: 700;
   }
   @media (max-width: 600px) {
       .map-locate-btn { top: auto; bottom: 16px; right: 16px; }
   }
   ```

### 3.3 Ubah Geolocation Menjadi On-Demand & Tambahkan Empty State Jam Operasional di `assets/js/landing.js`
Pada `c:\laragon\www\tetherbrew\assets\js\landing.js`:
1. **Ganti blok auto-geolocation saat page load** (`if ('geolocation' in navigator) { navigator.geolocation.getCurrentPosition(...); }` sekitar baris 60–74) menjadi fungsi **on-demand** `window.locateNearestCart`:
   ```javascript
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
   ```
2. Di dalam blok `.then(carts => { ... })` tempat `renderCartsOnMap` dan `renderCartCards` didefinisikan, daftarkan helper `window.__refreshCartDistances`:
   ```javascript
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
   ```
3. Pada fungsi `renderCartCards(carts)` ketika `carts.length === 0`:
   - Ubah teks `#cart-count-label` menjadi `'Sedang di luar jam keliling'` dan sembunyikan animasi pulse hijau (`document.getElementById('map-live-dot').style.background = '#f59e0b'`), lalu kembalikan ke `var(--accent)` jika `carts.length > 0`.
   - Ganti empty state polos menjadi banner informatif:
     ```javascript
     if (carts.length === 0) {
         document.getElementById('cart-count-label').textContent = 'Gerobak sedang istirahat / persiapan';
         const dot = document.getElementById('map-live-dot');
         if (dot) dot.style.background = '#f59e0b';
         grid.innerHTML = `
             <div class="gerobak-hours-banner">
                 <h3>☕ Armada Gerobak Sedang Bersiap / Istirahat</h3>
                 <p>Saat ini belum ada rider yang mengaktifkan titik lokasi keliling. Gerobak Tether Brew beroperasi setiap hari mulai pagi hingga sore di titik-titik strategis Kota Medan. Anda tetap dapat melihat katalog menu di atas atau memesan paket kopi untuk acara/kantor!</p>
                 <a href="https://wa.me/6282379742368?text=${encodeURIComponent('Halo Tether Brew! Saya ingin tanya jadwal gerobak / pesan kopi untuk acara.')}" target="_blank" class="gerobak-hours-cta">
                     💬 Hubungi Admin via WhatsApp
                 </a>
             </div>
         `;
         paginationEl.innerHTML = '';
         return;
     }
     const dot = document.getElementById('map-live-dot');
     if (dot) dot.style.background = '';
     ```

### 3.4 Sambungkan Tombol Chat & Pesan Langsung ke Rider di Customer App (`pages/customer/dashboard.php` & `pages/customer/menu.php`)
1. **Pada `pages/customer/dashboard.php`:**
   - Di popup marker peta (sekitar baris 277–284), sanitasi nama gerobak/rider dengan `escapeHtml(...)` dan tambahkan tombol **"💬 Chat Rider"** (memanggil `startChatWithRider(cart.rider_id, cart.id)`) jika `cart.rider_id` tersedia, berdampingan dengan link menu!
   - Jika `data.length === 0`, tampilkan info ringan di bawah `#customerMap`: `"Saat ini belum ada gerobak yang sedang keliling aktif. Cek kembali pada jam operasional ya!"`.
   - Tambahkan fungsi JS `startChatWithRider(riderId, cartId)`:
     ```javascript
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
     ```
2. **Pada `pages/customer/menu.php`:**
   - Ambil daftar gerobak aktif beserta rider-nya di bagian atas PHP:
     ```php
     $activeCarts = $pdo->query("
         SELECT c.id, c.name, c.user_id AS rider_id, u.name AS rider_name, u.whatsapp
         FROM carts c
         INNER JOIN users u ON c.user_id = u.id
         WHERE c.status = 'active' AND u.is_active = 1
         ORDER BY c.name ASC
     ")->fetchAll();
     ```
   - Ganti `onclick="Swal.fire({toast:true, ... 'Silakan temui gerobak terdekat untuk memesan!'})"` pada tombol `.order-btn` (baris 117) dengan pemanggilan fungsi `orderFromCustomerMenu(<?= json_encode($product['name']) ?>)` yang:
     - Jika ada `$activeCarts`: menampilkan pilihan gerobak aktif via `Swal.fire` (`input: 'select'`) lalu langsung membuka ruang chat Rider (`/api/chat/start` → redirect ke `/customer/chat_detail?id=...`) supaya customer benar-benar bisa memesan ke Rider!
     - Jika `$activeCarts` sedang kosong: menampilkan dialog ramah dengan tombol menuju Peta Beranda atau WhatsApp Admin.

---

## Task 4: Global Anti Double-Submit, Sticky Header Tabel Matriks, & Loading State

### Tujuan
Mencegah duplikasi data akibat klik ganda oleh Admin/Rider pada jaringan lambat di **seluruh form POST aplikasi**, serta memastikan judul kolom tabel matriks (`rider_sales`, `productions`, `inventories`, dll.) tetap menempel (*sticky*) saat di-scroll panjang.

### 4.1 Tambahkan Global Anti Double-Submit di `assets/js/app.js`
Pada `c:\laragon\www\tetherbrew\assets\js\app.js`, di dalam blok `document.addEventListener('DOMContentLoaded', function() { ... });` (tepat sebelum penutup `});` baris 117), tambahkan kode berikut:

```javascript
    // Global Anti Double-Submit untuk seluruh form POST standar
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // 1. Abaikan jika submit dibatalkan (misal sedang menunggu dialog data-confirm SweetAlert2 atau validasi custom)
        if (e.defaultPrevented || form.hasAttribute('data-confirm')) return;

        // 2. Abaikan form GET (filter laporan / pencarian) dan form yang punya atribut data-no-lock
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'POST' || form.hasAttribute('data-no-lock')) return;

        // 3. Cegah submit kedua jika form sudah dikunci
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';

        const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach(function(btn) {
            btn.disabled = true;
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';
            if (btn.tagName === 'BUTTON' && !btn.dataset.origHtml) {
                btn.dataset.origHtml = btn.innerHTML;
                // Hanya ganti teks jika tombol cukup lebar (bukan tombol ikon kecil di tabel)
                if (btn.offsetWidth >= 90) {
                    btn.innerHTML = '⏳ Memproses...';
                }
            }
        });
    });
```

> **Catatan Penting Kompatibilitas `data-confirm`:** Di `layouts/app.php` baris 64, saat user menekan *"Ya, Lanjutkan"* pada dialog SweetAlert2, kode memanggil `form.removeAttribute('data-confirm'); form.submit();`. Perlu diketahui bahwa pemanggilan `HTMLFormElement.prototype.submit()` secara programatik **tidak memicu event `submit`**, sehingga untuk mengunci tombol saat `data-confirm` disetujui, perbarui baris 64 di `layouts/app.php` menjadi:
> ```javascript
> }).then((result) => {
>     if (result.isConfirmed) {
>         form.removeAttribute('data-confirm');
>         if (typeof form.requestSubmit === 'function') {
>             form.requestSubmit();
>         } else {
>             form.submit();
>         }
>     }
> });
> ```

### 4.2 Tambahkan Sticky Header Tabel & Highlight Baris Fokus Matriks di `assets/css/app.css`
Pada `c:\laragon\www\tetherbrew\assets\css\app.css` di sekitar baris 765–797 (bagian `/* Tables */`), perbarui/tambahkan aturan CSS berikut:

```css
        /* Tables & Sticky Header untuk Tabel Panjang / Matriks */
        .table-container {
            overflow-x: auto;
            max-height: 75vh;
            overflow-y: auto;
        }

        .table-container thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--bg-card);
            box-shadow: 0 1px 0 var(--border-color);
        }

        /* Highlight baris aktif saat Admin mengetik angka di tabel matriks */
        tr:focus-within td {
            background: rgba(34, 197, 94, 0.06);
        }

        td input[type="number"]:focus {
            outline: none;
            border-color: var(--accent-green) !important;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2) !important;
        }

        @media print {
            .table-container {
                max-height: none !important;
                overflow: visible !important;
            }
            .table-container thead th {
                position: static !important;
            }
        }
```

---

## Task 5: Perbaikan Konsistensi Dark Mode di Seluruh Halaman Backoffice

### Tujuan
Menghilangkan bug "kotak putih menyilaukan dengan teks gelap tak terbaca" ketika Owner/Admin/Rider mengaktifkan **Dark Mode** (`html:not(.light-theme)`), **tanpa merusak tampilan cetak kertas (`@media print`)**.

> **ATURAN WAJIB TASK 5:** Jangan mengubah properti warna apa pun yang berada di dalam blok `@media print { ... }` atau elemen yang memiliki class `.print-only` / `.print-title` / `.print-summary-row`! Hanya ubah elemen UI layar normal.

### 5.1 Perbaiki SweetAlert2 & Select2 Dark Mode di `layouts/app.php`
Pada `c:\laragon\www\tetherbrew\layouts\app.php` baris 36–47 (`<style>` di `<head>`):
1. Ganti:
   ```css
   .swal2-popup { border-radius: 12px !important; font-family: 'Inter', sans-serif !important; }
   .light-theme .swal2-popup { background: var(--bg-card) !important; color: var(--text-primary) !important; }
   ```
   Menjadi (berlaku untuk Dark Mode maupun Light Mode):
   ```css
   .swal2-popup { border-radius: 12px !important; font-family: 'Inter', sans-serif !important; background: var(--bg-card) !important; color: var(--text-primary) !important; border: 1px solid var(--border-color) !important; }
   .swal2-html-container { color: var(--text-secondary) !important; }
   ```
2. Tambahkan tepat di bawah `.select2-search--dropdown .select2-search__field { ... }`:
   ```css
   .select2-results__option { color: var(--text-primary, #1e293b); }
   .select2-container--default .select2-results__option--selected { background-color: var(--bg-secondary, #f1f5f9); }
   .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: var(--accent-green, #22c55e); color: #ffffff; }
   ```

### 5.2 Perbaiki Warna Hardcoded di 11 File Halaman Backoffice
Gunakan variabel CSS tema (`var(--bg-card)`, `var(--bg-secondary)`, `var(--text-primary)`, `var(--text-secondary)`, `var(--border-color)`) pada baris-baris UI layar (bukan `@media print`) di 11 file berikut:

1. **`pages/rider_sales/create.php` (sekitar baris 89) & `pages/rider_sales/edit.php` (sekitar baris 107):**
   - Ganti `background: #ffffff; border: 2px solid #22c55e; font-weight: 700; color: #15803d;`
   - Menjadi: `background: var(--bg-card); border: 2px solid #22c55e; font-weight: 700; color: var(--accent-green);`
2. **`pages/rider/chat_detail.php` (sekitar baris 20):**
   - Ganti `<div style="padding:12px; background:white; border-top:1px solid var(--border-color);">`
   - Menjadi: `<div style="padding:12px; background:var(--bg-card); border-top:1px solid var(--border-color);">`
3. **`pages/dashboard/owner.php` (sekitar baris 333 & 335 — tombol paginasi performa rider):**
   - Ganti `style="background: white; border: 1px solid #cbd5e1; color: #475569;"` pada `#rider-prev-btn` dan `#rider-next-btn`
   - Menjadi: `style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary);"`
   - *(Biarkan blok `@media print` di baris ~500+ tetap utuh!).*
4. **`pages/journals/index.php`:**
   - Pada `<th>` tabel jurnal (sekitar baris 343–349): ganti `background-color: #f8fafc; color: #1e293b;` menjadi `background-color: var(--bg-secondary); color: var(--text-primary);`.
   - Pada `<td>` tabel jurnal (sekitar baris 360, 369, 377, 378) & teks modal (baris 474): ganti `color: #475569` → `color: var(--text-secondary)` dan `color: #1e293b` → `color: var(--text-primary)`.
   - Pada CSS `.stat-card` (sekitar baris 641–647, di luar `@media print`): ganti `background: white;` → `background: var(--bg-card);` dan `border: 1px solid #e2e8f0;` → `border: 1px solid var(--border-color);`.
   - *(Jangan sentuh `.print-only` baris 126 maupun `@media print` baris 504–600!).*
5. **`pages/office_kasbons/index.php`:**
   - Pada tombol Reset (baris ~135), `<td>` tabel (baris ~167–180), `.card-header` & `.card-title` modal (baris ~216–217, 258–259), `.form-label` modal (baris ~224–242, 267–285), input disabled `#payment_name` (baris ~268), dan tombol Batal modal (baris ~247, 290):
     - Ganti `background: white` → `background: var(--bg-card)`
     - Ganti `background: #f1f5f9` → `background: var(--bg-secondary)`
     - Ganti `border-bottom: 1px solid #e2e8f0` / `border: 1px solid #e2e8f0` / `#cbd5e1` → `var(--border-color)`
     - Ganti `color: #1e293b` → `color: var(--text-primary)` dan `color: #475569` → `color: var(--text-secondary)`
   - *(Jangan sentuh `@media print` baris 302–330!).*
6. **`pages/rider_finance/index.php`:**
   - Pada CSS `.stat-card-animated` (baris ~135, di luar `@media print`): ganti `background: white; border-radius: 12px; padding: 25px; border: 1px solid #e2e8f0;` menjadi `background: var(--bg-card); border-radius: 12px; padding: 25px; border: 1px solid var(--border-color);`.
   - Pada info box, filter `.form-label`, tombol `openPaymentModal()`, modal `.card-title` & `.form-label`, input `#reference_cups`, `<th>` & `<tr>` & `<td>` tabel (baris ~207–560):
     - Ganti `background:white` / `background: white` → `background: var(--bg-card)`
     - Ganti `background: #f8fafc` → `background: var(--bg-secondary)`
     - Ganti `border-bottom:1px solid #f1f5f9` / `border-top:1px solid #e2e8f0` / `border-color: #cbd5e1` → `var(--border-color)`
     - Ganti `color: #1e293b` / `color:#1e293b` → `color: var(--text-primary)` dan `color: #475569` / `color:#475569` → `color: var(--text-secondary)`
   - *(Jangan sentuh `@media print` baris 108–132 maupun `.print-title` / `.print-summary-row`!).*
7. **`pages/payrolls/index.php` (baris ~310, 338, 371) & `pages/payrolls/show.php` (baris ~29, 59, 102):**
   - Ganti `color: #1e293b` → `color: var(--text-primary)`.
   - Ganti `background: #f8fafc` dan `background: #f1f5f9` pada `<tr>` total/subtotal → `background: var(--bg-secondary)` serta `#cbd5e1` → `var(--border-color)`.
8. **`pages/riders/resign.php` (baris ~57 & 68):**
   - Ganti `color: #1e293b` → `color: var(--text-primary)`.
9. **`pages/rider_minus/index.php` (baris ~68 & 78) & `pages/rider_sales_report/index.php` (baris ~77):**
   - Ganti `color: #1e293b` → `color: var(--text-primary)` dan `background: #f8fafc` → `background: var(--bg-secondary)` *(jangan sentuh blok `@media print` di bagian bawah kedua file!)*.

---

## Task 6: Pengujian Akhir Menyeluruh Tahap 2 (Wajib Lulus 100%)

Setelah Task 1–5 selesai dieksekusi, jalankan seluruh pengujian berikut melalui terminal dan pastikan **0 error**:

1. **Syntax Check Seluruh File PHP (PHP 8.5):**
   Jalankan `C:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe -l` secara rekursif pada seluruh file `.php` di `c:\laragon\www\tetherbrew`. Wajib `0` syntax/deprecation error.
2. **Verifikasi Struktur Tabel `transactions`:**
   Jalankan:
   ```powershell
   C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root tetherbrew -e "SHOW COLUMNS FROM transactions; SHOW INDEX FROM transactions WHERE Key_name = 'transactions_client_tx_id_unique';"
   ```
   Pastikan kolom `client_tx_id`, `cash_received`, `change_amount` serta unique index `transactions_client_tx_id_unique` telah terdaftar.
3. **Verifikasi Endpoint PWA & Keamanan `.htaccess` via `curl.exe`:**
   - `http://tetherbrew.test/manifest.webmanifest` → `200 OK` (`application/manifest+json`)
   - `http://tetherbrew.test/sw.js` → `200 OK` (memiliki header `Cache-Control: no-cache...`)
   - `http://tetherbrew.test/offline.html` → `200 OK`
   - `http://tetherbrew.test/assets/images/pwa-192.png` & `pwa-512.png` → `200 OK`
   - `http://tetherbrew.test/docs/TAHAP_2_IMPLEMENTATION_PLAN.md` → **wajib `403 Forbidden`** (terlindungi `.htaccess`!)
4. **Verifikasi Landing Page Bebas Tailwind & Geolocation On-Demand:**
   - Ambil HTML `http://tetherbrew.test/` via `curl.exe`: pastikan **tidak ada lagi string `cdn.tailwindcss.com`**, terdapat `<link rel="manifest"`, dan terdapat tombol `id="locate-me-btn"`.
   - Pastikan di `assets/js/landing.js` pemanggilan `navigator.geolocation.getCurrentPosition` **hanya** berada di dalam fungsi `locateNearestCart()`.
5. **Verifikasi Fungsional Rider POS (AJAX Checkout + Idempotensi):**
   - Uji render `/rider/pos` dan `/rider/transactions` menggunakan simulasi session salah satu akun `rider` aktif: pastikan `0` PHP warning/error, tidak ada pemanggilan `$` / `jQuery`, dan kartu produk menampilkan `.pos-product-card` beserta kalkulator kembalian `#cash-calc-section`.
6. **Audit Sisa Hardcoded Light Color di Luar `@media print`:**
   - Pastikan ke-11 file di Task 5 tidak lagi menyisakan `background: white` atau `color: #1e293b` di luar blok `@media print` / `.print-only`.

---

## Lampiran: Catatan Rilis Tahap 2 ke Shared Hosting (Untuk User)

Saat seluruh perubahan Tahap 2 sudah teruji di lokal dan siap diunggah ke Shared Hosting:
1. Buka **phpMyAdmin** di panel shared hosting, pilih database production, lalu jalankan isi file `sql/migration_phase2.sql`.
2. Upload file baru (`manifest.webmanifest`, `sw.js`, `offline.html`, `assets/images/pwa-192.png`, `assets/images/pwa-512.png`) serta seluruh file `.htaccess`, `index.php`, `layouts/*`, `assets/*`, dan `pages/*` yang diperbarui ke `public_html` (**kecuali** `config/*.local.php`, folder `docs/`, dan `sql/`).
3. Di HP Rider (Chrome Android / Safari iOS), buka halaman `/rider/pos`, ketuk menu browser **"Tambahkan ke Layar Utama / Install App"** supaya Rider memiliki ikon aplikasi **Tether Brew** langsung di layar HP mereka.

