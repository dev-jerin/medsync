<?php
/**
 * Session Diagnostic Tool
 * Checks if you're logged in and shows session data
 */
require_once '../config.php';

header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'session_exists' => isset($_SESSION) && !empty($_SESSION),
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'name' => $_SESSION['name'] ?? null,
    'all_session_keys' => isset($_SESSION) ? array_keys($_SESSION) : [],
];

if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    $response['logged_in'] = true;
    $response['message'] = 'You are logged in as ' . ($_SESSION['role'] ?? 'unknown');
} else {
    $response['message'] = 'Not logged in or insufficient privileges';
}

echo json_encode($response, JSON_PRETTY_PRINT);
