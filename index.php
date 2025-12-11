<?php
require './services/checkroles.php';
require './services/db.php';
$pdo = dbConn();
protectRoute([1, 3]);

// Read filter from URL
$statusFilter = $_GET['status'] ?? 'all';

// Pagination setup
$limit = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT * FROM posts_staging";
$countQuery = "SELECT COUNT(*) FROM posts_staging";
$where = "";

// Filter conditions
if ($statusFilter === 'draft') {
    $where = " WHERE status='draft'";
} elseif ($statusFilter === 'approved') {
    $where = " WHERE status='approved'";
}

$query .= $where . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
$countQuery .= $where;

// Fetch total posts count
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute();
$totalPosts = $stmtCount->fetchColumn();
$totalPages = ceil($totalPosts / $limit);

// Fetch posts
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();
?>

<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staging - Posts Grid</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="./css/home.css"">
</head>

<body>
<header class=" header">
    <div>
        <h1>Staging — Cricket News Posts</h1>
    </div>

    <div>
        <p>
            Logged in as:
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
        </p>
        <a class="btn" href="logout.php">Logout</a>


    </div>
    </header>

    <!-- DELETE ALL APPROVED POSTS BUTTON -->
    <a class="btn btn-danger" style="margin-bottom: 20px;"
        href="delete-all.php?status=approved"
        onclick="return confirm('Are you SURE you want to DELETE ALL APPROVED POSTS? This cannot be undone.');">
        Delete All Approved Posts
    </a>

    <a class="tab" style="margin-bottom: 20px;"
        href="create.php">
        + Add Post
    </a>
    <!-- FILTER TABS -->
    <nav class="tabs">

        <a href="index.php?status=all" class="tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
        <a href="index.php?status=draft" class="tab <?= $statusFilter === 'draft' ? 'active' : '' ?>">Draft</a>
        <a href="index.php?status=approved" class="tab <?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
    </nav>

    <main class="grid">
        <?php if (empty($posts)) : ?>
            <p>No posts found for this filter.</p>

            <?php else :
            foreach ($posts as $post) : ?>
                <article class="card">
                    <div class="card-image">
                        <?php if (!empty($post['image'])) : ?>
                            <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['name'] ?? '') ?>">
                        <?php else : ?>
                            <div class="placeholder">No image</div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h2><?= htmlspecialchars(html_entity_decode($post['name'] ?? '')) ?></h2>

                        <p class="meta">
                            Status: <strong><?= htmlspecialchars($post['status']) ?></strong>
                        </p>

                        <div class="actions">
                            <a class="btn" href="view.php?id=<?= $post['id'] ?>">View Details</a>

                            <?php if ($post['status'] !== 'approved') : ?>
                                <a class="btn btn-primary"
                                    href="push.php?id=<?= $post['id'] ?>"
                                    onclick="return confirm('Push this post to live site?')">Push to Public</a>
                            <?php else : ?>
                                <span class="btn-disabled">Approved</span>
                            <?php endif; ?>

                            <!-- DELETE BUTTON -->
                            <a class="btn-danger"
                                href="delete.php?id=<?= $post['id'] ?>"
                                onclick="return confirm('Are you sure you want to DELETE this post? This action cannot be undone.');">
                                Delete
                            </a>
                        </div>


                    </div>
                </article>
        <?php endforeach;
        endif; ?>
    </main>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = ($i == $page) ? "active" : "";
                echo "<a class='$active' href='index.php?status=$statusFilter&page=$i'>$i</a>";
            }
            ?>
        </div>
    <?php endif; ?>

    </body>

</html>