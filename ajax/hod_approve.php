<?php
session_start();
require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../notification_helper.php";
header("Content-Type: application/json");

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "hod") {
    echo json_encode(["status"=>false,"message"=>"Unauthorized access."]);
    exit;
}
if (!isset($_POST["leave_id"]) || empty($_POST["leave_id"])) {
    echo json_encode(["status"=>false,"message"=>"Leave ID missing."]);
    exit;
}

$leaveId = intval($_POST["leave_id"]);
$remarks = trim($_POST["remarks"] ?? "");
$hodDepartment = trim($_SESSION["department"] ?? "");
if ($hodDepartment === "") {
    echo json_encode(["status"=>false,"message"=>"HOD department is missing from the session."]);
    exit;
}

$sql = "SELECT id, reg_no, from_date, to_date,
               advisor_status, advisor_locked, forwarded_to_hod, hod_status
        FROM leave_requests
        WHERE id = ? AND department = ? LIMIT 1";
$stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $leaveId, $hodDepartment);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status"=>false,"message"=>"Leave request not found."]);
    $stmt->close(); $conn->close(); exit;
}
$leave = $result->fetch_assoc();

if (
    (int)$leave["forwarded_to_hod"] !== 1 ||
    (int)$leave["hod_status"] !== 0 ||
    (
        (int)$leave["advisor_status"] !== 1 &&
        (int)$leave["advisor_locked"] !== 1
    )
) {
    echo json_encode([
        "status" => false,
        "message" => "Leave already processed or not available for HOD."
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

$updateSql = "UPDATE leave_requests
              SET hod_status = 3, hod_remarks = ?
              WHERE id = ? AND department = ?
                AND forwarded_to_hod = 1
                AND hod_status = 0
                AND (
                    advisor_status = 1
                    OR advisor_locked = 1
                )";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("sis", $remarks, $leaveId, $hodDepartment);

if (!$updateStmt->execute() || $updateStmt->affected_rows <= 0) {
    echo json_encode(["status"=>false,"message"=>"Unable to approve leave or leave was already processed."]);
    $updateStmt->close(); $stmt->close(); $conn->close(); exit;
}

$title = "Leave Approved by HOD";
$message = "Your leave request from " .
           date("d-m-Y", strtotime($leave["from_date"])) .
           " to " .
           date("d-m-Y", strtotime($leave["to_date"])) .
           " has been finally approved by the HOD.";

if ($remarks !== "") {
    $message .= " HOD Remarks: " . $remarks;
}

createLeaveNotification($conn, $leave["reg_no"], $leaveId, $title, $message, "hod_approved");

echo json_encode(["status"=>true,"message"=>"Leave approved by HOD and student notified."]);

$updateStmt->close();
$stmt->close();
$conn->close();
?>
