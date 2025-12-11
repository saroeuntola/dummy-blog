<?php
require './services/checkroles.php';
require './services/db.php';
protectRoute([1, 3]);

$pdo = dbConn();

$status = $_GET['status'] ?? null;

if ($status !== 'approved') {
    die("Invalid delete action.");
}

// Delete all approved posts
$stmt = $pdo->prepare("DELETE FROM posts_staging WHERE status='approved'");
$success = $stmt->execute();

if ($success) {
    echo "<script>
        alert('All approved posts deleted successfully.');
        window.location.href = 'index.php?status=approved';
    </script>";
} else {
    echo "<script>
        alert('Failed to delete approved posts.');
        window.location.href = 'index.php?status=approved';
    </script>";
}
