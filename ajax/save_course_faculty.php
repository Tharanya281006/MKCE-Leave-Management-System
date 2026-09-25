<?php
session_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/course_faculty_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cfa_respond(['status' => false, 'message' => 'Invalid request method.'], 405);
}

try {
    $actor = cfa_actor($conn, ['staff', 'hod']);

    $year = trim((string)($_POST['year_name'] ?? ''));
    $batch = trim((string)($_POST['batch'] ?? ''));
    $section = trim((string)($_POST['section'] ?? ''));
    $facultyDepartment = strtoupper(trim((string)($_POST['faculty_department'] ?? $actor['department'])));
    $courseId = (int)($_POST['course_id'] ?? 0);
    $facultyId = trim((string)($_POST['faculty_id'] ?? ''));
    $action = strtolower(trim((string)($_POST['action'] ?? 'save')));

    $class = cfa_resolve_class($conn, $actor, $year, $batch, $section);

    if (!in_array($facultyDepartment, ['IT', 'CSE', 'ECE'], true)) {
        throw new CfaException('Please select a valid faculty department.', 400, 'INVALID_FACULTY_DEPARTMENT');
    }

    $courses = cfa_load_courses($conn, $class['department']);
    $selectedCourse = null;
    foreach ($courses as $course) {
        if ((int)$course['id'] === $courseId) {
            $selectedCourse = $course;
            break;
        }
    }

    if (!$selectedCourse || $selectedCourse['type'] !== 'Theory') {
        throw new CfaException('Only active theory courses can be assigned here. Collaborative electives are managed by the HOD.', 403, 'COURSE_NOT_ALLOWED');
    }

    $lockKey = cfa_lock_key($class);
    if (!cfa_lock($conn, $lockKey, 10)) {
        throw new CfaException('Another course-faculty update is in progress. Please try again.', 409, 'ASSIGNMENT_LOCKED');
    }

    try {
        if ($action === 'remove') {
            cfa_exec(
                $conn,
                "UPDATE course_faculty_assignments
                 SET status = 'Inactive', assigned_by = ?
                 WHERE course_id = ? AND year_name = ? AND batch = ? AND department = ? AND section = ?",
                'sissss',
                [$actor['staff_id'], $courseId, $class['year_name'], $class['batch'], $class['department'], $class['section']]
            );

            cfa_respond([
                'status' => true,
                'message' => $selectedCourse['course_code'] . ' assignment removed.',
                'state' => cfa_build_state($conn, $actor, $class, $facultyDepartment),
            ]);
        }

        if ($facultyId === '') {
            throw new CfaException('Please select a faculty member.', 400, 'FACULTY_REQUIRED');
        }

        $facultyRows = cfa_rows(
            $conn,
            "SELECT staff_id FROM staff_login
             WHERE staff_id = ? AND role = 'staff' AND status = 'Active' AND department = ? LIMIT 1",
            'ss',
            [$facultyId, $facultyDepartment]
        );
        if (!$facultyRows) {
            throw new CfaException('The selected faculty is not an active faculty member of the selected department.', 403, 'FACULTY_NOT_ALLOWED');
        }

        /* One faculty member can teach only one theory subject in the same class.
         * This is enforced on the server as well as in the UI so the rule cannot be
         * bypassed by submitting a manual request. The current course is excluded
         * so that its existing faculty can be edited/re-saved.
         */
        $alreadyUsed = cfa_rows(
            $conn,
            "SELECT id, course_id
             FROM course_faculty_assignments
             WHERE faculty_id = ?
               AND year_name = ?
               AND batch = ?
               AND department = ?
               AND section = ?
               AND status IN ('Active', 'Assigned')
               AND course_id <> ?
             LIMIT 1",
            'sssss' . 'i',
            [$facultyId, $class['year_name'], $class['batch'], $class['department'], $class['section'], $courseId]
        );
        if ($alreadyUsed) {
            throw new CfaException(
                'This faculty is already assigned to another subject in the selected class. Please choose another faculty.',
                409,
                'FACULTY_ALREADY_ASSIGNED'
            );
        }

        $existing = cfa_rows(
            $conn,
            "SELECT id FROM course_faculty_assignments
             WHERE course_id = ? AND year_name = ? AND batch = ? AND department = ? AND section = ? LIMIT 1",
            'issss',
            [$courseId, $class['year_name'], $class['batch'], $class['department'], $class['section']]
        );

        if ($existing) {
            cfa_exec(
                $conn,
                "UPDATE course_faculty_assignments
                 SET faculty_id = ?, assigned_by = ?, status = 'Active', assigned_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                'ssi',
                [$facultyId, $actor['staff_id'], (int)$existing[0]['id']]
            );
            $message = $selectedCourse['course_code'] . ' faculty updated successfully.';
        } else {
            cfa_exec(
                $conn,
                "INSERT INTO course_faculty_assignments
                 (course_id, faculty_id, year_name, batch, department, section, assigned_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')",
                'issssss',
                [$courseId, $facultyId, $class['year_name'], $class['batch'], $class['department'], $class['section'], $actor['staff_id']]
            );
            $message = $selectedCourse['course_code'] . ' faculty assigned successfully.';
        }

        /* Keep existing timetable slots synchronized with the current faculty assignment. */
        cfa_exec(
            $conn,
            "UPDATE timetable_slots ts
             INNER JOIN timetable_sets t ON t.id = ts.timetable_id
             SET ts.faculty_id = ?
             WHERE ts.course_id = ? AND t.department = ? AND t.batch = ? AND t.section = ?
               AND t.status IN ('Draft','Submitted','Pending HOD Approval','Approved')",
            'sisss',
            [$facultyId, $courseId, $class['department'], $class['batch'], $class['section']]
        );

        cfa_respond([
            'status' => true,
            'message' => $message,
            'state' => cfa_build_state($conn, $actor, $class, $facultyDepartment),
        ]);
    } finally {
        cfa_unlock($conn, $lockKey);
    }
} catch (CfaException $e) {
    cfa_respond(['status' => false, 'message' => $e->getMessage(), 'error_code' => $e->errorCode], $e->http);
} catch (Throwable $e) {
    cfa_respond(['status' => false, 'message' => 'Unable to save course faculty assignment.'], 500);
}
