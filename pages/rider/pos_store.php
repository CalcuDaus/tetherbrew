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

// Dukung payload JSON maupun form-urlencoded biasa
$rawBody = file_get_contents('php://input');
$jsonBody = (!empty($rawBody) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
    ? json_decode($rawBody, true)
    : null;

if (!empty($jsonBody['_token']) && empty($_POST['_token'])) {
    $_POST['_token'] = $jsonBody['_token'];
}

csrf_verify();

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
