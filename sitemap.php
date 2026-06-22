<?php
include('./_config.php');

header('Content-type: application/xml');

echo "<?xml version='1.0' encoding='UTF-8'?>"."\n";
echo "<sitemapindex xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>"."\n";
?>
  <sitemap>
    <loc><?=$websiteAbsoluteUrl?>/sitemaps/sitemap.xml</loc>
  </sitemap>
  <sitemap>
    <loc><?=$websiteAbsoluteUrl?>/sitemaps/ongoing-sitemap.xml</loc>
  </sitemap>
  <sitemap>
    <loc><?=$websiteAbsoluteUrl?>/sitemaps/recentCN-sitemap.xml</loc>
  </sitemap>
  <sitemap>
    <loc><?=$websiteAbsoluteUrl?>/sitemaps/recentDUB-sitemap.xml</loc>
  </sitemap>
  <sitemap>
    <loc><?=$websiteAbsoluteUrl?>/sitemaps/recentSUB-sitemap.xml</loc>
  </sitemap>
  <?php
  $array = range(1, 85);
array_walk($array, function ($value) use ($websiteAbsoluteUrl) {
    echo "<sitemap>";
    echo "<loc>{$websiteAbsoluteUrl}/sitemaps/allanime-sitemap.xml?page=".$value."</loc>";
    echo "</sitemap>";
});
 ?>
  
</sitemapindex>
