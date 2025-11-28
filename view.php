<?php
require './services/checkroles.php';
require './services/db.php';
$pdo = dbConn();
protectRoute([1, 3]);
$id = $_GET['id'] ?? null;
if (!$id) die("Missing ID");

$stmt = $pdo->prepare("SELECT * FROM posts_staging WHERE id=?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) die("Post not found");

// Status color
$statusColor = ($post['status'] === 'approved') ? '#27ae60' : '#e67e22';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Post #<?= $post['id'] ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 20px;
        }

        .content-html table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .content-html th,
        .content-html td {
            border: 1px solid #ccc;
            padding: 10px 12px;
            text-align: left;
        }

        .content-html th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .content-html tr:nth-child(even) {
            background-color: #fafafa;
        }

        .content-html tr:hover {
            background-color: #f1f7ff;
        }

        .content-html caption {
            caption-side: top;
            text-align: left;
            font-weight: bold;
            font-size: 16px;
            padding-bottom: 8px;
        }

        .content-html td img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        h1 {
            margin-bottom: 20px;
        }

        .view-box {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .view-row {
            margin-bottom: 15px;
        }

        .view-label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

        .view-content {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 6px;
            line-height: 1.6;
        }

        .content-html {

            background: #fff;
            padding: 16px;
            border-radius: 6px;
        }

        .content-html img {
            width: 100% !important;
        }

        img.preview {
            width: 100%;
            border-radius: 6px;
            display: block;
            margin-top: 10px;
        }



        /* Buttons */
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            background: #3498db;
            color: #fff;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #555 !important;
        }

        .btn-disabled {
            padding: 10px 16px;
            background: #ccc;
            color: #666;
            border-radius: 6px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 6px;
            background: #eee;
            font-weight: bold;
            transition: 0.2s;
        }

        .tab.active {
            background: #3498db;
            color: #fff;
        }

        .tab:hover {
            background: #ddd;
        }

        /* Responsive */
        @media(max-width:600px) {
            .view-box {
                padding: 15px;
            }

            img.preview {
                max-width: 100%;
            }
        }
    </style>
    <script>
        function openTab(lang) {
            document.getElementById('en').style.display = (lang === 'en') ? 'block' : 'none';
            document.getElementById('bn').style.display = (lang === 'bn') ? 'block' : 'none';
            document.getElementById('tab-en').classList.toggle('active', lang === 'en');
            document.getElementById('tab-bn').classList.toggle('active', lang === 'bn');
        }
        window.onload = () => {
            openTab('en');
        };
    </script>
</head>

<body>
    <div class="container">

        <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        <!-- ACTIONS -->
        <div class="actions">
            <a href="edit.php?id=<?= $post['id'] ?>" class="btn">Edit</a>

            <?php if ($post['status'] !== 'approved'): ?>
                <a href="push.php?id=<?= $post['id'] ?>" class="btn" onclick="return confirm('Push this post to live site?')">Push to Public</a>
            <?php else: ?>
                <span class="btn-disabled">Already Approved</span>
            <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars(html_entity_decode($post['name'])) ?></h1>

        <div class="view-box">

            <div class="view-row">
                <?php if ($post['image']): ?>
                    <img src="<?= $post['image'] ?>" class="preview">
                <?php else: ?>
                    <div class="view-content">No image available</div>
                <?php endif; ?>
            </div>

            <!-- Tabs for EN / BN -->
            <div class="tabs">
                <div class="tab" id="tab-en" onclick="openTab('en')">English</div>
                <div class="tab" id="tab-bn" onclick="openTab('bn')">Bangla</div>
            </div>

            <div id="en">
                <div class="view-row">
                    <div class="content-html"><?= $post['description'] ?></div>
                </div>

                <h3 class="">
                    SEO Meta Data (EN)
                </h3>
                <div class="view-row">
                    <span class="view-label"> Meta Description(EN)</span>
                    <div class="view-content"><?= htmlspecialchars($post['meta_desc'] ?? 'NULL') ?></div>
                </div>
                <div class="view-row">
                    <span class="view-label">Meta Keywords(EN)</span>
                    <div class="view-content"><?= htmlspecialchars($post['meta_keyword'] ?? 'NULL') ?></div>
                </div>
            </div>


            <!-- bn -->
            <div id="bn" style="display:none;">

                <div class="view-row">

                    <div class="content-html"><?= $post['description_bn'] ?? 'NULL' ?></div>
                </div>
                <h3 class="">
                    SEO Meta Data (BN)
                </h3>
                <div class="view-row">
                    <span class="view-label"> Meta Description(BN)</span>
                    <div class="view-content"><?= htmlspecialchars($post['meta_desc_bn'] ?? 'NULL') ?></div>
                </div>
                <div class="view-row">
                    <span class="view-label">Meta Keywords(BN)</span>
                    <div class="view-content"><?= htmlspecialchars($post['meta_keyword_bn'] ?? 'NULL') ?></div>
                </div>
            </div>


            <?php
            // Example status colors
            $statusColors = [
                'draft'    => '#f39c12', // orange
                'approved' => '#27ae60', // green
                'rejected' => '#c0392b',
            ];

            // Current post status
            $currentStatus = $post['status'] ?? 'draft';
            $statusColor = $statusColors[$currentStatus] ?? '#7f8c8d';

            // Handle status update
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
                $newStatus = $_POST['status'] ?? $currentStatus;
                $stmt = $pdo->prepare("UPDATE posts_staging SET status=? WHERE id=?");
                $stmt->execute([$newStatus, $post['id']]);
                $currentStatus = $newStatus;
                $statusColor = $statusColors[$currentStatus] ?? '#7f8c8d';
                echo "<div style='color:green; margin-bottom:10px;'>Status updated to: $currentStatus</div>";
            }
            ?>

            <div class="view-row">
                <span class="view-label">Status</span>
                <div class="view-content">
                    <form method="post" style="display:inline-block;">
                        <select name="status" style="padding:4px 10px; border-radius:4px; border:1px solid #ccc; background:<?= $statusColor ?>; color:#fff;">
                            <?php foreach ($statusColors as $status => $color): ?>
                                <option value="<?= $status ?>" <?= ($status === $currentStatus ? 'selected' : '') ?>>
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status" style="padding:4px 10px; border:none; border-radius:4px; background:#2980b9; color:#fff; cursor:pointer;">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>