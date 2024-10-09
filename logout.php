<?php
//
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookies
ini_set('session.cookie_secure', 1);   // Ensure cookies are sent over HTTPS only
ini_set('session.use_strict_mode', 1); // Strict session mode

session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit;
