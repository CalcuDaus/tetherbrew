<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

csrf_verify();

$role = auth_user()['role'] ?? '';

auth_logout();

if ($role === 'customer') {
    redirect('/customer/login');
}

if (in_array($role, ['admin', 'rider', 'owner', 'bar'])) {
    redirect('/login');
}

redirect('/');
