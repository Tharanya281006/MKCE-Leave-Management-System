<?php

session_start();

error_reporting(E_ALL);
ini_set("display_errors", 0);

require_once __DIR__ . "/../db/connection.php";

header("Content-Type: application/json");

// Allow only POST request

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "status" => false,
        "message" => "Method not allowed"
    ]);

    exit;
}


// Get AJAX values

$role = trim($_POST["role"] ?? "");
$login_id = trim($_POST["login_id"] ?? "");
$password = trim($_POST["password"] ?? "");


// Check empty fields

if ($role === "" || $login_id === "" || $password === "") {

    http_response_code(200);

    echo json_encode([
        "status" => false,
        "message" => "All fields are required"
    ]);

    exit;
}


// Role based login

switch ($role) {


    // ==========================================
    // STUDENT LOGIN
    // ==========================================

    case "student":

        $sql = "SELECT id, reg_no, password, department, status
                FROM stu_login
                WHERE reg_no = ?
                LIMIT 1";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            echo json_encode([
                "status" => false,
                "message" => "Database query error"
            ]);

            exit;
        }


        $stmt->bind_param("s", $login_id);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 0) {

            echo json_encode([
                "status" => false,
                "message" => "Register Number not found"
            ]);

            exit;
        }


        $user = $result->fetch_assoc();


        // Check account status

        if ($user["status"] !== "Active") {

            echo json_encode([
                "status" => false,
                "message" => "Your account is inactive"
            ]);

            exit;
        }


        // Password check

        if ($password !== $user["password"]) {

            echo json_encode([
                "status" => false,
                "message" => "Incorrect password"
            ]);

            exit;
        }


        // Create Student Session

        session_regenerate_id(true);

$_SESSION["role"] = "student";
$_SESSION["reg_no"] = $user["reg_no"];
$_SESSION["department"] = $user["department"];
$_SESSION["login_id"] = $user["id"];
echo json_encode([
    "status" => true,
    "message" => "Student login successful",
    "redirect" => "/leave_tih_1/index.php?page=dashboard"
]);

break;
    // ==========================================
    // STAFF LOGIN
    // ==========================================

    case "staff":

        $sql = "SELECT id, staff_id, password, role, department, status
                FROM staff_login
                WHERE staff_id = ?
                AND role = 'staff'
                LIMIT 1";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            echo json_encode([
                "status" => false,
                "message" => "Database query error"
            ]);

            exit;
        }


        $stmt->bind_param("s", $login_id);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 0) {

            echo json_encode([
                "status" => false,
                "message" => "Staff ID not found"
            ]);

            exit;
        }


        $user = $result->fetch_assoc();


        // Check account status

        if ($user["status"] !== "Active") {

            echo json_encode([
                "status" => false,
                "message" => "Your account is inactive"
            ]);

            exit;
        }


        // Password check

        if ($password !== $user["password"]) {

            echo json_encode([
                "status" => false,
                "message" => "Incorrect password"
            ]);

            exit;
        }


        // Create Staff Session

        session_regenerate_id(true);

        $_SESSION["role"] = "staff";
        $_SESSION["staff_id"] = $user["staff_id"];
        $_SESSION["department"] = $user["department"];
        $_SESSION["login_id"] = $user["id"];

echo json_encode([
    "status" => true,
    "message" => "Staff login successful",
    "redirect" => "/leave_tih_1/index.php?page=dashboard"
]);
        break;


    // ==========================================
    // HOD LOGIN
    // ==========================================

    case "hod":

        $sql = "SELECT id, staff_id, password, role, department, status
                FROM staff_login
                WHERE staff_id = ?
                AND role = 'hod'
                LIMIT 1";


        $stmt = $conn->prepare($sql);


        if (!$stmt) {

            echo json_encode([
                "status" => false,
                "message" => "Database query error"
            ]);

            exit;
        }


        $stmt->bind_param("s", $login_id);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 0) {

            echo json_encode([
                "status" => false,
                "message" => "HOD ID not found"
            ]);

            exit;
        }


        $user = $result->fetch_assoc();


        // Check account status

        if ($user["status"] !== "Active") {

            echo json_encode([
                "status" => false,
                "message" => "Your account is inactive"
            ]);

            exit;
        }


        // Password check

        if ($password !== $user["password"]) {

            echo json_encode([
                "status" => false,
                "message" => "Incorrect password"
            ]);

            exit;
        }


        // Create HOD Session

        session_regenerate_id(true);

        $_SESSION["role"] = "hod";
        $_SESSION["staff_id"] = $user["staff_id"];
        $_SESSION["department"] = $user["department"];
        $_SESSION["login_id"] = $user["id"];

echo json_encode([
    "status" => true,
    "message" => "HOD login successful",
    "redirect" => "/leave_tih_1/index.php?page=dashboard"
]);


        break;


    // ==========================================
    // INVALID ROLE
    // ==========================================

    default:

        echo json_encode([
            "status" => false,
            "message" => "Invalid login role"
        ]);

        break;

}


// Close connection

$conn->close();

?>