<?php
require_once __DIR__ . '/../_config.php';

if (app_current_user() && (app_current_user()['role'] ?? '') === 'admin') {
    app_redirect('/admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $login = app_auth_login($_POST['identity'] ?? '', $_POST['password'] ?? '');
        if (!empty($login['ok'])) {
            $u = app_current_user();
            if (($u['role'] ?? '') !== 'admin') {
                app_auth_logout();
                $error = 'Admin access only';
            } else {
                app_redirect('/admin/index.php');
            }
        } else {
            $error = $login['error'] ?? 'Login failed';
        }
    }
}

$pageTitle = 'Admin Login - ' . $websiteTitle;
$pageRobots = 'noindex, nofollow';
?>
<!doctype html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('../_php/ak_page_head.php'); ?>
<?php include('../_php/ak_auth_style.php'); ?>
</head>
<body class="ak-body">
<?php include('../_php/ak_header.php'); ?>

<div class="ak-auth-wrap">
  <div class="ak-auth-card">
    <div class="ak-auth-logo"><i class="fas fa-shield-halved"></i> ADMIN</div>
    <h1 class="ak-auth-title">Admin Login</h1>
    <p class="ak-auth-sub">Restricted area — staff only</p>

    <?php if ($error): ?>
    <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span><?=app_e($error)?></span></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">

      <div class="ak-auth-field">
        <label>Email or Username</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-user ak-auth-icon"></i>
          <input type="text" name="identity" placeholder="admin" required autofocus>
        </div>
      </div>

      <div class="ak-auth-field">
        <label>Password</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-lock ak-auth-icon"></i>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
      </div>

      <button class="ak-auth-btn" type="submit">Login</button>
    </form>

    <div class="ak-auth-links center">
      <a href="<?=$websiteUrl?>/"><i class="fas fa-arrow-left"></i> Back to site</a>
    </div>
  </div>
</div>

<?php include('../_php/ak_footer.php'); ?>
</body>
</html>
