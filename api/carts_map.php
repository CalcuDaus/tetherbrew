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
        INNER JOIN users u ON c.user_id = u.id
        INNER JOIN cart_locations cl ON cl.cart_id = c.id
        WHERE c.status = 'active' AND c.user_id IS NOT NULL AND cl.latitude IS NOT NULL
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
