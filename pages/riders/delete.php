<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/riders'); csrf_verify();
$id=$_POST['id'];
$pdo->prepare("UPDATE carts SET user_id=NULL, status='inactive', updated_at=NOW() WHERE user_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM users WHERE id=? AND role='rider'")->execute([$id]);
clear_carts_map_cache();
redirect_with('/riders','success','Rider berhasil dihapus!');
