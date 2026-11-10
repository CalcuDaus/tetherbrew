<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/bars'); csrf_verify();
$errors=validate($_POST,['name'=>'required|string|max:255','email'=>'required|email|unique:users,email','password'=>'required|min:6']);
if(!empty($errors)) redirect_back_with_errors($errors);
$pdo->prepare("INSERT INTO users (name,email,role,branch_id,password,created_at,updated_at) VALUES(?,?,'bar',?,?,NOW(),NOW())")
    ->execute([trim($_POST['name']),trim($_POST['email']),activeBranchId(),password_hash($_POST['password'],PASSWORD_BCRYPT)]);
redirect_with('/bars','success','Bar berhasil ditambahkan!');
