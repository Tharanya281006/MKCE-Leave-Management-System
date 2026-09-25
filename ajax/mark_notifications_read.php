<?php
session_start();
require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../notification_helper.php";
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "student" || !isset($_SESSION["reg_no"])) {
    echo json_encode(["status"=>false,"message"=>"Unauthorized access."]);
    exit;
}

ensureNotificationTable($conn);

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE reg_no = ?");
$stmt->bind_param("s", $_SESSION["reg_no"]);
$ok = $stmt->execute();

echo json_encode([
    "status" => $ok,
    "message" => $ok ? "Notifications marked as read." : "Unable to update notifications."
]);

$stmt->close();
$conn->close();
?>
