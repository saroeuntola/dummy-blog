<?php
require './services/checkroles.php';
require './services/db.php';
require "./vendor/autoload.php";
protectRoute([1, 3]);
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


$postId = $_GET['id'] ?? null;
if (!$postId) {
    die("Please provide a post ID to push.");
}

// Fetch the specific draft post
$stmt = $pdo->prepare("SELECT * FROM posts_staging WHERE id=? AND status='draft'");
$stmt->execute([$postId]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die("No draft post found with ID $postId");
}

$CurrrentUser = $_SESSION['username'] ?? 0;
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
    'image'           => $post['image'],
    'link'            => $post['link'] ?? '',
    'public_by'       => $post['public_by'] ?? $CurrrentUser,
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

// Success check
$isSuccess = strpos($response, "Post Published Successfully") !== false;

if ($isSuccess) {
    $update = $pdo->prepare("UPDATE posts_staging SET status='approved' WHERE id=?");
    $update->execute([$post['id']]);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Result</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            padding: 40px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }

        .message-box {
            padding: 18px 20px;
            border-radius: 10px;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 20px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .success {
            background: #eaffea;
            border: 1px solid #46c36f;
            color: #2d7d42;
        }

        .error {
            background: #ffeaea;
            border: 1px solid #ff6d6d;
            color: #b92626;
        }

        .footer {
            margin-top: 15px;
            font-size: 15px;
            color: #555;
        }

        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 25px;
            background: #3498db;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
        }

        .btn-back:hover {
            background: #1d78b7;
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="title">Push Result — Post ID <?= $post['id'] ?></div>

        <div class="message-box <?= $isSuccess ? 'success' : 'error' ?>">
            <?= htmlspecialchars($response ?: $error) ?>
        </div>

        <div class="footer">
            <?= $isSuccess
                ? "✔ Post has been marked as <strong>approved</strong>."
                : "✖ Push failed. The post remains in <strong>draft</strong>."
            ?>
        </div>

        <a class="btn-back" href="/">← Back Home</a>

    </div>
</body>

</html>