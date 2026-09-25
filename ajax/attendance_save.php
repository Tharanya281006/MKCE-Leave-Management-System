<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strtolower(trim($_SESSION['role'] ?? '')) !== 'staff') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/academic_helper.php';

$staff_id = trim($_SESSION['staff_id'] ?? '');
$slot_id = (int)($_POST['slot_id'] ?? 0);
$attendance_date = trim((string)($_POST['attendance_date'] ?? ''));
$students = $_POST['students'] ?? [];
$tz = new DateTimeZone('Asia/Kolkata');
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $attendance_date, $tz);
$date_errors = DateTimeImmutable::getLastErrors();

if ($slot_id <= 0 || !$date || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0)) || $date->format('Y-m-d') !== $attendance_date || !is_array($students)) {
    echo json_encode(['status' => false, 'message' => 'Invalid attendance data.']);
    exit;
}
if ($attendance_date > $today) {
    echo json_encode(['status' => false, 'message' => 'Future attendance cannot be marked. You can mark attendance only up to today (' . $today . ').']);
    exit;
}

$slot_sql = "
    SELECT ts.course_id, ts.class_type, ts.hour_no, ts.day_name, c.course_code, t.batch, t.section, t.department, t.semester, t.academic_year,
           COALESCE(ya.year_name, CONCAT('Semester ', t.semester)) AS year_name
    FROM timetable_slots ts
    INNER JOIN timetable_sets t ON t.id = ts.timetable_id
    INNER JOIN course_master c ON c.id = ts.course_id
    LEFT JOIN year_advisor ya ON ya.department=t.department AND ya.batch=t.batch AND ya.section=t.section AND ya.year_name='3rd Year'
    WHERE ts.id = ?
      AND ts.faculty_id = ?
      AND t.status = 'Approved'
      AND t.id = (
          SELECT MAX(current_set.id)
          FROM timetable_sets current_set
          WHERE current_set.department=t.department
            AND current_set.batch=t.batch
            AND current_set.section=t.section
            AND current_set.semester=t.semester
            AND current_set.academic_year=t.academic_year
            AND current_set.status='Approved'
      )
    LIMIT 1";
$slot_stmt = $conn->prepare($slot_sql);
if (!$slot_stmt) {
    echo json_encode(['status' => false, 'message' => 'Unable to validate timetable slot.']);
    exit;
}
$slot_stmt->bind_param('is', $slot_id, $staff_id);
$slot_stmt->execute();
$slot = $slot_stmt->get_result()->fetch_assoc();
$slot_stmt->close();

if (!$slot) {
    echo json_encode(['status' => false, 'message' => 'You are not assigned to this approved timetable slot.']);
    exit;
}

if (strcasecmp(trim((string)$slot['day_name']), $date->format('l')) !== 0) {
    echo json_encode(['status' => false, 'message' => 'The selected date does not match this timetable period. This period is scheduled on ' . $slot['day_name'] . '.']);
    exit;
}

$valid_students = [];
$department = trim($slot['department']);
$batch = trim($slot['batch']);
$section = strtoupper(trim($slot['section']));
$student_section = ($section === 'A' || $section === 'B') ? $department . '-' . $section : $section;

if ($slot['class_type'] === 'Elective') {
    $roster_stmt = $conn->prepare("SELECT DISTINCT es.reg_no, COALESCE(sl.student_name, es.reg_no) AS student_name, COALESCE(sl.department, es.department) AS department, COALESCE(sl.section, es.section) AS section FROM elective_students es LEFT JOIN stu_login sl ON sl.reg_no=es.reg_no WHERE es.course_id=? AND es.batch=? AND (es.section=? OR es.section=CONCAT(es.department,'-',?)) AND es.semester=? AND es.academic_year=?");
    if ($roster_stmt) $roster_stmt->bind_param('isssis', $slot['course_id'], $batch, $section, $section, $slot['semester'], $slot['academic_year']);
} elseif ($department === 'IT' && ($section === 'A' || $section === 'B')) {
    $range = $section === 'A' ? [1,63] : [64,126];
    $roster_stmt = $conn->prepare("SELECT reg_no, student_name, department, section FROM stu_login WHERE department=? AND batch=? AND LOWER(TRIM(status))='active' AND reg_no LIKE '%BIT%' AND CAST(RIGHT(reg_no,3) AS UNSIGNED) BETWEEN ? AND ? ORDER BY CAST(RIGHT(reg_no,3) AS UNSIGNED)");
    if ($roster_stmt) $roster_stmt->bind_param('ssii', $department, $batch, $range[0], $range[1]);
} else {
    $roster_stmt = $conn->prepare("SELECT reg_no, student_name, department, section FROM stu_login WHERE department=? AND batch=? AND section=? AND LOWER(TRIM(status))='active' ORDER BY reg_no ASC");
    if ($roster_stmt) $roster_stmt->bind_param('sss', $department, $batch, $student_section);
}

if (empty($roster_stmt)) {
    echo json_encode(['status' => false, 'message' => 'Unable to load the class student roster.']);
    exit;
}
$roster_stmt->execute();
$roster_result = $roster_stmt->get_result();
while ($row = $roster_result->fetch_assoc()) $valid_students[$row['reg_no']] = $row;
$roster_stmt->close();

if (!$valid_students) {
    echo json_encode(['status' => false, 'message' => 'No active students were found for this class.']);
    exit;
}

$upsert = $conn->prepare("INSERT INTO attendance (timetable_slot_id, reg_no, student_name, year_name, batch, section, attendance_date, period, subject, status, marked_by, course_id, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by), timetable_slot_id=VALUES(timetable_slot_id), course_id=VALUES(course_id), faculty_id=VALUES(faculty_id)");
if (!$upsert) {
    echo json_encode(['status' => false, 'message' => 'Unable to prepare attendance save.']);
    exit;
}

$valid_statuses = ['Present','Absent'];
$conn->begin_transaction();
try {
    foreach ($students as $reg_no => $status) {
        $reg_no = trim((string)$reg_no);
        $status = trim((string)$status);
        if (!isset($valid_students[$reg_no])) throw new RuntimeException('Invalid student selected for this class.');

        $approved_status = getApprovedLeaveOrOD($conn, $reg_no, $attendance_date, 'Hour ' . (int)$slot['hour_no']);
        if ($approved_status !== '') {
            if ($status !== $approved_status) throw new RuntimeException('Approved Leave/OD attendance is locked for this student and date.');
        } elseif (!in_array($status, $valid_statuses, true)) {
            throw new RuntimeException('Invalid attendance status.');
        }

        $student_name = $valid_students[$reg_no]['student_name'] ?? $reg_no;
        $period = 'Hour ' . (int)$slot['hour_no'];
        $subject = $slot['course_code'];
        $year_name = $slot['year_name'];
        $types = 'i' . str_repeat('s', 10) . 'is';
        $upsert->bind_param($types, $slot_id, $reg_no, $student_name, $year_name, $slot['batch'], $slot['section'], $attendance_date, $period, $subject, $status, $staff_id, $slot['course_id'], $staff_id);
        if (!$upsert->execute()) throw new RuntimeException('Unable to save attendance.');
    }
    $conn->commit();
    echo json_encode(['status' => true, 'message' => 'Attendance saved successfully for ' . $attendance_date . '.']);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
$upsert->close();
