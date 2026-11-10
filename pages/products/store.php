<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/products');
csrf_verify();
$errors = validate($_POST, ['name' => 'required|string|max:255', 'price' => 'required|numeric', 'category' => 'required|string|max:100']);
if (!empty($errors)) redirect_back_with_errors($errors);

$name = trim($_POST['name']);
$imageFilename = 'kopi-tether-new.webp';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded = upload_product_image_webp($_FILES['image'], null, $name);
    if ($uploaded) {
        $imageFilename = $uploaded;
    }
}

$pdo->prepare("INSERT INTO products (name, description, price, category, image, is_active, is_live_brew, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
    ->execute([
        $name, 
        $_POST['description'] ?? null, 
        $_POST['price'], 
        trim($_POST['category']),
        $imageFilename,
        isset($_POST['is_active']) ? 1 : 0, 
        isset($_POST['is_live_brew']) ? 1 : 0, 
        (int)($_POST['sort_order'] ?? 0)
    ]);

clear_carts_map_cache();
redirect_with('/products', 'success', 'Produk berhasil ditambahkan!');
