<?php
require 'vendor/autoload.php';
require './services/db.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$pdo = dbConn();
$client = new Client([
    'verify' => false,
    'timeout' => 15,
    'headers' => ['User-Agent' => 'Mozilla/5.0']
]);

$baseUrl = "https://www.dhakatribune.com/sport/cricket";
$uploadDir = __DIR__ . '/uploads';
$uploadWebPath = 'uploads/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// ---------------------- Helpers ----------------------
function absoluteUrl(string $url, string $base = 'https://www.dhakatribune.com'): string
{
    $url = trim($url);
    if (!$url) return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if (strpos($url, '//') === 0) return 'https:' . $url;
    if (strpos($url, '/') === 0) return rtrim($base, '/') . $url;
    return rtrim($base, '/') . '/' . ltrim($url, '/');
}

function downloadImage(string $url, string $uploadDir, string $uploadWebPath): string
{
    $url = absoluteUrl($url);
    if (!$url) return '';

    $data = @file_get_contents($url);
    if (!$data) return '';

    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $ext = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) ? strtolower($ext) : 'jpg';

    $filename = 'img_' . uniqid() . '.' . $ext;
    $savePath = rtrim($uploadDir, '/') . '/' . $filename;

    if (file_put_contents($savePath, $data) === false) return '';

    return rtrim($uploadWebPath, '/') . '/' . $filename;
}

// ---------------------- 1. Fetch Article List ----------------------
try {
    $html = $client->get($baseUrl)->getBody()->getContents();
} catch (Exception $e) {
    die("Failed to fetch list page: " . $e->getMessage());
}

$crawler = new Crawler($html);
$news = [];

$crawler->filter('div.each.col_in')->each(function (Crawler $node) use (&$news) {

    $linkNode = $node->filter('h2.title a.link_overlay');
    if (!$linkNode->count()) return;

    $href = $linkNode->attr('href');
    $title = trim($linkNode->text());
    $url = preg_match('#^https?://#', $href) ? $href : 'https:' . $href;
    $slug = basename(parse_url($url, PHP_URL_PATH));
    $external_id = md5($url);

    // Thumbnail
    $imgNode = $node->filter('div.image img');
    $thumb = $imgNode->count() ? downloadImage($imgNode->attr('src'), $GLOBALS['uploadDir'], $GLOBALS['uploadWebPath']) : '';

    // Published time
    $timeNode = $node->filter('span.time');
    $publishedAt = date('Y-m-d H:i:s');
    if ($timeNode->count()) {
        $dt = $timeNode->attr('data-published') ?: $timeNode->text();
        if ($dt) $publishedAt = date('Y-m-d H:i:s', strtotime($dt));
    }

    $news[] = [
        'external_id'  => $external_id,
        'title'        => $title,
        'slug'         => $slug,
        'url'          => $url,
        'thumbnail'    => $thumb,
        'published_at' => $publishedAt
    ];
});

echo "Found " . count($news) . " articles\n";

// ---------------------- 2. Scrape Full Article ----------------------
function scrapeFullArticle($url, $client)
{
    try {
        $html = $client->get($url)->getBody()->getContents();
    } catch (Exception $e) {
        return '';
    }

    $crawler = new Crawler($html);
    $contentHtml = '';

    try {
        // Main article container
        $container = $crawler->filter('div.story-content');
        if ($container->count()) {

            // Fix all <img> lazy-loads
            $container->filter('img')->each(function (Crawler $img) {
                $el = $img->getNode(0);
                $src = $el->getAttribute('src');
                $dataSrc = $el->getAttribute('data-src') ?: $el->getAttribute('data-lazy');
                $finalSrc = $src ?: $dataSrc;
                if ($finalSrc) $el->setAttribute('src', \absoluteUrl($finalSrc));
            });

            // Handle <img> inside <a.jw_media_holder>
            $container->filter('a.jw_media_holder img')->each(function (Crawler $img) {
                $el = $img->getNode(0);
                $src = $el->getAttribute('src');
                if ($src && strpos($src, '//') === 0) $src = 'https:' . $src;
                if ($src) $el->setAttribute('src', $src);
            });

            $raw = $container->html();
            $allowed = '<p><img><figure><figcaption><h1><h2><h3><h4><ul><ol><li><strong><em><a><div><span><blockquote><br>';
            $contentHtml = strip_tags($raw, $allowed);

            // Download images in content
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentHtml, $matches)) {
                foreach ($matches[1] as $src) {
                    $local = \downloadImage($src, $GLOBALS['uploadDir'], $GLOBALS['uploadWebPath']);
                    if ($local) $contentHtml = str_replace($src, $local, $contentHtml);
                }
            }
        }
    } catch (Exception $e) {
        // silent fallback
    }

    // Fallback if empty
    if (!$contentHtml) {
        $paragraphs = [];
        $crawler->filter('p')->each(function ($p) use (&$paragraphs) {
            $text = trim($p->text());
            if ($text) $paragraphs[] = "<p>{$text}</p>";
        });
        $contentHtml = implode("\n", $paragraphs);
    }

    return $contentHtml;
}

// ---------------------- 3. Insert Into DB ----------------------
foreach ($news as $item) {
    try {
        // Skip duplicates
        $stmt = $pdo->prepare("SELECT id FROM posts_staging WHERE external_id=? OR slug=? LIMIT 1");
        $stmt->execute([$item['external_id'], $item['slug']]);
        if ($stmt->fetch()) {
            echo "Skipped duplicate: {$item['title']}\n";
            continue;
        }

        echo "Scraping full article: {$item['title']}\n";
        $content = scrapeFullArticle($item['url'], $client);
        if (!$content) continue;

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO posts_staging
            (external_id, name, slug, image, description, game_link, category_id, meta_text, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $item['external_id'],
            $item['title'],
            $item['slug'],
            $item['thumbnail'],
            $content,
            $item['url'],
            2,
            $item['title'],
            'draft',
            $item['published_at']
        ]);

        echo "Inserted: {$item['title']}\n";
        usleep(200000); // polite pause
    } catch (Exception $e) {
        echo "DB Error for {$item['title']}: " . $e->getMessage() . "\n";
    }
}

echo "Scraping complete.\n";
