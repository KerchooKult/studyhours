<?php

return function($method, $params, $body) {
    if ($method !== 'GET') {
        http_response_code(405);
        return ['error' => 'Method not allowed'];
    }

    // Check if the student is logged in
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        return ['error' => 'Not authenticated'];
    }

    // Check if a study session has started
    if (!isset($_SESSION['start_time'])) {
        http_response_code(400);
        return ['error' => 'No active study session'];
    }

    $now = time();

    // Check if ping exists and is not older than 5 minutes (300 seconds)
    if (isset($_SESSION['ping']) && ($now - $_SESSION['ping']) > 300) {
        http_response_code(408); // Request Timeout
        return ['error' => 'Session expired due to inactivity'];
    }

    // Update the ping timestamp
    $_SESSION['ping'] = $now;

    return ['message' => 'Ping successful', 'timestamp' => $now];
};
