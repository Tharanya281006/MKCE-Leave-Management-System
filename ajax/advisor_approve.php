<?php

session_start();

require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../includes/academic_helper.php";

/*
=========================================
MAIL CONFIGURATION
=========================================
*/

require_once __DIR__ . "/../mail_config.php";
require_once __DIR__ . "/../notification_helper.php";


header(
    "Content-Type: application/json"
);


/*
=========================================
STAFF LOGIN CHECK
=========================================
*/

if (

    !isset($_SESSION["role"])

    ||

    $_SESSION["role"] !== "staff"

) {

    echo json_encode([

        "status" => false,

        "message" =>
            "Unauthorized access."

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

if (!isset($_POST["leave_id"]) ||
    empty($_POST["leave_id"])) {

    echo json_encode([

        "status" => false,

        "message" =>
            "Leave ID missing."

    ]);

    exit;

}


$leaveId = intval($_POST["leave_id"]);

$remarks = trim($_POST["remarks"] ?? "");


/*
=========================================
GET STUDENT DETAILS BEFORE APPROVAL
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


if ($result->num_rows === 0) {

    echo json_encode([

        "status" => false,

        "message" =>
            "Leave request not found."

    ]);

    exit;

}


$leave = $result->fetch_assoc();

/*
=========================================
VERIFY CORRECT CLASS ADVISOR
=========================================

For the current project:

2024-2028 + IT + 3rd Year

BIT001 - BIT063  -> IT-A -> ITSTAFF001
BIT064 - BIT126  -> IT-B -> ITSTAFF002

The section is derived from the register number
inside isAdvisorForStudent().
*/

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
        "message" => "This leave request is outside your assigned section."
    ]);
    exit;
}


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

    exit;
}

/*
=========================================
APPROVE + FORWARD TO HOD
=========================================
*/

$updateSql = "
    UPDATE leave_requests
    SET
        advisor_status = 1,
        forwarded_to_hod = 1,
        advisor_remarks = ?,
        updated_at = NOW()
    WHERE id = ? AND advisor_status = 0 AND advisor_locked = 0
";

$updateStmt = $conn->prepare($updateSql);


if (!$updateStmt) {

    echo json_encode([
        "status" => false,
        "message" => "Update prepare error: " . $conn->error
    ]);

    exit;
}


$updateStmt->bind_param("si", $remarks, $leaveId);

/*
=========================================
EXECUTE APPROVAL
=========================================
*/

if (!$updateStmt->execute()) {

    echo json_encode([
        "status" => false,
        "message" =>
            "Unable to approve leave: "
            . $updateStmt->error
    ]);

    exit;
}


if ($updateStmt->affected_rows <= 0) {

    echo json_encode([
        "status" => false,
        "message" =>
            "Leave was already processed or could not be updated."
    ]);

    exit;
}

/*
=========================================
CREATE STUDENT NOTIFICATION
=========================================
*/
$notificationTitle = "Leave Approved by Advisor";
$notificationMessage =
    "Your leave request from " .
    date("d-m-Y", strtotime($leave["from_date"])) .
    " to " .
    date("d-m-Y", strtotime($leave["to_date"])) .
    " has been approved by the Advisor and forwarded to the HOD.";

createLeaveNotification(
    $conn,
    $studentRegNo,
    $leaveId,
    $notificationTitle,
    $notificationMessage,
    "advisor_approved"
);

/*
=========================================
SEND EMAIL TO STUDENT
=========================================
*/

$subject = "Leave Request Approved by Staff";


$message = "

<p>
    Your leave request has been
    <strong style='color:green;'>
        approved by the Staff/Advisor
    </strong>.
</p>

<p>
    Your leave request has now been
    <strong>forwarded to the HOD</strong>
    for final approval.
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
    " . (
        !empty($remarks)
        ? htmlspecialchars($remarks)
        : "-"
    ) . "
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
            "Leave approved, forwarded to HOD, and email sent successfully."

    ]);

} else {

    echo json_encode([

        "status" => true,

        "message" =>
            "Leave approved and forwarded to HOD, but email could not be sent."

    ]);

}


$updateStmt->close();

$stmt->close();

$conn->close();

exit;

?>
