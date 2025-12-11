<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./services/auth.php');

$auth = new Auth();

$error_message = '';
 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $loginStatus = $auth->login($username, $password, $remember);

    if ($loginStatus === true) {
        $result = dbSelect('users', 'role_id', "username=" . $auth->db->quote($username));
        if ($result && count($result) > 0) {
            $user = $result[0];

            if ($user['role_id'] == 1 || $user['role_id'] == 3) {
                header('Location: ./');
                exit();
            } elseif ($user['role_id'] == 2) {
                header('Location: ./unauthorized');
                exit();
            }
        }
    } elseif ($loginStatus === "inactive") {
        $error_message = "Your account is disabled! Please Contact Admin";
    } else {
        $error_message = "Invalid username or password!";
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/login.css">
    <title>Login</title>
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="header">
                <h1>Login</h1>
            </div>

            <form action="login" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" class="checkbox">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="submit-btn">Sign In</button>

                <div class="error-message" style="<?= empty($error_message) ? 'display:none;' : '' ?>">
                  <?= htmlspecialchars($error_message); ?>
                </div>
            </form>
        </div>
    </div>

</body>

</html>