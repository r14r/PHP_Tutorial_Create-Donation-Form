<?php

$ip = $_SERVER['REMOTE_ADDR'];
$maxAttempts = 5;
$lockoutTime = 900; // 15 minutes

//
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookies
ini_set('session.cookie_secure', 1);   // Ensure cookies are sent over HTTPS only
ini_set('session.use_strict_mode', 1); // Strict session mode

// Initialize attempt counter in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

foreach ($_SESSION['login_attempts'] as $ipAddress => $attempts) {
    $_SESSION['login_attempts'][$ipAddress] = array_filter($attempts, function($timestamp) use ($lockoutTime) {
        return ($timestamp + $lockoutTime) > time();
    });
    if (empty($_SESSION['login_attempts'][$ipAddress])) {
        unset($_SESSION['login_attempts'][$ipAddress]);
    }
}

if (isset($_SESSION['login_attempts'][$ip]) && count($_SESSION['login_attempts'][$ip]) >= $maxAttempts) {
    die("Too many login attempts. Please try again later.");
}

// After failed login attempt, record it
// Inside the authentication failure block:
$_SESSION['login_attempts'][$ip][] = time();


session_start();

// Define the directory and path to the SQLite database file
define('DB_DIR', __DIR__ . '/database');
define('DB_FILE', DB_DIR . '/donations.db');

/**
 * Initialize the SQLite database.
 *
 * @return PDO The PDO instance connected to the SQLite database.
 * @throws Exception If the database directory cannot be created.
 */
function initializeDatabase()
{
    if (!is_dir(DB_DIR)) {
        if (!mkdir(DB_DIR, 0755, true)) {
            throw new Exception("Failed to create database directory.");
        }
    }

    $dbExists = file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // If the database file didn't exist, set up the donations and users tables
    if (!$dbExists) {
        // Create donations table
        $createDonationsTable = "
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                bank_info TEXT NOT NULL,
                amount REAL NOT NULL CHECK(amount > 0),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($createDonationsTable);

        // Create users table
        $createUsersTable = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($createUsersTable);
    }

    return $pdo;
}

try {
    $pdo = initializeDatabase();
} catch (Exception $e) {
    die("Initialization failed: " . htmlspecialchars($e->getMessage()));
}

// If user is already logged in, redirect to admin.php
if (isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit;
}

// Initialize variables
$username = $password = "";
$errors = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and sanitize input data
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate inputs
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    // If no errors, proceed to authenticate
    if (empty($errors)) {
        try {
            // Fetch user from the database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Authentication successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Redirect to admin dashboard
                header("Location: admin.php");
                exit;
            } else {
                // Authentication failed
                $errors['general'] = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            die("Database error: " . htmlspecialchars($e->getMessage()));
        }
    }

    // Store errors and old input in session
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = [
        'username' => htmlspecialchars($username),
    ];

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve errors and old input from session
$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];

// Clear messages from session
unset($_SESSION['errors'], $_SESSION['old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <!-- Bootstrap CSS (optional for styling) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom styles for the form */
        .container {
            max-width: 400px;
            margin-top: 100px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Admin Login</h2>

        <!-- Display General Errors -->
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" novalidate>
            <!-- Username Field -->
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input 
                    type="text" 
                    class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                    id="username" 
                    name="username" 
                    value="<?= $old['username'] ?? '' ?>" 
                    required
                >
                <?php if (isset($errors['username'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['username']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Password Field -->
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                    id="password" 
                    name="password" 
                    required
                >
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['password']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>

    <!-- Bootstrap JS (optional for interactivity) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for Bootstrap form validation
        (function () {
            'use strict'

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('form')

            // Loop over them and prevent submission if invalid
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
