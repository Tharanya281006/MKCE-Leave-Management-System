<?php

session_start();

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');

require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/../includes/course_faculty_helper.php';


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    strtolower(trim($_SESSION['role'] ?? '')) !== 'hod'
) {

    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}


try {

    $actor =
        cfa_actor(
            $conn,
            ['hod']
        );


    $batch =
        trim(
            (string)(
                $_POST['batch'] ?? ''
            )
        );


    $courseId =
        (int)(
            $_POST['course_id'] ?? 0
        );


    $semester =
        (int)(
            $_POST['semester'] ?? 0
        );


    $academicYear =
        trim(
            (string)(
                $_POST['academic_year'] ?? ''
            )
        );


    if ($batch === '') {

        throw new CfaException(
            'Batch is required.'
        );

    }


    /* =====================================================
       LOAD ALL ACTIVE STUDENTS OF THE BATCH

       IMPORTANT:
       NO SECTION FILTER.
       NO DEPARTMENT FILTER.

       This is intentional because the elective
       is collaborative across departments.
    ====================================================== */

    $students =
        cfa_rows(
            $conn,

            "
            SELECT
                reg_no,
                student_name,
                department,
                section

            FROM stu_login

            WHERE batch = ?
              AND LOWER(status) = 'active'

            ORDER BY
                department,
                section,
                reg_no
            ",

            's',

            [$batch]
        );


    /* =====================================================
       LOAD EXISTING ELECTIVE STUDENTS
       FOR EDIT MODE
    ====================================================== */

    $selected = [];


    if (
        $courseId > 0 &&
        $semester >= 1 &&
        $semester <= 8 &&
        $academicYear !== ''
    ) {

        $selectedRows =
            cfa_rows(
                $conn,

                "
                SELECT reg_no
                FROM elective_students

                WHERE course_id = ?
                  AND batch = ?
                  AND semester = ?
                  AND academic_year = ?
                ",

                'isis',

                [
                    $courseId,
                    $batch,
                    $semester,
                    $academicYear
                ]
            );


        foreach ($selectedRows as $row) {

            $selected[] =
                $row['reg_no'];

        }

    }


    echo json_encode([

        'status' => true,

        'students' => $students,

        'selected' => $selected

    ], JSON_UNESCAPED_UNICODE);

} catch (CfaException $e) {

    http_response_code(
        $e->http
    );

    echo json_encode([

        'status' => false,

        'message' => $e->getMessage(),

        'error_code' =>
            $e->errorCode

    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'status' => false,

        'message' =>
            'Unable to load students.'

    ]);

}