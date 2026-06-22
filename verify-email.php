<?php
require_once __DIR__ . '/_config.php';

$token = (string)($_GET['token'] ?? '');
$ok = $token !== '' && app_verify_email_token($token);

$pageTitle = 'Verify Email - ' . $websiteTitle;
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
  <div class="ak-auth-card" style="text-align:center;">
    <div class="ak-auth-logo"><i class="fas fa-heart"></i> ANIKAMI</div>
    <h1 class="ak-auth-title">Email Verification</h1>

    <?php if ($ok): ?>
      <div class="ak-auth-icon-hero success"><i class="fas fa-circle-check"></i></div>
      <div class="ak-auth-alert success"><i class="fas fa-circle-info"></i><span>Your email has been verified.</span></div>
      <a class="ak-auth-btn" href="<?=$websiteUrl?>/profile.php" style="display:block;text-decoration:none;">Go to Profile</a>
    <?php else: ?>
      <div class="ak-auth-icon-hero error"><i class="fas fa-triangle-exclamation"></i></div>
      <div class="ak-auth-alert error"><i class="fas fa-circle-exclamation"></i><span>This verification link is invalid or has expired.</span></div>
      <a class="ak-auth-btn secondary" href="<?=$websiteUrl?>/login.php">Back to Login</a>
    <?php endif; ?>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
