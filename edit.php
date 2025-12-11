<?php

require './vendor/autoload.php';
require './services/checkroles.php';
require './services/db.php';
$pdo = dbConn();
protectRoute([1, 3]);

use Stichoza\GoogleTranslate\GoogleTranslate;

$pdo = dbConn();

$id = $_GET['id'] ?? null;
if (!$id) die("Missing ID");

// Fetch post
$stmt = $pdo->prepare("SELECT * FROM posts_staging WHERE id=?");
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) die("Post not found");

$message = '';

// ---------------------------
// Handle POST form
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
    $image = $post['image'];
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

        // Translate
        $name_bn = $tr->translate($name);
        $description_bn = $tr->translate($description);
        $meta_text_bn = $tr->translate($meta_text);
        $meta_desc_bn = $tr->translate($meta_desc);
        $meta_keyword_bn = $tr->translate($meta_keyword);

        $message = "Auto-translated to Bangla! You can still edit before saving.";

        // Preserve English values in $post so the form keeps user input
        $post['name'] = $name;
        $post['description'] = $description;
        $post['meta_text'] = $meta_text;
        $post['meta_desc'] = $meta_desc;
        $post['meta_keyword'] = $meta_keyword;
        $post['category_id'] = $category_id;

        // Set Bangla values for form
        $post['name_bn'] = $name_bn;
        $post['description_bn'] = $description_bn;
        $post['meta_text_bn'] = $meta_text_bn;
        $post['meta_desc_bn'] = $meta_desc_bn;
        $post['meta_keyword_bn'] = $meta_keyword_bn;
    } else {

        // ---------------------------
        // Save to Database
        // ---------------------------
        $stmt = $pdo->prepare("
            UPDATE posts_staging SET 
                name=?, description=?, meta_text=?, image=?,
                meta_desc=?, meta_keyword=?, category_id=?,
                
                name_bn=?, description_bn=?, meta_text_bn=?,
                meta_desc_bn=?, meta_keyword_bn=?
            WHERE id=?
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
            $meta_keyword_bn,

            $id
        ]);

        $message = "Post saved successfully!";
        header(
            'Location: view.php?id=' . $id
        );
        // Reload latest DB data
        $stmt = $pdo->prepare("SELECT * FROM posts_staging WHERE id=?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Post: <?= $post['name'] ?></title>

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

        .preview-img {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 6px;
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
        <a href="view.php?id=<?= $post['id'] ?>">&larr; Back to View</a>

        <h1>Edit Post: <?= $post['name'] ?></h1>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <!-- Category -->
            <label>Category</label>
            <select name="category_id">
                <option value="">-- Select Category --</option>
                <option value="3" <?= ($post['category_id'] == 3 ? 'selected' : '') ?>>Cricket News</option>
                <option value="2" <?= ($post['category_id'] == 2 ? 'selected' : '') ?>>Cricket Betting Guides</option>
                <option value="6" <?= ($post['category_id'] == 6 ? 'selected' : '') ?>> Match Preview </option>
            </select>

            <!-- English -->
            <h3>English Content</h3>

            <label>Title (EN)</label>
            <input type="text" name="name" value="<?= htmlspecialchars(html_entity_decode($post['name'] ?? '')) ?>" required>

            <label>Description (EN)</label>
            <textarea class="tinymce" name="description">  <?= str_replace('"', '', html_entity_decode($post['description'])) ?></textarea>



            <label>Meta Description (EN)</label>
            <textarea name="meta_desc" rows="6"><?= htmlspecialchars($post['meta_desc'] ?? '') ?></textarea>

            <label>Meta Keywords (EN)</label>
            <input type="text" name="meta_keyword" value="<?= htmlspecialchars($post['meta_keyword'] ?? '') ?>">

            <hr>

            <!-- Bangla -->
            <h3>Bangla (BN)</h3>

            <label>Title (BN)</label>
            <input type="text" name="name_bn" value="<?= htmlspecialchars(html_entity_decode($post['name_bn'] ?? '')) ?>">

            <label>Description (BN)</label>
            <textarea class="tinymce" name="description_bn">  <?= str_replace('"', '', html_entity_decode($post['description_bn'] ?? '')) ?></textarea>

            <label>Meta Description (BN)</label>
            <textarea name="meta_desc_bn" rows="6"><?= htmlspecialchars($post['meta_desc_bn'] ?? '') ?></textarea>

            <label>Meta Keywords (BN)</label>
            <input type="text" name="meta_keyword_bn" value="<?= htmlspecialchars($post['meta_keyword_bn'] ?? '') ?>">

            <!-- Image -->
            <label>Image</label>
            <?php if ($post['image']): ?>
                <img src="<?= $post['image'] ?>" class="preview-img">
            <?php endif; ?>
            <input type="file" name="image">

            <button type="submit" name="save" class="btn">Save Post</button>
            <button type="submit" name="translate_bn" class="btn">Translate to Bangla</button>
        </form>
    </div>


    <script>
        const example_image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.withCredentials = true; // send cookies if needed
            xhr.open('POST', 'https://link123dzo.net/upload_image.php'); // PHP endpoint

            xhr.upload.onprogress = (e) => {
                progress(e.loaded / e.total * 100);
                console.log(`Uploading: ${(e.loaded / e.total * 100).toFixed(2)}%`);
            };

            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(`HTTP Error: ${xhr.status}`);
                    return;
                }

                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (err) {
                    reject('Invalid JSON: ' + xhr.responseText);
                    return;
                }

                if (!json || typeof json.url !== 'string') {
                    reject('Invalid JSON structure: ' + xhr.responseText);
                    return;
                }

                console.log('Upload success:', json.url);
                resolve(json.url);
            };

            xhr.onerror = () => {
                reject('Image upload failed due to XHR transport error.');
            };

            const formData = new FormData();
            formData.append('image', blobInfo.blob(), blobInfo.filename());
            xhr.send(formData);
        });
        tinymce.init({
            selector: 'textarea.tinymce',
            height: 600,
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