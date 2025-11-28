<?php
header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Upload folder
$uploadDir = "uploads/";

// Ensure folder exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['image']['name']));
    $targetFile = $uploadDir . uniqid() . "_" . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        // Return URL relative to web root
        $url = "uploads/" . basename($targetFile);
        echo json_encode(['success' => true, 'url' => $url]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Upload failed']);
