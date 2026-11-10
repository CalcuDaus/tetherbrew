<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_owner();
if ($_SERVER['REQUEST_METHOD']!=='POST') redirect('/branches'); csrf_verify();
$id=$_POST['id']; $stmt=$pdo->prepare("SELECT * FROM branches WHERE id=? AND is_active=1"); $stmt->execute([$id]); $branch=$stmt->fetch();
if(!$branch) redirect_with('/branches','error','Tidak bisa pindah ke cabang yang tidak aktif.');
$_SESSION['active_branch_id']=$id;
redirect_back('success','Berhasil pindah ke '.$branch['name'].'.');
