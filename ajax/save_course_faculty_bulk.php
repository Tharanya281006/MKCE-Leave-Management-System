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
    $rawAssignments = (string)($_POST['assignments'] ?? '');

    $class = cfa_resolve_class($conn, $actor, $year, $batch, $section);
    $assignments = json_decode($rawAssignments, true);

    if (!is_array($assignments)) {
        throw new CfaException('Invalid bulk assignment data.', 400, 'INVALID_ASSIGNMENTS');
    }

    $courses = cfa_load_courses($conn, $class['department']);
    $theoryCourses = [];
    foreach ($courses as $course) {
        if ($course['type'] === 'Theory') {
            $theoryCourses[(int)$course['id']] = $course;
        }
    }

    $allFaculty = cfa_load_all_faculty($conn);
    $facultyMap = [];
    foreach ($allFaculty as $row) {
        $facultyMap[trim((string)$row['staff_id'])] = $row;
    }

    $desired = [];
    $usedFaculty = [];

    foreach ($assignments as $item) {
        if (!is_array($item)) {
            throw new CfaException('Invalid assignment row.', 400, 'INVALID_ASSIGNMENT_ROW');
        }

        $courseId = (int)($item['course_id'] ?? 0);
        $facultyId = trim((string)($item['faculty_id'] ?? ''));

        if (!isset($theoryCourses[$courseId])) {
            throw new CfaException('Only active IT theory subjects can be assigned here.', 403, 'COURSE_NOT_ALLOWED');
        }

        if ($facultyId === '') {
            $desired[$courseId] = null;
            continue;
        }

        if (!isset($facultyMap[$facultyId])) {
            throw new CfaException('Selected faculty is not an active IT, CSE or ECE faculty member.', 403, 'FACULTY_NOT_ALLOWED');
        }

        if (isset($usedFaculty[$facultyId])) {
            throw new CfaException(
                'A faculty member can be assigned to only one theory subject in the selected class. Please choose a different faculty.',
                409,
                'FACULTY_ALREADY_ASSIGNED'
            );
        }

        $usedFaculty[$facultyId] = true;
        $desired[$courseId] = $facultyId;
    }

    $lockKey = cfa_lock_key($class);
    if (!cfa_lock($conn, $lockKey, 10)) {
        throw new CfaException('Another course-faculty update is in progress. Please try again.', 409, 'ASSIGNMENT_LOCKED');
    }

    $transactionStarted = false;
    try {
        $conn->begin_transaction();
        $transactionStarted = true;

        // Put all currently active rows for this class into unique temporary IDs.
        // This makes faculty swaps possible in one save (A->B and B->A) without
        // violating the existing UNIQUE KEY while the transaction is in progress.
        $activeRows = cfa_rows(
            $conn,
            "SELECT id FROM course_faculty_assignments
             WHERE year_name = ? AND batch = ? AND department = ? AND section = ?
               AND status IN ('Active', 'Assigned')
             FOR UPDATE",
            'ssss',
            [$class['year_name'], $class['batch'], $class['department'], $class['section']]
        );

        foreach ($activeRows as $row) {
            $temporaryFaculty = '__CFA_TMP_' . (int)$row['id'];
            cfa_exec(
                $conn,
                "UPDATE course_faculty_assignments SET faculty_id = ? WHERE id = ?",
                'si',
                [$temporaryFaculty, (int)$row['id']]
            );
        }

        foreach ($theoryCourses as $courseId => $course) {
            if (!array_key_exists($courseId, $desired)) {
                continue;
            }

            $facultyId = $desired[$courseId];

            $existingRows = cfa_rows(
                $conn,
                "SELECT id FROM course_faculty_assignments
                 WHERE course_id = ? AND year_name = ? AND batch = ? AND department = ? AND section = ?
                 ORDER BY id DESC LIMIT 1
                 FOR UPDATE",
                'issss',
                [$courseId, $class['year_name'], $class['batch'], $class['department'], $class['section']]
            );

            if ($facultyId === null) {
                if ($existingRows) {
                    cfa_exec(
                        $conn,
                        "UPDATE course_faculty_assignments
                         SET status = 'Inactive', assigned_by = ?
                         WHERE id = ?",
                        'si',
                        [$actor['staff_id'], (int)$existingRows[0]['id']]
                    );
                }
                continue;
            }

            if ($existingRows) {
                cfa_exec(
                    $conn,
                    "UPDATE course_faculty_assignments
                     SET faculty_id = ?, assigned_by = ?, status = 'Active', assigned_at = CURRENT_TIMESTAMP
                     WHERE id = ?",
                    'ssi',
                    [$facultyId, $actor['staff_id'], (int)$existingRows[0]['id']]
                );
            } else {
                cfa_exec(
                    $conn,
                    "INSERT INTO course_faculty_assignments
                     (course_id, faculty_id, year_name, batch, department, section, assigned_by, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')",
                    'issssss',
                    [$courseId, $facultyId, $class['year_name'], $class['batch'], $class['department'], $class['section'], $actor['staff_id']]
                );
            }

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
        }

        // Any temporary rows that were not reassigned are inactive remnants.
        foreach ($activeRows as $row) {
            cfa_exec(
                $conn,
                "UPDATE course_faculty_assignments
                 SET status = 'Inactive', assigned_by = ?
                 WHERE id = ? AND faculty_id LIKE '__CFA_TMP_%'",
                'si',
                [$actor['staff_id'], (int)$row['id']]
            );
        }

        $conn->commit();
        $transactionStarted = false;

        cfa_respond([
            'status' => true,
            'message' => 'All course-faculty assignments updated successfully.',
            'state' => cfa_build_state($conn, $actor, $class, $class['department']),
        ]);
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        throw $e;
    } finally {
        cfa_unlock($conn, $lockKey);
    }
} catch (CfaException $e) {
    cfa_respond(['status' => false, 'message' => $e->getMessage(), 'error_code' => $e->errorCode], $e->http);
} catch (Throwable $e) {
    cfa_respond(['status' => false, 'message' => 'Unable to save all course faculty assignments.'], 500);
}
