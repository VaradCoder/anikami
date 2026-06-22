<?php
require_once __DIR__ . '/../_config.php';
app_require_admin();
app_auth_logout();
app_redirect('/admin/login.php');
