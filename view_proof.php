<?php
session_start();

$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!in_array($role, ['staff', 'hod'], true)) {
    http_response_code(403);
    exit('Unauthorized access.');
}

$proof = basename(trim((string)($_GET['file'] ?? '')));
if ($proof === '' || $proof !== basename($proof)) {
    http_response_code(400);
    exit('Invalid proof file.');
}

$path = __DIR__ . '/uploads/proofs/' . $proof;
if (!is_file($path)) {
    http_response_code(404);
    exit('Proof file not found.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $proof) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
