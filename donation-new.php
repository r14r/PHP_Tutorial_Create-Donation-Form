<?php
// create_admin.php

/**
 * Script to create an initial admin user.
 * Run this script once and then delete it for security.
 */

define('DB_DIR', __DIR__ . '/database');
define('DB_FILE', DB_DIR . '/donations.db');

// Function to initialize the SQLite database.
function initializeDatabase()
{
    // Check if the database directory exists; if not, create it
    if (!is_dir(DB_DIR)) {
        if (!mkdir(DB_DIR, 0755, true)) {
            throw new Exception("Failed to create database directory.");
        }
    }

    // Check if the database file exists
    $dbExists = file_exists(DB_FILE);

    // Create (if not exists) and connect to the SQLite database
    $pdo = new PDO('sqlite:' . DB_FILE);
    // Set error mode to exceptions
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

// Check if admin user already exists
$adminUsername = 'admin'; // Change as needed
$adminPassword = 'admin123'; // Change to a strong password

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $adminUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Admin user already exists. Username: {$adminUsername}\n";
    } else {
        // Hash the password
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

        // Insert the admin user
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, password)
            VALUES (:username, :password)
        ");
        $insertStmt->execute([
            ':username' => $adminUsername,
            ':password' => $hashedPassword,
        ]);

        echo "Admin user created successfully.\n";
        echo "Username: {$adminUsername}\n";
        echo "Password: {$adminPassword}\n";
    }
} catch (PDOException $e) {
    die("Error creating admin user: " . htmlspecialchars($e->getMessage()));
}
?>
