<?php
require_once __DIR__ . '/_config.php';
app_auth_logout();
app_redirect('/home');
