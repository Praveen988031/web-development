<?php
// ajax_get_doctors.php — returns doctors for a given department as JSON
// No HTML output ever — this file ONLY returns JSON.

session_start();

// Always send JSON header first, even for errors
header('Content-Type: application/json');

// ---- Access control ----
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php'; // defines $pdo (PDO connection)

// ---- Validate input ----
if (!isset($_GET['department_id']) || !ctype_digit($_GET['department_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid department_id']);
    exit;
}

$department_id = (int) $_GET['department_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, name
        FROM doctors
        WHERE dept_id = ? AND available = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$department_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($doctors);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log('ajax_get_doctors.php DB error: ' . $e->getMessage());
    exit;
}