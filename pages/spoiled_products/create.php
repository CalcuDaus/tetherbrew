<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_bar_admin_owner();
redirect('/admin/spoiled-products?action=create');
