<?php
// api returns $_SESSION['latitude'] and $_SESSION['longitude']
session_start();
header('Content-Type: application/json');
require 'db.php';
if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$latitude = isset($_SESSION['latitude']) ? (float) $_SESSION['latitude'] : null;
$longitude = isset($_SESSION['longitude']) ? (float) $_SESSION['longitude'] : null;
// return
echo json_encode(['latitude' => $latitude, 'longitude' => $longitude]);
?>