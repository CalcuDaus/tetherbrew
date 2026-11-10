<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
$id = $_GET['id'] ?? null;
if ($id) {
    redirect('/artikel/admin?edit_id=' . urlencode($id));
}
redirect('/artikel/admin');
