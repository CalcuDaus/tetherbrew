<?php
/**
 * Helper functions used throughout the application
 * Pengganti berbagai Laravel helper
 */

/**
 * Get active branch ID from session
 */
function activeBranchId() {
    return $_SESSION['active_branch_id'] ?? null;
}

/**
 * Get active branch data from database
 */
function activeBranch() {
    global $pdo;
    $branchId = activeBranchId();
    if (!$branchId) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    return $stmt->fetch();
}

/**
 * Format number as Rupiah currency
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

/**
 * HTML escape helper (XSS prevention)
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get old input from session (for form repopulation after validation errors)
 */
function old($key, $default = '') {
    return $_SESSION['old_input'][$key] ?? $default;
}

/**
 * Clear old input from session
 */
function clear_old_input() {
    unset($_SESSION['old_input']);
    unset($_SESSION['_flashed_old_input']);
}

/**
 * Store old input in session for the next request
 */
function store_old_input() {
    $_SESSION['_flashed_old_input'] = $_POST;
}

/**
 * Get current datetime string
 */
function now() {
    return date('Y-m-d H:i:s');
}

/**
 * Get current date string
 */
function today() {
    return date('Y-m-d');
}

/**
 * Convert datetime to "X waktu lalu" format (Indonesian)
 */
function diffForHumans($datetime) {
    if (!$datetime) return '-';
    
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) {
        if ($diff->d == 1) return 'kemarin';
        return $diff->d . ' hari lalu';
    }
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'baru saja';
}

/**
 * Generate URL-safe slug from title
 */
function generateSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Estimate read time from content (words per minute)
 */
function estimateReadTime($content) {
    $wordCount = str_word_count(strip_tags($content));
    $minutes = max(1, ceil($wordCount / 200));
    return $minutes;
}

/**
 * Get base URL of the application
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    
    return $protocol . '://' . $host . $baseDir . '/' . ltrim($path, '/');
}

/**
 * Generate URL for the application (relative)
 */
function url($path = '') {
    $baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    
    $scriptFilename = basename($_SERVER['SCRIPT_NAME']);
    if (strpos($_SERVER['REQUEST_URI'], '/' . $scriptFilename) !== false) {
        $baseDir .= '/' . $scriptFilename;
    }
    
    return $baseDir . '/' . ltrim($path, '/');
}

/**
 * Check if current URL matches path (for active menu highlighting)
 */
function is_active($path) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $baseDir = str_replace('\\', '/', dirname($scriptName));

    if ($baseDir !== '/' && strpos($currentPath, $baseDir) === 0) {
        $currentPath = substr($currentPath, strlen($baseDir));
    }

    $basename = basename($scriptName);
    if (strpos($currentPath, '/' . $basename) === 0) {
        $currentPath = substr($currentPath, strlen('/' . $basename));
    }

    $currentPath = rtrim($currentPath, '/');
    if ($currentPath === '') $currentPath = '/';

    $targetPath = rtrim($path, '/');
    if ($targetPath === '') $targetPath = '/';

    if ($targetPath === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $targetPath || strpos($currentPath, $targetPath . '/') === 0;
}

/**
 * Format date in Indonesian
 */
function formatTanggal($date, $format = 'd M Y') {
    if (!$date) return '-';
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $bulanFull = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $dt = new DateTime($date);
    $m = (int)$dt->format('n');
    
    $result = $format;
    $result = str_replace('F', $bulanFull[$m], $result);
    $result = str_replace('M', $bulan[$m], $result);
    $result = str_replace('d', $dt->format('d'), $result);
    $result = str_replace('Y', $dt->format('Y'), $result);
    $result = str_replace('H:i', $dt->format('H:i'), $result);
    
    return $result;
}

/**
 * Get setting value from app_settings
 */
function getSetting($key, $default = null) {
    global $pdo;
    $branchId = activeBranchId();
    
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE `key` = ? AND (branch_id = ? OR branch_id IS NULL) ORDER BY branch_id DESC LIMIT 1");
    $stmt->execute([$key, $branchId]);
    $result = $stmt->fetch();
    
    return $result ? $result['value'] : $default;
}

/**
 * Truncate text to specified length
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Clear cached public carts map JSON
 */
function clear_carts_map_cache() {
    $cacheFile = __DIR__ . '/../storage/cache/carts_map.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Get public URL for artikel cover image
 */
function artikel_cover_url(?string $coverImage): string {
    if (!$coverImage) {
        return base_url('uploads/image/kopi-tether-new.webp');
    }
    if (str_starts_with($coverImage, '/uploads/')) {
        return base_url(ltrim($coverImage, '/'));
    }
    if (str_starts_with($coverImage, 'uploads/')) {
        return base_url($coverImage);
    }
    return base_url('uploads/' . ltrim($coverImage, '/'));
}

/**
 * Upload product image and convert to WebP
 *
 * @param array|null $fileInput $_FILES['image']
 * @param string|null $oldFilename Current filename in DB
 * @param string $baseSlug Optional slug/prefix for filename
 * @return string|null New filename or null if no upload / invalid
 */
function upload_product_image_webp($fileInput, $oldFilename = null, $baseSlug = 'product') {
    if (!isset($fileInput) || !is_array($fileInput) || ($fileInput['error'] ?? null) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($fileInput['size'] ?? 0) > 5242880 || ($fileInput['size'] ?? 0) <= 0) {
        return null;
    }

    $tmpPath = $fileInput['tmp_name'];
    if (!file_exists($tmpPath)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) {
        return null;
    }

    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $srcImg = @imagecreatefromwebp($tmpPath);
            break;
    }

    if (!$srcImg) {
        return null;
    }

    $origW = imagesx($srcImg);
    $origH = imagesy($srcImg);

    $maxW = 600;
    if ($origW > $maxW) {
        $targetW = $maxW;
        $targetH = (int)round(($origH / $origW) * $maxW);
    } else {
        $targetW = $origW;
        $targetH = $origH;
    }

    $destImg = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($destImg, false);
    imagesavealpha($destImg, true);
    $transparent = imagecolorallocatealpha($destImg, 0, 0, 0, 127);
    imagefilledrectangle($destImg, 0, 0, $targetW, $targetH, $transparent);

    imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

    $cleanSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($baseSlug));
    $cleanSlug = trim($cleanSlug, '-');
    if (empty($cleanSlug)) {
        $cleanSlug = 'product';
    }

    $filename = $cleanSlug . '-' . time() . '.webp';
    $targetDir = __DIR__ . '/../uploads/image/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . $filename;

    $saved = imagewebp($destImg, $targetPath, 82);

    if (!$saved) {
        return null;
    }

    if ($oldFilename) {
        $oldFileBasename = basename($oldFilename);
        if ($oldFileBasename !== 'kopi-tether-new.webp') {
            $oldPath = $targetDir . $oldFileBasename;
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    return $filename;
}
