<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_owner();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/branches'); csrf_verify();
$errors = validate($_POST, ['name'=>'required|string|max:255','code'=>'required|string|max:255|unique:branches,code']);
if (!empty($errors)) redirect_back_with_errors($errors);
$pdo->prepare("INSERT INTO branches (name,code,address,phone,is_active,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())")
    ->execute([trim($_POST['name']),trim($_POST['code']),$_POST['address']??null,$_POST['phone']??null,isset($_POST['is_active'])?1:0]);
redirect_with('/branches','success','Cabang berhasil ditambahkan!');
