<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/customer/register');
csrf_verify();

$errors = validate($_POST, [
    'name' => 'required|string|max:100',
    'email' => 'required|email|max:255|unique:users,email',
    'phone' => 'required|string|max:20|unique:users,phone',
    'password' => 'required|min:6|confirmed',
]);

if (!empty($errors)) { redirect_back_with_errors($errors); }

$email = trim($_POST['email']);
$hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (name, phone, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, 'customer', NOW(), NOW())");
$stmt->execute([trim($_POST['name']), trim($_POST['phone']), $email, $hashedPassword]);

$userId = $pdo->lastInsertId();
auth_login($userId);
redirect('/customer/dashboard');
