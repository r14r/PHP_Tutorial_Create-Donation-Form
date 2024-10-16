<?php
/**
 * Create donation page and store donations in sqlite database
 *
 * @author Ralph Göstenmeier
 * @version 1.0
 */


//
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookies
ini_set('session.cookie_secure', 1);   // Ensure cookies are sent over HTTPS only
ini_set('session.use_strict_mode', 1); // Strict session mode


// Start the session to store messages and old input
session_start();

// Define the directory and path to the SQLite database file
define('DB_DIR', __DIR__ . '/database');
define('DB_FILE', DB_DIR . '/donations.db');

/**
 * Initialize the SQLite database.
 * Creates the database directory and file if they do not exist.
 * Also creates the donations table if it does not exist.
 *
 * @return PDO The PDO instance connected to the SQLite database.
 * @throws Exception If the database directory cannot be created.
 */
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

    // If the database file didn't exist, set up the donations table
    if (!$dbExists) {
        $createTableQuery = "
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                bank_info TEXT NOT NULL,
                amount REAL NOT NULL CHECK(amount > 0),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($createTableQuery);

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
 * Validate donation data.
 *
 * @param array $data The donation data.
 * @return array An array of validation errors. Empty if no errors.
 */
function validateDonation($data)
{
    $errors = [];

    // Validate Name
    if (empty($data['name'])) {
        $errors['name'] = "Name is required.";
    } elseif (mb_strlen($data['name']) > 255) {
        $errors['name'] = "Name must not exceed 255 characters.";
    }

    // Validate Bank Information
    if (empty($data['bank_info'])) {
        $errors['bank_info'] = "Bank Information is required.";
    } elseif (mb_strlen($data['bank_info']) > 255) {
        $errors['bank_info'] = "Bank Information must not exceed 255 characters.";
    }

    // Validate Amount
    if (empty($data['amount'])) {
        $errors['amount'] = "Amount is required.";
    } elseif (!is_numeric($data['amount']) || floatval($data['amount']) <= 0) {
        $errors['amount'] = "Amount must be a positive number.";
    }

    // Validate Description (optional)
    if (!empty($data['description']) && mb_strlen($data['description']) > 1000) {
        $errors['description'] = "Description must not exceed 1000 characters.";
    }

    return $errors;
}

/**
 * Insert donation data into the database.
 *
 * @param PDO $pdo The PDO instance.
 * @param array $data The donation data.
 * @return void
 * @throws PDOException If the insertion fails.
 */
function insertDonation($pdo, $data)
{
    $insertQuery = "
        INSERT INTO donations (name, bank_info, amount, description)
        VALUES (:name, :bank_info, :amount, :description)
    ";
    $stmt = $pdo->prepare($insertQuery);
    $stmt->execute([
        ':name' => $data['name'],
        ':bank_info' => $data['bank_info'],
        ':amount' => floatval($data['amount']),
        ':description' => !empty($data['description']) ? $data['description'] : null,
    ]);
}

/**
 * Determine if the request is an API call based on Content-Type header.
 *
 * @return bool True if API call, false otherwise.
 */
function isApiRequest()
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    return stripos($contentType, 'application/json') !== false;
}

// Initialize the database
try {
    $pdo = initializeDatabase();
} catch (Exception $e) {
    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Server initialization failed.']);
        exit;
    } else {
        die("Initialization failed: " . htmlspecialchars($e->getMessage()));
    }
}

// Handle POST Requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isApiRequest()) {
        // Handle API POST Request
        header('Content-Type: application/json');

        // Get the raw POST data
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        // If JSON decoding fails
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Invalid JSON payload.']);
            exit;
        }

        // Validate the data
        $errors = validateDonation($data);

        if (!empty($errors)) {
            http_response_code(422); // Unprocessable Entity
            echo json_encode(['errors' => $errors]);
            exit;
        }

        // Insert the donation
        try {
            insertDonation($pdo, $data);
            http_response_code(201); // Created
            echo json_encode(['message' => 'Donation successful.']);
            exit;
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Failed to save donation.']);
            exit;
        }
    } else {
        // Handle Form POST Request

        // Retrieve and sanitize input data
        $name = trim($_POST['name'] ?? '');
        $bank_info = trim($_POST['bank_info'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $formData = [
            'name' => $name,
            'bank_info' => $bank_info,
            'amount' => $amount,
            'description' => $description,
        ];

        // Validate the data
        $errors = validateDonation($formData);

        if (empty($errors)) {
            // Insert the donation
            try {
                insertDonation($pdo, $formData);

                // Set a success message in session
                $_SESSION['success'] = "Thank you for your donation, " . htmlspecialchars($name) . "!";

                // Redirect to avoid form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                die("Failed to insert donation: " . htmlspecialchars($e->getMessage()));
            }
        } else {
            // Store errors in session to display after redirect
            $_SESSION['errors'] = $errors;
            // Store old input in session to repopulate the form
            $_SESSION['old'] = [
                'name' => htmlspecialchars($name),
                'bank_info' => htmlspecialchars($bank_info),
                'amount' => htmlspecialchars($amount),
                'description' => htmlspecialchars($description),
            ];
            // Redirect to display errors
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Handle GET Requests (Display the Form)
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Retrieve messages and old input from session
    $successMessage = $_SESSION['success'] ?? '';
    $errors = $_SESSION['errors'] ?? [];
    $old = $_SESSION['old'] ?? [];

    // Clear messages from session
    unset($_SESSION['success'], $_SESSION['errors'], $_SESSION['old']);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Donation Form</title>
    <!-- Bootstrap CSS (optional for styling) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom styles for the form */
        .container {
            max-width: 600px;
            margin-top: 50px;
        }

        .error {
            color: #dc3545;
            font-size: 0.875em;
        }
    </style>

    <script>
        async function convertCurrency() {
            const amount = document.getElementById('amount').value;
            const currency = document.getElementById('currency').value;

            if (!amount || amount <= 0) {
                document.getElementById('convertedAmount').innerHTML = "Please enter a valid amount.";
                return;
            }

            // Fetch the exchange rate using Fixer API
            console.log('get exchange rate: ');

            var data = {};

            try {
                const response = await fetch('get_exchange_rate.php?currency=' + currency);
                data = await response.json();
                console.log('get exchange rate: ', data);
            } catch (error) {
                console.log('get exchange rate: ERROR ', error);

            }


            if (data.success) {
                const rate = data.rate;
                const converted = amount * rate;

                document.getElementById('convertedAmount').innerHTML =
                    `Converted Amount in USD: ${converted.toFixed(2)} USD`;
            } else {
                document.getElementById('convertedAmount').innerHTML = "Failed to fetch exchange rate.";
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <h2 class="mb-4">Donation Form</h2>

        <!-- Display Success Message -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?= $successMessage ?>
            </div>
        <?php endif; ?>

        <!-- Display Errors (for Form Submissions) -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $field => $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Donation Form -->
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" novalidate>
            <!-- Name Field -->
            <div class="mb-3">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name"
                    name="name" value="<?= $old['name'] ?? '' ?>" required maxlength="255">
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['name']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bank Information Field -->
            <div class="mb-3">
                <label for="bank_info" class="form-label">Bank Information <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['bank_info']) ? 'is-invalid' : '' ?>"
                    id="bank_info" name="bank_info" value="<?= $old['bank_info'] ?? '' ?>" required maxlength="255">
                <?php if (isset($errors['bank_info'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['bank_info']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Amount Field -->
            <div class="mb-3">
                <label for="amount" class="form-label">Amount<span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01"
                    class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>" id="amount" name="amount"
                    value="<?= $old['amount'] ?? '' ?>" required oninput="convertCurrency()">
                <?php if (isset($errors['amount'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['amount']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Currency Field -->
            <div class="mb-3">
                <label for="currency" class="form-label">Currency<span class="text-danger">*</span></label>
                <select id="currency" name="currency" onchange="convertCurrency()">
                    <option value="USD">USD - United States Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - British Pound</option>
                    <!-- Add more currencies as needed -->
                </select>
            </div>

            <!-- Converted Amount Display -->
            <div     id="convertedAmount"></div><br>



            <div class="form-row">
    <!-- Amount Field -->
    <div class="form-group">
        <label for="amount" class="form-label">Amount<span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01"
               class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>" id="amount"
               name="amount" value="<?= $old['amount'] ?? '' ?>" required oninput="convertCurrency()">
        <?php if (isset($errors['amount'])): ?>
            <div class="invalid-feedback">
                <?= htmlspecialchars($errors['amount']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Currency Field -->
    <div class="form-group">
        <label for="currency" class="form-label">Currency<span class="text-danger">*</span></label>
        <select class="form-control <?= isset($errors['currency']) ? 'is-invalid' : '' ?>" id="currency"
                name="currency" onchange="convertCurrency()">
            <option value="USD">USD - United States Dollar</option>
            <option value="EUR">EUR - Euro</option>
            <option value="GBP">GBP - British Pound</option>
            <!-- Add more currencies as needed -->
        </select>
        <?php if (isset($errors['currency'])): ?>
            <div class="invalid-feedback">
                <?= htmlspecialchars($errors['currency']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>






            <!-- Description Field -->
            <div class="mb-3">
                <label for="description" class="form-label">Description (Optional)</label>
                <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description"
                    name="description" rows="3" maxlength="1000"><?= $old['description'] ?? '' ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['description']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary">Donate</button>
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