<?php

session_start();

header("Content-Type: application/json");

require_once __DIR__ . "/../db/connection.php";


/*
==========================================
CHECK LOGIN
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

$leave_id = intval(

    $_POST["leave_id"] ?? 0

);


if ($leave_id <= 0) {

    echo json_encode([

        "status" => false,

        "message" => "Invalid leave request."

    ]);

    exit;

}


/*
==========================================
GET PROOF FILE

Only student's Pending request
==========================================
*/

$checkSql = "

    SELECT proof

    FROM leave_requests

    WHERE id = ?

    AND reg_no = ?

    AND advisor_status = 0

";


$stmt = $conn->prepare($checkSql);


$stmt->bind_param(

    "is",

    $leave_id,

    $reg_no

);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([

        "status" => false,

        "message" =>
            "This leave request cannot be deleted."

    ]);

    exit;

}


$row = $result->fetch_assoc();


$proof = $row["proof"];


/*
==========================================
DELETE DATABASE RECORD
==========================================
*/

$deleteSql = "

    DELETE FROM leave_requests

    WHERE id = ?

    AND reg_no = ?

    AND advisor_status = 0

";


$deleteStmt =

    $conn->prepare(

        $deleteSql

    );


$deleteStmt->bind_param(

    "is",

    $leave_id,

    $reg_no

);


if ($deleteStmt->execute()) {


    /*
    ==========================================
    DELETE PROOF FILE
    ==========================================
    */

    if (!empty($proof)) {

        $filePath =

            "../uploads/proofs/" .

            $proof;


        if (file_exists($filePath)) {

            unlink($filePath);

        }

    }


    echo json_encode([

        "status" => true,

        "message" =>
            "Leave request deleted successfully."

    ]);


} else {

    echo json_encode([

        "status" => false,

        "message" =>
            "Unable to delete leave request."

    ]);

}


exit;

?>