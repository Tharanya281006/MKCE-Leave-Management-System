<?php

session_start();

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set("display_errors", 0);


require_once __DIR__ . "/../db/connection.php";
require_once __DIR__ . "/../includes/academic_helper.php";


/*
==========================================
CHECK STUDENT LOGIN
==========================================
*/

if (

    !isset($_SESSION["role"]) ||

    $_SESSION["role"] !== "student"

) {

    echo json_encode([

        "status" => false,

        "message" => "Unauthorized access."

    ]);

    exit;

}


$reg_no = $_SESSION["reg_no"] ?? "";


if (empty($reg_no)) {

    echo json_encode([

        "status" => false,

        "message" => "Student information not found."

    ]);

    exit;

}

/* Get trusted student details from stu_login */
$studentStmt = $conn->prepare("
    SELECT department, batch, section, student_name
    FROM stu_login
    WHERE reg_no = ? AND LOWER(status) = 'active'
    LIMIT 1
");

if (!$studentStmt) {
    echo json_encode(["status" => false, "message" => "Unable to fetch student details."]);
    exit;
}

$studentStmt->bind_param("s", $reg_no);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$student = $studentResult->fetch_assoc();
$studentStmt->close();

if (!$student) {
    echo json_encode(["status" => false, "message" => "Active student details not found."]);
    exit;
}

$class = getCanonicalStudentClass($reg_no, $student["department"] ?? "", $student["section"] ?? "");
$department = $class['department'];
$batch = trim($student["batch"] ?? "");
$section = $class['section'];

if ($department === "" || $batch === "") {
    echo json_encode([
        "status" => false,
        "message" => "Student department or batch is missing."
    ]);
    exit;
}

if ($department === "" || $batch === "" || $section === "") {
    echo json_encode(["status" => false, "message" => "Student department, batch or section is missing."]);
    exit;
}


/*
==========================================
GET FORM DATA
==========================================
*/

$leave_id = intval($_POST["leave_id"] ?? 0);

$request_type = trim(
    $_POST["request_type"] ?? ""
);

$duration_type = trim(
    $_POST["duration_type"] ?? "Single Day"
);

$selected_period = trim(
    $_POST["selected_period"] ?? ""
);

$from_date = trim(
    $_POST["from_date"] ?? ""
);

$to_date = trim(
    $_POST["to_date"] ?? ""
);



$semester = intval($_POST["semester"] ?? 0);

$academic_year = trim($_POST['academic_year'] ?? '');

$reason = trim($_POST["reason"] ?? "");


/*
==========================================
VALIDATION
==========================================
*/

if (
    $leave_id <= 0 ||
    empty($request_type) ||
    empty($duration_type) ||
    empty($from_date) ||
    empty($to_date) ||
    $semester <= 0 ||
    !preg_match('/^\d{4}-\d{4}$/', $academic_year) ||
    empty($reason)
) {

    echo json_encode([

        "status" => false,

        "message" => "Please fill all required fields."

    ]);

    exit;

}


/*
==========================================
VALIDATE REQUEST TYPE
==========================================
*/

$allowedRequestTypes = [

    "OD",
    "LEAVE"

];

if (

    !in_array(

        $request_type,

        $allowedRequestTypes,

        true

    )

) {

    echo json_encode([

        "status" => false,

        "message" => "Please select a valid request type."

    ]);

    exit;

}


/*
==========================================
VALIDATE DURATION TYPE
==========================================
*/

$allowedDurationTypes = [

    "Single Day",
    "Half Day",
    "Specific Hours"

];

if (

    !in_array(

        $duration_type,

        $allowedDurationTypes,

        true

    )

) {

    echo json_encode([

        "status" => false,

        "message" => "Please select a valid duration."

    ]);

    exit;

}


/*
==========================================
VALIDATE HALF DAY
==========================================
*/

if ($duration_type === "Half Day") {

    if (

        !in_array(

            $selected_period,

            [

                "Morning",
                "Evening"

            ],

            true

        )

    ) {

        echo json_encode([

            "status" => false,

            "message" =>
                "Please select Morning or Evening for Half Day."

        ]);

        exit;

    }

}


/*
==========================================
VALIDATE SPECIFIC HOURS
==========================================
*/

if ($duration_type === "Specific Hours") {

    $allowedHours = [];

    for (

        $hour = 1;

        $hour <= 7;

        $hour++

    ) {

        $allowedHours[] = "Hour " . $hour;

    }

    if (

        !in_array(

            $selected_period,

            $allowedHours,

            true

        )

    ) {

        echo json_encode([

            "status" => false,

            "message" =>
                "Please select a valid hour from Hour 1 to Hour 7."

        ]);

        exit;

    }

}


/*
==========================================
SINGLE DAY PERIOD RESET
==========================================
*/

if ($duration_type === "Single Day") {

    $selected_period = "";

}

/*
==========================================
VALIDATE DATE FORMAT
==========================================
*/

$fromDateObject = DateTime::createFromFormat(
    "Y-m-d",
    $from_date
);

$toDateObject = DateTime::createFromFormat(
    "Y-m-d",
    $to_date
);

$fromDateErrors = DateTime::getLastErrors();

$toDateErrors = DateTime::getLastErrors();


if (

    !$fromDateObject ||

    !$toDateObject ||

    (
        $fromDateErrors !== false &&
        (
            $fromDateErrors["warning_count"] > 0 ||
            $fromDateErrors["error_count"] > 0
        )
    ) ||

    (
        $toDateErrors !== false &&
        (
            $toDateErrors["warning_count"] > 0 ||
            $toDateErrors["error_count"] > 0
        )
    ) ||

    $fromDateObject->format("Y-m-d") !== $from_date ||

    $toDateObject->format("Y-m-d") !== $to_date

) {

    echo json_encode([

        "status" => false,

        "message" => "Invalid date format."

    ]);

    exit;

}


/*
==========================================
CHECK DATE ORDER
==========================================
*/

if ($from_date > $to_date) {

    echo json_encode([

        "status" => false,

        "message" =>
            "From Date cannot be greater than To Date."

    ]);

    exit;

}


/*
==========================================
VERIFY LEAVE REQUEST

IMPORTANT:
Student can edit ONLY:

1. Their own request
2. Advisor status is Pending (0)
==========================================
*/

$checkSql = "

    SELECT proof

    FROM leave_requests

    WHERE id = ?

    AND reg_no = ?

    AND advisor_status = 0

";


$checkStmt = $conn->prepare($checkSql);


$checkStmt->bind_param(

    "is",

    $leave_id,

    $reg_no

);


$checkStmt->execute();


$checkResult = $checkStmt->get_result();


if ($checkResult->num_rows === 0) {

    echo json_encode([

        "status" => false,

        "message" =>
            "This leave request cannot be edited."

    ]);

    exit;

}


$currentLeave =

    $checkResult->fetch_assoc();


$currentProof =

    $currentLeave["proof"];

/*
==========================================
OD PROOF VALIDATION DURING EDIT
==========================================
*/

if (

    $request_type === "OD" &&

    empty($currentProof) &&

    (

        !isset($_FILES["proof"]) ||

        $_FILES["proof"]["error"] === UPLOAD_ERR_NO_FILE

    )

) {

    echo json_encode([

        "status" => false,

        "message" => "Proof is compulsory for OD."

    ]);

    exit;

}
/*
==========================================
FILE UPLOAD
==========================================
*/

$proofFileName = $currentProof;


if (

    isset($_FILES["proof"]) &&

    $_FILES["proof"]["error"] !== UPLOAD_ERR_NO_FILE

) {

    if ($_FILES["proof"]["error"] !== UPLOAD_ERR_OK) {

        echo json_encode([

            "status" => false,

            "message" => "Error while uploading proof."

        ]);

        exit;

    }


    $allowedExtensions = [

        "jpg",

        "jpeg",

        "png",

        "pdf"

    ];


    $extension = strtolower(

        pathinfo(

            $_FILES["proof"]["name"],

            PATHINFO_EXTENSION

        )

    );


    if (

        !in_array(

            $extension,

            $allowedExtensions

        )

    ) {

        echo json_encode([

            "status" => false,

            "message" =>
                "Only JPG, JPEG, PNG and PDF files are allowed."

        ]);

        exit;

    }


    $uploadDirectory =

        "../uploads/proofs/";


    if (!is_dir($uploadDirectory)) {

        mkdir(

            $uploadDirectory,

            0777,

            true

        );

    }


    $proofFileName =

        time() .

        "_" .

        uniqid() .

        "." .

        $extension;


    if (

        !move_uploaded_file(

            $_FILES["proof"]["tmp_name"],

            $uploadDirectory .

            $proofFileName

        )

    ) {

        echo json_encode([

            "status" => false,

            "message" =>
                "Unable to upload proof."

        ]);

        exit;

    }


    /*
    DELETE OLD FILE
    */

    if (

        !empty($currentProof) &&

        file_exists(

            $uploadDirectory .

            $currentProof

        )

    ) {

        unlink(

            $uploadDirectory .

            $currentProof

        );

    }

}


/*
==========================================
UPDATE LEAVE
==========================================
*/

$sql = "

    UPDATE leave_requests

    SET

        request_type = ?,

        duration_type = ?,

        selected_period = ?,

        from_date = ?,

        to_date = ?,

        batch = ?,

        section = ?,

        semester = ?,

        academic_year = ?,

        reason = ?,

        proof = ?

    WHERE

        id = ?

    AND

        reg_no = ?

    AND

        advisor_status = 0

";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([

        "status" => false,

        "message" =>
            "Database error: " .

            $conn->error

    ]);

    exit;

}


$stmt->bind_param(

    "sssssssisssis",

    $request_type,

    $duration_type,

    $selected_period,

    $from_date,

    $to_date,

    $batch,

    $section,

    $semester,

    $academic_year,

    $reason,

    $proofFileName,

    $leave_id,

    $reg_no

);

if ($stmt->execute()) {

    echo json_encode([

        "status" => true,

        "message" =>
            "Leave request updated successfully."

    ]);

} else {

    echo json_encode([

        "status" => false,

        "message" =>
            "Unable to update leave request."

    ]);

}


exit;

?>