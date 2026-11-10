<?php
require_once __DIR__ . '/../../core/bootstrap.php'; require_auth(); require_owner();
if ($_SERVER['REQUEST_METHOD']!=='POST') redirect('/branches'); csrf_verify();
$id=$_POST['id']; $errors=validate($_POST,['name'=>'required|string|max:255','code'=>'required|string|max:255|unique:branches,code,'.$id]);
if(!empty($errors)) redirect_back_with_errors($errors);
$pdo->prepare("UPDATE branches SET name=?,code=?,address=?,phone=?,is_active=?,updated_at=NOW() WHERE id=?")
    ->execute([trim($_POST['name']),trim($_POST['code']),$_POST['address']??null,$_POST['phone']??null,isset($_POST['is_active'])?1:0,$id]);
redirect_with('/branches','success','Cabang berhasil diperbarui!');
