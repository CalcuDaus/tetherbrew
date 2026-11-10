<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_admin_or_owner();
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/riders'); csrf_verify();
$id=$_POST['id']; $errors=validate($_POST,['name'=>'required|string|max:255','email'=>'required|email|unique:users,email,'.$id]);
if(!empty($errors)) redirect_back_with_errors($errors);
$pdo->prepare("UPDATE users SET name=?,email=?,whatsapp=?,updated_at=NOW() WHERE id=? AND role='rider'")
    ->execute([trim($_POST['name']),trim($_POST['email']),$_POST['whatsapp']??null,$id]);
if(!empty($_POST['password'])){$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_BCRYPT),$id]);}
redirect_with('/riders','success','Rider berhasil diperbarui!');
