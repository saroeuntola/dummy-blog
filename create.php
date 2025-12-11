<?php
require './vendor/autoload.php';
require './services/checkroles.php';
require './services/db.php';
$pdo = dbConn();
protectRoute([1, 3]);

use Stichoza\GoogleTranslate\GoogleTranslate;

$message = '';

// Initialize empty post array to prefill form
$post = [
    'name' => '',
    'description' => '',
    'meta_text' => '',
    'meta_desc' => '',
    'meta_keyword' => '',
    'name_bn' => '',
    'description_bn' => '',
    'meta_text_bn' => '',
    'meta_desc_bn' => '',
    'meta_keyword_bn' => '',
    'category_id' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get all form values
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $meta_text = $_POST['meta_text'] ?? '';
    $meta_desc = $_POST['meta_desc'] ?? '';
    $meta_keyword = $_POST['meta_keyword'] ?? '';

    $name_bn = $_POST['name_bn'] ?? '';
    $description_bn = $_POST['description_bn'] ?? '';
    $meta_text_bn = $_POST['meta_text_bn'] ?? '';
    $meta_desc_bn = $_POST['meta_desc_bn'] ?? '';
    $meta_keyword_bn = $_POST['meta_keyword_bn'] ?? '';

    $category_id = $_POST['category_id'] ?? null;

    // ---------------------------
    // Image Upload
    // ---------------------------
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['tmp_name']) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $filename = basename($_FILES['image']['name']);
        $targetFile = $targetDir . time() . "_" . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image = $targetFile;
        }
    }

    // ---------------------------
    // Translate Button (BN)
    // ---------------------------
    if (isset($_POST['translate_bn'])) {

        $tr = new GoogleTranslate('bn');

        $name_bn = $tr->translate($name);
        $description_bn = $tr->translate($description);
        $meta_text_bn = $tr->translate($meta_text);
        $meta_desc_bn = $tr->translate($meta_desc);
        $meta_keyword_bn = $tr->translate($meta_keyword);

        $message = "Auto-translated to Bangla! You can still edit before saving.";

        // Prefill $post with EN + translated BN values for the form
        $post = [
            'name' => $name,
            'description' => $description,
            'meta_text' => $meta_text,
            'meta_desc' => $meta_desc,
            'meta_keyword' => $meta_keyword,
            'name_bn' => $name_bn,
            'description_bn' => $description_bn,
            'meta_text_bn' => $meta_text_bn,
            'meta_desc_bn' => $meta_desc_bn,
            'meta_keyword_bn' => $meta_keyword_bn,
            'category_id' => $category_id
        ];
    } else {
        // ---------------------------
        // Save to Database
        // ---------------------------
        $stmt = $pdo->prepare("
            INSERT INTO posts_staging 
            (name, description, meta_text, image, meta_desc, meta_keyword, category_id,
             name_bn, description_bn, meta_text_bn, meta_desc_bn, meta_keyword_bn)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $description,
            $meta_text,
            $image,
            $meta_desc,
            $meta_keyword,
            $category_id,
            $name_bn,
            $description_bn,
            $meta_text_bn,
            $meta_desc_bn,
            $meta_keyword_bn
        ]);

        $message = "Post created successfully!";
        header('Location: view.php?id=' . $pdo->lastInsertId());
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create New Post</title>
    <script src="./js/tinymce/tinymce.min.js"></script>
    <style>
        body {
            font-family: Arial;
            background: #f5f6fa;
            padding: 20px;
        }

        .container {
            max-width: 970px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
        }

        input[type=text],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .btn {
            margin-top: 20px;
            padding: 10px 16px;
            border-radius: 6px;
            background: #3498db;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        .message {
            color: green;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="posts_list.php">&larr; Back to Posts</a>
        <h1>Create New Post</h1>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <!-- Category -->
            <label>Category</label>
            <select name="category_id" required>
                <option value="">-- Select Category --</option>
                <option value="3" <?= ($post['category_id'] == 3 ? 'selected' : '') ?>>Cricket News</option>
                <option value="2" <?= ($post['category_id'] == 2 ? 'selected' : '') ?>>Cricket Betting Guides</option>
                <option value="6" <?= ($post['category_id'] == 6 ? 'selected' : '') ?>>Match Preview</option>
            </select>

            <!-- English -->
            <h3>English Content</h3>
            <label>Title (EN)</label>
            <input type="text" name="name" value="<?= htmlspecialchars($post['name'] ?? '') ?>" required>

            <label>Description (EN)</label>
            <textarea class="tinymce" name="description"><?= htmlspecialchars($post['description'] ?? '') ?></textarea>

            <label>Meta Description (EN)</label>
            <textarea name="meta_desc" rows="6"><?= htmlspecialchars($post['meta_desc'] ?? '') ?></textarea>

            <label>Meta Keywords (EN)</label>
            <input type="text" name="meta_keyword" value="<?= htmlspecialchars($post['meta_keyword'] ?? '') ?>">

            <hr>

            <!-- Bangla -->
            <h3>Bangla (BN)</h3>
            <label>Title (BN)</label>
            <input type="text" name="name_bn" value="<?= htmlspecialchars($post['name_bn'] ?? '') ?>">

            <label>Description (BN)</label>
            <textarea class="tinymce" name="description_bn"><?= htmlspecialchars($post['description_bn'] ?? '') ?></textarea>

            <label>Meta Description (BN)</label>
            <textarea name="meta_desc_bn" rows="6"><?= htmlspecialchars($post['meta_desc_bn'] ?? '') ?></textarea>

            <label>Meta Keywords (BN)</label>
            <input type="text" name="meta_keyword_bn" value="<?= htmlspecialchars($post['meta_keyword_bn'] ?? '') ?>">

            <!-- Image -->
            <label>Image</label>
            <input type="file" name="image">

            <button type="submit" name="save" class="btn">Save Post</button>
            <button type="submit" name="translate_bn" class="btn">Translate to Bangla</button>
        </form>
    </div>

    <script>
        const example_image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.withCredentials = true;
            xhr.open('POST', 'https://link123dzo.net/upload_image');

            xhr.upload.onprogress = (e) => progress(e.loaded / e.total * 100);

            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) return reject('HTTP Error: ' + xhr.status);
                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (err) {
                    return reject('Invalid JSON: ' + xhr.responseText);
                }
                if (!json || typeof json.url !== 'string') return reject('Invalid JSON structure: ' + xhr.responseText);
                resolve(json.url);
            };

            xhr.onerror = () => reject('Image upload failed.');
            const formData = new FormData();
            formData.append('image', blobInfo.blob(), blobInfo.filename());
            xhr.send(formData);
        });

        tinymce.init({
            selector: 'textarea.tinymce',
            height: 800,
            plugins: 'lists link image code table',
            toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
            images_upload_credentials: true,
            automatic_uploads: true,
            images_upload_handler: example_image_upload_handler,
            images_reuse_filename: true,
            image_title: true,
            file_picker_types: 'image',
            license_key: 'gpl',
        });
    </script>
</body>

</html>