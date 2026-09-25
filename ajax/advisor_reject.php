<?php

session_start();

require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../includes/academic_helper.php";
require_once __DIR__ . "/../mail_config.php";
require_once __DIR__ . "/../notification_helper.php";

header("Content-Type: application/json");


/*
=========================================
STAFF LOGIN CHECK
=========================================
*/

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "staff"
) {

    echo json_encode([
        "status" => false,
        "message" => "Unauthorized access."
    ]);

    exit;
}

$advisorId = trim($_SESSION["staff_id"] ?? "");
if ($advisorId === "") {
    echo json_encode(["status" => false, "message" => "Advisor session not found."]);
    exit;
}


/*
=========================================
GET LEAVE ID
=========================================
*/

if (
    !isset($_POST["leave_id"]) ||
    empty($_POST["leave_id"])
) {

    echo json_encode([
        "status" => false,
        "message" => "Leave ID missing."
    ]);

    exit;
}


$leaveId = intval($_POST["leave_id"]);

$remarks = trim($_POST["remarks"] ?? "");


/*
=========================================
REMARKS REQUIRED
=========================================
*/

if (empty($remarks)) {

    echo json_encode([
        "status" => false,
        "message" => "Remarks are required for rejection."
    ]);

    exit;
}


/*
=========================================
GET STUDENT DETAILS BEFORE REJECTION
=========================================
*/

$sql = "
        SELECT lr.id, lr.reg_no, lr.from_date, lr.to_date, lr.batch, lr.department, lr.section, lr.year,
           lr.semester, lr.academic_year, lr.reason, lr.advisor_status, lr.advisor_locked, sl.email
    FROM leave_requests lr
    LEFT JOIN stu_login sl ON lr.reg_no = sl.reg_no
        WHERE lr.id = ?
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "status" => false,
        "message" => "Database error while preparing the query."
    ]);

    exit;
}


$stmt->bind_param("i", $leaveId);


$stmt->execute();

$result = $stmt->get_result();

/*
=========================================
VERIFY CORRECT CLASS ADVISOR
=========================================

Current project:

2024-2028 + IT + 3rd Year

BIT001 - BIT063
        ↓
IT-A
        ↓
ITSTAFF001

BIT064 - BIT126
        ↓
IT-B
        ↓
ITSTAFF002

IMPORTANT:
year_advisor.year_name stores:

3rd Year

So use "3rd Year" here instead of
$leave["year"].
=========================================
*/

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => false,
        "message" => "Leave request not found."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$leave = $result->fetch_assoc();


if (!isAdvisorForStudent(
    $conn,
    $advisorId,
    $leave['reg_no'],
    $leave['department'],
    $leave['batch'],
    $leave['section'],
    '3rd Year'
)) {

    echo json_encode([
        "status" => false,
        "message" =>
            "This leave request is outside your assigned section."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
CHECK LEAVE EXISTS
=========================================
*/

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => false,
        "message" => "Leave request not found."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$leave = $result->fetch_assoc();


$studentRegNo = $leave["reg_no"];

$studentEmail = trim($leave["email"] ?? "");


/*
=========================================
CHECK STUDENT EMAIL
=========================================
*/

if (empty($studentEmail)) {

    echo json_encode([
        "status" => false,
        "message" =>
            "Student email address not found for Register No: "
            . $studentRegNo
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
CHECK CURRENT STATUS
=========================================
*/

if ((int)$leave["advisor_status"] !== 0) {

    echo json_encode([
        "status" => false,
        "message" => "This leave request has already been processed."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
CHECK ADVISOR LOCK STATUS
=========================================
*/

if (
    isset($leave["advisor_locked"]) &&
    (int)$leave["advisor_locked"] === 1
) {

    echo json_encode([
        "status" => false,
        "message" =>
            "This leave request is locked because the Advisor deadline has expired. It has been forwarded to the HOD."
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
REJECT LEAVE
=========================================
*/
$updateSql = "
    UPDATE leave_requests
    SET advisor_status=4, forwarded_to_hod=0, advisor_remarks=?, updated_at=NOW()
        WHERE id=? AND advisor_status=0 AND advisor_locked=0
";


$updateStmt = $conn->prepare($updateSql);


if (!$updateStmt) {

    echo json_encode([
        "status" => false,
        "message" => "Update prepare error: " . $conn->error
    ]);

    $stmt->close();
    $conn->close();

    exit;
}


$updateStmt->bind_param("si", $remarks, $leaveId);


/*
=========================================
EXECUTE REJECTION
=========================================
*/

if (!$updateStmt->execute()) {

    echo json_encode([
        "status" => false,
        "message" =>
            "Unable to reject leave: "
            . $updateStmt->error
    ]);

    $updateStmt->close();
    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
CHECK UPDATE
=========================================
*/

if ($updateStmt->affected_rows <= 0) {

    echo json_encode([
        "status" => false,
        "message" =>
            "Leave was already processed or could not be updated."
    ]);

    $updateStmt->close();
    $stmt->close();
    $conn->close();

    exit;
}


/*
=========================================
CREATE STUDENT NOTIFICATION
=========================================
*/
$notificationTitle = "Leave Request Rejected by Advisor";
$notificationMessage =
    "Your leave request from " .
    date("d-m-Y", strtotime($leave["from_date"])) .
    " to " .
    date("d-m-Y", strtotime($leave["to_date"])) .
    " has been rejected by the Advisor. Reason: " .
    $remarks;

createLeaveNotification(
    $conn,
    $studentRegNo,
    $leaveId,
    $notificationTitle,
    $notificationMessage,
    "advisor_rejected"
);

/*
=========================================
SEND REJECTION EMAIL
=========================================
*/

$subject = "Leave Request Rejected by Staff";


$message = "

<p>
    Your leave request has been
    <strong style='color:red;'>
        rejected by the Staff/Advisor
    </strong>.
</p>

<hr>

<p>
    <strong>Register Number:</strong>
    " . htmlspecialchars($studentRegNo) . "
</p>

<p>
    <strong>From Date:</strong>
    " . htmlspecialchars($leave["from_date"]) . "
</p>

<p>
    <strong>To Date:</strong>
    " . htmlspecialchars($leave["to_date"]) . "
</p>

<p>
    <strong>Batch:</strong>
    " . htmlspecialchars($leave["batch"]) . "
</p>

<p>
    <strong>Semester:</strong>
    " . htmlspecialchars($leave["semester"]) . "
</p>

<p>
    <strong>Academic Year:</strong>
    " . htmlspecialchars($leave["academic_year"]) . "
</p>

<p>
    <strong>Reason:</strong>
    " . htmlspecialchars($leave["reason"]) . "
</p>

<p>
    <strong>Advisor Remarks:</strong>
    " . htmlspecialchars($remarks) . "
</p>

";


/*
=========================================
CALL PHPMailer
=========================================
*/

$mailSent = sendLeaveEmail(

    $studentEmail,

    "Student",

    $subject,

    $message

);


/*
=========================================
FINAL RESPONSE
=========================================
*/

if ($mailSent) {

    echo json_encode([

        "status" => true,

        "message" =>
            "Leave rejected and email sent successfully."

    ]);

} else {

    echo json_encode([

        "status" => true,

        "message" =>
            "Leave rejected, but email could not be sent."

    ]);

}


$updateStmt->close();

$stmt->close();

$conn->close();

exit;

?>