<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/accounts'); csrf_verify();
$id=$_POST['id']; $activeBranchId = activeBranchId();
// Verify ownership
$check=$pdo->prepare("SELECT id FROM users WHERE id=? AND (branch_id=? OR branch_id IS NULL)"); $check->execute([$id, $activeBranchId]);
if(!$check->fetch()) redirect_with('/accounts','error','Data akun tidak ditemukan.');

if(auth_id()==$id && $_POST['role']!=='owner' && auth_user()['role']==='owner') redirect_with('/accounts','error','Anda tidak bisa mengubah peran Anda sendiri dari owner.');
if(auth_id()==$id && $_POST['role']!=='admin' && auth_user()['role']==='admin') redirect_with('/accounts','error','Anda tidak bisa mengubah peran Anda sendiri dari admin.');
$errors=validate($_POST,['name'=>'required|string|max:255','email'=>'required|email|unique:users,email,'.$id,'role'=>'required|in:owner,admin,rider,bar']);

$role = $_POST['role'];
$branchId = null;
if ($role !== 'owner') {
    if (empty($_POST['branch_id'])) {
        $errors['branch_id'] = 'Cabang wajib dipilih untuk role ini.';
    } else {
        $branchId = $_POST['branch_id'];
    }
}

if(!empty($errors)) redirect_back_with_errors($errors);

$pdo->prepare("UPDATE users SET name=?,email=?,whatsapp=?,role=?,branch_id=?,updated_at=NOW() WHERE id=?")
    ->execute([trim($_POST['name']),trim($_POST['email']),$_POST['whatsapp']??null,$role,$branchId,$id]);
if(!empty($_POST['password'])){$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_BCRYPT),$id]);}
redirect_with('/accounts','success','Akun berhasil diperbarui!');
