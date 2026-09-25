<?php

session_start();

header('Content-Type: application/json');

error_reporting(0);


/*
|--------------------------------------------------------------------------
| REQUEST METHOD CHECK
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => false,
        'message' => 'Method not allowed'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| STUDENT LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    ($_SESSION['role'] ?? '') !== 'student' ||
    empty($_SESSION['reg_no'])
) {

    echo json_encode([
        'status' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE + HELPER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/academic_helper.php';


/*
|--------------------------------------------------------------------------
| STUDENT DETAILS
|--------------------------------------------------------------------------
*/

$reg_no = $_SESSION['reg_no'];

$studentStmt = $conn->prepare(
    "SELECT department,
            batch,
            section,
            student_name
     FROM stu_login
     WHERE reg_no = ?
       AND status = 'Active'
     LIMIT 1"
);

if (!$studentStmt) {

    echo json_encode([
        'status' => false,
        'message' => 'Unable to prepare student query.'
    ]);

    exit;
}

$studentStmt->bind_param('s', $reg_no);

$studentStmt->execute();

$student = $studentStmt->get_result()->fetch_assoc();

$studentStmt->close();


if (!$student) {

    echo json_encode([
        'status' => false,
        'message' => 'Active student details not found.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$request_type = trim(
    $_POST['request_type'] ?? 'LEAVE'
);

$duration_type = trim(
    $_POST['duration_type'] ?? 'Single Day'
);

$selected_period = trim(
    $_POST['selected_period'] ?? ''
);

$from_date = trim(
    $_POST['from_date'] ?? ''
);

$to_date = trim(
    $_POST['to_date'] ?? $from_date
);

$semester = (int) (
    $_POST['semester'] ?? 0
);

$academic_year = trim($_POST['academic_year'] ?? '');

$reason = trim(
    $_POST['reason'] ?? ''
);

$batch = trim(
    $student['batch']
);


/*
|--------------------------------------------------------------------------
| CANONICAL STUDENT CLASS
|--------------------------------------------------------------------------
*/

$class = getCanonicalStudentClass(
    $reg_no,
    $student['department'],
    $student['section']
);

$department = $class['department'];

$section = $class['section'];


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !$from_date ||
    !$to_date ||
    !$semester ||
    !$reason ||
    !$batch ||
    $section === ''
) {

    echo json_encode([
        'status' => false,
        'message' => 'Please fill all required fields.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTOMATIC ACADEMIC YEAR
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{4}-\d{4}$/', $academic_year)) {

    echo json_encode([
        'status' => false,
        'message' => 'Please choose a valid academic year.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| PROOF FILE
|--------------------------------------------------------------------------
*/

$proof = '';


/*
|--------------------------------------------------------------------------
| NORMALIZE REQUEST TYPE
|--------------------------------------------------------------------------
*/

$request_type = strtoupper(
    trim($request_type)
);


/*
|--------------------------------------------------------------------------
| CHECK WHETHER A FILE WAS UPLOADED
|--------------------------------------------------------------------------
*/

$proofUploaded = (
    isset($_FILES['proof']) &&
    $_FILES['proof']['error'] !== UPLOAD_ERR_NO_FILE
);


/*
|--------------------------------------------------------------------------
| OD REQUIRES PROOF
|--------------------------------------------------------------------------
*/

if (
    $request_type === 'OD' &&
    !$proofUploaded
) {

    echo json_encode([
        'status' => false,
        'message' => 'Proof is required for OD.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| PROCESS PROOF UPLOAD
|--------------------------------------------------------------------------
*/

if ($proofUploaded) {

    /*
    |--------------------------------------------------------------------------
    | PHP UPLOAD ERROR
    |--------------------------------------------------------------------------
    */

    $uploadError = $_FILES['proof']['error'];


    if ($uploadError !== UPLOAD_ERR_OK) {

        $uploadErrors = [

            UPLOAD_ERR_INI_SIZE =>
                'Proof file is larger than the server upload limit.',

            UPLOAD_ERR_FORM_SIZE =>
                'Proof file is larger than the allowed size.',

            UPLOAD_ERR_PARTIAL =>
                'Proof file was only partially uploaded.',

            UPLOAD_ERR_NO_FILE =>
                'No proof file was selected.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'Server temporary upload folder is missing.',

            UPLOAD_ERR_CANT_WRITE =>
                'Server could not write the uploaded proof file.',

            UPLOAD_ERR_EXTENSION =>
                'A PHP extension stopped the proof upload.'
        ];


        $message = $uploadErrors[$uploadError]
            ?? 'Unknown proof upload error.';


        echo json_encode([
            'status' => false,
            'message' => $message
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | FILE INFORMATION
    |--------------------------------------------------------------------------
    */

    $tmpFile = $_FILES['proof']['tmp_name'];

    $originalName = $_FILES['proof']['name'];

    $fileSize = (int) $_FILES['proof']['size'];


    /*
    |--------------------------------------------------------------------------
    | MAXIMUM FILE SIZE
    |--------------------------------------------------------------------------
    | 5 MB
    |--------------------------------------------------------------------------
    */

    $maxFileSize = 5 * 1024 * 1024;


    if ($fileSize > $maxFileSize) {

        echo json_encode([
            'status' => false,
            'message' => 'Proof file must be 5 MB or smaller.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY UPLOADED FILE
    |--------------------------------------------------------------------------
    */

    if (!is_uploaded_file($tmpFile)) {

        echo json_encode([
            'status' => false,
            'message' => 'Invalid proof upload.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | GET FILE EXTENSION
    |--------------------------------------------------------------------------
    */

    $ext = strtolower(
        pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        )
    );


    /*
    |--------------------------------------------------------------------------
    | ALLOWED FILE TYPES
    |--------------------------------------------------------------------------
    */

    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'pdf'
    ];


    if (
        !in_array(
            $ext,
            $allowedExtensions,
            true
        )
    ) {

        echo json_encode([
            'status' => false,
            'message' =>
                'Invalid proof file. Only JPG, JPEG, PNG and PDF are allowed.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | PROOF UPLOAD DIRECTORY
    |--------------------------------------------------------------------------
    */

    $dir = __DIR__ .
           '/../uploads/proofs/';


    /*
    |--------------------------------------------------------------------------
    | CREATE DIRECTORY IF REQUIRED
    |--------------------------------------------------------------------------
    */

    if (!is_dir($dir)) {

        if (!mkdir($dir, 0775, true)) {

            echo json_encode([
                'status' => false,
                'message' =>
                    'Unable to create proof upload folder.'
            ]);

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DIRECTORY WRITE PERMISSION
    |--------------------------------------------------------------------------
    */

    if (!is_writable($dir)) {

        echo json_encode([
            'status' => false,
            'message' =>
                'Proof upload folder is not writable.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE UNIQUE FILE NAME
    |--------------------------------------------------------------------------
    */

    try {

        $randomPart = bin2hex(
            random_bytes(6)
        );

    } catch (Exception $e) {

        $randomPart = uniqid();
    }


    $proof =
        'proof_' .
        date('Ymd_His') .
        '_' .
        $randomPart .
        '.' .
        $ext;


    /*
    |--------------------------------------------------------------------------
    | FINAL DESTINATION
    |--------------------------------------------------------------------------
    */

    $destination = $dir . $proof;


    /*
    |--------------------------------------------------------------------------
    | MOVE UPLOADED FILE
    |--------------------------------------------------------------------------
    */

    if (
        !move_uploaded_file(
            $tmpFile,
            $destination
        )
    ) {

        echo json_encode([
            'status' => false,
            'message' =>
                'Unable to save proof file on the server.'
        ]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| INSERT LEAVE REQUEST
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO leave_requests
    (
        reg_no,
        request_type,
        duration_type,
        department,
        from_date,
        to_date,
        batch,
        year,
        section,
        semester,
        academic_year,
        selected_period,
        reason,
        proof,
        advisor_status,
        hod_status,
        forwarded_to_hod,
        created_at,
        updated_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        0,
        0,
        0,
        NOW(),
        NOW()
    )
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    echo json_encode([
        'status' => false,
        'message' =>
            'Database error: ' . $conn->error
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| YEAR
|--------------------------------------------------------------------------
*/

$year = trim(
    (string) (
        $_POST['year'] ?? ''
    )
);


if ($year === '') {

    $year = trim(
        (string) (
            $_POST['year_name'] ?? ''
        )
    );
}


if ($year === '') {

    $year =
        'Year ' .
        (int) ceil(
            $semester / 2
        );
}


/*
|--------------------------------------------------------------------------
| BIND VALUES
|--------------------------------------------------------------------------
*/

$stmt->bind_param(
    'ssssssssisssss',
    $reg_no,
    $request_type,
    $duration_type,
    $department,
    $from_date,
    $to_date,
    $batch,
    $year,
    $section,
    $semester,
    $academic_year,
    $selected_period,
    $reason,
    $proof
);


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    echo json_encode([
        'status' => true,
        'message' =>
            'Leave submitted successfully.'
    ]);

} else {

    echo json_encode([
        'status' => false,
        'message' =>
            'Unable to save leave: ' .
            $stmt->error
    ]);
}


$stmt->close();