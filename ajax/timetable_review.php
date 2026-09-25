<?php

session_start();
header("Content-Type: application/json");
ini_set("display_errors", "0");

if ($_SERVER["REQUEST_METHOD"] !== "POST" || strtolower(trim($_SESSION["role"] ?? "")) !== "hod") {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Unauthorized access."]);
    exit;
}
require_once __DIR__ . "/../db/connection.php";

$timetable_id = (int)($_POST["timetable_id"] ?? 0);
$decision = trim($_POST["decision"] ?? "");
$remarks = trim($_POST["remarks"] ?? "");
$hod_id = trim($_SESSION["staff_id"] ?? "");
$department = trim($_SESSION["department"] ?? "");
if ($timetable_id <= 0 || !in_array($decision, ["approve", "reject"], true) || $hod_id === "" || $department === "") {
    echo json_encode(["status" => false, "message" => "Invalid timetable review data."]);
    exit;
}

$status = $decision === "approve" ? "Approved" : "Rejected";
$set_stmt = $conn->prepare("SELECT t.id,t.batch,t.section,t.semester,t.academic_year
        FROM timetable_sets t
        INNER JOIN year_advisor ya
                ON ya.department=t.department AND ya.batch=t.batch AND ya.section=t.section
             AND ya.year_name='3rd Year'
        WHERE t.id=? AND t.department=? AND t.semester=5 AND t.academic_year='2026-2027'
            AND t.status IN ('Submitted','Pending HOD Approval') LIMIT 1");
$set_stmt->bind_param('is', $timetable_id, $department);
$set_stmt->execute();
$set = $set_stmt->get_result()->fetch_assoc();
$set_stmt->close();
if (!$set) { echo json_encode(['status'=>false,'message'=>'Timetable is not pending review or was already processed.']); exit; }

$conn->begin_transaction();
try {
    if ($decision === 'approve') {
        /* Refresh every slot from the current course/elective faculty assignment before approval. */
        $slot_stmt=$conn->prepare("SELECT id,course_id FROM timetable_slots WHERE timetable_id=?");
        $slot_stmt->bind_param('i',$timetable_id); $slot_stmt->execute(); $sr=$slot_stmt->get_result();
        $slot_update=$conn->prepare("UPDATE timetable_slots SET faculty_id=? WHERE id=?");
        while($slot=$sr->fetch_assoc()){
            $faculty=null;
                        $q=$conn->prepare("SELECT a.faculty_id FROM course_faculty_assignments a
                                WHERE a.course_id=? AND a.year_name='3rd Year' AND a.batch=? AND a.department=? AND a.section=?
                                    AND a.status IN ('Active','Assigned') LIMIT 1");
            $q->bind_param('isss',$slot['course_id'],$set['batch'],$department,$set['section']); $q->execute(); $facultyRow=$q->get_result()->fetch_assoc(); $q->close();
            if($facultyRow) $faculty=$facultyRow['faculty_id'];
            if($faculty===null){
                $q=$conn->prepare("SELECT faculty_id FROM elective_faculty_assignments WHERE course_id=? AND batch=? AND section=? AND semester=? AND academic_year=? AND status='Active' ORDER BY updated_at DESC,id DESC LIMIT 1");
                $q->bind_param('issis',$slot['course_id'],$set['batch'],$set['section'],$set['semester'],$set['academic_year']); $q->execute(); $facultyRow=$q->get_result()->fetch_assoc(); $q->close();
                if($facultyRow) $faculty=$facultyRow['faculty_id'];
            }
            if($faculty===null) throw new RuntimeException('A timetable subject no longer has an active faculty assignment.');
            $slot_update->bind_param('si',$faculty,$slot['id']); $slot_update->execute();
        }
        $slot_stmt->close(); $slot_update->close();
    }
    $stmt = $conn->prepare("UPDATE timetable_sets SET status = ?, hod_remarks = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND department = ? AND status IN ('Submitted','Pending HOD Approval')");
    $stmt->bind_param("sssis", $status, $remarks, $hod_id, $timetable_id, $department);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new RuntimeException('Timetable status update failed.');
    $stmt->close();
    $conn->commit();
    echo json_encode(["status" => true, "message" => "Timetable " . strtolower($status) . "."]);
} catch(Throwable $e) {
    $conn->rollback();
    echo json_encode(['status'=>false,'message'=>$e->getMessage() ?: 'Unable to review timetable.']);
}
