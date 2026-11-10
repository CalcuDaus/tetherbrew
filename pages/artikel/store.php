<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/artikel/admin'); csrf_verify();

$title = $_POST['title']; $category = $_POST['category']; $excerpt = $_POST['excerpt']; $content = $_POST['content'];
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$readTime = $_POST['read_time'] ?: max(1, ceil(str_word_count(strip_tags($content)) / 200));

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return strtolower(trim($text, '-'));
}
$slug = slugify($title);
$check = $pdo->prepare("SELECT id FROM artikels WHERE slug=?"); $check->execute([$slug]);
if($check->fetch()) $slug .= '-' . time();

$coverPath = null;
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['cover_image'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime, $allowedMimes) && $file['size'] <= 5242880) {
        $ext = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg'
        };
        $filename = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir = __DIR__ . '/../../uploads/artikel/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $coverPath = 'artikel/' . $filename;
        }
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO artikels (user_id, title, slug, excerpt, content, category, cover_image, is_published, published_at, read_time, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([auth_id(), $title, $slug, $excerpt, $content, $category, $coverPath, $isPublished, $isPublished ? date('Y-m-d H:i:s') : null, $readTime]);
    redirect_with('/artikel/admin', 'success', 'Artikel berhasil ditambahkan.');
} catch (Exception $e) {
    redirect_with('/artikel/create', 'error', 'Gagal: '.$e->getMessage());
}
