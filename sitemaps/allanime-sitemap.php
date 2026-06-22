<?php
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
include('../_config.php');

header('Content-type: application/xml');

echo "<?xml version='1.0' encoding='UTF-8'?>"."\n";
echo "<urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>"."\n";

$json = legacy_api("animeList?page=$page");
$json = json_decode($json, true);
foreach($json as $getAllAnime) 
{  
    echo "<url>";
    echo "<loc>{$websiteAbsoluteUrl}/anime/".$getAllAnime['animeId']."</loc>";
    echo "<changefreq>daily</changefreq>";
    echo "<priority>0.8000</priority>";
    echo "</url>";
}

echo "</urlset>";

?>
