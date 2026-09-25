<?php
/**
 * Course & Faculty Assignment helper.
 *
 * Single source of truth for the authority model, shared by:
 *   - ajax/get_faculty_assignments.php   (read the class table)
 *   - ajax/save_course_faculty.php       (assign / change / remove a THEORY course)
 *   - ajax/save_elective_faculty.php     (HOD-only elective)
 *   - advisor_course_faculty.php, hod/course_faculty_assign.php (course_faculty_panel.php)
 *   - student_dashboard.php, staff_dashboard.php
 *
 * Authority model
 *   HOD      : all 6 IT courses. Theory courses through save_course_faculty.php,
 *              the elective (CSC1352) through save_elective_faculty.php.
 *   Advisor  : ONLY the 5 theory courses of a class he/she advises. The elective
 *              is read-only and is rejected by the backend.
 *   Student  : view only.
 *
 * No data is inserted by this file.
 */

const CFA_ELECTIVE_CODES = ['CSC1352'];
const CFA_THEORY_CODES = ['GEA1301', 'GEA1301_2', 'ITB1301', 'ITB1302', 'ITB1303'];

require_once __DIR__ . '/academic_helper.php';

/**
 * Raised for any rejected request. Endpoints translate it into JSON.
 * (exit() would skip the finally-block that releases the assignment lock.)
 */
class CfaException extends Exception
{
    public $http;
    public $errorCode;

    public function __construct($message, $http = 400, $errorCode = 'BAD_REQUEST')
    {
        parent::__construct($message);
        $this->http = (int) $http;
        $this->errorCode = (string) $errorCode;
    }
}

/* ------------------------------------------------------------------ */
/* Small mysqli wrappers                                               */
/* ------------------------------------------------------------------ */

function cfa_query(mysqli $conn, $sql, $types = '', array $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function cfa_rows(mysqli $conn, $sql, $types = '', array $params = [])
{
    $stmt = cfa_query($conn, $sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** Runs INSERT/UPDATE/DELETE and returns affected rows. */
function cfa_exec(mysqli $conn, $sql, $types = '', array $params = [])
{
    $stmt = cfa_query($conn, $sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function cfa_respond(array $payload, $http = 200)
{
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* ------------------------------------------------------------------ */
/* Course code helpers                                                 */
/* ------------------------------------------------------------------ */

/** All six IT course codes in display order (elective first, as in the existing UI). */
function cfa_all_codes()
{
    return array_merge(CFA_ELECTIVE_CODES, CFA_THEORY_CODES);
}

function cfa_is_elective_code($code)
{
    return in_array((string) $code, CFA_ELECTIVE_CODES, true);
}

function cfa_is_theory_code($code)
{
    return in_array((string) $code, CFA_THEORY_CODES, true);
}

/**
 * Section labels are stored as "A" for classes but some student rows use
 * "IT-A" (the project already matches both). Compare on the last token.
 */
function cfa_section_key($section)
{
    $parts = preg_split('/[\s\-_\/]+/', strtoupper(trim((string) $section)), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? (string) end($parts) : '';
}

/* ------------------------------------------------------------------ */
/* Actor / class authorisation                                         */
/* ------------------------------------------------------------------ */

/**
 * Resolves the logged-in staff/HOD from the SESSION and re-checks the account
 * in staff_login, so a deactivated account or a student session is rejected.
 *
 * @return array{role:string,staff_id:string,department:string}
 */
function cfa_actor(mysqli $conn, array $allowedRoles)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = strtolower(trim((string) ($_SESSION['role'] ?? '')));

    if ($role === '') {
        throw new CfaException('Please log in to continue.', 401, 'NOT_AUTHENTICATED');
    }
    if (!in_array($role, $allowedRoles, true)) {
        throw new CfaException('You are not permitted to perform this action.', 403, 'FORBIDDEN_ROLE');
    }

    $staffId = trim((string) ($_SESSION['staff_id'] ?? ''));
    if ($staffId === '') {
        throw new CfaException('Session details are missing. Please log in again.', 401, 'SESSION_INCOMPLETE');
    }

    $rows = cfa_rows(
        $conn,
        "SELECT staff_id, department FROM staff_login
         WHERE staff_id = ? AND role = ? AND status = 'Active' LIMIT 1",
        'ss',
        [$staffId, $role]
    );

    if (!$rows || trim((string) $rows[0]['department']) === '') {
        throw new CfaException('Your account is not active. Please log in again.', 401, 'ACCOUNT_INACTIVE');
    }

    return [
        'role' => $role,
        'staff_id' => $rows[0]['staff_id'],
        'department' => trim($rows[0]['department']),
    ];
}

/**
 * Validates the posted class and returns the CANONICAL year/batch/section as
 * stored in year_advisor (string comparison is case-insensitive, and rows must
 * be written with the stored spelling or they would never match again).
 *
 *   staff : must be the advisor of exactly this class
 *   hod   : the class must belong to the HOD's department
 */
function cfa_resolve_class(mysqli $conn, array $actor, $year, $batch, $section)
{
    $year = trim((string) $year);
    $batch = trim((string) $batch);
    $section = trim((string) $section);

    if ($year === '' || $batch === '' || $section === '') {
        throw new CfaException('Year, batch and section are required.', 400, 'INVALID_CLASS');
    }

    if ($actor['role'] === 'hod') {
        $rows = cfa_rows(
            $conn,
            "SELECT year_name, batch, section FROM year_advisor
             WHERE department = ? AND year_name = ? AND batch = ? AND section = ? LIMIT 1",
            'ssss',
            [$actor['department'], $year, $batch, $section]
        );
    } else {
        $rows = cfa_rows(
            $conn,
            "SELECT year_name, batch, section FROM year_advisor
             WHERE advisor_staff_id = ? AND department = ? AND year_name = ? AND batch = ? AND section = ? LIMIT 1",
            'sssss',
            [$actor['staff_id'], $actor['department'], $year, $batch, $section]
        );
    }

    if (!$rows) {
        throw new CfaException(
            $actor['role'] === 'hod'
                ? 'This class does not exist in your department.'
                : 'You are not the Year Advisor of this class.',
            403,
            'CLASS_NOT_ALLOWED'
        );
    }

    return [
        'department' => $actor['department'],
        'year_name' => $rows[0]['year_name'],
        'batch' => $rows[0]['batch'],
        'section' => $rows[0]['section'],
    ];
}

/* ------------------------------------------------------------------ */
/* Loaders                                                             */
/* ------------------------------------------------------------------ */

/** Load all active courses for a department, each tagged Theory/Elective. */
function cfa_load_courses(mysqli $conn, $department)
{
    $rows = cfa_rows(
        $conn,
        "SELECT id, course_code, course_name, credits FROM course_master
         WHERE status = 'Active' AND department = ?
         ORDER BY course_code",
        's',
        [$department]
    );

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['credits'] = (int) $row['credits'];
        $row['type'] = cfa_is_elective_code($row['course_code']) ? 'Elective' : 'Theory';
    }
    unset($row);

    return $rows;
}

/** Every active teaching staff member of the department (advisors included). */
function cfa_load_faculty(mysqli $conn, $department)
{
    $department = strtoupper(trim((string) $department));

    if (!in_array($department, ['IT', 'CSE', 'ECE'], true)) {
        return [];
    }

    return cfa_rows(
        $conn,
        "SELECT staff_id,
                COALESCE(NULLIF(staff_name, ''), staff_id) AS staff_name,
                department
         FROM staff_login
         WHERE role = 'staff'
           AND status = 'Active'
           AND department = ?
         ORDER BY staff_name, staff_id",
        's',
        [$department]
    );
}

/** Load all active teaching faculty from the supported departments. */
function cfa_load_all_faculty(mysqli $conn)
{
    return cfa_rows(
        $conn,
        "SELECT staff_id,
                COALESCE(NULLIF(staff_name, ''), staff_id) AS staff_name,
                department
         FROM staff_login
         WHERE role = 'staff'
           AND status = 'Active'
           AND department IN ('IT', 'CSE', 'ECE')
         ORDER BY department, staff_name, staff_id",
        '',
        []
    );
}

/** Active theory assignments of one class: course_id => row. Latest row wins. */
function cfa_load_theory_assignments(mysqli $conn, array $class)
{
    $rows = cfa_rows(
        $conn,
        "SELECT a.id,
                a.course_id,
                a.faculty_id,
                a.assigned_by,
                COALESCE(NULLIF(sl.staff_name, ''), a.faculty_id) AS faculty_name,
                COALESCE(NULLIF(sl.department, ''), '') AS faculty_department
         FROM course_faculty_assignments a
         LEFT JOIN staff_login sl ON sl.staff_id = a.faculty_id
         WHERE a.department = ?
           AND a.year_name = ?
           AND a.batch = ?
           AND a.section = ?
           AND a.status IN ('Active', 'Assigned')
         ORDER BY a.id",
        'ssss',
        [$class['department'], $class['year_name'], $class['batch'], $class['section']]
    );

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['course_id']] = $row;
    }
    return $map;
}

/** Return every faculty ID already assigned to another active theory subject of this class. */
function cfa_load_assigned_faculty_ids(mysqli $conn, array $class)
{
    $rows = cfa_rows(
        $conn,
        "SELECT DISTINCT faculty_id
         FROM course_faculty_assignments
         WHERE department = ?
           AND year_name = ?
           AND batch = ?
           AND section = ?
           AND status IN ('Active', 'Assigned')
           AND faculty_id <> ''",
        'ssss',
        [$class['department'], $class['year_name'], $class['batch'], $class['section']]
    );

    return array_values(array_unique(array_map(
        static function ($row) {
            return trim((string) $row['faculty_id']);
        },
        $rows
    )));
}

/**
 * The current (latest semester / academic year) ACTIVE elective assignment for
 * a department class, or null. Removed (Inactive) assignments are ignored.
 */
function cfa_latest_elective(
    mysqli $conn,
    $courseId,
    $department,
    $batch,
    $section = null
) {
    /*
     * Collaborative elective:
     *
     * Section is intentionally NOT used.
     *
     * The HOD assigns one elective class to the
     * entire batch. Students may belong to different
     * departments and different sections.
     */

    $rows = cfa_rows(
        $conn,

        "
        SELECT
            efa.id,
            efa.faculty_id,
            efa.section,
            efa.semester,
            efa.academic_year,

            COALESCE(
                NULLIF(sl.staff_name, ''),
                efa.faculty_id
            ) AS faculty_name

        FROM elective_faculty_assignments efa

        LEFT JOIN staff_login sl
            ON sl.staff_id = efa.faculty_id

        WHERE efa.course_id = ?
          AND efa.department = ?
          AND efa.batch = ?
          AND efa.section = 'ALL'
          AND efa.status = 'Active'

        ORDER BY
            efa.academic_year DESC,
            efa.semester DESC,
            efa.updated_at DESC,
            efa.id DESC

        LIMIT 1
        ",

        'iss',

        [
            (int)$courseId,
            $department,
            $batch
        ]
    );


    return $rows
        ? $rows[0]
        : null;
}

/**
 * Everything the class table needs. The same function feeds the initial load
 * and the response of every save, so the UI always mirrors the database.
 */
function cfa_build_state(mysqli $conn, array $actor, array $class, $facultyDepartment = null)
{
    /*
     * The class department is always the department of the class being advised.
     * For the current IT advisor workflow, the subjects therefore remain IT
     * subjects. The optional facultyDepartment controls only the staff pool.
     */
    $facultyDepartment = strtoupper(trim((string) ($facultyDepartment ?: $class['department'])));
    if (!in_array($facultyDepartment, ['IT', 'CSE', 'ECE'], true)) {
        $facultyDepartment = $class['department'];
    }

    $courses = cfa_load_courses($conn, $class['department']);
    $assigned = cfa_load_theory_assignments($conn, $class);
    $assignedFacultyIds = cfa_load_assigned_faculty_ids($conn, $class);

    $out = [];
    foreach ($courses as $course) {
        $row = [
            'course_id' => $course['id'],
            'course_code' => $course['course_code'],
            'course_name' => $course['course_name'],
            'credits' => $course['credits'],
            'type' => $course['type'],
            'faculty_id' => null,
            'faculty_name' => null,
            'faculty_department' => null,
            'assigned_by' => null,
            'editable' => false,
        ];

        if ($course['type'] === 'Elective') {
            $elective = cfa_latest_elective(
                $conn,
                $course['id'],
                $class['department'],
                $class['batch'],
                $class['section']
            );
            if ($elective) {
                $row['faculty_id'] = $elective['faculty_id'];
                $row['faculty_name'] = $elective['faculty_name'];
            }
        } else {
            if (isset($assigned[$course['id']])) {
                $row['faculty_id'] = $assigned[$course['id']]['faculty_id'];
                $row['faculty_name'] = $assigned[$course['id']]['faculty_name'];
                $row['faculty_department'] = $assigned[$course['id']]['faculty_department'];
                $row['assigned_by'] = $assigned[$course['id']]['assigned_by'];
            }
            $row['editable'] = in_array($actor['role'], ['hod', 'staff'], true);
        }

        $out[] = $row;
    }

    return [
        'role' => $actor['role'],
        'scope' => [
            'department' => $class['department'],
            'year_name' => $class['year_name'],
            'batch' => $class['batch'],
            'section' => $class['section'],
        ],
        'faculty_department' => $facultyDepartment,
        'assigned_faculty_ids' => $assignedFacultyIds,
        'courses' => $out,
        'faculty' => cfa_load_faculty($conn, $facultyDepartment),
        'all_faculty' => cfa_load_all_faculty($conn),
    ];
}

/* ------------------------------------------------------------------ */
/* Assignment lock (serialises concurrent writes for one class)        */
/* ------------------------------------------------------------------ */

function cfa_lock_key(array $class)
{
    return 'cfa:' . md5(strtolower(implode('|', [
        $class['department'], $class['year_name'], $class['batch'], $class['section'],
    ])));
}

function cfa_lock(mysqli $conn, $key, $timeout = 10)
{
    $rows = cfa_rows($conn, 'SELECT GET_LOCK(?, ?) AS got', 'si', [$key, (int) $timeout]);
    return isset($rows[0]['got']) && (int) $rows[0]['got'] === 1;
}

function cfa_unlock(mysqli $conn, $key)
{
    try {
        cfa_rows($conn, 'SELECT RELEASE_LOCK(?) AS released', 's', [$key]);
    } catch (Throwable $ignored) {
        // Named locks are released automatically when the connection closes.
    }
}

/* ------------------------------------------------------------------ */
/* Student view (read-only)                                            */
/* ------------------------------------------------------------------ */

/** Trusted student details from stu_login (the session only holds reg_no/department). */
function cfa_student_profile(mysqli $conn, $regNo)
{
    $rows = cfa_rows(
        $conn,
        "SELECT reg_no, student_name, email, department, batch, section
         FROM stu_login WHERE reg_no = ? AND LOWER(status) = 'active' LIMIT 1",
        's',
        [trim((string) $regNo)]
    );
    if (!$rows) {
        return null;
    }

    $class = getCanonicalStudentClass($rows[0]['reg_no'], $rows[0]['department'], $rows[0]['section']);
    $rows[0]['department'] = $class['department'];
    $rows[0]['section'] = $class['section'];
    return $rows[0];
}

/**
 * Course/faculty view for one student, read from the saved assignments.
 *
 * theory   : the 5 theory courses of the student's department, each with the
 *            currently assigned faculty (null = not assigned yet).
 * elective : CSC1352 with the HOD-assigned faculty (null = not assigned yet).
 *            Resolution: (a) the student's own elective_students enrolment,
 *            otherwise (b) the class-level active elective assignment.
 *
 * @return array{theory:array,elective:array}
 */
function cfa_student_course_view(mysqli $conn, array $profile)
{
    $department = trim($profile['department']);
    $batch = trim($profile['batch']);
    $sectionKey = cfa_section_key($profile['section']);

    /* ---- theory ---- */
    $courses = cfa_load_courses($conn, $department);

    $assignRows = cfa_rows(
        $conn,
        "SELECT a.course_id, a.faculty_id, a.section,
                COALESCE(NULLIF(sl.staff_name, ''), a.faculty_id) AS faculty_name
         FROM course_faculty_assignments a
         LEFT JOIN staff_login sl ON sl.staff_id = a.faculty_id
         WHERE a.department = ? AND a.batch = ? AND a.status IN ('Active', 'Assigned')
         ORDER BY a.assigned_at, a.id",
        'ss',
        [$department, $batch]
    );
    $byCourse = [];
    foreach ($assignRows as $row) {
        if (cfa_section_key($row['section']) === $sectionKey) {
            $byCourse[(int) $row['course_id']] = $row; // later rows override earlier ones
        }
    }

    $theory = [];
    foreach ($courses as $course) {
        if ($course['type'] !== 'Theory') {
            continue;
        }
        $hit = $byCourse[$course['id']] ?? null;
        $theory[] = [
            'course_code' => $course['course_code'],
            'course_name' => $course['course_name'],
            'credits' => $course['credits'],
            'faculty_id' => $hit ? $hit['faculty_id'] : null,
            'faculty_name' => $hit ? $hit['faculty_name'] : null,
        ];
    }

    /* ---- elective ---- */
    $codes = CFA_ELECTIVE_CODES;
    $marks = implode(',', array_fill(0, count($codes), '?'));
    $electiveCourses = cfa_rows(
        $conn,
        "SELECT id, course_code, course_name, credits, department FROM course_master
         WHERE status = 'Active' AND course_code IN ($marks)
         ORDER BY FIELD(course_code, $marks)",
        str_repeat('s', 2 * count($codes)),
        array_merge($codes, $codes)
    );

    $elective = [];
    foreach ($electiveCourses as $course) {
        $courseId = (int) $course['id'];

        $enrolments = cfa_rows(
            $conn,
            "SELECT COUNT(*) AS n FROM elective_students WHERE reg_no = ? AND course_id = ?",
            'si',
            [$profile['reg_no'], $courseId]
        );
        $enrolled = $enrolments && (int) $enrolments[0]['n'] > 0;

        // A student of another department only sees the elective if enrolled in it.
        if (strcasecmp(trim($course['department']), $department) !== 0 && !$enrolled) {
            continue;
        }

        $hit = null;
        if ($enrolled) {
            $rows = cfa_rows(
                $conn,
                "SELECT efa.faculty_id,
                        COALESCE(NULLIF(sl.staff_name, ''), efa.faculty_id) AS faculty_name
                 FROM elective_students es
                 INNER JOIN elective_faculty_assignments efa
                         ON efa.course_id = es.course_id AND efa.batch = es.batch
                        AND efa.section = es.section AND efa.semester = es.semester
                        AND efa.academic_year = es.academic_year AND efa.status = 'Active'
                 LEFT JOIN staff_login sl ON sl.staff_id = efa.faculty_id
                 WHERE es.reg_no = ? AND es.course_id = ?
                 ORDER BY efa.academic_year DESC, efa.semester DESC, efa.updated_at DESC, efa.id DESC
                 LIMIT 1",
                'si',
                [$profile['reg_no'], $courseId]
            );
            $hit = $rows ? $rows[0] : null;
        } else {
            $hit = cfa_latest_elective($conn, $courseId, $department, $batch, $profile['section']);
        }

        $elective[] = [
            'course_code' => $course['course_code'],
            'course_name' => $course['course_name'],
            'credits' => (int) $course['credits'],
            'faculty_id' => $hit ? $hit['faculty_id'] : null,
            'faculty_name' => $hit ? $hit['faculty_name'] : null,
        ];
    }

    return ['theory' => $theory, 'elective' => $elective];
}