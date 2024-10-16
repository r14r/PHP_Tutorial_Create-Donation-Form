<?php
require 'vendor/autoload.php'; // Load Stripe SDK

\Stripe\Stripe::setApiKey('YOUR_STRIPE_SECRET_KEY');

// Read the POST request body
$input = file_get_contents("php://input");
$body = json_decode($input, true);

// Calculate the donation amount (amount in cents for Stripe)
$amount = isset($body['amount']) ? $body['amount'] * 100 : 100; // Amount is in USD cents

// Create a new Stripe Checkout Session
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Donation',
            ],
            'unit_amount' => $amount,
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => 'https://your-website.com/success.html',
    'cancel_url' => 'https://your-website.com/cancel.html',
]);

// Return session ID as JSON
echo json_encode($session->id);
?>
