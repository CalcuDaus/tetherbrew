<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/artikel/admin'); csrf_verify();

$id = $_POST['id']; $title = $_POST['title']; $category = $_POST['category']; $excerpt = $_POST['excerpt']; $content = $_POST['content'];
$isPublished = isset($_POST['is_published']) ? 1 : 0;
$readTime = $_POST['read_time'] ?: max(1, ceil(str_word_count(strip_tags($content)) / 200));

$stmt = $pdo->prepare("SELECT * FROM artikels WHERE id=?"); $stmt->execute([$id]); $a = $stmt->fetch();
if(!$a) redirect('/artikel/admin');

$slug = $a['slug'];
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return strtolower(trim($text, '-'));
}
if($a['title'] !== $title) {
    $slug = slugify($title);
    $check = $pdo->prepare("SELECT id FROM artikels WHERE slug=? AND id!=?"); $check->execute([$slug, $id]);
    if($check->fetch()) $slug .= '-' . time();
}

$coverPath = $a['cover_image'];
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
            if (!empty($a['cover_image'])) {
                $oldFile = __DIR__ . '/../../uploads/' . ltrim($a['cover_image'], '/');
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }
    }
}

$publishedAt = $a['published_at'];
if($isPublished && !$a['is_published']) $publishedAt = date('Y-m-d H:i:s');
elseif(!$isPublished) $publishedAt = null;

try {
    $upd = $pdo->prepare("UPDATE artikels SET title=?, slug=?, excerpt=?, content=?, category=?, cover_image=?, is_published=?, published_at=?, read_time=?, updated_at=NOW() WHERE id=?");
    $upd->execute([$title, $slug, $excerpt, $content, $category, $coverPath, $isPublished, $publishedAt, $readTime, $id]);
    redirect_with('/artikel/admin', 'success', 'Artikel berhasil diperbarui.');
} catch (Exception $e) {
    redirect_with("/artikel/edit?id={$id}", 'error', 'Gagal: '.$e->getMessage());
}
