<?php
require_once __DIR__ . '/_config.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;

$validUserId = $token !== '' ? app_get_password_reset_user($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } elseif (!$validUserId) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $result = app_consume_password_reset($token, $password);
            if ($result['ok']) {
                $success = true;
            } else {
                $error = $result['error'] ?? 'Could not reset password';
            }
        }
    }
}

$pageTitle = 'Reset Password - ' . $websiteTitle;
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
    <h1 class="ak-auth-title">Reset Password</h1>

    <?php if ($error): ?>
    <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span><?=app_e($error)?></span></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="ak-auth-icon-hero success"><i class="fas fa-circle-check"></i></div>
      <div class="ak-auth-alert success">
        <i class="fas fa-circle-info"></i>
        <span>Your password has been reset. All previous sessions were logged out for security — please log in again.</span>
      </div>
      <a class="ak-auth-btn" href="<?=$websiteUrl?>/login.php" style="display:block;text-align:center;text-decoration:none;">Go to Login</a>
    <?php elseif (!$validUserId): ?>
      <div class="ak-auth-icon-hero error"><i class="fas fa-triangle-exclamation"></i></div>
      <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span>This reset link is invalid or has expired.</span></div>
      <a class="ak-auth-btn secondary" href="<?=$websiteUrl?>/forgot-password.php">Request a new link</a>
    <?php else: ?>
    <p class="ak-auth-sub">Choose a new password for your account</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <input type="hidden" name="token" value="<?=app_e($token)?>">
      <div class="ak-auth-field">
        <label>New Password</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-lock ak-auth-icon"></i>
          <input type="password" name="password" placeholder="At least 8 characters" required minlength="8" autofocus>
        </div>
      </div>
      <div class="ak-auth-field">
        <label>Confirm New Password</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-lock ak-auth-icon"></i>
          <input type="password" name="password_confirm" placeholder="Repeat password" required minlength="8">
        </div>
      </div>
      <button class="ak-auth-btn" type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
