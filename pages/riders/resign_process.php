<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();
require_admin_or_owner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/riders');
}

csrf_verify();

$id = $_POST['id'];
$outstandingKasbon = (float)$_POST['outstanding_kasbon'];
$branchId = activeBranchId();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND branch_id = ? AND role = 'rider'");
$stmt->execute([$id, $branchId]);
$rider = $stmt->fetch();

if (!$rider) {
    redirect_with('/riders', 'error', 'Rider tidak valid.');
}

try {
    $pdo->beginTransaction();

    // 1. Nonaktifkan rider
    $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$id]);

    // 2. Putihkan Minus (Set paid = amount, status = paid)
    $pdo->prepare("
        UPDATE rider_daily_sales 
        SET minus_paid = minus_amount, 
            minus_status = 'paid', 
            minus_notes = CONCAT(IFNULL(minus_notes, ''), ' (Resign - Written off)')
        WHERE rider_id = ? AND minus_amount > 0 AND minus_status != 'paid'
    ")->execute([$id]);

    // 3. Putihkan Kasbon
    if ($outstandingKasbon > 0) {
        // Kita masukkan entri negatif sebesar kasbon untuk men-nol-kan total kasbon
        $negKasbon = -$outstandingKasbon;
        $note = "Resign - Written off";
        
        $pdo->prepare("
            INSERT INTO rider_finances (branch_id, admin_id, rider_id, date, type, amount, reference_cups, notes, created_at, updated_at) 
            VALUES (?, ?, ?, CURDATE(), 'kasbon', ?, NULL, ?, NOW(), NOW())
        ")->execute([$branchId, auth_id(), $id, $negKasbon, $note]);
        
        // Catatan: Sesuai request, kita TIDAK memasukkan jurnal umum di sini.
    }
    
    // 4. Lepaskan gerobak jika sedang assign dan nonaktifkan gerobak
    $pdo->prepare("UPDATE carts SET user_id = NULL, status = 'inactive', updated_at = NOW() WHERE user_id = ?")->execute([$id]);

    $pdo->commit();
    clear_carts_map_cache();
    redirect_with('/riders', 'success', 'Rider berhasil diproses resign dan hutang diputihkan.');
} catch (Exception $e) {
    $pdo->rollBack();
    redirect_with("/riders/resign?id={$id}", 'error', 'Gagal memproses resign: ' . $e->getMessage());
}
