<?php
// API endpoint for Logout (logout.php)
require '../internal/db.php';
session_start();

return function($method, $params, $body) {
    if ($method === 'POST') {
        // Check if a session exists
        if (!isset($_SESSION['student_id'])) {
            http_response_code(400);
            return ['error' => 'No active session'];
        }

        // Destroy the session
        session_unset();
        session_destroy();

        // Respond with success
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    return ['error' => 'Unsupported method'];
};
?>