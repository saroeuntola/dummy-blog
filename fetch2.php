<?php
require 'vendor/autoload.php';
require './services/db.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$pdo = dbConn();

$client = new Client(['verify' => false, 'timeout' => 10, 'headers' => ['User-Agent' => 'Mozilla/5.0']]);

// 1. Fetch list of latest news
$listUrl = "https://www.cricbuzz.com/cricket-news/latest-news";
$html = $client->get($listUrl)->getBody()->getContents();
$crawler = new Crawler($html);

$items = $crawler->filter('div.flex.flex-col.gap-2.py-4');

$news = [];
$items->each(function ($node) use (&$news) {
    $titleNode = $node->filter('a.font-bold');
    $imageNode = $node->filter('img')->first();

    
    if ($titleNode->count()) {
        $url = "https://www.cricbuzz.com" . $titleNode->attr('href');
        $slug = basename($titleNode->attr('href'));
        $title = trim($titleNode->text());
        $thumb = $imageNode->count() ? $imageNode->attr('src') : '';
        $external_id = preg_replace('/\D/', '', $url);

        $news[] = [
            'external_id' => $external_id,
            'title' => $title,
            'slug' => $slug,
            'url' => $url,
            'thumbnail' => $thumb
        ];
    }
});

echo "Found " . count($news) . " articles\n";

// 2. Function to scrape full article
function scrapeFullArticle($url, $client)
{
    $html = $client->get($url)->getBody()->getContents();
    $crawler = new Crawler($html);

    $title = $crawler->filter('h1')->first()->text('');
    $author = $crawler->filter('section a.text-cbTextLink')->text('');
    $date = $crawler->filter('section span.text-gray-500')->text('');

    $image = '';
    $imgNode = $crawler->filter('div.wb\\:flex img');
    if ($imgNode->count()) $image = $imgNode->first()->attr('src');

    $paragraphs = [];
    $crawler->filter('div.flex.flex-col.gap-6 section p')->each(function ($p) use (&$paragraphs) {
        $text = trim($p->text());
        if ($text !== '') {
            $paragraphs[] = "<p>{$text}</p>";
        }
    });

    $content = implode("\n", $paragraphs);

    return [
        'title' => $title,
        'author' => $author,
        'date' => $date,
        'image' => $image,
        'content' => $content
    ];
}


// 3. Insert into DB with duplicate prevention
foreach ($news as $item) {

    // Check duplicate by external_id or slug
    $stmt = $pdo->prepare("SELECT id FROM posts_staging WHERE external_id=? OR slug=?");
    $stmt->execute([$item['external_id'], $item['slug']]);
    if ($stmt->fetch()) {
        echo "Skipped duplicate: {$item['title']}\n";
        continue;
    }

    echo "Scraping full article: {$item['title']}\n";
    $article = scrapeFullArticle($item['url'], $client);

    // Insert into DB
    $stmt = $pdo->prepare("
        INSERT INTO posts_staging
        (external_id, name, slug, image, description, game_link, category_id, meta_text, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $item['external_id'],
        $article['title'],
        $item['slug'],
        $article['image'],
        $article['content'],
        $item['url'],
        2,    
        $article['title'],
        'draft', 
        date('Y-m-d H:i:s')
    ]);

    echo "Inserted: {$article['title']}\n";
}

echo "\nScraping complete.\n";
