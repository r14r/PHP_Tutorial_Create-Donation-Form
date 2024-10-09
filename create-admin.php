<?php
// create_admin.php

/**
 * Script to create an initial admin user.
 * This script checks for the existence of required tables and creates them if they do not exist.
 * It then checks if an admin user exists and creates one if necessary.
 * 
 * **Important**: Run this script once to set up the admin user and then delete it for security purposes.
 */

// Define the directory and path to the SQLite database file
define('DB_DIR', __DIR__ . '/database');
define('DB_FILE', DB_DIR . '/donations.db');

/**
 * Initialize the SQLite database.
 * Creates the database directory and required tables if they do not exist.
 *
 * @return PDO The PDO instance connected to the SQLite database.
 * @throws Exception If the database directory cannot be created or tables cannot be created.
 */
function initializeDatabase()
{
    // Check if the database directory exists; if not, create it
    if (!is_dir(DB_DIR)) {
        if (!mkdir(DB_DIR, 0755, true)) {
            throw new Exception("Failed to create database directory at " . DB_DIR);
        }
        echo "Database directory created at " . DB_DIR . "\n";
    } else {
        echo "Database directory already exists at " . DB_DIR . "\n";
    }

    // Connect to the SQLite database
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to SQLite database at " . DB_FILE . "\n";
    } catch (PDOException $e) {
        throw new Exception("Failed to connect to SQLite database: " . $e->getMessage());
    }

    // Array of required tables with their creation SQL
    $requiredTables = [
        'donations' => "
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                bank_info TEXT NOT NULL,
                amount REAL NOT NULL CHECK(amount > 0),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
    ];

    // Check and create each required table
    foreach ($requiredTables as $tableName => $createSQL) {
        if (!doesTableExist($pdo, $tableName)) {
            try {
                $pdo->exec($createSQL);
                echo "Table '{$tableName}' created successfully.\n";
            } catch (PDOException $e) {
                throw new Exception("Failed to create table '{$tableName}': " . $e->getMessage());
            }
        } else {
            echo "Table '{$tableName}' already exists.\n";
        }
    }

    return $pdo;
}

/**
 * Check if a table exists in the SQLite database.
 *
 * @param PDO $pdo The PDO instance connected to the SQLite database.
 * @param string $tableName The name of the table to check.
 * @return bool True if the table exists, false otherwise.
 */
function doesTableExist(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table_name");
        $stmt->execute([':table_name' => $tableName]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Failed to check existence of table '{$tableName}': " . $e->getMessage());
    }
}

/**
 * Create an admin user if none exists.
 *
 * @param PDO $pdo The PDO instance connected to the SQLite database.
 * @param string $username The desired username for the admin.
 * @param string $password The desired password for the admin.
 * @return void
 * @throws Exception If the admin user cannot be created.
 */
function createAdminUser(PDO $pdo, string $username, string $password): void
{
    // Check if the admin user already exists
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Failed to query users table: " . $e->getMessage());
    }

    if ($user) {
        echo "Admin user '{$username}' already exists.\n";
        return;
    }

    // Hash the password securely
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        throw new Exception("Failed to hash the password.");
    }

    // Insert the new admin user into the users table
    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO users (username, password)
            VALUES (:username, :password)
        ");
        $insertStmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
        ]);
        echo "Admin user '{$username}' created successfully.\n";
    } catch (PDOException $e) {
        throw new Exception("Failed to insert admin user: " . $e->getMessage());
    }
}

// ----------------------------
// Execution Starts Here
// ----------------------------

echo "Initializing the database...\n";

try {
    $pdo = initializeDatabase();
} catch (Exception $e) {
    die("Initialization Error: " . $e->getMessage() . "\n");
}

// Define admin credentials
$adminUsername = 'admin';      // **Change this to a desired username**
$adminPassword = 'admin123';   // **Change this to a strong password**

// Create the admin user
echo "Creating admin user...\n";

try {
    createAdminUser($pdo, $adminUsername, $adminPassword);
} catch (Exception $e) {
    die("Admin Creation Error: " . $e->getMessage() . "\n");
}

echo "Admin setup completed successfully.\n";

// Optional: Prompt to delete the script for security
echo "For security reasons, please delete 'create_admin.php' after successful setup.\n";
