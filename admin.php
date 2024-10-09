<?php
// admin.php

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

/**
 * Redirect to login.php if the user is not authenticated.
 *
 * @return void
 */
function ensureAuthenticated()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Ensure the user is authenticated
ensureAuthenticated();

// Initialize the database
try {
    $pdo = initializeDatabase();
} catch (Exception $e) {
    die("Initialization failed: " . htmlspecialchars($e->getMessage()));
}

// Fetch all donations from the database
try {
    $stmt = $pdo->query("SELECT * FROM donations ORDER BY created_at DESC");
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Failed to fetch donations: " . htmlspecialchars($e->getMessage()));
}

// Handle optional filtering or pagination here if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Donations</title>
    <!-- Bootstrap CSS (optional for styling) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom styles */
        .container {
            margin-top: 50px;
        }
        .logout-btn {
            float: right;
        }
        table {
            margin-top: 20px;
        }
        th, td {
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin Dashboard - Donations</h2>
        <a href="logout.php" class="btn btn-danger logout-btn">Logout</a>
        
        <?php if (empty($donations)): ?>
            <p class="mt-4">No donations found.</p>
        <?php else: ?>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Bank Information</th>
                        <th>Amount ($)</th>
                        <th>Description</th>
                        <th>Donated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td><?= htmlspecialchars($donation['id']) ?></td>
                            <td><?= htmlspecialchars($donation['name']) ?></td>
                            <td><?= htmlspecialchars($donation['bank_info']) ?></td>
                            <td><?= number_format($donation['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($donation['description']) ?></td>
                            <td><?= htmlspecialchars($donation['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS (optional for interactivity) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
