<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth();

$user = auth_user();
$role = $user['role'];

switch ($role) {
    case 'owner':
        include __DIR__ . '/owner.php';
        break;
    case 'admin':
        include __DIR__ . '/admin.php';
        break;
    case 'rider':
        include __DIR__ . '/rider.php';
        break;
    case 'bar':
        // Bar uses productions page as dashboard
        redirect('/admin/productions');
        break;
    case 'customer':
        redirect('/customer/dashboard');
        break;
    default:
        redirect('/');
}
