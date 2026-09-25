<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (strtolower(trim($_SESSION['role'] ?? '')) !== 'student') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/course_faculty_helper.php';
require_once __DIR__ . '/includes/academic_helper.php';

$reg_no = trim((string)($_SESSION['reg_no'] ?? $_SESSION['student_id'] ?? ''));
$student = cfa_student_profile($conn, $reg_no);

if (!$student) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Student details could not be loaded.</div></div>';
    exit;
}

$semester = (int)($_SESSION['semester'] ?? 5);
if (!in_array($semester, [5, 6], true)) {
    $semester = 5;
}

$academic_year = getAcademicYear($student['batch'], $semester);
$section = cfa_section_key($student['section']);
$year_name = '';

$year_stmt = $conn->prepare(
    "SELECT year_name
     FROM year_advisor
     WHERE department = ?
       AND batch = ?
       AND section = ?
     ORDER BY
       CASE year_name
         WHEN '3rd Year' THEN 1
         WHEN '4th Year' THEN 2
         WHEN '2nd Year' THEN 3
         WHEN '1st Year' THEN 4
         ELSE 5
       END
     LIMIT 1"
);

if ($year_stmt) {
    $year_stmt->bind_param('sss', $student['department'], $student['batch'], $section);
    $year_stmt->execute();
    $year_row = $year_stmt->get_result()->fetch_assoc();
    $year_name = trim((string)($year_row['year_name'] ?? ''));
    $year_stmt->close();
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$hours = range(1, 7);
$slots = [];

$set_stmt = $conn->prepare(
    "SELECT id, status, submitted_at, reviewed_at
     FROM timetable_sets
     WHERE department = ?
       AND batch = ?
       AND section = ?
       AND semester = ?
       AND academic_year = ?
       AND status = 'Approved'
     ORDER BY id DESC
     LIMIT 1"
);

if ($set_stmt) {
    $set_stmt->bind_param(
        'sssis',
        $student['department'],
        $student['batch'],
        $section,
        $semester,
        $academic_year
    );
    $set_stmt->execute();
    $timetable_set = $set_stmt->get_result()->fetch_assoc();
    $set_stmt->close();
} else {
    $timetable_set = null;
}

if (!empty($timetable_set['id'])) {
    $slot_stmt = $conn->prepare(
        "SELECT
            ts.id AS slot_id,
            ts.day_name,
            ts.hour_no,
            ts.class_type,
            c.course_code,
            c.course_name,
            ts.faculty_id,
            COALESCE(NULLIF(sl.staff_name, ''), ts.faculty_id) AS faculty_name
         FROM timetable_slots ts
         INNER JOIN course_master c ON c.id = ts.course_id
         LEFT JOIN staff_login sl ON sl.staff_id = ts.faculty_id
         WHERE ts.timetable_id = ?
         ORDER BY ts.day_name, ts.hour_no"
    );

    if ($slot_stmt) {
        $timetable_id = (int)$timetable_set['id'];
        $slot_stmt->bind_param('i', $timetable_id);
        $slot_stmt->execute();
        $slot_result = $slot_stmt->get_result();

        while ($slot = $slot_result->fetch_assoc()) {
            $slots[$slot['day_name']][(int)$slot['hour_no']] = $slot;
        }

        $slot_stmt->close();
    }
}
?>

<div class="container-fluid p-4">
    <div class="mb-4">
        <h3 class="mb-1">
            <i class="fas fa-calendar-days me-2"></i>
            My Timetable
        </h3>
        <p class="text-muted mb-0">
            Your latest HOD-approved timetable for the class assigned by your advisor.
        </p>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <strong>Department</strong>
                    <div><?= htmlspecialchars($student['department'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="col-md-3">
                    <strong>Year</strong>
                    <div><?= htmlspecialchars($year_name ?: 'Not available', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="col-md-3">
                    <strong>Batch</strong>
                    <div><?= htmlspecialchars($student['batch'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="col-md-3">
                    <strong>Section / Semester</strong>
                    <div>
                        <?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>
                        / Semester <?= $semester ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$timetable_set): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock me-2"></i>
            Your advisor's timetable is not approved by the HOD yet.
        </div>
    <?php else: ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 fw-bold">Approved Timetable</h5>
                    <small class="text-muted">
                        Academic Year <?= htmlspecialchars($academic_year, ENT_QUOTES, 'UTF-8') ?>
                    </small>
                </div>
                <span class="badge bg-success">Approved</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Day</th>
                            <?php foreach ($hours as $hour): ?>
                                <th>Hour <?= $hour ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <th class="table-light"><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?></th>

                                <?php foreach ($hours as $hour): ?>
                                    <?php $slot = $slots[$day][$hour] ?? null; ?>
                                    <td style="min-width: 145px;">
                                        <?php if (!$slot): ?>
                                            <span class="text-muted">Free</span>
                                        <?php else: ?>
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($slot['course_code'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <small class="d-block text-muted">
                                                <?= htmlspecialchars($slot['course_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                            <small class="d-block mt-1">
                                                <?= htmlspecialchars($slot['faculty_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>
