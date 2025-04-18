<?php
// API endpoint for updating student hours
session_start(); // Start the session to access session variables
require './internal/db.php';

return function($method, $params, $body) use ($pdo) {
    if ($method !== 'POST') {
        http_response_code(405);
        return ['error' => 'Method not allowed'];
    }

    // if studentid is not set in session, return error
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        return ['error' => 'Not authenticated'];
    }

    // Ensure required parameters exist
    if (!isset($body['id'], $body['newHours'], $body['type'])) {
        http_response_code(400);
        return ['error' => 'Missing required parameters'];
    }

    // Retrieve the data from the body
    $id = $body['id'];
    $newHours = $body['newHours'];
    $type = $body['type'];

    // Validate newHours to ensure it's numeric and non-negative
    if (!is_numeric($newHours) || $newHours < 0) {
        http_response_code(400);
        return ['error' => 'Invalid value for hours. Please provide a valid numeric value.'];
    }

    // Determine the column to update based on the "type" field
    if ($type === 'weekly_hours') {
        $column = 'weekly_hours';
    } elseif ($type === 'current_hours') {
        $column = 'current_hours';
    } else {
        http_response_code(400);
        return ['error' => 'Invalid type. Must be "weekly_hours" or "current_hours".'];
    }

    // Prepare and execute the SQL statement to update the hours
    try {
        $stmt = $pdo->prepare("UPDATE students SET $column = ? WHERE id = ?");
        $stmt->execute([$newHours, $id]);
        return ['message' => "Success: $column updated for student with ID $id."];
    } catch (Exception $e) {
        http_response_code(500);
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
};
