<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_jurnal.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Tanggal', 'No Dokumen', 'Referensi', 'Debit', 'Kredit', 'Keterangan'], ',', '"', '\\');
fputcsv($output, [date('Y-m-d'), 'CASH', 'Lain-lain', '100000', '', 'Pembelian ATK'], ',', '"', '\\');
fputcsv($output, [date('Y-m-d'), 'CASH', 'Lain-lain', '', '50000', 'Bayar Listrik'], ',', '"', '\\');
fclose($output);
exit;
