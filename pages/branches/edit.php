<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_owner();
$id = $_GET['id'] ?? null;
if ($id) {
    redirect('/branches?edit_id=' . urlencode($id));
}
redirect('/branches');
