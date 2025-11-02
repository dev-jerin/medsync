<?php
/**
 * Firebase Configuration Helper
 * 
 * This file provides Firebase configuration from environment variables
 * Usage: require_once '../auth/firebase_helper.php'; // From sibling directories
 */

// Prevent direct access to this file
if (!defined('CONFIG_LOADED')) {
    http_response_code(403);
    die('Direct access to this file is not allowed.');
}

// Ensure environment variables are loaded
if (!isset($_ENV['FIREBASE_API_KEY'])) {
    throw new Exception('Environment variables not loaded. Make sure config.php is included first.');
}

$firebaseConfig = [
    "apiKey" => $_ENV['FIREBASE_API_KEY'],
    "authDomain" => $_ENV['FIREBASE_AUTH_DOMAIN'],
    "projectId" => $_ENV['FIREBASE_PROJECT_ID'],
    "storageBucket" => $_ENV['FIREBASE_STORAGE_BUCKET'],
    "messagingSenderId" => $_ENV['FIREBASE_MESSAGING_SENDER_ID'],
    "appId" => $_ENV['FIREBASE_APP_ID'],
];
?>
