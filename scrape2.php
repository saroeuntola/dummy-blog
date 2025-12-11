<?php
// scrape_dhakatribune_full.php
require 'vendor/autoload.php';
require './services/db.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

// ---------- CONFIG ----------
$pdo = dbConn();
$client = new Client([
    'timeout' => 15,
    'verify'  => false, // set to true in production if you have CA setup
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (compatible; DhakaTribuneScraper/1.0)'
    ]
]);

$baseListUrl   = "https://www.dhakatribune.com/sport/cricket";
$uploadDir     = __DIR__ . '/uploads';
$uploadWebPath = 'uploads/'; // stored in DB, adjust if your web path differs
$limitArticles = 60; // change / set null to fetch all found links
// ----------------------------

// Ensure upload dir exists
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        die("Failed to create upload dir: $uploadDir");
    }
}

// ----------------------------
// Helpers
// ----------------------------
function absoluteUrl(string $url, string $base): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if (strpos($url, '//') === 0) {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }
    if (strpos($url, '/') === 0) {
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        return $scheme . '://' . $host . $url;
    }
    $parts = parse_url($base);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $basePath = rtrim(dirname($parts['path'] ?? '/'), '/');
    return $scheme . '://' . $host . $basePath . '/' . ltrim($url, '/');
}

function downloadImageToUploads(string $url, string $uploadDir, string $uploadWebPath): string
{
    $url = trim($url);
    if ($url === '') return '';

    // try file_get_contents
    $data = @file_get_contents($url);
    if ($data === false) {
        // fallback to curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data === false) return '';
    }

    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : 'jpg';
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) $ext = 'jpg';

    $filename = 'img_' . uniqid() . '.' . $ext;
    $savePath = rtrim($uploadDir, '/') . '/' . $filename;

    if (file_put_contents($savePath, $data) === false) return '';

    // Return web-accessible relative path
    return rtrim($uploadWebPath, '/') . '/' . $filename;
}

function safeTextFromCrawler(Crawler $c, string $selector = ''): string
{
    try {
        if ($selector === '') {
            return trim($c->text());
        }
        $node = $c->filter($selector);
        if ($node->count()) return trim($node->first()->text());
    } catch (Exception $e) {
    }
    return '';
}

// ----------------------------
// 1) Fetch listing page and collect article links
// ----------------------------
echo "Fetching list page: $baseListUrl\n";
try {
    $res = $client->get($baseListUrl)->getBody()->getContents();
} catch (Exception $e) {
    die("Failed to fetch listing page: " . $e->getMessage() . PHP_EOL);
}
$crawler = new Crawler($res);

// Collect links
$links = [];

// Typical anchor selectors on DhakaTribune listing pages: scan anchors under container
$crawler->filter('a')->each(function (Crawler $node) use (&$links, $baseListUrl) {
    $href = $node->attr('href') ?? '';
    if (!$href) return;
    // only include article links under /sport/cricket/ or /sport/ (cricket)
    if (strpos($href, '/sport/cricket') !== false || strpos($href, '/sport/') !== false) {
        // normalize absolute
        $abs = absoluteUrl($href, $baseListUrl);
        // ensure domain match
        if (strpos($abs, 'dhakatribune.com') !== false) {
            $links[] = $abs;
        }
    }
});

// dedupe and keep order
$links = array_values(array_unique($links));
if ($limitArticles) $links = array_slice($links, 0, $limitArticles);

echo "Found " . count($links) . " candidate links.\n";

if (count($links) === 0) {
    echo "No links found. Exiting.\n";
    exit;
}

// ----------------------------
// 2) Process each link: scrape article, download images, insert to DB
// ----------------------------
foreach ($links as $articleUrl) {
    echo "Processing: $articleUrl\n";

    // build external_id and slug
    $external_id = md5($articleUrl); // reliable unique id from url
    $slug = basename(parse_url($articleUrl, PHP_URL_PATH) ?: $external_id);

    // skip duplicates
    $chk = $pdo->prepare("SELECT id FROM posts_staging WHERE external_id = ? OR slug = ? LIMIT 1");
    $chk->execute([$external_id, $slug]);
    if ($chk->fetch()) {
        echo "  Skip — duplicate found for slug/external_id\n";
        continue;
    }

    // fetch article HTML
    try {
        $html = $client->get($articleUrl)->getBody()->getContents();
    } catch (Exception $e) {
        echo "  Failed to fetch article: " . $e->getMessage() . "\n";
        continue;
    }

    $adoc = new Crawler($html);

    // TITLE: try few selectors
    $title = safeTextFromCrawler($adoc, 'h1.entry-title') ?: safeTextFromCrawler($adoc, 'h1.post-title') ?: safeTextFromCrawler($adoc, 'h1') ?: 'Untitled';

    // PUBLISHED DATE: try meta or time tags
    $publishedRaw = '';
    try {
        $metaPub = $adoc->filter('meta[property="article:published_time"], meta[name="pubdate"], meta[name="publication_date"]');
        if ($metaPub->count()) $publishedRaw = $metaPub->first()->attr('content') ?? '';
    } catch (Exception $e) {
    }
    if (!$publishedRaw) {
        $publishedRaw = safeTextFromCrawler($adoc, 'time') ?: '';
    }
    $publishedAt = date('Y-m-d H:i:s');
    if ($publishedRaw) {
        $ts = strtotime($publishedRaw);
        if ($ts !== false) $publishedAt = date('Y-m-d H:i:s', $ts);
    }

    // FEATURED IMAGE: try og:image or first article image
    $featured = '';
    try {
        $og = $adoc->filter('meta[property="og:image"]');
        if ($og->count()) $featured = $og->first()->attr('content') ?? '';
    } catch (Exception $e) {
    }
    if (!$featured) {
        try {
            $imgNode = $adoc->filter('article img, .post-image img, .entry-content img')->first();
            if ($imgNode->count()) $featured = $imgNode->attr('src') ?? '';
        } catch (Exception $e) {
        }
    }
    $featured = $featured ? absoluteUrl($featured, $articleUrl) : '';

    // ARTICLE CONTENT: try common containers, fallback to all <p>
    $contentSelectors = [
        'div.entry-content',
        'div.post-content',
        'article .entry-content',
        'div.article-body',
        'div.content',
        'div.container .content'
    ];

    // ----------------------------
    // SCRAPE EXACT FULL ARTICLE BLOCK REQUESTED
    // ----------------------------
    $contentHtml = '';

    try {
        $container = $adoc->filter(
            'div.row.detail_holder 
         div.col 
         div.col_in 
         div.content_detail_small_width1 
         div.content_detail_small_width_inner1 
         div.content_highlights.jw_detail_content_holder.content.mb16'
        );

        if ($container->count()) {

            // Fix lazy-loaded images
            $container->filter('img')->each(function (Crawler $img) {
                try {
                    $el = $img->getNode(0);
                    $src = $el->getAttribute('src');
                    $dataSrc = $el->getAttribute('data-src') ?: $el->getAttribute('data-lazy');

                    if (!$src && $dataSrc) {
                        $el->setAttribute('src', $dataSrc);
                    }
                } catch (Exception $e) {
                }
            });

            // HTML CONTENT WITH ALLOWED TAGS
            $raw = $container->html();
            $allowed = '<p><img><figure><figcaption><h1><h2><h3><h4><ul><ol><li><strong><em><a><div><span><br>';
            $contentHtml = strip_tags($raw, $allowed);
        }
    } catch (Exception $e) {
        $contentHtml = '';
    }

    // If still empty → fallback
    if (!$contentHtml) {
        $paras = [];
        try {
            $adoc->filter('p')->each(function (Crawler $p) use (&$paras) {
                $t = trim($p->text());
                if ($t !== '') $paras[] = "<p>{$t}</p>";
            });
        } catch (Exception $e) {
        }
        $contentHtml = implode("\n", $paras);
    }


    // DOWNLOAD IMAGES referenced inside contentHtml (replace src with local path)
    if ($contentHtml) {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $contentHtml, $m)) {
            $srcs = $m[1];
            foreach ($srcs as $src) {
                $abs = absoluteUrl($src, $articleUrl);
                $local = downloadImageToUploads($abs, $uploadDir, $uploadWebPath);
                if ($local) {
                    // replace all occurrences of the original src with the local relative path
                    $contentHtml = str_replace($src, $local, $contentHtml);
                }
            }
        }
    }

    // download featured image
    $localFeatured = '';
    if ($featured) {
        $localFeatured = downloadImageToUploads($featured, $uploadDir, $uploadWebPath);
    }

    // meta_text: small excerpt (first 160 chars)
    $metaText = trim(strip_tags($contentHtml));
    if (mb_strlen($metaText) > 160) $metaText = mb_substr($metaText, 0, 157) . '...';

    // Prepare data with safe defaults
    $insertData = [
        'external_id'    => $external_id,
        'name'           => $title,
        'slug'           => $slug,
        'image'          => $localFeatured ?: '',
        'description'    => $contentHtml ?: '',
        'game_link'      => $articleUrl,
        'category_id'    => 2,
        'meta_text'      => $metaText ?: $title,
        'name_bn'        => '',
        'description_bn' => '',
        'meta_text_bn'   => '',
        'meta_desc'      => '',
        'meta_keyword'   => '',
        'meta_desc_bn'   => '',
        'meta_keyword_bn' => '',
        'public_by'      => 0,
        'status'         => 'draft',
        'created_at'     => $publishedAt
    ];

    // Insert using named placeholders (avoids parameter number mismatch)
    $sql = "INSERT INTO posts_staging
            (external_id, name, slug, image, description, game_link, category_id, meta_text,
             name_bn, description_bn, meta_text_bn, meta_desc, meta_keyword, meta_desc_bn, meta_keyword_bn,
             public_by, status, created_at)
            VALUES
            (:external_id, :name, :slug, :image, :description, :game_link, :category_id, :meta_text,
             :name_bn, :description_bn, :meta_text_bn, :meta_desc, :meta_keyword, :meta_desc_bn, :meta_keyword_bn,
             :public_by, :status, :created_at)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertData);
        echo "  Inserted -> {$title}\n";
    } catch (PDOException $e) {
        echo "  DB Insert failed: " . $e->getMessage() . "\n";
        // optionally log $insertData for debugging
        // file_put_contents('debug_insert.json', json_encode($insertData, JSON_PRETTY_PRINT));
    }

    // short wait to be polite
    usleep(250000);
}

echo "Scrape finished.\n";
