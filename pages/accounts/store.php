<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/accounts'); csrf_verify();
$errors=validate($_POST,['name'=>'required|string|max:255','email'=>'required|email|unique:users,email','role'=>'required|in:owner,admin,rider,bar','password'=>'required|min:6|confirmed']);

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

$pdo->prepare("INSERT INTO users (name,email,whatsapp,role,branch_id,password,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())")
    ->execute([trim($_POST['name']),trim($_POST['email']),$_POST['whatsapp']??null,$role,$branchId,password_hash($_POST['password'],PASSWORD_BCRYPT)]);
redirect_with('/accounts','success','Akun berhasil ditambahkan!');
