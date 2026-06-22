<?php
require_once __DIR__ . '/_config.php';

// If already logged in, redirect to profile.
if (app_current_user() && app_is_logged_in()) {
    app_redirect('/profile.php');
}

$next = (string)($_GET['next'] ?? '');
if ($next === '') {
    $next = '/profile.php';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $login = app_auth_login($_POST['identity'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
        if (!empty($login['ok'])) {
            // Prevent open redirects: only allow internal paths.
            $nextPath = parse_url($next, PHP_URL_PATH) ?: $next;
            if (!is_string($nextPath) || $nextPath === '') {
                $nextPath = '/profile.php';
            }
            if (strpos($nextPath, '/') !== 0) {
                $nextPath = '/profile.php';
            }
            app_redirect($nextPath);
        } else {
            $error = $login['error'] ?? 'Login failed';
        }
    }
}

$pageTitle = 'Login - ' . $websiteTitle;
$pageRobots = 'noindex, nofollow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<?php include('./_php/ak_auth_style.php'); ?>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-auth-wrap">
  <div class="ak-auth-card">
    <div class="ak-auth-logo"><i class="fas fa-heart"></i> ANIKAMI</div>
    <h1 class="ak-auth-title">Welcome Back</h1>
    <p class="ak-auth-sub">Login to continue watching</p>

    <?php if ($error): ?>
    <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span><?=app_e($error)?></span></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">

      <div class="ak-auth-field">
        <label>Email or Username</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-user ak-auth-icon"></i>
          <input type="text" name="identity" placeholder="you@example.com" required autofocus>
        </div>
      </div>

      <div class="ak-auth-field">
        <label>Password</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-lock ak-auth-icon"></i>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
      </div>

      <label class="ak-auth-checkbox">
        <input type="checkbox" name="remember" value="1">
        Remember me for 30 days
      </label>

      <button class="ak-auth-btn" type="submit">Login</button>
    </form>

    <div class="ak-auth-links">
      <a href="<?=$websiteUrl?>/register.php">Create an account</a>
      <a href="<?=$websiteUrl?>/forgot-password.php">Forgot password?</a>
    </div>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
