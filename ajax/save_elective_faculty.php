<?php

session_start();

ini_set(
    'display_errors',
    '0'
);

require_once __DIR__ . '/../db/connection.php';

require_once __DIR__ .
    '/../includes/course_faculty_helper.php';


if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    cfa_respond(
        [
            'status' => false,
            'message' =>
                'Invalid request method.'
        ],
        405
    );

}


try {

    $actor =
        cfa_actor(
            $conn,
            ['hod']
        );


    $courseId =
        (int)(
            $_POST['course_id'] ?? 0
        );


    $facultyId =
        trim(
            (string)(
                $_POST['faculty_id'] ?? ''
            )
        );


    $batch =
        trim(
            (string)(
                $_POST['batch'] ?? ''
            )
        );


    /*
     * IMPORTANT:
     *
     * No section is accepted from the UI.
     *
     * ALL is the internal identifier
     * for a collaborative elective.
     */

    $section = 'ALL';


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


    $action =
        strtolower(
            trim(
                (string)(
                    $_POST['action'] ?? 'save'
                )
            )
        );


    /*
     * Accept:
     *
     * 927624BIT001
     * 927624BIT002
     *
     * OR
     *
     * 927624BIT001, 927624BIT002
     *
     * OR
     *
     * 927624BIT001 927624BIT002
     */

    $rawStudentText =
        (string)(
            $_POST['reg_nos'] ?? ''
        );


    $rawStudents =
        preg_split(
            '/[\s,]+/',
            $rawStudentText,
            -1,
            PREG_SPLIT_NO_EMPTY
        );


    $regNos =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'trim',
                        $rawStudents
                    )
                )
            )
        );


    /* =====================================================
       VALIDATION
    ====================================================== */

    if (
        $courseId <= 0 ||
        $batch === '' ||
        $semester < 1 ||
        $semester > 8 ||
        $academicYear === ''
    ) {

        throw new CfaException(
            'Course, batch, semester and academic year are required.'
        );

    }


    /* =====================================================
       ONLY CSC1352
    ====================================================== */

    $courses =
        cfa_load_courses(
            $conn,
            $actor['department']
        );


    $course = null;


    foreach (
        $courses as $row
    ) {

        if (
            (int)$row['id']
            === $courseId
        ) {

            $course = $row;

            break;

        }

    }


    if (
        !$course ||
        $course['course_code']
        !== 'CSC1352'
    ) {

        throw new CfaException(
            'Only CSC1352 is allowed in the HOD elective assignment.',
            403,
            'ELECTIVE_NOT_ALLOWED'
        );

    }


    /* =====================================================
       LOCK
    ====================================================== */

    $lockKey =
        'elective:' .
        md5(
            strtolower(
                $actor['department']
                . '|'
                . $courseId
                . '|'
                . $batch
                . '|ALL|'
                . $semester
                . '|'
                . $academicYear
            )
        );


    if (
        !cfa_lock(
            $conn,
            $lockKey,
            10
        )
    ) {

        throw new CfaException(
            'Another elective update is in progress. Please try again.',
            409,
            'ASSIGNMENT_LOCKED'
        );

    }


    try {

        /* =================================================
           REMOVE
        ================================================== */

        if (
            $action === 'remove'
        ) {

            cfa_exec(
                $conn,

                "
                UPDATE elective_faculty_assignments

                SET
                    status = 'Inactive',
                    assigned_by = ?

                WHERE course_id = ?
                  AND department = ?
                  AND batch = ?
                  AND section = 'ALL'
                  AND semester = ?
                  AND academic_year = ?
                ",

                'sissis',

                [
                    $actor['staff_id'],
                    $courseId,
                    $actor['department'],
                    $batch,
                    $semester,
                    $academicYear
                ]
            );


            cfa_respond([
                'status' => true,
                'message' =>
                    'Collaborative elective assignment removed.'
            ]);

        }


        /* =================================================
           FACULTY VALIDATION
        ================================================== */

        if (
            $facultyId === ''
        ) {

            throw new CfaException(
                'Please select a faculty member.'
            );

        }


        if (
            !$regNos
        ) {

            throw new CfaException(
                'Please select at least one student.'
            );

        }


        $faculty =
            cfa_rows(
                $conn,

                "
                SELECT staff_id

                FROM staff_login

                WHERE staff_id = ?
                  AND role = 'staff'
                  AND status = 'Active'
                  AND department = ?

                LIMIT 1
                ",

                'ss',

                [
                    $facultyId,
                    $actor['department']
                ]
            );


        if (
            !$faculty
        ) {

            throw new CfaException(
                'Invalid faculty.',
                403,
                'FACULTY_NOT_ALLOWED'
            );

        }


        /* =================================================
           VALIDATE STUDENTS

           IMPORTANT:
           Only batch is checked.

           Department and section are NOT checked,
           because this is collaborative.
        ================================================== */

        $studentCheck =
            $conn->prepare(
                "
                SELECT
                    reg_no,
                    department,
                    batch,
                    section

                FROM stu_login

                WHERE reg_no = ?
                  AND batch = ?
                  AND LOWER(status) = 'active'

                LIMIT 1
                "
            );


        if (
            !$studentCheck
        ) {

            throw new RuntimeException(
                'Unable to validate students.'
            );

        }


        /* =================================================
           INSERT / UPDATE ASSIGNMENT
        ================================================== */

        $assignment =
            $conn->prepare(
                "
                INSERT INTO
                    elective_faculty_assignments
                (
                    course_id,
                    faculty_id,
                    department,
                    batch,
                    section,
                    semester,
                    academic_year,
                    assigned_by,
                    status
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'ALL',
                    ?,
                    ?,
                    ?,
                    'Active'
                )

                ON DUPLICATE KEY UPDATE

                    faculty_id =
                        VALUES(faculty_id),

                    assigned_by =
                        VALUES(assigned_by),

                    status =
                        'Active',

                    updated_at =
                        CURRENT_TIMESTAMP
                "
            );


        if (
            !$assignment
        ) {

            throw new RuntimeException(
                'Unable to prepare elective assignment.'
            );

        }


        /* =================================================
           STUDENT INSERT
        ================================================== */

        $studentInsert =
            $conn->prepare(
                "
                INSERT INTO
                    elective_students
                (
                    course_id,
                    reg_no,
                    batch,
                    section,
                    department,
                    semester,
                    academic_year
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )

                ON DUPLICATE KEY UPDATE

                    batch =
                        VALUES(batch),

                    section =
                        VALUES(section),

                    department =
                        VALUES(department)
                "
            );


        if (
            !$studentInsert
        ) {

            throw new RuntimeException(
                'Unable to prepare student mapping.'
            );

        }


        /* =================================================
           TRANSACTION
        ================================================== */

        $conn->begin_transaction();


        try {

            /*
             * Save / update faculty assignment.
             */

            $assignment->bind_param(
                'isssiss',
                $courseId,
                $facultyId,
                $actor['department'],
                $batch,
                $semester,
                $academicYear,
                $actor['staff_id']
            );


            /*
             * Above has 7 values after the type string,
             * so use the correct complete bind below.
             */

            $assignment->close();


            $assignment =
                $conn->prepare(
                    "
                    INSERT INTO
                        elective_faculty_assignments
                    (
                        course_id,
                        faculty_id,
                        department,
                        batch,
                        section,
                        semester,
                        academic_year,
                        assigned_by,
                        status
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'ALL',
                        ?,
                        ?,
                        ?,
                        'Active'
                    )

                    ON DUPLICATE KEY UPDATE

                        faculty_id =
                            VALUES(faculty_id),

                        assigned_by =
                            VALUES(assigned_by),

                        status =
                            'Active',

                        updated_at =
                            CURRENT_TIMESTAMP
                    "
                );


            $assignment->bind_param(
                'isssiss',
                $courseId,
                $facultyId,
                $actor['department'],
                $batch,
                $semester,
                $academicYear,
                $actor['staff_id']
            );


            if (
                !$assignment->execute()
            ) {

                throw new RuntimeException(
                    'Unable to save elective faculty assignment.'
                );

            }


            /*
             * IMPORTANT:
             *
             * Remove the old student mapping for this
             * exact elective/batch/semester/year.
             *
             * This makes EDIT actually synchronize
             * the student list.
             */

            $deleteStudents =
                $conn->prepare(
                    "
                    DELETE FROM elective_students

                    WHERE course_id = ?
                      AND batch = ?
                      AND semester = ?
                      AND academic_year = ?
                    "
                );


            $deleteStudents->bind_param(
                'isis',
                $courseId,
                $batch,
                $semester,
                $academicYear
            );


            if (
                !$deleteStudents->execute()
            ) {

                throw new RuntimeException(
                    'Unable to update student mapping.'
                );

            }


            $deleteStudents->close();


            /*
             * Insert the newly selected students.
             */

            foreach (
                $regNos as $regNo
            ) {

                $studentCheck->bind_param(
                    'ss',
                    $regNo,
                    $batch
                );


                $studentCheck->execute();


                $student =
                    $studentCheck
                        ->get_result()
                        ->fetch_assoc();


                if (
                    !$student
                ) {

                    throw new RuntimeException(
                        "Student {$regNo} is not an active member of batch {$batch}."
                    );

                }


                $studentInsert->bind_param(
                    'issssis',
                    $courseId,
                    $student['reg_no'],
                    $student['batch'],
                    $student['section'],
                    $student['department'],
                    $semester,
                    $academicYear
                );


                if (
                    !$studentInsert->execute()
                ) {

                    throw new RuntimeException(
                        "Unable to map student {$regNo}."
                    );

                }

            }


            $conn->commit();


        } catch (
            Throwable $e
        ) {

            $conn->rollback();

            throw $e;

        } finally {

            if (
                $assignment
            ) {

                $assignment->close();

            }

            $studentCheck->close();

            $studentInsert->close();

        }


        cfa_respond([
            'status' => true,
            'message' =>
                'Collaborative elective faculty and student mapping saved successfully.'
        ]);


    } finally {

        cfa_unlock(
            $conn,
            $lockKey
        );

    }


} catch (
    CfaException $e
) {

    cfa_respond(
        [
            'status' => false,
            'message' =>
                $e->getMessage(),
            'error_code' =>
                $e->errorCode
        ],
        $e->http
    );


} catch (
    Throwable $e
) {

    cfa_respond(
        [
            'status' => false,
            'message' =>
                $e->getMessage()
                    ?: 'Unable to save elective assignment.'
        ],
        500
    );

}