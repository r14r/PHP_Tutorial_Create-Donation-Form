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

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .error {
            color: #dc3545;
            font-size: 0.875em;
        }
    </style>

    <script>
        async function convertCurrency() {
            const amount = document.getElementById('amount_value').value;
            const currency = document.getElementById('amount_currency').value;

            const amount_converted = document.getElementById('amount_converted');

            if (!amount || amount <= 0) {
                document.getElementById('convertedAmount').innerHTML = "Please enter a valid amount.";
                return;
            }

            // Fetch the exchange rate using Fixer API
            console.log('get exchange rate: ');

            var data = {};

            try {
                const response = await fetch('lib/php/get_exchange_rate.php?currency=' + currency);
                data = await response.json();
                console.log(`get exchange rate: amount=${amount}: ${data}`);
            } catch (error) {
                console.log('get exchange rate: ERROR ', error);

            }

            if (data.success) {
                const rate = data.rate;
                const converted = amount * rate;

                console.log(`get exchange rate: rate=${rate} amount=${amount} ==> ${converted}`);
                amount_converted.innerHTML =
                    `Converted Amount in USD: ${converted.toFixed(2)} USD`;
            } else {
                console.log('get exchange rate: ERROR: failed to fetch amont_converted element');
                amount_converted.innerHTML = "Failed to fetch exchange rate.";
            }
        }
    </script>
</head>

<body>
    <div class="container">
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



        <section id="login" class="p-4 flex flex-col justify-center min-h-screen max-w-md mx-auto">
            <div class="p-6 bg-sky-100 rounded">
                <div class="flex items-center justify-center font-black m-3 mb-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mr-3 text-red-600 animate-pulse"
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                            clip-rule="evenodd" />
                    </svg>
                    <h1 class="tracking-wide text-3xl text-gray-900">Buy Me a Laptop</h1>
                </div>
                <form id="login_form" action="api_login" method="POST" class="flex flex-col justify-center">
                    <div class="flex justify-between items-center mb-3">
                        <div class="inline-flex items-center self-start">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="h-8 w-8 mr-3 bg-gradient-to-r from-pink-600 to-red-600 shadow-lg rounded p-1.5 text-gray-100"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13 7H7v6h6V7z" />
                                <path fill-rule="evenodd"
                                    d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="font-bold text-gray-900">$5 / Core</span>
                        </div>
                        <div class="flex">
                            <button type="button" onclick="minus()" class="bg-yellow-600 p-1.5 font-bold rounded">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>

                            <input id="item_count" type="number" value="1" class="max-w-[100px] font-bold font-mono py-1.5 px-2 mx-1.5
            block border border-gray-300 rounded-md text-sm shadow-sm  placeholder-gray-400
            focus:outline-none
            focus:border-sky-500
            focus:ring-1
            focus:ring-sky-500
            focus:invalid:border-red-500  focus:invalid:ring-red-500">

                            <button type="button" onclick="plus()" class="bg-green-600 p-1.5 font-bold rounded">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <label class="text-sm font-medium">From</label>
                    <input class="mb-3 px-2 py-1.5
          mb-3 mt-1 block w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm shadow-sm placeholder-gray-400
          focus:outline-none
          focus:border-sky-500
          focus:ring-1
          focus:ring-sky-500
          focus:invalid:border-red-500 focus:invalid:ring-red-500" type="text" name="username" placeholder="wahyusa">
                    <label class="text-sm font-medium">Messages (optional)</label>
                    <textarea class="
          mb-3 mt-1 block w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm shadow-sm placeholder-gray-400
          focus:outline-none
          focus:border-sky-500
          focus:ring-1
          focus:ring-sky-500
          focus:invalid:border-red-500 focus:invalid:ring-red-500" name="messages"
                        placeholder="Write something"></textarea>
                    <button
                        class="px-4 py-1.5 rounded-md shadow-lg bg-gradient-to-r from-pink-600 to-red-600 font-medium text-gray-100 block transition duration-300"
                        type="submit">
                        <span id="login_process_state" class="hidden">Sending :)</span>
                        <span id="login_default_state">Donate<span id="subtotal"></span></span>
                    </button>
                </form>
            </div>
        </section>


        <section class="bg-white dark:bg-gray-900">
            <div class="py-8 px-4 mx-auto max-w-2xl lg:py-16">
                <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">Donate</h2>
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" novalidate>
                    <div class="grid gap-4 sm:grid-cols-2 sm:gap-6">
                        <!-- NAME -->
                        <div class="sm:col-span-2">
                            <label for="name"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Name</label>
                            <input type="text" name="name" id="name"
                                class="<?= isset($errors['name']) ? 'is-invalid' : '' ?> bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                placeholder="First and Last Name" value="<?= $old['name'] ?? '' ?>" required
                                maxlength="255">
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- BANK INFORMATION -->
                        <div class="sm:col-span-2">
                            <label for="bankinformation"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Name</label>
                            <input type="text" name="bankinformation" id="bankinformation"
                                class="<?= isset($errors['bankinformation']) ? 'is-invalid' : '' ?> bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                placeholder="Bank Information" value="<?= $old['bankinformation'] ?? '' ?>" required
                                maxlength="255">
                            <?php if (isset($errors['nabankinformationme'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['bankinformation']) ?>
                                </div>
                            <?php endif; ?>
                        </div>


                        <!-- BRAND
                        <div class="w-full">
                            <label for="brand" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Brand</label>
                            <input type="text" name="brand" id="brand"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                placeholder="Product brand" required>
                        </div>
                        -->

                        <!-- AMOUNT  -->
                        <div class="w-full">
                            <label for="amount_value"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Amount</label>

                            <input type="number" id="amount_value" name="amount_value" step="0.01" min="0.01"
                                placeholder="$100" required value="<?= $old['amount_value'] ?? '' ?>" required
                                oninput="convertCurrency()"
                                class="<?= isset($errors['amount_value']) ? 'is-invalid' : '' ?> bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">

                        </div>

                        <!-- AMOUNT CURRENTY -->
                        <div>
                            <label for="amount_currency"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Donation
                                Currency</label>

                            <select id="amount_currency" name="amount_currency" onchange="convertCurrency()"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                <option selected="">Select Currency</option>
                                <option value="USD">USD - United States Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>

                            </select>
                        </div>


                        <!-- Converted Amount Display -->
                        <div class="sm:col-span-2">
                            <label for="amount_converted"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Donation
                                Currency</label>
                            <div id="amount_converted"></div>
                        </div>

                        <!-- DESCRIPTION -->
                        <div class="sm:col-span-2">
                            <label for="description"
                                class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Description</label>
                            <textarea id="description" rows="8"
                                class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                placeholder="Your description here"></textarea>
                        </div>


                        <!-- ADD -->
                        <div class="sm:col-span-2">
                            <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 mt-4 sm:mt-6 text-sm font-medium text-center text-white bg-primary-700 rounded-lg focus:ring-4 focus:ring-primary-200 dark:focus:ring-primary-900 hover:bg-primary-800">
                                Donate
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

    </div>




    <script>
        document.getElementById("login_form").onsubmit = function () {
            event.preventDefault()
            // animation
            document.getElementById("login_process_state").classList.remove("hidden")
            document.getElementById("login_process_state").classList.add("animate-pulse")

            document.getElementById("login_default_state").classList.add("hidden")
        }

        let current_count = parseInt(document.getElementById("item_count").value)
        let subtotal = parseInt(5)

        function plus() {
            document.getElementById("item_count").value = ++current_count
            document.getElementById("subtotal").innerHTML = ` $${subtotal * document.getElementById("item_count").value}`

        }

        function minus() {
            if (current_count < 2) {
                current_count = 1
                document.getElementById("item_count").value = 1
                document.getElementById("subtotal").innerHTML = ` $${subtotal * document.getElementById("item_count").value}`
            } else {
                document.getElementById("item_count").value = --current_count
                document.getElementById("subtotal").innerHTML = ` $${subtotal * document.getElementById("item_count").value}`
            }
        }

    </script>
</body>

</html>