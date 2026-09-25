<?php

session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../notification_helper.php";
require_once __DIR__ . "/../mail_config.php";


/*
==========================================
FIND EXPIRED ADVISOR REQUESTS
==========================================
*/

$sql = "
    SELECT
        lr.id,
        lr.reg_no,
        lr.department,
        lr.batch,
        lr.section,
        lr.from_date,
        lr.to_date
    FROM leave_requests lr
    WHERE lr.advisor_status = 0
      AND lr.advisor_locked = 0
      AND lr.advisor_deadline IS NOT NULL
      AND lr.advisor_deadline <= NOW()
";

$result = $conn->query($sql);

if (!$result) {

    echo json_encode([
        "status" => false,
        "message" => "Unable to check expired requests."
    ]);

    exit;
}


$lockedCount = 0;
$mailSentCount = 0;
$mailFailedCount = 0;


while ($row = $result->fetch_assoc()) {

    $leaveId = (int)$row["id"];
    $regNo = $row["reg_no"];
    $department = $row["department"];


    /*
    ==========================================
    FIND CORRECT ADVISOR
    BASED ON DEPARTMENT
    ==========================================
    */

    $advisorSql = "
        SELECT
            sl.staff_id,
            sl.email
        FROM year_advisor ya
        INNER JOIN staff_login sl
            ON sl.staff_id = ya.advisor_staff_id
        WHERE ya.department = ?
          AND ya.batch = ?
          AND (
              (
                  UPPER(ya.department) = 'IT'
                  AND (
                      (CAST(REGEXP_SUBSTR(?, '[0-9]+$') AS UNSIGNED) BETWEEN 1 AND 63
                       AND RIGHT(UPPER(ya.section), 1) = 'A')
                      OR
                      (CAST(REGEXP_SUBSTR(?, '[0-9]+$') AS UNSIGNED) BETWEEN 64 AND 126
                       AND RIGHT(UPPER(ya.section), 1) = 'B')
                  )
              )
              OR
              (UPPER(ya.department) <> 'IT'
               AND RIGHT(UPPER(ya.section), 1) = RIGHT(UPPER(?), 1))
          )
          AND sl.role = 'staff'
          AND sl.status = 'Active'
        LIMIT 1
    ";

    $advisorStmt = $conn->prepare($advisorSql);

    $advisorEmail = "";
    $advisorStaffId = "";

    if ($advisorStmt) {

        $advisorStmt->bind_param(
            "sssss",
            $department,
            $row["batch"],
            $regNo,
            $regNo,
            $row["section"]
        );

        if ($advisorStmt->execute()) {

            $advisorResult = $advisorStmt->get_result();

            if ($advisorRow = $advisorResult->fetch_assoc()) {

                $advisorStaffId = $advisorRow["staff_id"];
                $advisorEmail = trim($advisorRow["email"]);
            }
        }

        $advisorStmt->close();
    }


    /*
    ==========================================
    LOCK REQUEST + FORWARD TO HOD
    ==========================================
    */

    $updateSql = "
        UPDATE leave_requests
        SET
            advisor_locked = 1,
            forwarded_to_hod = 1,
            updated_at = NOW()
        WHERE id = ?
          AND advisor_status = 0
          AND advisor_locked = 0
    ";

    $stmt = $conn->prepare($updateSql);

    if (!$stmt) {
        continue;
    }

    $stmt->bind_param(
        "i",
        $leaveId
    );


    if (
        $stmt->execute() &&
        $stmt->affected_rows > 0
    ) {

        $lockedCount++;


        /*
        ==========================================
        SEND EMAIL TO ADVISOR
        ==========================================
        */

        if (
            !empty($advisorEmail) &&
            filter_var($advisorEmail, FILTER_VALIDATE_EMAIL)
        ) {

            $subject = "Leave Request Automatically Forwarded to HOD";

            $message = "
                <p>
                    A student leave request has been automatically
                    forwarded to the HOD because it was not processed
                    within the 48-hour advisor deadline.
                </p>

                <p>
                    <b>Leave Request ID:</b> {$leaveId}
                </p>

                <p>
                    <b>Student Register Number:</b> " .
                    htmlspecialchars($regNo) .
                "
                </p>

                <p>
                    <b>Department:</b> " .
                    htmlspecialchars($department) .
                "
                </p>

                <p>
                    <b>From Date:</b> " .
                    date(
                        "d-m-Y",
                        strtotime($row["from_date"])
                    ) .
                "
                </p>

                <p>
                    <b>To Date:</b> " .
                    date(
                        "d-m-Y",
                        strtotime($row["to_date"])
                    ) .
                "
                </p>

                <p>
                    <b>Advisor Status:</b>
                    Locked - Deadline Expired
                </p>

                <p>
                    The request has been forwarded to the HOD
                    for further processing.
                </p>
            ";


            $mailSent = sendLeaveEmail(
                $advisorEmail,
                $advisorStaffId,
                $subject,
                $message
            );


            if ($mailSent) {

                $mailSentCount++;

            } else {

                $mailFailedCount++;
            }
        }


        /*
        ==========================================
        CREATE STUDENT NOTIFICATION
        ==========================================
        */

        createLeaveNotification(
            $conn,
            $regNo,
            $leaveId,
            "Request Forwarded to HOD",
            "Your leave request from " .
                date(
                    "d-m-Y",
                    strtotime($row["from_date"])
                ) .
                " to " .
                date(
                    "d-m-Y",
                    strtotime($row["to_date"])
                ) .
                " was not processed by the Advisor within 2 days. The request is now locked and forwarded to the HOD.",
            "advisor_locked"
        );
    }

    $stmt->close();
}


/*
==========================================
FINAL RESPONSE
==========================================
*/

echo json_encode([
    "status" => true,
    "locked_count" => $lockedCount,
    "advisor_mail_sent" => $mailSentCount,
    "advisor_mail_failed" => $mailFailedCount
]);