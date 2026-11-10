<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/products');
csrf_verify();
$errors = validate($_POST, ['name' => 'required|string|max:255', 'price' => 'required|numeric', 'category' => 'required|string|max:100']);
if (!empty($errors)) redirect_back_with_errors($errors);

$id = $_POST['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) redirect('/products');

$name = trim($_POST['name']);
$imageFilename = $product['image'];
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploaded = upload_product_image_webp($_FILES['image'], $product['image'], $name);
    if ($uploaded) {
        $imageFilename = $uploaded;
    }
}

$pdo->prepare("UPDATE products SET name=?, description=?, price=?, category=?, image=?, is_active=?, is_live_brew=?, sort_order=?, updated_at=NOW() WHERE id=?")
    ->execute([
        $name, 
        $_POST['description'] ?? null, 
        $_POST['price'], 
        trim($_POST['category']), 
        $imageFilename,
        isset($_POST['is_active']) ? 1 : 0, 
        isset($_POST['is_live_brew']) ? 1 : 0, 
        (int)($_POST['sort_order'] ?? 0), 
        $id
    ]);

clear_carts_map_cache();
redirect_with('/products', 'success', 'Produk berhasil diperbarui!');
