<?php
require "./services/db.php";

$pdo = dbConn();

// Create uploads dir if not exists
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);


function fetchApiJson($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/119.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_REFERER => 'https://sportzwiki.com/cricket',
    ]);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch);
    }
    curl_close($ch);
    return $res ? json_decode($res, true) : false;
}
// Function to download image via cURL
function downloadImage($url, $uploadDir)
{
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $localPath = $uploadDir . time() . "_" . rand(1000, 9999) . "." . $ext;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data) {
        file_put_contents($localPath, $data);
        return $localPath;
    }
    return '';
}

// Fetch posts
$posts = fetchApiJson("https://sportzwiki.com/wp-json/wp/v2/posts?per_page=20&_embed&categories=2");
if (!$posts) die("Failed to fetch posts");

foreach ($posts as $p) {

    $external_id = $p["id"];
    $name = $p["title"]["rendered"] ?? '';
    $slug = $p["slug"] ?? '';
    $description = $p["content"]["rendered"] ?? '';
    $created_at = date('Y-m-d H:i:s');

    // Skip duplicates
    $check = $pdo->prepare("SELECT id FROM posts_staging WHERE external_id=?");
    $check->execute([$external_id]);
    if ($check->rowCount()) continue;

    // Featured image
    $featured_image = '';
    if (isset($p['_embedded']['wp:featuredmedia'][0]['source_url'])) {
        $featured_image = downloadImage($p['_embedded']['wp:featuredmedia'][0]['source_url'], $uploadDir);
    }

    // Content images
    $description = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function ($matches) use ($uploadDir) {
        $localImg = downloadImage($matches[1], $uploadDir);
        return $localImg ? str_replace($matches[1], $localImg, $matches[0]) : $matches[0];
    }, $description);

    // Insert into DB
    $stmt = $pdo->prepare("
        INSERT INTO posts_staging
        (external_id, name, slug, description, image, created_at, status)
        VALUES (?, ?, ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([$external_id, $name, $slug, $description, $featured_image, $created_at]);
}

echo "Imported successfully with images!";