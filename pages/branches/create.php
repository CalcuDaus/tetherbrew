<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_auth(); require_owner();
redirect('/branches?action=create');
