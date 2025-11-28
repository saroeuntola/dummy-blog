<?php
require "./vendor/autoload.php";
require "./services/db.php";

$pdo = dbConn();

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get REAL_SITE_ENDPOINT and SECRET_KEY from env
$REAL_SITE_ENDPOINT = $_ENV['REAL_SITE_ENDPOINT'] ?? '';
$SECRET_KEY         = $_ENV['SECRET_KEY'] ?? '';

if (!$REAL_SITE_ENDPOINT || !$SECRET_KEY) {
    die("REAL_SITE_ENDPOINT or SECRET_KEY not set in .env");
}

// Get the post ID to push from URL query: push.php?id=5
$postId = $_GET['id'] ?? null;
if (!$postId) {
    die("Please provide a post ID to push. Example: push.php?id=5");
}

// Fetch the specific draft post
$stmt = $pdo->prepare("SELECT * FROM posts_staging WHERE id=? AND status='draft'");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die("No draft post found with ID $postId");
}

// Prepare payload
$payload = [
    'secret_key'      => $SECRET_KEY,
    'slug'            => $post['slug'],
    'name'            => $post['name'],
    'description'     => $post['description'],
    'meta_text'       => $post['meta_text'],

    'name_bn'         => $post['name_bn'],
    'description_bn'  => $post['description_bn'],
    'meta_text_bn'    => $post['meta_text_bn'],

    'meta_desc'       => $post['meta_desc'],
    'meta_keyword'    => $post['meta_keyword'],
    'meta_desc_bn'    => $post['meta_desc_bn'],
    'meta_keyword_bn' => $post['meta_keyword_bn'],

    'category_id'     => $post['category_id'],
    'image'           => $post['image'], // URL from dummy site
    'link'            => $post['link'] ?? '',
    'public_by'       => $post['public_by'] ?? 0
];

// Send POST request to real site
$ch = curl_init($REAL_SITE_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => "DummySitePublisher/1.0",
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// Log result
echo "<b>Post ID {$post['id']} → Push Result:</b> " . ($response ?: $error) . "<br>";

// Mark as published if successful
if (strpos($response, "Post Published Successfully") !== false) {
    $update = $pdo->prepare("UPDATE posts_staging SET status='approved' WHERE id=?");
    $update->execute([$post['id']]);
    echo "<br>Post marked as published on dummy site.";
} else {
    echo "<br>Push failed. Post status remains draft.";
}

echo "<hr><b>Push complete.</b>";
