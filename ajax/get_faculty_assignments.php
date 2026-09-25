<?php
session_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/course_faculty_helper.php';

try {
    $actor = cfa_actor($conn, ['staff', 'hod']);
    $class = cfa_resolve_class(
        $conn,
        $actor,
        $_REQUEST['year_name'] ?? '',
        $_REQUEST['batch'] ?? '',
        $_REQUEST['section'] ?? ''
    );

    $facultyDepartment = strtoupper(trim((string)($_REQUEST['faculty_department'] ?? $class['department'])));
    if (!in_array($facultyDepartment, ['IT', 'CSE', 'ECE'], true)) {
        throw new CfaException('Invalid faculty department selected.', 400, 'INVALID_FACULTY_DEPARTMENT');
    }

    cfa_respond([
        'status' => true,
        'state' => cfa_build_state($conn, $actor, $class, $facultyDepartment)
    ]);
} catch (CfaException $e) {
    cfa_respond(['status' => false, 'message' => $e->getMessage(), 'error_code' => $e->errorCode], $e->http);
} catch (Throwable $e) {
    cfa_respond(['status' => false, 'message' => 'Unable to load course faculty assignments.'], 500);
}
