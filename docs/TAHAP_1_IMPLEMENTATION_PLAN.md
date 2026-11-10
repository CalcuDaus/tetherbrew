# Dokumen Rencana Implementasi — Tahap 1 (Quick Wins Performa, Keamanan Kritis, & Bug Fix)

> **INSTRUKSI KHUSUS UNTUK MODEL AI EKSEKUTOR (WAJIB DIBACA SEBELUM MENULIS KODE):**
> Dokumen ini dirancang sebagai spesifikasi teknis presisi tinggi. Tugas Anda adalah mengeksekusi **Task 1 sampai Task 10 secara berurutan** tanpa berimprovisasi di luar instruksi yang tertulis.

---

## 0. Konteks Lingkungan & Pantangan Keras (Guardrails)

### Informasi Lingkungan Lokal (Laragon Windows)
- **Root Proyek:** `c:\laragon\www\tetherbrew`
- **PHP CLI Binary:** `C:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe`
- **MySQL CLI Binary:** `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- **Database Lokal:** `tetherbrew` (host `127.0.0.1`, user `root`, password kosong `""`)
- **URL Lokal Aktif:** `http://tetherbrew.test`

### 7 Pantangan Keras (DILARANG DILANGGAR!)
1. **JANGAN** mengubah kredensial production di `config/database.php` atau URL production di `config/app.php`. Biarkan keduanya tetap me-load `database.local.php` dan `app.local.php` jika tersedia.
2. **JANGAN** menambahkan `RewriteRule ^api/ - [F,L]` atau `RewriteRule ^pages/ - [F,L]` secara mentah pada URL di `.htaccess`, karena route publik seperti `/api/carts-map` dan `/api/chat/send` memiliki prefix `/api/`! Ikuti aturan `.htaccess` berbasis `%{THE_REQUEST}` yang sudah disediakan di **Task 1**.
3. **JANGAN** memindahkan script CDN seperti `ApexCharts` atau `Leaflet` ke `$extraScripts` (di bagian bawah `<body>`) jika konten halaman memanggilnya sebelum `</body>`. Wajib letakkan library eksternal di **`$extraHead`** sesuai instruksi **Task 4**.
4. **JANGAN** menghapus file `.webp` apa pun di dalam `uploads/image/` atau `assets/images/` yang sedang dipakai produk/halaman. Hanya hapus file `.png` duplikat sesuai skrip verifikasi di **Task 5**.
5. **JANGAN** memutar ulang (me-regenerate) `$_SESSION['_token']` di setiap akhir `csrf_verify()`, karena akan merusak request AJAX beruntun dan penggunaan multi-tab (`419 Sesi Kedaluwarsa`). Rotasi token hanya dilakukan saat login/logout.
6. **WAJIB** menjalankan pengecekan sintaks PHP (`php.exe -l <nama_file>`) setiap selesai mengedit file PHP.
7. **JANGAN** menghapus tabel database apa pun tanpa izin eksplisit user. Perubahan struktur database pada Tahap 1 hanya berupa penambahan **Index** dan **Unique Constraint** yang dicatat di `sql/migration_phase1.sql` (**Task 3**).

---

## Task 1: Hardening `.htaccess`, Proteksi `uploads/`, & Hapus File Debug

### Tujuan
Menutup akses publik langsung ke file `.php` internal, folder sensitif (`storage/`, `docs/`, `.agent*`, dll.), mematikan eksekusi PHP di folder `uploads/`, mengaktifkan kompresi Gzip serta HTTP Browser Caching, dan membersihkan file sementara di root.

### 1.1 Perbarui `.htaccess` di Root Proyek
Ganti **seluruh isi** `c:\laragon\www\tetherbrew\.htaccess` menjadi persis seperti berikut:

```apache
RewriteEngine On
Options -Indexes

# 1. Blokir akses ke semua dotfile / dotfolder (.git, .env, .agent, .cursor, .windsurfrules, dll.)
RewriteRule (^|/)\. - [F,L]

# 2. Blokir akses langsung ke folder internal
RewriteRule ^(core|config|sql|layouts|storage|docs)/ - [F,L]

# 3. Blokir akses langsung ke file dokumentasi / konfigurasi di root
RewriteRule \.(md|sql|json|lock|log)$ - [F,L,NC]

# 4. Blokir request HTTP langsung ke file .php mana pun KECUALI index.php dan router.php
#    (Catatan: internal require PHP dari router.php tetap berjalan normal!)
RewriteCond %{THE_REQUEST} \s/+(.+?)\.php[\s?] [NC]
RewriteCond %1 !^(index|router)$ [NC]
RewriteRule ^ - [F,L]

# 5. Izinkan file statis (gambar, css, js, font, webp, pdf) dilayani langsung
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# 6. Arahkan semua route lainnya ke router.php
RewriteRule ^(.*)$ router.php [QSA,L]

# 7. Kompresi Gzip untuk teks/kode agar hemat bandwidth
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

# 8. Browser Caching untuk aset statis
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

### 1.2 Buat File Baru `uploads/.htaccess`
Buat file `c:\laragon\www\tetherbrew\uploads\.htaccess` untuk mencegah eksekusi webshell `.php` di dalam folder upload:

```apache
Options -Indexes -ExecCGI

# Blokir eksekusi segala jenis script di dalam folder uploads
<FilesMatch "\.(php|php\d+|phtml|phar|pl|py|cgi|sh|htaccess)$">
    Require all denied
</FilesMatch>
```

### 1.3 Hapus 7 File Debug/Sementara di Root
Hapus file-file berikut dari root `c:\laragon\www\tetherbrew\`:
- `scratch_create_table.php`
- `scratch_update.php`
- `replace_icons.php`
- `test.php`
- `test_fetch.php`
- `test_products.php`
- `test_router.php`

### Verifikasi Task 1
Jalankan perintah berikut dan pastikan hasilnya sesuai:
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/` → harus `200`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/api/carts-map` → harus `200`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/api/carts_map.php` → harus `403`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/CLAUDE.md` → harus `403`
- `curl.exe -s -o NUL -w "%{http_code}" http://tetherbrew.test/docs/TAHAP_1_IMPLEMENTATION_PLAN.md` → harus `403`

---

## Task 2: Hardening Session, Validasi `is_active` User, & Perbaikan CSRF Global

### Tujuan
Memperketat keamanan cookie session & HTTP headers, mencegah akun nonaktif/resign (`is_active = 0`) login atau tetap aktif, memperbaiki CSRF agar aman untuk multi-tab & AJAX, serta menutup seluruh endpoint POST yang belum memakai CSRF.

### 2.1 Perbarui `core/bootstrap.php`
Pada `c:\laragon\www\tetherbrew\core\bootstrap.php`, ganti bagian baris 1–3 (`<?php` dan `session_start();`) menjadi:

```php
<?php
// Security headers dasar
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Hardening konfigurasi PHP Session sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
```
*(Biarkan baris 4 dan seterusnya di `core/bootstrap.php` tetap seperti semula).*

### 2.2 Perbarui `core/auth.php` (Wajib Cek `is_active = 1` & Refresh Berkala)
Perbarui 4 fungsi di `c:\laragon\www\tetherbrew\core\auth.php`:

1. Pada `auth_user()`:
   - Segarkan cache `$_SESSION['user_data']` dari database jika belum ada `$_SESSION['user_checked_at']` atau sudah lebih dari `60` detik (`time() - $_SESSION['user_checked_at'] > 60`).
   - Jika user tidak ditemukan **ATAU** `(int)($user['is_active'] ?? 1) !== 1`, panggil `auth_logout()` (atau hapus `$_SESSION['user_id']`, `$_SESSION['user_data']`, `$_SESSION['user_checked_at']`) dan return `null`!
   Kode pengganti `auth_user()`:
   ```php
   function auth_user() {
       global $pdo;
       if (!isset($_SESSION['user_id'])) return null;

       $needsRefresh = !isset($_SESSION['user_data'])
           || $_SESSION['user_data']['id'] != $_SESSION['user_id']
           || !isset($_SESSION['user_checked_at'])
           || (time() - $_SESSION['user_checked_at']) > 60;

       if ($needsRefresh) {
           $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
           $stmt->execute([$_SESSION['user_id']]);
           $user = $stmt->fetch();
           if (!$user || (int)($user['is_active'] ?? 1) !== 1) {
               unset($_SESSION['user_id'], $_SESSION['user_data'], $_SESSION['user_checked_at'], $_SESSION['active_branch_id']);
               return null;
           }
           $_SESSION['user_data'] = $user;
           $_SESSION['user_checked_at'] = time();
       }

       return $_SESSION['user_data'];
   }
   ```
2. Pada `auth_check()`:
   Ganti `return isset($_SESSION['user_id']);` menjadi:
   ```php
   function auth_check() {
       return auth_user() !== null;
   }
   ```
3. Pada `auth_login($userId)`:
   Tambahkan `$_SESSION['user_checked_at'] = time();` dan rotasi CSRF token baru `$_SESSION['_token'] = bin2hex(random_bytes(32));` tepat sebelum `session_regenerate_id(true);`.
4. Pada `auth_attempt($email, $password)` dan `auth_attempt_phone($phone, $password)`:
   - Di `auth_attempt`: ubah query menjadi `"SELECT * FROM users WHERE email = ? AND is_active = 1"`
   - Di `auth_attempt_phone`: ubah query menjadi `"SELECT * FROM users WHERE phone = ? AND role = 'customer' AND is_active = 1"`
5. Pada `auth_refresh()`:
   Tambahkan `$_SESSION['user_checked_at'] = time();` setelah `$_SESSION['user_data'] = $stmt->fetch();`.

### 2.3 Perbarui `core/csrf.php` (Per-Session Token + Dukungan Response JSON)
Ganti isi fungsi `csrf_verify()` di `c:\laragon\www\tetherbrew\core\csrf.php` menjadi:

```php
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['_token']) || !hash_equals($_SESSION['_token'], (string)$token)) {
        http_response_code(419);
        $isJson = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Sesi kedaluwarsa (CSRF invalid). Silakan muat ulang halaman.']);
        } else {
            echo '<h1>419 - Sesi Kedaluwarsa</h1>';
            echo '<p>Token CSRF tidak valid. Silakan muat ulang halaman.</p>';
            echo '<a href="javascript:history.back()">Kembali</a>';
        }
        exit;
    }
}
```
*(Perhatikan: baris `$_SESSION['_token'] = bin2hex(...)` di akhir `csrf_verify()` sengaja dihapus agar AJAX & multi-tab tidak error 419).*

### 2.4 Pasang Interceptor `window.fetch` Otomatis untuk `X-CSRF-TOKEN` di Layout
Agar **semua** `fetch(..., { method: 'POST' })` di seluruh aplikasi otomatis mengirim header `X-CSRF-TOKEN`, tambahkan script berikut di dalam `<head>` (tepat setelah `<meta name="csrf-token" ...>`) pada **KEDUA** file layout berikut:
1. `c:\laragon\www\tetherbrew\layouts\app.php`
2. `c:\laragon\www\tetherbrew\layouts\customer.php`

```html
    <script>
        (function() {
            const origFetch = window.fetch;
            window.fetch = function(input, init) {
                init = init || {};
                const method = (init.method || 'GET').toUpperCase();
                const urlStr = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                const isSameOrigin = !urlStr.startsWith('http') || urlStr.startsWith(window.location.origin);
                if (isSameOrigin && method !== 'GET' && method !== 'HEAD') {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) {
                        init.headers = new Headers(init.headers || {});
                        if (!init.headers.has('X-CSRF-TOKEN')) {
                            init.headers.set('X-CSRF-TOKEN', meta.getAttribute('content'));
                        }
                    }
                }
                return origFetch.call(this, input, init);
            };
        })();
    </script>
```

### 2.5 Tambahkan `csrf_verify()` & Perbaikan Otorisasi pada 6 Endpoint POST/API
Tambahkan pemanggilan `csrf_verify();` tepat setelah `require_auth();` (atau guard role) pada ke-6 file berikut:
1. `pages/carts/toggle_status.php` *(tambahkan juga pemanggilan `clear_carts_map_cache();` setelah `UPDATE carts`, lihat Task 6.1)*.
2. `pages/rider/location_live.php` *(tambahkan juga `clear_carts_map_cache();` setelah query lokasi)*.
3. `pages/api/chat/send.php`
4. `pages/api/chat/send_qris.php`
5. `pages/api/chat/start.php`
6. `pages/api/chat/mark_read.php` — sekaligus tutup celah IDOR dengan memverifikasi percakapan terlebih dahulu:
   ```php
   <?php
   require_once __DIR__ . '/../../../core/bootstrap.php';
   require_auth();
   csrf_verify();
   header('Content-Type: application/json');

   $convId = $_POST['conversation_id'] ?? null;
   if (!$convId) { echo json_encode(['error' => 'conversation_id required']); exit; }

   $stmt = $pdo->prepare("SELECT customer_id, rider_id FROM conversations WHERE id = ?");
   $stmt->execute([$convId]);
   $conv = $stmt->fetch();
   $role = auth_user()['role'] ?? '';
   if (!$conv || ($conv['customer_id'] != auth_id() && $conv['rider_id'] != auth_id() && !in_array($role, ['admin', 'owner']))) {
       http_response_code(403);
       echo json_encode(['error' => 'unauthorized']);
       exit;
   }

   $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0")
       ->execute([$convId, auth_id()]);

   echo json_encode(['success' => true]);
   ```

### 2.6 Tambahkan Rate Limiting pada Lupa Password Customer
Pada `pages/customer/auth/forgot_password_process.php`, tepat setelah `csrf_verify();`, tambahkan pembatasan maksimal 3 percobaan per 10 menit (600 detik):
```php
$rateKey = rate_limit_key('customer_forgot_pw');
if (rate_limit_exceeded($rateKey, 3, 600)) {
    $rem = rate_limit_remaining_seconds($rateKey);
    redirect_with('/customer/forgot-password', 'error', 'Terlalu banyak permintaan. Coba lagi dalam ' . rate_limit_format_time($rem) . '.');
}
rate_limit_hit($rateKey, 3, 600);
```

---

## Task 3: Indexing Database & `UNIQUE(cart_id)` pada `cart_locations`

### Tujuan
Mencegah duplikasi baris lokasi gerobak pada `ON DUPLICATE KEY UPDATE` dan mempercepat query filter tanggal/cabang di seluruh aplikasi.

### 3.1 Buat File Migrasi `sql/migration_phase1.sql`
Buat file `c:\laragon\www\tetherbrew\sql\migration_phase1.sql` (file ini juga akan dijalankan di phpMyAdmin shared hosting saat rilis ke production):

```sql
-- =====================================================
-- MIGRATION TAHAP 1: DEDUPLIKASI LOKASI & COMPOSITE INDEXES
-- Aman dijalankan di MySQL 8.x (Lokal) maupun MariaDB 11.x (Production)
-- =====================================================

-- 1. Bersihkan duplikat cart_id di cart_locations (simpan baris terbaru per cart_id)
DELETE cl1 FROM cart_locations cl1
INNER JOIN cart_locations cl2
    ON cl1.cart_id = cl2.cart_id AND cl1.id < cl2.id;

-- 2. Tambahkan UNIQUE KEY pada cart_locations(cart_id) agar ON DUPLICATE KEY UPDATE berfungsi
ALTER TABLE `cart_locations`
    ADD UNIQUE KEY `cart_locations_cart_id_unique` (`cart_id`);

-- 3. Composite Indexes untuk query laporan, dashboard, jurnal, dan transaksi
ALTER TABLE `rider_daily_sales`
    ADD INDEX `idx_rds_branch_date` (`branch_id`, `date`);

ALTER TABLE `transactions`
    ADD INDEX `idx_tx_branch_created` (`branch_id`, `created_at`),
    ADD INDEX `idx_tx_user_created` (`user_id`, `created_at`),
    ADD INDEX `idx_tx_cart_created` (`cart_id`, `created_at`);

ALTER TABLE `journals`
    ADD INDEX `idx_journals_branch_type_date` (`branch_id`, `type`, `date`),
    ADD INDEX `idx_journals_branch_date` (`branch_id`, `date`);

ALTER TABLE `rider_finances`
    ADD INDEX `idx_rf_branch_type_date` (`branch_id`, `type`, `date`);
```

### 3.2 Eksekusi Migrasi ke Database Lokal `tetherbrew`
Jalankan perintah berikut:
```powershell
cmd /c "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root tetherbrew < c:\laragon\www\tetherbrew\sql\migration_phase1.sql"
```
Verifikasi bahwa `SHOW INDEX FROM cart_locations` kini menampilkan `cart_locations_cart_id_unique` dengan `Non_unique = 0`.

---

## Task 4: Pemuatan Library CDN Secara On-Demand (Pangkas ~700+ KB JS per Halaman)

### Tujuan
Menghapus beban download & eksekusi library berat (`ApexCharts`, `Leaflet`, `AlpineJS`, serta `jQuery + Select2` untuk Rider/Bar) dari halaman yang tidak membutuhkannya.

> **PERINGATAN KRITIS:** Semua library yang dipindahkan ke halaman spesifik **WAJIB dimasukkan ke variabel `$extraHead`** sebelum `require .../layouts/app.php` (atau `layouts/customer.php`), **BUKAN** di `$extraScripts`, supaya script inline di dalam halaman tidak mengalami `ReferenceError`.

### 4.1 Perbarui `layouts/app.php`
1. **Di bagian `<head>` (`layouts/app.php` sekitar baris 24–34):**
   - **HAPUS** baris Leaflet CSS & Leaflet JS (`unpkg.com/leaflet...`).
   - **HAPUS** baris ApexCharts (`cdn.jsdelivr.net/npm/apexcharts`).
   - **HAPUS** baris AlpineJS (`cdn.jsdelivr.net/npm/alpinejs...`).
   - Bungkus pemuatan **jQuery + Select2 CSS & JS** dengan kondisi role supaya **halaman POS Rider & Bar tidak memuat jQuery/Select2 sama sekali**:
     ```php
     <?php if (in_array($currentRole, ['owner', 'admin'])): ?>
     <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
     <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
     <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
     <?php endif; ?>
     ```
2. **Di bagian Topbar Owner Branch Switcher (`layouts/app.php` sekitar baris 466–490):**
   Karena AlpineJS dihapus dari `layouts/app.php`, ganti blok `<div x-data="{ open: false }" ...>` menjadi Vanilla JS murni:
   ```php
   <div id="branch-switcher-wrap" style="position: relative;">
       <button type="button" id="branch-switcher-btn" onclick="var m=document.getElementById('branch-switcher-menu'),i=document.getElementById('branch-switcher-icon'); var isOpen=m.style.display==='block'; m.style.display=isOpen?'none':'block'; i.style.transform=isOpen?'':'rotate(180deg)';" class="btn btn-secondary btn-sm" style="height: 32px; padding: 0 12px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
           <svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.38-2.92a2 2 0 0 1 2.22 0L13 7"/><path d="M2 7v14"/><path d="M22 7v14"/><path d="M22 21H2"/><path d="M13 7v14"/><path d="M7 21v-5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/></svg>
           <?= e(activeBranch()['name']) ?>
           <svg id="branch-switcher-icon" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"></polyline></svg>
       </button>
       <div id="branch-switcher-menu" style="position: absolute; right: 0; top: calc(100% + 8px); background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; min-width: 200px; z-index: 50; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); overflow: hidden; display: none;">
   ```
   *(Biarkan isi loop `foreach($branches as $b)` di dalamnya tetap sama persis)*. Lalu tambahkan listener klik-luar sederhana di bawah `app.js` atau di script bawah `layouts/app.php`:
   ```javascript
   document.addEventListener('click', function(e) {
       var wrap = document.getElementById('branch-switcher-wrap');
       var menu = document.getElementById('branch-switcher-menu');
       var icon = document.getElementById('branch-switcher-icon');
       if (wrap && menu && !wrap.contains(e.target)) {
           menu.style.display = 'none';
           if (icon) icon.style.transform = '';
       }
   });
   ```
3. Pada `assets/js/app.js` sekitar baris 59:
   Ubah `if (typeof $.fn.select2 !== 'undefined')` menjadi:
   ```javascript
   if (typeof window.jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
   ```
   *(PENTING: Tanpa pengecekan `typeof window.jQuery !== 'undefined'`, halaman Rider/Bar akan terkena `ReferenceError: $ is not defined`!)*.

### 4.2 Tambahkan `$extraHead` pada 3 Halaman Admin/Owner yang Memakai ApexCharts & Leaflet
1. `pages/dashboard/owner.php` — tepat sebelum `require __DIR__ . '/../../layouts/app.php';`, tambahkan:
   ```php
   $extraHead = '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
   ```
2. `pages/dashboard/admin.php` — tepat sebelum `require __DIR__ . '/../../layouts/app.php';`, tambahkan:
   ```php
   $extraHead = '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>';
   ```
3. `pages/carts/index.php` — tepat sebelum `require __DIR__ . '/../../layouts/app.php';`, tambahkan:
   ```php
   $extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
   ```

### 4.3 Perbarui `layouts/customer.php` & `pages/customer/dashboard.php`
1. Di `layouts/customer.php`:
   - Ubah baris `<meta name="viewport" ...>` menjadi:
     ```html
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     ```
     *(Menghapus `maximum-scale=1.0, user-scalable=no` demi aksesibilitas).*
   - Tambahkan cache-busting `?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>` pada link `app.css`.
   - **HAPUS** baris Leaflet CSS & Leaflet JS dari `<head>` `layouts/customer.php`.
2. Di `pages/customer/dashboard.php`:
   - Tepat sebelum `require __DIR__ . '/../../layouts/customer.php';`, tambahkan:
     ```php
     $extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
     ```

---

## Task 5: Optimasi Gambar Berat & Pembersihan PNG Mentah Duplikat

### Tujuan
Memperkecil ikon marker peta dari `350 KB` menjadi `< 10 KB` WebP, mengecilkan ukuran `favicon.webp` & `tether-icon-head.webp`, serta menghapus file `.png` mentah di `uploads/image/` yang sudah punya versi `.webp`.

### 5.1 Jalankan Skrip Optimasi Gambar via PHP GD
Jalankan perintah CLI PHP satu kali (tanpa membuat file permanen di root proyek) untuk:
1. Membaca `assets/images/custom_icon_maps.png`, mengubah ukurannya ke `80x80` dengan alpha transparency terjaga (`imagepalettetotruecolor`, `imagealphablending(false)`, `imagesavealpha(true)`), dan menyimpannya ke **`assets/images/custom_icon_maps.webp`** (kualitas `85`).
2. Mengganti referensi `custom_icon_maps.png` menjadi `custom_icon_maps.webp` di **2 file**:
   - `assets/js/landing.js` (sekitar baris 317)
   - `pages/carts/index.php` (sekitar baris 177)
   Lalu hapus file lama `assets/images/custom_icon_maps.png`.
3. Mengecilkan `assets/images/favicon.webp` (dari 142 KB menjadi lebar maks `128px` dengan alpha terjaga) dan `assets/images/tether-icon-head.webp` (dari 175,9 KB menjadi tinggi maks `160px` dengan alpha terjaga).
4. Menghapus file `.png` di dalam `uploads/image/` **HANYA JIKA**:
   - File `.webp` dengan nama dasar yang sama persis (`pathinfo($png, PATHINFO_FILENAME) . '.webp'`) **sudah ada** di `uploads/image/`, **DAN**
   - Nama file `.png` tersebut **tidak tercatat** di kolom `image` tabel `products` maupun `cover_image` tabel `artikels`.

---

## Task 6: Optimasi `/api/carts-map` (Micro-Cache 5 Detik, Hapus N+1, Stok Asli) & Polling Peta

### Tujuan
Menghilangkan beban puluhan query SQL setiap 8 detik di shared hosting, mengembalikan jumlah stok riil gerobak (`i.stock`), dan menghentikan polling saat tab browser tidak aktif.

### 6.1 Tambahkan Helper `clear_carts_map_cache()` di `core/helpers.php`
Tambahkan fungsi berikut di bagian paling bawah `c:\laragon\www\tetherbrew\core\helpers.php`:

```php
/**
 * Clear cached public carts map JSON
 */
function clear_carts_map_cache() {
    $cacheFile = __DIR__ . '/../storage/cache/carts_map.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}
```
Pastikan juga folder `storage/cache/` diabaikan di `.gitignore` (tambahkan baris `storage/cache/` ke `.gitignore`).
Panggil `clear_carts_map_cache();` setelah perubahan data pada:
- `pages/carts/toggle_status.php`, `pages/carts/store.php`, `pages/carts/update.php`, `pages/carts/delete.php`, `pages/carts/delete_all.php`
- `pages/rider/location_live.php`, `pages/rider/location_update.php`, `pages/rider/pos_store.php`
- `pages/inventories/update.php`

### 6.2 Tulis Ulang `api/carts_map.php` (1 Query Menu + Micro-Cache 5 Detik + Stok Asli)
Ganti seluruh isi `c:\laragon\www\tetherbrew\api\carts_map.php` menjadi:

```php
<?php
require_once __DIR__ . '/../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$all = isset($_GET['all']) && $_GET['all'] == 1;
$user = auth_user();
$role = $user['role'] ?? null;
$branchId = activeBranchId();

// Lepas lock file session secepatnya agar request paralel tidak saling tunggu
session_write_close();

$isAdminScoped = ($all || in_array($role, ['admin', 'owner'])) && $branchId;
$cacheFile = __DIR__ . '/../storage/cache/carts_map.json';

// Gunakan micro-cache 5 detik untuk pengunjung publik
if (!$isAdminScoped && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 5) {
    readfile($cacheFile);
    exit;
}

if ($isAdminScoped) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.description, c.status, c.user_id,
               u.name AS rider_name, u.whatsapp,
               cl.latitude, cl.longitude, cl.updated_at AS location_updated
        FROM carts c
        LEFT JOIN users u ON c.user_id = u.id
        INNER JOIN cart_locations cl ON cl.cart_id = c.id
        WHERE c.status = 'active' AND cl.latitude IS NOT NULL AND c.branch_id = ?
    ");
    $stmt->execute([$branchId]);
} else {
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.description, c.status, c.user_id,
               u.name AS rider_name, u.whatsapp,
               cl.latitude, cl.longitude, cl.updated_at AS location_updated
        FROM carts c
        LEFT JOIN users u ON c.user_id = u.id
        INNER JOIN cart_locations cl ON cl.cart_id = c.id
        WHERE c.status = 'active' AND cl.latitude IS NOT NULL
    ");
}
$carts = $stmt->fetchAll();

// Ambil seluruh menu untuk semua gerobak aktif dalam 1 query saja (Tanpa N+1!)
$menusByCart = [];
if (!empty($carts)) {
    $cartIds = array_map(fn($c) => (int)$c['id'], $carts);
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $menuStmt = $pdo->prepare("
        SELECT i.cart_id, i.stock, p.name, p.price, p.category, p.image
        FROM inventories i
        INNER JOIN products p ON i.product_id = p.id
        WHERE i.cart_id IN ({$placeholders}) AND i.stock > 0 AND p.is_active = 1
        ORDER BY p.sort_order ASC, p.name ASC
    ");
    $menuStmt->execute($cartIds);
    foreach ($menuStmt->fetchAll() as $m) {
        $menusByCart[(int)$m['cart_id']][] = [
            'name'     => $m['name'],
            'price'    => (float)$m['price'],
            'stock'    => (int)$m['stock'],
            'category' => $m['category'],
            'image'    => $m['image'],
        ];
    }
}

$result = [];
foreach ($carts as $cart) {
    $cid = (int)$cart['id'];
    $result[] = [
        'id'          => $cid,
        'name'        => $cart['name'],
        'description' => $cart['description'],
        'rider'       => $cart['rider_name'] ?? 'Tidak ada rider',
        'rider_id'    => $cart['user_id'] ? (int)$cart['user_id'] : null,
        'whatsapp'    => $cart['whatsapp'],
        'status'      => $cart['status'],
        'latitude'    => (float)$cart['latitude'],
        'longitude'   => (float)$cart['longitude'],
        'updated_at'  => diffForHumans($cart['location_updated']),
        'menu'        => $menusByCart[$cid] ?? [],
    ];
}

$json = json_encode($result, JSON_UNESCAPED_UNICODE);

if (!$isAdminScoped) {
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, $json, LOCK_EX);
}

echo $json;
```

### 6.3 Optimasi Polling & Sanitasi XSS di `assets/js/landing.js`
Pada `c:\laragon\www\tetherbrew\assets\js\landing.js`:
1. Tambahkan helper `escapeHtml(str)` di bagian atas file:
   ```javascript
   function escapeHtml(str) {
       if (str === null || str === undefined) return '';
       return String(str)
           .replace(/&/g, '&amp;')
           .replace(/</g, '&lt;')
           .replace(/>/g, '&gt;')
           .replace(/"/g, '&quot;')
           .replace(/'/g, '&#039;');
   }
   ```
   Gunakan `escapeHtml(...)` saat menyisipkan `cart.name`, `cart.rider`, `cart.updated_at`, dan `m.name` ke dalam template HTML (`popupContent`, `renderCartCards`, `renderMenu`, dan `openOrderPanel`).
2. Ganti blok `setInterval(..., 8000)` (sekitar baris 483–493) menjadi `15000` ms dengan pengecekan `document.hidden`:
   ```javascript
   function refreshCartsMap() {
       if (document.hidden) return;
       fetch(apiBaseUrl + '/api/carts-map?_t=' + Date.now(), { cache: 'no-store' })
           .then(r => r.json())
           .then(carts => {
               allCartsData = carts;
               document.getElementById('stat-carts').textContent = carts.length;
               renderCartsOnMap(carts);
               renderCartCards(carts);
           })
           .catch(err => console.warn('Refresh failed:', err));
   }

   setInterval(refreshCartsMap, 15000);
   document.addEventListener('visibilitychange', () => {
       if (!document.hidden) refreshCartsMap();
   });
   ```

---

## Task 7: Eliminasi Query N+1 di Dashboard, Sisa Stok, & Optimasi Pagination

### Tujuan
Memangkas ~50 query berulang di Dashboard Owner & ~51 query di Sisa Stok menjadi 1–3 query `GROUP BY` cepat, serta mempercepat query `COUNT(*)` di pagination.

### 7.1 Optimasi `pages/dashboard/owner.php`
1. Ganti loop `foreach ($riders as $rider)` pada baris 38–63 (yang menjalankan 2 query per rider) menjadi **1 query tunggal**:
   ```php
   $perfStmt = $pdo->prepare("
       SELECT u.id, u.name,
              COALESCE(SUM(rds.total_gross_income), 0) AS revenue,
              COALESCE(SUM(items_agg.cups), 0) AS cups
       FROM users u
       LEFT JOIN rider_daily_sales rds
           ON rds.rider_id = u.id AND rds.branch_id = ? AND rds.date BETWEEN ? AND ?
       LEFT JOIN (
           SELECT rider_daily_sale_id, SUM(stock_sold) AS cups
           FROM rider_daily_sale_items
           GROUP BY rider_daily_sale_id
       ) items_agg ON items_agg.rider_daily_sale_id = rds.id
       WHERE u.role = 'rider' AND u.branch_id = ?
       GROUP BY u.id, u.name
       ORDER BY cups DESC, revenue DESC
   ");
   $perfStmt->execute([$branchId, $thirtyDaysAgo, $today, $branchId]);
   $riderPerformance = array_map(fn($r) => [
       'id'      => $r['id'],
       'name'    => $r['name'],
       'cups'    => (int)$r['cups'],
       'revenue' => (int)$r['revenue'],
   ], $perfStmt->fetchAll());
   ```
2. Pada baris 95–102 (`journals` summary), ganti `MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())` menjadi query tunggal yang memakai index `idx_journals_branch_type_date`:
   ```php
   $journalStmt = $pdo->prepare("
       SELECT
           COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit,
           COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit
       FROM journals
       WHERE branch_id = ? AND date BETWEEN ? AND ?
   ");
   $journalStmt->execute([$branchId, $monthStart, $monthEnd]);
   $jRow = $journalStmt->fetch();
   $journalDebit = $jRow['total_debit'];
   $journalCredit = $jRow['total_credit'];
   $journalBalance = $journalDebit - $journalCredit;
   ```

### 7.2 Optimasi `pages/dashboard/admin.php`
Pada `pages/dashboard/admin.php` sekitar baris 25–28, ganti penggunaan fungsi `DATE(created_at) = CURDATE()` dan `MONTH(created_at) = ...` agar index `idx_tx_cart_created` terpakai:
```php
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');
$monthStartTs = date('Y-m-01 00:00:00');
$monthEndTs   = date('Y-m-t 23:59:59');

$todayStats = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total, COUNT(*) as cnt FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at BETWEEN '{$todayStart}' AND '{$todayEnd}'")->fetch();
$todayRevenue = $todayStats['total'];
$todayTransactions = $todayStats['cnt'];
$weekRevenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetch()['total'];
$monthRevenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM transactions WHERE cart_id IN ({$cartIdsStr}) AND created_at BETWEEN '{$monthStartTs}' AND '{$monthEndTs}'")->fetch()['total'];
```

### 7.3 Optimasi `pages/available_stocks/index.php` (51 Query → 4 Query)
Ganti blok baris 9–41 di `pages/available_stocks/index.php` dengan pengambilan agregat per `product_id`:

```php
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND (is_live_brew = 0 OR is_live_brew IS NULL) ORDER BY sort_order")->fetchAll();

// 1. Total Produced pada tanggal tersebut (1 query)
$prodStmt = $pdo->prepare("
    SELECT dpi.product_id, SUM(dpi.quantity_produced) AS qty
    FROM daily_production_items dpi
    INNER JOIN daily_productions dp ON dpi.daily_production_id = dp.id
    WHERE dp.branch_id = ? AND dp.date = ?
    GROUP BY dpi.product_id
");
$prodStmt->execute([$branchId, $filterDate]);
$producedMap = $prodStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Total Spoiled pada tanggal tersebut (1 query)
$spoilStmt = $pdo->prepare("
    SELECT spi.product_id, SUM(spi.quantity) AS qty
    FROM spoiled_product_items spi
    INNER JOIN spoiled_products sp ON spi.spoiled_product_id = sp.id
    WHERE sp.branch_id = ? AND sp.date = ?
    GROUP BY spi.product_id
");
$spoilStmt->execute([$branchId, $filterDate]);
$spoiledMap = $spoilStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Total dibawa Rider pada tanggal tersebut (1 query)
$usedStmt = $pdo->prepare("
    SELECT rsi.product_id, SUM(rsi.stock_out + rsi.stock_added - rsi.stock_return) AS qty
    FROM rider_daily_sale_items rsi
    INNER JOIN rider_daily_sales rs ON rsi.rider_daily_sale_id = rs.id
    WHERE rs.branch_id = ? AND rs.date = ?
    GROUP BY rsi.product_id
");
$usedStmt->execute([$branchId, $filterDate]);
$usedMap = $usedStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stockData = [];
foreach ($products as $p) {
    $pid = $p['id'];
    $produced = (int)($producedMap[$pid] ?? 0);
    $spoiled  = (int)($spoiledMap[$pid] ?? 0);
    $used     = (int)($usedMap[$pid] ?? 0);
    $available = max(0, $produced - $used - $spoiled);

    $stockData[] = [
        'id'        => $pid,
        'name'      => $p['name'],
        'category'  => $p['category'],
        'produced'  => $produced,
        'used'      => $used,
        'spoiled'   => $spoiled,
        'available' => $available,
    ];
}
```

### 7.4 Optimasi `core/pagination.php`
Pada `c:\laragon\www\tetherbrew\core\pagination.php`:
1. Di fungsi `paginate()` baris 22, hapus klausa `ORDER BY ...` di bagian akhir query sebelum dibungkus ke `count_table` agar MySQL tidak melakukan *filesort* sia-sia:
   ```php
   $queryWithoutOrder = preg_replace('/\s+ORDER\s+BY\s+[^)]+$/i', '', $query);
   $countQuery = "SELECT COUNT(*) as total FROM (" . $queryWithoutOrder . ") as count_table";
   ```
2. Di fungsi `render_pagination()` baris 59–60, bungkus `$baseUrl` dengan `e()` untuk mencegah Reflected XSS pada URL:
   ```php
   $baseUrl = e($baseUrl ?: strtok($_SERVER['REQUEST_URI'], '?'));
   ```

---

## Task 8: Perbaikan Landing Page (`index.php` & `landing.js`)

### Tujuan
Memastikan daftar **Menu Tether Brew** & statistik jumlah menu selalu tampil 24 jam meskipun semua gerobak sedang tutup (`inactive`), mempercepat LCP Hero image, serta menambahkan cache-busting aset.

### 8.1 Perbarui `index.php`
1. Tambahkan `?v=<?= filemtime(__DIR__ . '/assets/css/landing.css') ?>` pada `<link rel="stylesheet" href="...landing.css">` di `<head>`.
2. Pada baris ~275 (Hero background `<img>`):
   Ganti `loading="lazy"` menjadi `fetchpriority="high" decoding="async"`.
3. Tepat sebelum `<script src="<?= base_url('assets/js/landing.js') ?>">` di bagian bawah `index.php` (sekitar baris 624), suntikkan data `$products` dari PHP dan tambahkan `?v=filemtime`:
   ```php
   <script>
       window.__catalogMenus = <?= json_encode(array_map(fn($p) => [
           'name'        => $p['name'],
           'description' => $p['description'],
           'price'       => (float)$p['price'],
           'category'    => $p['category'],
           'image'       => $p['image'],
       ], $products), JSON_UNESCAPED_UNICODE) ?>;
   </script>
   <script src="<?= base_url('assets/js/landing.js') ?>?v=<?= filemtime(__DIR__ . '/assets/js/landing.js') ?>"></script>
   ```

### 8.2 Perbarui `assets/js/landing.js`
1. Inisialisasi `allMenuItems = Array.isArray(window.__catalogMenus) ? window.__catalogMenus : [];` langsung sebelum `fetch('/api/carts-map')`, isi `#stat-menus` dengan `allMenuItems.length`, pasang listener `.menu-tab`, dan panggil `renderMenu('semua');` segera tanpa menunggu `/api/carts-map` selesai!
2. Di dalam `.then(carts => { ... })` hasil `fetch('/api/carts-map')`, jika `allMenuItems.length === 0` (fallback saja), barulah ambil dari `carts`. Jangan menimpa `allMenuItems` dengan array kosong ketika `carts.length === 0`!
3. Di dalam `renderMenu(category)` (sekitar baris 522–540):
   - Gunakan `m.description ? escapeHtml(m.description) : (...)` agar deskripsi produk asli dari database tampil jika tersedia.
   - Rapikan tombol `Pesan` yang sebelumnya memakai class Tailwind + SVG `487x487` tanpa Tailwind CSS (`relative inline-flex bg-orange-900...`) menjadi elemen bersih:
     ```html
     <a href="#maps" class="menu-card-btn">Pesan</a>
     ```
   - Sekaligus lengkapi rule `.menu-card-btn` pada `assets/css/landing.css` baris 454 agar tombol tampil rapi tanpa bergantung pada Tailwind:
     ```css
     .menu-card-btn {
         display: inline-flex;
         align-items: center;
         justify-content: center;
         padding: 8px 20px;
         font-size: 14px;
         font-weight: 700;
         background: linear-gradient(135deg, #22c55e, #16a34a);
         color: #ffffff;
         text-decoration: none;
         border-radius: 10px;
         transition: transform 0.2s ease, opacity 0.2s ease;
     }
     .menu-card-btn:hover { transform: translateY(-1px); opacity: 0.92; }
     ```

---

## Task 9: Perbaikan 6 Bug Fungsional (Customer, Chat, Artikel, Carts, & Upload Produk WebP)

### Tujuan
Menuntaskan seluruh bug fungsional yang ditemukan pada saat audit kode production.

### 9.1 Perbaiki Route Hilang di `router.php`
Pada `c:\laragon\www\tetherbrew\router.php`:
1. Di grup `// Customer Auth` & `// Customer App`:
   - Ubah `'/customer/logout' => 'pages/customer/auth/logout.php'` menjadi `'/customer/logout' => 'pages/auth/logout.php'`.
   - Tambahkan route `'/customer/chat_detail' => 'pages/customer/chat_detail.php',` di bawah `'/customer/chats'`.
2. Di grup `// Rider Chat` (baris 140–146):
   - Ubah `'/rider/chat' => 'pages/rider/chat/index.php'` menjadi `'/rider/chat' => 'pages/rider/chat.php'`.
   - Tambahkan `'/rider/chat_detail' => 'pages/rider/chat_detail.php',` dan arahkan `'/rider/chat/show' => 'pages/rider/chat_detail.php',`.

### 9.2 Perbaiki Halaman Profil & Logout Customer (`pages/customer/profile.php`)
Pada `c:\laragon\www\tetherbrew\pages\customer\profile.php`:
1. Baris 20–21: ganti `$_SESSION['user']['name'] = $name; $_SESSION['user']['phone'] = $phone;` dengan `auth_refresh();`.
2. Baris 188: ganti `$transaction['total_amount']` menjadi `$transaction['total_price']`.
3. Baris 196: ganti `<form method="POST" action="<?= url('/auth/logout') ?>">` menjadi `<form method="POST" action="<?= url('/logout') ?>">`.
4. Di `pages/auth/logout.php`: jika `$role === 'customer'`, arahkan redirect ke `redirect('/customer/login');`.

### 9.3 Perbaiki Bug Tabel `cart_items` di `pages/carts/delete_all.php`
Pada `c:\laragon\www\tetherbrew\pages\carts\delete_all.php` baris 16:
- Ganti `DELETE ci FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.branch_id = ?` menjadi:
  ```php
  $stmt = $pdo->prepare("DELETE inv FROM inventories inv JOIN carts c ON inv.cart_id = c.id WHERE c.branch_id = ?");
  ```
- Panggil `clear_carts_map_cache();` setelah `$pdo->commit();`.

### 9.4 Perbaiki Fatal Error, Path Upload, Endpoint URL, & XSS di Modul Chat
1. **`pages/api/chat/send.php`:**
   - Baris 14: ganti `!in_array(auth()->role, ['admin','owner'])` *(fungsi `auth()` tidak ada!)* menjadi `!in_array(auth_user()['role'] ?? '', ['admin', 'owner'])`.
   - Baris 18–30 (upload attachment): validasi MIME asli menggunakan `finfo_open(FILEINFO_MIME_TYPE)` (hanya izinkan `image/jpeg`, `image/png`, `image/webp`, `application/pdf`), ubah direktori tujuan menjadi `__DIR__ . '/../../../uploads/chat-attachments/'` (permission `0755`, **TANPA** `/public`), dan simpan `$attachmentPath = '/uploads/chat-attachments/' . $filename;`.
2. **`pages/customer/chat_detail.php` dan `pages/rider/chat_detail.php`:**
   - Tambahkan fungsi `escapeHtml(str)` dan bungkus `${m.body}` menjadi `${escapeHtml(m.body)}` untuk menutup celah Stored XSS!
   - Ganti `fetch('/api/chat/mark_read', ...)` menjadi `fetch('<?= url('/api/chat/read') ?>', ...)` *(sesuai `router.php`)*.
   - Di `pages/rider/chat_detail.php` baris 85, ganti `fetch('/api/chat/send_qris', ...)` menjadi `fetch('<?= url('/api/chat/send-qris') ?>', ...)` *(pakai tanda hubung `-`, bukan `_`)*.
   - Di `pages/rider/chat.php` baris 25–26, bungkus `${c.customer_name}`, `${cartName}`, dan `${c.last_message}` dengan `escapeHtml(...)`.

### 9.5 Perbaiki Path Upload & Preview Gambar Sampul Artikel
Di database saat ini, format kolom `artikels.cover_image` adalah `'artikel/namafile.webp'` (relatif terhadap `uploads/`, cocok dengan `index.php` baris 415: `base_url('uploads/' . $item['cover_image'])`).
1. Di `pages/artikel/store.php` dan `pages/artikel/update.php`:
   - Validasi MIME asli via `finfo_open(FILEINFO_MIME_TYPE)` (`image/jpeg`, `image/png`, `image/webp`).
   - Simpan file ke `$dir = __DIR__ . '/../../uploads/artikel/';` (`mkdir($dir, 0755, true)` — **TANPA** `/public`).
   - Simpan nilai kolom DB `$coverPath = 'artikel/' . $filename;` (konsisten dengan data lama dan `index.php`!).
   - Di `pages/artikel/update.php` saat menghapus cover lama: cek `file_exists(__DIR__ . '/../../uploads/' . ltrim($a['cover_image'], '/'))`.
2. Di `pages/artikel/admin.php` baris 49 (dan cek juga `pages/artikel/index.php` serta `pages/artikel/show.php` jika merender `cover_image`):
   - Pastikan URL gambar memakai helper konsisten: jika `$a['cover_image']` diawali `/uploads/` gunakan `base_url(ltrim($a['cover_image'], '/'))`, jika tidak gunakan `base_url('uploads/' . ltrim($a['cover_image'], '/'))`.

### 9.6 Tambahkan Fitur Upload Gambar Produk + Auto-Convert WebP (`products`)
1. Tambahkan helper `upload_product_image_webp($fileInput, $oldFilename = null)` di `core/helpers.php`:
   - Memvalidasi `error === UPLOAD_ERR_OK`, ukuran maks `5 MB`, dan MIME asli via `finfo` (`image/jpeg`, `image/png`, `image/webp`).
   - Memuat gambar menggunakan GD (`imagecreatefromjpeg` / `imagecreatefrompng` / `imagecreatefromwebp`), mengubah ukuran secara proporsional jika lebar > `600px` (jaga transparansi PNG/WebP dengan `imagepalettetotruecolor`, `imagealphablending(false)`, `imagesavealpha(true)`), lalu menyimpan ke `__DIR__ . '/../uploads/image/' . $slugName . '-' . time() . '.webp'` dengan `imagewebp(..., 82)`.
   - Jika `$oldFilename` ada di `uploads/image/` dan bukan `kopi-tether-new.webp`, hapus file lama. Mengembalikan string nama file `.webp` saja (contoh: `'kopi-pasir-1731000.webp'`, sesuai format kolom `products.image` di DB!).
2. Perbarui `pages/products/create.php` dan `pages/products/edit.php`:
   - Tambahkan `enctype="multipart/form-data"` pada tag `<form>`.
   - Tambahkan `<div class="form-group"><label class="form-label">Foto Produk (JPG/PNG/WebP, maks 5MB)</label><input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp"></div>` (pada `edit.php`, tampilkan juga thumbnail gambar saat ini jika `$product['image']` terisi!).
3. Perbarui `pages/products/store.php` dan `pages/products/update.php`:
   - Panggil `upload_product_image_webp($_FILES['image'] ?? null, ...)` jika ada file yang diunggah, dan sertakan kolom `image` pada query `INSERT INTO products` / `UPDATE products`.
   - Panggil `clear_carts_map_cache();` setelah simpan/update/delete produk.

---

## Task 10: Pengujian Akhir Menyeluruh (Wajib Lulus 100% Sebelum Selesai)

Setelah Task 1–9 selesai, jalankan rangkaian verifikasi berikut dan pastikan **0 error**:

1. **Syntax Check Seluruh File PHP (PHP 8.5):**
   Jalankan loop `php.exe -l` pada seluruh file `.php` di `c:\laragon\www\tetherbrew`. Tidak boleh ada 1 pun `Parse error`, `Fatal error`, atau `Deprecated`.
2. **Tes Keamanan `.htaccess` via `curl.exe`:**
   - `http://tetherbrew.test/config/database.php` → `403`
   - `http://tetherbrew.test/core/bootstrap.php` → `403`
   - `http://tetherbrew.test/sql/schema.sql` → `403`
   - `http://tetherbrew.test/docs/TAHAP_1_IMPLEMENTATION_PLAN.md` → `403`
   - `http://tetherbrew.test/pages/products/index.php` → `403`
   - `http://tetherbrew.test/api/carts_map.php` → `403`
3. **Tes Fungsional Endpoint Publik & Cache:**
   - `http://tetherbrew.test/` → `200 OK`, pastikan `window.__catalogMenus` berisi produk aktif dan tidak ada `loading="lazy"` pada Hero image.
   - `http://tetherbrew.test/api/carts-map` → `200 OK` JSON valid, dan file `storage/cache/carts_map.json` terbentuk otomatis.
   - `http://tetherbrew.test/login`, `http://tetherbrew.test/customer/login`, `http://tetherbrew.test/artikel` → semuanya `200 OK`.
4. **Tes Halaman Terautentikasi (Owner, Admin, Rider, Customer):**
   - Pastikan `/dashboard` (Owner & Admin) memuat `apexcharts` di `<head>` dan merender tanpa SQL error.
   - Pastikan `/rider/pos` **tidak** memuat `apexcharts`, `leaflet`, `alpinejs`, maupun `jquery`/`select2`, serta tidak memunculkan JS error di console.
   - Pastikan `/admin/available-stocks` merender angka stok yang akurat tanpa warning/error SQL.

---

## Lampiran: Catatan Rilis ke Shared Hosting (Untuk User Setelah Tahap 1 Selesai)

Saat seluruh perubahan Tahap 1 sudah teruji di lokal dan siap diunggah ke Shared Hosting:
1. Upload seluruh file yang berubah ke `public_html` (**kecuali** `config/database.local.php`, `config/app.local.php`, dan `sql/u665669172_tetherbrew.sql`), serta hapus ke-7 file `test*.php` / `scratch_*.php` di `public_html`.
2. Buka **phpMyAdmin** di panel shared hosting (database `u665669172_tetherbrew`), pilih tab **Import** atau **SQL**, lalu jalankan isi file `sql/migration_phase1.sql`.
3. Buka website utama di browser (mode Incognito / Ctrl+F5) untuk memastikan peta, daftar menu, dan login berjalan kencang dan normal.
