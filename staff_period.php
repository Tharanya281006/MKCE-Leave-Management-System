<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (strtolower(trim($_SESSION['role'] ?? '')) !== 'staff') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}

require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/academic_helper.php';

$staff_id = trim($_SESSION['staff_id'] ?? '');
$slot_id = (int)($_GET['slot_id'] ?? 0);
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
$attendance_date = trim((string)($_GET['date'] ?? $today));
$edit_mode = ((string)($_GET['edit'] ?? '0') === '1');

$date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $attendance_date, new DateTimeZone('Asia/Kolkata'));
$date_errors = DateTimeImmutable::getLastErrors();
$date_invalid = !$date_object || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0)) || $date_object->format('Y-m-d') !== $attendance_date;

if ($date_invalid) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Invalid attendance date.</div></div>';
    exit;
}

if ($attendance_date > $today) {
    echo '<div class="container-fluid p-4"><div class="alert alert-warning"><strong>Future attendance is not allowed.</strong><br>You can mark attendance only up to today (' . htmlspecialchars($today, ENT_QUOTES, 'UTF-8') . ').</div></div>';
    exit;
}

$selected_day = $date_object->format('l');

$slot_sql = "
    SELECT
        ts.id AS slot_id,
        ts.day_name,
        ts.hour_no,
        ts.course_id,
        ts.faculty_id,
        ts.class_type,
        c.course_code,
        c.course_name,
        t.batch,
        t.section,
        t.department,
        t.semester,
        t.academic_year,
        t.status,
        COALESCE(ya.year_name, CONCAT('Semester ', t.semester)) AS year_name
    FROM timetable_slots ts
    INNER JOIN timetable_sets t ON t.id = ts.timetable_id
    INNER JOIN course_master c ON c.id = ts.course_id
    LEFT JOIN year_advisor ya
        ON ya.department = t.department
       AND ya.batch = t.batch
       AND ya.section = t.section
       AND ya.year_name = '3rd Year'
    WHERE ts.id = ?
      AND ts.faculty_id = ?
      AND t.status = 'Approved'
      AND t.id = (
          SELECT MAX(current_set.id)
          FROM timetable_sets current_set
          WHERE current_set.department = t.department
            AND current_set.batch = t.batch
            AND current_set.section = t.section
            AND current_set.semester = t.semester
            AND current_set.academic_year = t.academic_year
            AND current_set.status = 'Approved'
      )
    LIMIT 1";

$slot_stmt = $conn->prepare($slot_sql);
if (!$slot_stmt) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Unable to prepare timetable query.</div></div>';
    exit;
}
$slot_stmt->bind_param('is', $slot_id, $staff_id);
$slot_stmt->execute();
$slot = $slot_stmt->get_result()->fetch_assoc();
$slot_stmt->close();

if (!$slot) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">This timetable slot is not assigned to you or is not approved.</div></div>';
    exit;
}

if (strcasecmp(trim((string)$slot['day_name']), $selected_day) !== 0) {
    echo '<div class="container-fluid p-4"><div class="alert alert-warning"><strong>Date does not match this timetable period.</strong><br>This period is scheduled on ' . htmlspecialchars($slot['day_name'], ENT_QUOTES, 'UTF-8') . '. Select a previous/current ' . htmlspecialchars($slot['day_name'], ENT_QUOTES, 'UTF-8') . ' date.</div></div>';
    exit;
}

$department = trim($slot['department']);
$batch = trim($slot['batch']);
$section = strtoupper(trim($slot['section']));
$student_section = ($section === 'A' || $section === 'B') ? $department . '-' . $section : $section;
$year_name = trim($slot['year_name']);

$students = [];
if ($slot['class_type'] === 'Elective') {
    $roster_stmt = $conn->prepare("SELECT DISTINCT es.reg_no, COALESCE(sl.student_name, es.reg_no) AS student_name, COALESCE(sl.department, es.department) AS department, COALESCE(sl.section, es.section) AS section
        FROM elective_students es
        LEFT JOIN stu_login sl ON sl.reg_no = es.reg_no
        WHERE es.course_id = ? AND es.batch = ? AND (es.section = ? OR es.section = CONCAT(es.department, '-', ?)) AND es.semester = ? AND es.academic_year = ?");
    if ($roster_stmt) {
        $roster_stmt->bind_param('isssis', $slot['course_id'], $batch, $section, $section, $slot['semester'], $slot['academic_year']);
    }
} elseif ($department === 'IT' && ($section === 'A' || $section === 'B')) {
    $range = $section === 'A' ? [1, 63] : [64, 126];
    $roster_stmt = $conn->prepare("SELECT reg_no, student_name, department, batch, section, status
        FROM stu_login
        WHERE department = ? AND batch = ? AND LOWER(TRIM(status)) = 'active'
          AND reg_no LIKE '%BIT%'
          AND CAST(RIGHT(reg_no, 3) AS UNSIGNED) BETWEEN ? AND ?
        ORDER BY CAST(RIGHT(reg_no, 3) AS UNSIGNED)");
    if ($roster_stmt) {
        $roster_stmt->bind_param('ssii', $department, $batch, $range[0], $range[1]);
    }
} else {
    $roster_stmt = $conn->prepare("SELECT reg_no, student_name, department, batch, section, status
        FROM stu_login
        WHERE department = ? AND batch = ? AND section = ? AND LOWER(TRIM(status)) = 'active'
        ORDER BY reg_no ASC");
    if ($roster_stmt) {
        $roster_stmt->bind_param('sss', $department, $batch, $student_section);
    }
}

if (!empty($roster_stmt)) {
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    while ($student = $roster_result->fetch_assoc()) {
        $students[$student['reg_no']] = $student;
    }
    $roster_stmt->close();
}

$attendance = [];
$has_existing_attendance = false;
$attendance_stmt = $conn->prepare("SELECT reg_no, status FROM attendance WHERE timetable_slot_id = ? AND attendance_date = ?");
if ($attendance_stmt) {
    $attendance_stmt->bind_param('is', $slot_id, $attendance_date);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    while ($row = $attendance_result->fetch_assoc()) {
        $attendance[$row['reg_no']] = $row['status'];
    }
    $has_existing_attendance = !empty($attendance);
    $attendance_stmt->close();
}

function leaveStatusForAttendance(mysqli $conn, string $reg_no, string $date, string $durationHour): string
{
    $approvedType = getApprovedLeaveOrOD($conn, $reg_no, $date, $durationHour);
    if ($approvedType === 'OD') return 'OD Approved';
    if ($approvedType === 'LE') return 'Leave Approved';
    return 'No Leave';
}

$attendance_history = [];
$history_stmt = $conn->prepare("SELECT attendance_date, period, year_name, batch, section, subject
    FROM attendance
    WHERE course_id = ? AND faculty_id = ? AND batch = ? AND section = ? AND year_name = ?
    GROUP BY attendance_date, period, year_name, batch, section, subject
    ORDER BY attendance_date DESC, CAST(REPLACE(period, 'Hour ', '') AS UNSIGNED) DESC");
if ($history_stmt) {
    $history_stmt->bind_param('issss', $slot['course_id'], $staff_id, $slot['batch'], $slot['section'], $year_name);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    while ($history_row = $history_result->fetch_assoc()) $attendance_history[] = $history_row;
    $history_stmt->close();
}
?>

<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h3 class="mb-1"><?= $edit_mode ? "Edit Attendance" : "Attendance" ?></h3>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($slot['course_code'] . ' - ' . $slot['course_name'], ENT_QUOTES, 'UTF-8') ?>
                · <?= htmlspecialchars($year_name . ' / ' . $department . '-' . $section . ' / ' . $slot['day_name'] . ' / Hour ' . $slot['hour_no'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <form method="GET" class="d-flex gap-2" id="attendanceDateForm">
            <input type="hidden" name="page" value="staff_period">
            <input type="hidden" name="slot_id" value="<?= (int)$slot_id ?>">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($attendance_date, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" required>
            <button class="btn btn-primary" type="submit"><i class="fas fa-calendar-day"></i></button>
        </form>
    </div>

    <div class="alert alert-info py-2">
        <strong>Attendance date:</strong> <?= htmlspecialchars($attendance_date, ENT_QUOTES, 'UTF-8') ?>
        &nbsp;|&nbsp; Previous dates and today are allowed. Future dates are blocked.
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="mb-3">
                <span class="badge bg-primary">Department: <?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-secondary">Batch: <?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-info">Section: <?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-dark">Students: <?= count($students) ?></span>
            </div>

            <form id="attendanceForm">
                <input type="hidden" name="slot_id" value="<?= (int)$slot_id ?>">
                <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendance_date, ENT_QUOTES, 'UTF-8') ?>">

                <?php if (!empty($students)): ?>
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" id="markAllPresent" class="btn btn-outline-success">
                            <i class="fas fa-user-check me-1"></i> Mark All Present
                        </button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr><th>Department</th><th>Register Number</th><th>Student Name</th><th>Leave Status</th><th>Attendance</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $leave_status = leaveStatusForAttendance($conn, $student['reg_no'], $attendance_date, 'Hour ' . $slot['hour_no']);
                                $approved_status = $leave_status === 'OD Approved' ? 'OD' : ($leave_status === 'Leave Approved' ? 'LE' : '');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['department'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge <?= $approved_status ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($leave_status, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <?php if ($approved_status): ?>
                                            <input type="hidden" name="students[<?= htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8') ?>]" value="<?= $approved_status ?>">
                                            <span class="badge bg-success">Locked: <?= $approved_status === 'OD' ? 'OD' : 'LEAVE' ?></span>
                                        <?php else: ?>
                                            <select name="students[<?= htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8') ?>]" class="form-select form-select-sm">
                                                <option value="Present" <?= ($attendance[$student['reg_no']] ?? '') === 'Present' ? 'selected' : '' ?>>Present</option>
                                                <option value="Absent" <?= ($attendance[$student['reg_no']] ?? '') === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-danger py-4"><strong>No students found for this assigned class.</strong></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($students)): ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> <?= $edit_mode ? "Save Changes" : "Save Attendance" ?></button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!empty($attendance_history)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-1"><i class="fas fa-table me-2 text-primary"></i>Attendance Marked</h5>
            <small class="text-muted">One row = one attendance-marked session. Students are not displayed.</small>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0" id="attendanceMarkedTable">
                <thead class="table-light"><tr><th>S.No</th><th>Date</th><th>Hour</th><th>Class</th><th>Batch</th><th>Section</th><th>Subject</th><th>Attendance</th></tr></thead>
                <tbody>
                <?php foreach ($attendance_history as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td><td><?= htmlspecialchars($row['attendance_date'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['period'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['year_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['batch'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['section'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge bg-success">Marked</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
$(function(){
    $('#markAllPresent').on('click', function(){
        $('#attendanceForm select[name^="students["]').each(function(){ $(this).val('Present'); });
    });
    if ($.fn.DataTable && document.getElementById('attendanceMarkedTable')) {
        $('#attendanceMarkedTable').DataTable({pageLength:10, order:[[1,'desc'],[2,'desc']], scrollX:true});
    }
    $('#attendanceForm').on('submit', function(e){
        e.preventDefault();
        const form = this;
        Swal.fire({
            icon: 'question',
            title: 'Confirm Attendance',
            text: 'Are you sure you want to save this attendance?',
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then(function(result){
            if (!result.isConfirmed) return;
            $.ajax({url:'ajax/attendance_save.php', type:'POST', data:$(form).serialize(), dataType:'json'})
            .done(function(response){
                if(response.status){
                    Swal.fire({icon:'success', title:'Saved', text:response.message, confirmButtonText:'OK'}).then(function(){
                        window.location.href='index.php?page=attendance_history';
                    });
                } else {
                    Swal.fire({icon:'error', title:'Unable to Save', text:response.message});
                }
            })
            .fail(function(xhr){
                let message='Unable to save attendance.';
                try { message = xhr.responseJSON?.message || message; } catch(e) {}
                Swal.fire({icon:'error', title:'Error', text:message});
            });
        });
    });
});
</script>
