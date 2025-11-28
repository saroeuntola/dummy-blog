<?php
session_start();
include("auth.php");

function protectRoute($allowedRoles = [])
{
    $auth = new Auth();

    // Not logged in at all
    if (!$auth->is_logged_in()) {
        session_destroy();
        header("Location: /login");
        exit;
    }

    // Always check latest status from DB
    $userId = $_SESSION['user_id'];
    $results = dbSelect('users', 'status', "id = $userId LIMIT 1");

    if (!$results || $results[0]['status'] == 0) {
        // User is disabled while logged in → force logout
        session_unset();
        session_destroy();
        header("Location: /account-disable");
        exit;
    }

    // Role not allowed
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowedRoles)) {
        header("Location: /unauthorized");
        exit;
    }
}
