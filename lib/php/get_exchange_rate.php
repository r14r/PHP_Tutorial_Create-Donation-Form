<?php

// Replace with your Fixer.io API Key
$apiKey = '3ff60558007b9fb625bb27be6051adc1';

// Default currency to USD
$baseCurrency = 'USD';

if (isset($_GET['currency'])) {
    $targetCurrency = $_GET['currency'];

    // API URL for Fixer.io
    $url = "http://data.fixer.io/api/latest?access_key=$apiKey&symbols=$baseCurrency,$targetCurrency";

    // Fetch exchange rates from Fixer.io
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    // Check if the response is valid
    if ($data['success']) {
        // Calculate the exchange rate
        $rate = $data['rates'][$baseCurrency] / $data['rates'][$targetCurrency];
        echo json_encode(['success' => true, 'rate' => $rate]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch exchange rates']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Currency not provided']);
}
?>
