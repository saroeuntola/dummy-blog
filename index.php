<?php
require './services/checkroles.php';
require './services/db.php';
$pdo = dbConn();
protectRoute([1, 3]);
// Read filter from URL
$statusFilter = $_GET['status'] ?? 'all';

$query = "SELECT * FROM posts_staging";
$params = [];

if ($statusFilter === 'draft') {
    $query .= " WHERE status='draft'";
} elseif ($statusFilter === 'approved') {
    $query .= " WHERE status='approved'";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Staging - Posts Grid</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .tab {
            padding: 8px 16px;
            border-radius: 6px;
            background: #f0f0f0;
            text-decoration: none;
            font-weight: bold;
            color: #333;
            transition: 0.2s;
        }

        .tab:hover {
            background: #ddd;
        }

        .tab.active {
            background: #3b82f6;
            color: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;

        }
        .header .btn {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.2s;
        }
    </style>
</head>

<body>
    <header class="header">
        <div>
        <h1>Staging — Cricket News Posts </h1>
        <p>Filter by status, review, edit, and push to public site.</p>

        </div>

        <div>
            <p>
               Logged in as: <strong> <?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
            </p>
            <a class="btn" href="logout.php">Logout</a>
        </div>
     
       
    </header>

    <!-- FILTER TABS -->
    <nav class="tabs">
        <a href="index.php?status=all" class="tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
        <a href="index.php?status=draft" class="tab <?= $statusFilter === 'draft' ? 'active' : '' ?>">Draft</a>
        <a href="index.php?status=approved" class="tab <?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
    </nav>

    <main class="grid">
        <?php if (empty($posts)): ?>
            <p>No posts found for this filter.</p>
            <?php else: foreach ($posts as $post): ?>
                <article class="card">
                    <div class="card-image">
                        <?php if (!empty($post['image'])): ?>
                            <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['name'] ?? '') ?>">
                        <?php else: ?>
                            <div class="placeholder">No image</div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h2><?= htmlspecialchars(html_entity_decode($post['name'] ?? '')) ?></h2>
                        <p class="meta">
                            Status: <strong><?= htmlspecialchars($post['status']) ?></strong>
                        </p>
                        <!-- <p class="excerpt">
                            <?= htmlspecialchars(
                                mb_strimwidth(
                                    html_entity_decode(strip_tags($post['description'] ?? '')),
                                    0,
                                    180,
                                    '...'
                                )
                            ) ?>
                        </p> -->


                        <div class="actions">
                            <a class="btn" href="view.php?id=<?= $post['id'] ?>">View Details</a>

                            <?php if ($post['status'] !== 'approved'): ?>
                                <a class="btn btn-primary" href="push.php?id=<?= $post['id'] ?>" onclick="return confirm('Push this post to live site?')">Push to Public</a>
                            <?php else: ?>
                                <span class="btn-disabled">Approved</span>
                            <?php endif; ?>
                        </div>

                    </div>
                </article>
        <?php endforeach;
        endif; ?>
    </main>
</body>

</html>