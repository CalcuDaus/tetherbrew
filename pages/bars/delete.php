<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/bars'); csrf_verify();
$pdo->prepare("DELETE FROM users WHERE id=? AND role='bar'")->execute([$_POST['id']]);
redirect_with('/bars','success','Bar berhasil dihapus!');
