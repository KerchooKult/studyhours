<?php
ini_set('display_errors', 0); // Don't show errors in response
ini_set('log_errors', 1);     // Log them to a file
error_reporting(E_ALL);       // Log all types of errors
session_set_cookie_params([
    'lifetime' => 102489, // Cookie expiration time in seconds
    'path' => '/',      // Path where the cookie is valid
    'secure' => true,  // Set to true if using HTTPS
    'httponly' => true, // Prevent JavaScript access to the cookie
    'samesite' => 'None' // Set to 'None' for cross-site cookies
]);

session_start(); 
error_log('Session save path: ' . ini_get('session.save_path'));
error_log('Session ID: ' . session_id());

// Set headers for JSON API
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Content-Type: application/json');
$allowed_origin = 'chrome-extension://cpicngjcoodhlaiiebdilocdbpjjimjo';

if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Get request URI path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri_parts = explode('/', trim($request_uri, '/'));

// Find and slice after api.php
$api_index = array_search('api.php', $uri_parts);
if ($api_index !== false) {
    $uri_parts = array_slice($uri_parts, $api_index + 1);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid API request']);
    exit;
}

// Get the endpoint
$endpoint = array_shift($uri_parts);
if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'No endpoint specified']);
    exit;
}

// Get method and request body (if applicable)
$method = $_SERVER['REQUEST_METHOD'];
$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $raw_input = file_get_contents('php://input');
    $body = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON in request body']);
        exit;
    }
}

// Parse parameters from URI segments (e.g., /student/700746673 → ['student_id' => 700746673])
$params = [];
if (!empty($uri_parts)) {
    if ($endpoint === 'student' && isset($uri_parts[0])) {
        $params['student_id'] = $uri_parts[0];
    }
    if ($endpoint === 'login' && isset($uri_parts[0])) {
        $params['student_id'] = $uri_parts[0];
    }
}

// Sanitize endpoint
$sanitized_endpoint = preg_replace('/[^a-zA-Z0-9_-]/', '', $endpoint);
$endpoint_file = __DIR__ . '/endpoints/' . $sanitized_endpoint . '.php';

if (!file_exists($endpoint_file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

$handler = require $endpoint_file;

if (is_callable($handler)) {
    $response = $handler($method, $params, $body);
    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Endpoint handler not callable']);
}
?>
