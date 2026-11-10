<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/riders'); csrf_verify();
$errors=validate($_POST,['name'=>'required|string|max:255','email'=>'required|email|unique:users,email','password'=>'required|min:6|confirmed']);
if(!empty($errors)) redirect_back_with_errors($errors);
$pdo->prepare("INSERT INTO users (name,email,whatsapp,role,branch_id,password,created_at,updated_at) VALUES(?,?,?,'rider',?,?,NOW(),NOW())")
    ->execute([trim($_POST['name']),trim($_POST['email']),$_POST['whatsapp']??null,activeBranchId(),password_hash($_POST['password'],PASSWORD_BCRYPT)]);
redirect_with('/riders','success','Rider berhasil ditambahkan!');
