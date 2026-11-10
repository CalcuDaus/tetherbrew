<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/journals'); csrf_verify();
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) redirect_with('/journals', 'error', 'Gagal upload file.');

// Enable auto detection of line endings for Mac Excel CSVs
ini_set('auto_detect_line_endings', TRUE);

$file = fopen($_FILES['file']['tmp_name'], 'r');
if (!$file) redirect_with('/journals', 'error', 'Gagal membaca file.');

$firstLine = fgets($file); rewind($file);
$delimiter = ',';
if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) $delimiter = ';';

// Use explicit escape parameter to avoid PHP 8.4 deprecation warning
$header = fgetcsv($file, 0, $delimiter, '"', '\\');
if (!$header) { fclose($file); redirect_with('/journals', 'error', 'File kosong atau format salah.'); }
$header = array_map(function($h) { return strtolower(trim(preg_replace('/\x{FEFF}/u', '', $h))); }, $header);

$catStmt = $pdo->query("SELECT id, name FROM journal_categories"); $categories = [];
while($c = $catStmt->fetch()) { $categories[strtolower(trim($c['name']))] = $c['id']; }

$lainLainId = $categories['lain-lain'] ?? null;
if(!$lainLainId) {
    $pdo->prepare("INSERT INTO journal_categories (name, created_at, updated_at) VALUES ('Lain-lain', NOW(),NOW())")->execute();
    $lainLainId = $pdo->lastInsertId(); $categories['lain-lain'] = $lainLainId;
}

function parseIdr($val) {
    if(empty($val)) return 0;
    $val = str_replace(['.', ' '], '', trim($val));
    return (float) preg_replace('/[^0-9\-]/', '', $val);
}

$imported=0; $skipped=0;
$branchId = activeBranchId(); $userId = auth_id();

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO journals (branch_id, journal_category_id, date, type, amount, description, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())");
    while (($row = fgetcsv($file, 0, $delimiter, '"', '\\')) !== false) {
        $data = []; foreach($header as $i => $k) $data[$k] = $row[$i] ?? null;
        if(empty($data['tanggal']) && empty($data['keterangan'])) { $skipped++; continue; }
        
        $date = !empty($data['tanggal']) ? date('Y-m-d', strtotime(str_replace('/','-',$data['tanggal']))) : date('Y-m-d');
        $debit = parseIdr($data['debit'] ?? ''); $kredit = parseIdr($data['kredit'] ?? '');
        
        if($debit > 0) { $type = 'debit'; $amount = $debit; }
        elseif($kredit > 0) { $type = 'credit'; $amount = $kredit; }
        else { $skipped++; continue; }

        $catId = $lainLainId;
        if(!empty($data['referensi'])) {
            $ref = strtolower(trim($data['referensi']));
            if(isset($categories[$ref])) $catId = $categories[$ref];
        }
        $desc = trim($data['keterangan'] ?? '-'); if(empty($desc)) $desc='-';

        $stmt->execute([$branchId, $catId, $date, $type, $amount, $desc, $userId]);
        $imported++;
    }
    $pdo->commit(); fclose($file);
    $msg = "Berhasil import {$imported} jurnal."; if($skipped>0) $msg .= " ({$skipped} baris dilewati)";
    redirect_with('/journals', 'success', $msg);
} catch (Exception $e) {
    $pdo->rollBack(); fclose($file); redirect_with('/journals', 'error', 'Gagal import: '.$e->getMessage());
}
