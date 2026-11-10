<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/accounts'); csrf_verify();
$id=$_POST['id']; $branchId = activeBranchId();
// Verify ownership
$check=$pdo->prepare("SELECT id FROM users WHERE id=? AND (branch_id=? OR branch_id IS NULL)"); $check->execute([$id, $branchId]);
if(!$check->fetch()) redirect_with('/accounts','error','Data akun tidak ditemukan.');

if(auth_id()==$id) redirect_with('/accounts','error','Anda tidak dapat menghapus akun Anda sendiri.');
$pdo->prepare("UPDATE carts SET user_id=NULL WHERE user_id=?")->execute([$id]);
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
redirect_with('/accounts','success','Akun berhasil dihapus!');
