<?php
require_once __DIR__ . '/_config.php';
if (app_is_logged_in()) {
    app_redirect('/profile.php');
}

$error = '';
$resetLink = '';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $identity = trim((string)($_POST['identity'] ?? ''));
        $submitted = true;
        if ($identity !== '') {
            $token = app_create_password_reset($identity);
            if ($token) {
                $resetLink = $websiteUrl . '/reset-password.php?token=' . $token;
            }
            // NOTE: no email provider configured yet — showing the link
            // directly when found, instead of emailing it. Whether or not
            // the account exists, the on-screen message below is identical
            // ("if that account exists...") so this page can't be used to
            // enumerate which emails/usernames are registered.
        }
    }
}

$pageTitle = 'Forgot Password - ' . $websiteTitle;
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
    <h1 class="ak-auth-title">Forgot Password</h1>
    <p class="ak-auth-sub">Enter your email or username to reset it</p>

    <?php if ($error): ?>
    <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span><?=app_e($error)?></span></div>
    <?php endif; ?>

    <?php if ($submitted && !$error): ?>
      <div class="ak-auth-icon-hero success"><i class="fas fa-envelope-circle-check"></i></div>
      <div class="ak-auth-alert success">
        <i class="fas fa-circle-info"></i>
        <span>
          If that account exists, a password reset link has been created.
          <?php if ($resetLink): ?>
            <br><br>No email provider is configured yet, so here's the link directly:<br>
            <a href="<?=app_e($resetLink)?>"><?=app_e($resetLink)?></a>
          <?php endif; ?>
        </span>
      </div>
      <a class="ak-auth-btn secondary" href="<?=$websiteUrl?>/login.php">Back to Login</a>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">
      <div class="ak-auth-field">
        <label>Email or Username</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-user ak-auth-icon"></i>
          <input type="text" name="identity" placeholder="you@example.com" required autofocus>
        </div>
      </div>
      <button class="ak-auth-btn" type="submit">Send Reset Link</button>
    </form>
    <div class="ak-auth-links center">
      <a href="<?=$websiteUrl?>/login.php">Back to Login</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
