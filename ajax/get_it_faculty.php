<?php
session_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../db/connection.php';

header('Content-Type: application/json; charset=UTF-8');

$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($role, ['staff', 'hod'], true)) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$department = trim((string)($_SESSION['department'] ?? ''));
$stmt = $conn->prepare("SELECT staff_id, staff_name FROM staff_login WHERE role='staff' AND status='Active' AND department=? ORDER BY staff_name, staff_id");
$stmt->bind_param('s', $department);
$stmt->execute();
$result = $stmt->get_result();
$faculty = [];
while ($row = $result->fetch_assoc()) $faculty[] = $row;
$stmt->close();
echo json_encode(['status' => true, 'faculty' => $faculty], JSON_UNESCAPED_UNICODE);
