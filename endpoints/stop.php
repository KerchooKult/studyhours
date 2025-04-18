<?php
session_start(); // Start the session
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: chrome-extension://cpicngjcoodhlaiiebdilocdbpjjimjo");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}
require_once './internal/db.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = 'chrome-extension://cpicngjcoodhlaiiebdilocdbpjjimjo';
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

error_log("SESSION DATA: " . print_r($_SESSION, true));

return function($method, $body) {
    if ($method !== 'POST') {
        http_response_code(405);
        return ['error' => 'Method not allowed'];
    }

    error_log("Processing stop session request");
    error_log("Student ID from session: " . ($_SESSION['student_id'] ?? 'not set'));

    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        return ['error' => 'Not authenticated'];
    }

    if (!isset($_SESSION['start_time'])) {
        http_response_code(400);
        return ['error' => 'No active study session to stop'];
    }

    $now = time();
    $duration = ($now - $_SESSION['start_time']) / 60;
    $student_id = $_SESSION['student_id'];
    updateCurrentHours($student_id, $duration);

    unset($_SESSION['start_time']);

    return [
        'message' => 'Study session stopped successfully',
        'duration_seconds' => $duration,
    ];
};
