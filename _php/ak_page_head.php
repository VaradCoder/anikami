<?php
/**
 * Shared <head> partial for Anikami pages.
 * Call before <head> output. Expects:
 *   $pageTitle    - string, already escaped
 *   $pageDesc     - string, plain text (will be htmlspecialchars'd here)
 *   $pageKeywords - string (optional)
 *   $pageCanonical- string (optional, full URL)
 *   $pageImage    - string (optional, full URL)
 *   $pageRobots   - string (optional, defaults 'index, follow')
 *   $extraCss     - string (optional, extra <link> or <style> tags)
 * All globals from _config.php ($websiteUrl, $websiteTitle, $version, $banner) are used.
 */
$pageDesc     = htmlspecialchars(strip_tags((string)($pageDesc ?? '')), ENT_QUOTES, 'UTF-8');
$pageKeywords = htmlspecialchars((string)($pageKeywords ?? "$websiteTitle, watch anime online, free anime, anime stream, anime hd, english sub"), ENT_QUOTES, 'UTF-8');
$pageCanonical= (string)($pageCanonical ?? '');
$pageImage    = (string)($pageImage ?? $banner);
$pageRobots   = (string)($pageRobots ?? 'index, follow');
$pageOgType   = (string)($pageOgType ?? 'website');
$extraCss     = (string)($extraCss ?? '');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?=$pageDesc?>">
<meta name="keywords" content="<?=$pageKeywords?>">
<meta name="robots" content="<?=$pageRobots?>">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta property="og:title" content="<?=$pageTitle?>">
<meta property="og:description" content="<?=$pageDesc?>">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="<?=htmlspecialchars($pageOgType)?>">
<meta property="og:site_name" content="<?=$websiteTitle?>">
<?php if ($pageCanonical): ?>
<link rel="canonical" href="<?=htmlspecialchars($pageCanonical)?>">
<meta property="og:url" content="<?=htmlspecialchars($pageCanonical)?>">
<?php endif; ?>
<meta property="og:image" content="<?=htmlspecialchars($pageImage)?>">
<meta property="og:image:alt" content="<?=$pageTitle?>">
<meta property="og:image:width" content="650">
<meta property="og:image:height" content="350">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=$pageTitle?>">
<meta name="twitter:description" content="<?=$pageDesc?>">
<meta name="twitter:image" content="<?=htmlspecialchars($pageImage)?>">
<meta name="theme-color" content="#07060d">
<link rel="shortcut icon" href="<?=$websiteUrl?>/favicon.ico?v=<?=$version?>" type="image/x-icon">
<link rel="apple-touch-icon" href="<?=$websiteUrl?>/favicon.ico?v=<?=$version?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@300;400;500;600;700&display=swap"></noscript>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
<link rel="stylesheet" href="<?=$websiteUrl?>/files/css/anikami.css?v=<?=$version?>">
<?=$extraCss?>
