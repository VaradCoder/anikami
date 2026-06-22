<?php
require('./_config.php');
http_response_code(404);

$pageTitle  = '404 — Page Not Found — ' . $websiteTitle;
$pageDesc   = 'The page you were looking for could not be found.';
$pageRobots = 'noindex, nofollow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$pageTitle?></title>
<?php include('./_php/ak_page_head.php'); ?>
<style>
.ak-404-wrap { min-height: 60vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 60px 20px; }
.ak-404-code { font-family: 'Cinzel', serif; font-size: clamp(80px, 15vw, 140px); font-weight: 700; line-height: 1; color: transparent; -webkit-text-stroke: 2px var(--accent); letter-spacing: 10px; margin-bottom: 10px; }
.ak-404-title { font-size: 24px; font-weight: 600; color: var(--text-main); margin-bottom: 10px; }
.ak-404-desc { font-size: 15px; color: var(--text-secondary); max-width: 400px; line-height: 1.6; margin-bottom: 32px; }
.ak-404-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
.ak-404-btn { display: inline-flex; align-items: center; gap: 7px; padding: 11px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all .2s; }
.ak-404-btn-primary { background: var(--accent); color: #fff; border: 1px solid var(--accent); }
.ak-404-btn-primary:hover { background: var(--accent-hover); }
.ak-404-btn-secondary { background: rgba(255,255,255,.06); color: var(--text-secondary); border: 1px solid rgba(255,255,255,.12); }
.ak-404-btn-secondary:hover { background: rgba(255,255,255,.1); color: #fff; }
</style>
</head>
<body class="ak-body">
<?php include('./_php/ak_header.php'); ?>

<div class="ak-page-wrap">
  <div class="ak-404-wrap">
    <div class="ak-404-code">404</div>
    <h1 class="ak-404-title">Page Not Found</h1>
    <p class="ak-404-desc">The page you were looking for doesn't exist or has been moved. Let's get you back on track.</p>
    <div class="ak-404-actions">
      <a href="<?=$websiteUrl?>" class="ak-404-btn ak-404-btn-primary"><i class="fas fa-home"></i> Back to Home</a>
      <a href="<?=$websiteUrl?>/popular" class="ak-404-btn ak-404-btn-secondary"><i class="fas fa-fire"></i> Browse Popular</a>
      <a href="<?=$websiteUrl?>/random" class="ak-404-btn ak-404-btn-secondary"><i class="fas fa-random"></i> Random Anime</a>
    </div>
  </div>
</div>

<?php include('./_php/ak_footer.php'); ?>
</body>
</html>
