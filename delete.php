<?php
require './services/checkroles.php';
require './services/db.php';
protectRoute([1, 3]); // Only admin and editor

$pdo = dbConn();

$postId = $_GET['id'] ?? null;

if (!$postId) {
    die("Invalid post ID.");
}

// Delete query
$stmt = $pdo->prepare("DELETE FROM posts_staging WHERE id=?");
$success = $stmt->execute([$postId]);

if ($success) {
    echo "<script>
        alert('Post deleted successfully.');
        window.location.href = 'index.php';
    </script>";
} else {
    echo "<script>
        alert('Failed to delete post.');
        window.location.href = 'index.php';
    </script>";
}
