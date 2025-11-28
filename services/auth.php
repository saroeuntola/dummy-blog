<?php
include('db-login.php');
class Auth
{
    public $db;

    public function __construct()
    {
         $this->db = DBConnect(); 
    }


    public function exists($field, $value)
    {
        $allowed = ['username', 'email', 'phone'];
        if (!in_array($field, $allowed)) return false;

        $stmt = $this->db->prepare("SELECT id FROM users WHERE $field = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? true : false;
    }
    // Register

    public function createAccUser($username, $email, $phone, $password, $role_id)
    {
        // Check if exists
        if (
            $this->exists('username', $username) ||
            $this->exists('email', $email) ||
            $this->exists('phone', $phone)
        ) {
            throw new PDOException("Email, username, or phone already exists", 23000);
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
        INSERT INTO users (username, email, phone, password, role_id) 
        VALUES (:username, :email, :phone, :password, :role_id)
    ");

        return $stmt->execute([
            'username' => $username,
            'email'    => $email,
            'phone'    => $phone,
            'password' => $hashed_password,
            'role_id'  => $role_id
        ]);
    }

    public function register($username, $email, $phone, $password, $role_id = 2)
    {
        // Check if exists
        if (
            $this->exists('username', $username) ||
            $this->exists('email', $email) ||
            $this->exists('phone', $phone)
        ) {
            throw new PDOException("Email, username, or phone already exists", 23000);
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("INSERT INTO users (username,email,phone,password,role_id) VALUES (:username,:email,:phone,:password,:role_id)");
        return $stmt->execute([
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $hashed_password,
            'role_id' => $role_id
        ]);
    }

    public function is_active($user_id)
    {
        global $conn; // or your DB object

        $stmt = $conn->prepare("SELECT active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && $row['active'] == 1;
    }

    // Login
    public function login($username, $password, $remember = false)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $loginTime = new DateTime('now', new DateTimeZone('Asia/Phnom_Penh'));

        $results = dbSelect(
            'users',
            'id, username, password, role_id, status',
            "username = " . $this->db->quote($username) . " LIMIT 1"
        );

        // Helper to log login attempt
        $logAttempt = function ($status) use ($username, $ip, $userAgent, $loginTime) {
            dbInsert('login_logs', [
                'username' => $username,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'status' => $status,
                'login_time' => $loginTime->format('Y-m-d H:i:s')
            ]);
        };

        // User not found
        if (!$results || count($results) == 0) {
            $logAttempt('failure');
            return false;
        }

        $user = $results[0];

        // User inactive
        if ($user['status'] == 0) {
            $logAttempt('failure');
            return "inactive";
        }

        // Password correct
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (86400 * 30);
                dbInsert('user_tokens', [
                    'user_id' => $user['id'],
                    'token' => hash('sha256', $token),
                    'expires_at' => date('Y-m-d H:i:s', $expiry)
                ]);
                setcookie("remember_token", $token, $expiry, "/", "", true, true);
            }

            $logAttempt('success');
            return true;
        }

        // Wrong password
        $logAttempt('failure');
        return false;
    }

    public function getLoginLogs($limit = 50)
    {
        $stmt = $this->db->prepare("SELECT * FROM login_logs ORDER BY login_time DESC LIMIT :limit");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if user is logged in
    public function is_logged_in()
    {
        if (isset($_SESSION['user_id'])) {
            return true;
        } elseif (isset($_COOKIE['remember_token'])) {
            $token = hash('sha256', $_COOKIE['remember_token']);
            $results = dbSelect('user_tokens', 'user_id', "token = " . $this->db->quote($token) . " AND expires_at > NOW() LIMIT 1");

            if ($results && count($results) > 0) {
                $user = $results[0];
                $_SESSION['user_id'] = $user['user_id'];

                // Fetch role_id
                $roleResults = dbSelect('users', 'role_id', "id = " . $this->db->quote($user['user_id']));
                if ($roleResults && count($roleResults) > 0) {
                    $_SESSION['role_id'] = $roleResults[0]['role_id'];
                }

                return true;
            }
        }
        return false;
    }
    // Logout 
    public function logout()
    {
        session_destroy();
        if (isset($_COOKIE['remember_token'])) {
            $token = hash('sha256', $_COOKIE['remember_token']);
            dbDelete('user_tokens', "token = " . $this->db->quote($token));
            setcookie("remember_token", "", time() - 3600, "/", "", true, true);
        }
    }

    // Check if the user has a specific role
    public function has_role($role_id)
    {
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $role_id;
    }

    // Check if user has any of the roles
    public function has_any_role($role_ids = [])
    {
        return isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], $role_ids);
    }
}
