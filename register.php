<?php
require_once __DIR__ . '/_config.php';
if (app_is_logged_in()) {
    app_redirect('/profile.php');
}

$error = '';
$verifyLink = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_validate_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $result = app_auth_register($_POST['email'] ?? '', $_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['ok']) {
            $token = app_create_email_verification((int)$result['user_id']);
            $verifyLink = $websiteUrl . '/verify-email.php?token=' . $token;
            // NOTE: no email provider configured yet — showing the link
            // directly instead of sending it. Swap this for a real send
            // once SMTP/an email API is wired in (see auth_security.php).
            app_auth_login($_POST['email'] ?? '', $_POST['password'] ?? '');
        } else {
            $error = $result['error'] ?? 'Signup failed';
        }
    }
}

$pageTitle = 'Sign Up - ' . $websiteTitle;
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
    <h1 class="ak-auth-title">Create Account</h1>
    <p class="ak-auth-sub">Join to track your watchlist and progress</p>

    <?php if ($error): ?>
    <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span><?=app_e($error)?></span></div>
    <?php endif; ?>

    <?php if ($verifyLink): ?>
      <div class="ak-auth-icon-hero success"><i class="fas fa-envelope-circle-check"></i></div>
      <div class="ak-auth-alert success">
        <i class="fas fa-circle-info"></i>
        <span>
          Account created! No email provider is configured yet, so here's your verification link directly:<br>
          <a href="<?=app_e($verifyLink)?>"><?=app_e($verifyLink)?></a>
        </span>
      </div>
      <a class="ak-auth-btn secondary" href="<?=$websiteUrl?>/profile.php">Go to Profile</a>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=app_e(app_csrf_token())?>">

      <div class="ak-auth-field">
        <label>Email</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-envelope ak-auth-icon"></i>
          <input type="email" name="email" placeholder="you@example.com" required autofocus>
        </div>
      </div>

      <div class="ak-auth-field">
        <label>Username</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-user ak-auth-icon"></i>
          <input type="text" name="username" placeholder="3-24 characters, letters/numbers/_" required>
        </div>
      </div>

      <div class="ak-auth-field">
        <label>Password</label>
        <div class="ak-auth-input-wrap">
          <i class="fas fa-lock ak-auth-icon"></i>
          <input type="password" name="password" placeholder="At least 8 characters" required minlength="8">
        </div>
      </div>

      <button class="ak-auth-btn" type="submit">Register</button>
    </form>

    <div class="ak-auth-links center">
      Already have an account? <a href="<?=$websiteUrl?>/login.php">Login</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
