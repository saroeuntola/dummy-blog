<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./services/auth.php');
$auth = new Auth();
// Initialize error message
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
    <title>Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f0f1e;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(138, 43, 226, 0.3) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(75, 0, 130, 0.3) 0%, transparent 20%),
                radial-gradient(circle at 50% 50%, rgba(30, 0, 60, 0.4) 0%, transparent 30%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: rgba(30, 30, 50, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow:
                0 20px 40px rgba(0, 0, 0, 0.4),
                0 0 60px rgba(138, 43, 226, 0.15);
            border: 1px solid rgba(138, 43, 226, 0.2);
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-8px);
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(90deg, #9d4edd, #c77dff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
            opacity: 0.85;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="text"]::placeholder,
        input[type="password"]::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #9d4edd;
            background: rgba(157, 78, 221, 0.15);
            box-shadow: 0 0 0 3px rgba(157, 78, 221, 0.25);
        }

        .remember-me {
            display: flex;
            align-items: center;
            font-size: 14px;
            opacity: 0.85;
            cursor: pointer;
            user-select: none;
        }

        .checkbox {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 6px;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .checkbox:checked {
            background: #9d4edd;
            border-color: #9d4edd;
        }

        .checkbox:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #9d4edd, #c77dff);
            color: white;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(157, 78, 221, 0.4);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(157, 78, 221, 0.5);
            background: linear-gradient(90deg, #b156ff, #d98aff);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 25px;
            }

            .header h1 {
                font-size: 32px;
            }
        }
    </style>
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

                <!-- Example error message (remove or control with PHP) -->
                <!--
        
                -->

                <div class="error-message" style="<?= empty($error_message) ? 'display:none;' : '' ?>">
                  <?= htmlspecialchars($error_message); ?>
                </div>
            </form>
        </div>
    </div>

</body>

</html>