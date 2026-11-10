<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_admin_or_owner();
$id = $_GET['id'] ?? null;
if ($id) {
    redirect('/riders?edit_id=' . urlencode($id));
}
redirect('/riders');
