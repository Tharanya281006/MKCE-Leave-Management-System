<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = strtolower(trim($_SESSION['role'] ?? ''));
if ($role !== 'staff') {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Access denied.</div></div>';
    exit;
}
require_once __DIR__ . '/db/connection.php';

date_default_timezone_set('Asia/Kolkata');
$staffId = trim($_SESSION['staff_id'] ?? '');
$today = date('Y-m-d');
$historyStart = date('Y-m-d', strtotime($today . ' -30 days'));
$rows = [];
$sessionKeys = [];

/*
 * 1) Already marked sessions for THIS logged-in staff member.
 * We keep these even if the timetable assignment was later changed.
 */
$markedSql = "SELECT MIN(a.timetable_slot_id) AS slot_id,
                     a.attendance_date, a.period, a.year_name, a.batch,
                     a.section, a.subject
              FROM attendance a
              WHERE a.marked_by = ?
              GROUP BY a.attendance_date, a.period, a.year_name,
                       a.batch, a.section, a.subject
              ORDER BY a.attendance_date DESC,
                       CAST(REPLACE(a.period,'Hour ','') AS UNSIGNED) DESC";
$stmt = $conn->prepare($markedSql);
if ($stmt) {
    $stmt->bind_param('s', $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = $row['attendance_date'] . '|Hour ' . (int)filter_var($row['period'], FILTER_SANITIZE_NUMBER_INT) . '|' . (int)$row['slot_id'];
        $sessionKeys[$key] = true;
        $row['attendance_status'] = 'Marked';
        $rows[] = $row;
    }
    $stmt->close();
}

/*
 * 2) Build the staff member's scheduled sessions for today + previous 30 days.
 * This is what lets History show NOT MARKED even when attendance has zero rows.
 */
$slotSql = "SELECT ts.id AS slot_id, ts.day_name, ts.hour_no,
                   c.course_code AS subject,
                   COALESCE(ya.year_name, CONCAT('Semester ', t.semester)) AS year_name,
                   t.batch, t.section, t.created_at AS timetable_created_at
            FROM timetable_slots ts
            INNER JOIN timetable_sets t ON t.id = ts.timetable_id
            INNER JOIN course_master c ON c.id = ts.course_id
            LEFT JOIN year_advisor ya
              ON ya.department = t.department
             AND ya.batch = t.batch
             AND ya.section = t.section
             AND ya.year_name = '3rd Year'
            WHERE ts.faculty_id = ?
              AND t.status = 'Approved'
              AND t.id = (
                  SELECT MAX(t2.id)
                  FROM timetable_sets t2
                  WHERE t2.department = t.department
                    AND t2.batch = t.batch
                    AND t2.section = t.section
                    AND t2.semester = t.semester
                    AND t2.academic_year = t.academic_year
                    AND t2.status = 'Approved'
              )
            ORDER BY ts.hour_no";

$slots = [];
$stmt = $conn->prepare($slotSql);
if ($stmt) {
    $stmt->bind_param('s', $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($slot = $result->fetch_assoc()) $slots[] = $slot;
    $stmt->close();
}

/* Existing attendance keys, only for this staff member, so status is exact. */
$existingSql = "SELECT timetable_slot_id, attendance_date
                FROM attendance
                WHERE marked_by = ?
                  AND attendance_date BETWEEN ? AND ?
                GROUP BY timetable_slot_id, attendance_date";
$existing = [];
$stmt = $conn->prepare($existingSql);
if ($stmt) {
    $stmt->bind_param('sss', $staffId, $historyStart, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $existing[(int)$r['timetable_slot_id'] . '|' . $r['attendance_date']] = true;
    }
    $stmt->close();
}

$start = new DateTimeImmutable($historyStart, new DateTimeZone('Asia/Kolkata'));
$end = new DateTimeImmutable($today, new DateTimeZone('Asia/Kolkata'));

for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
    $dateStr = $date->format('Y-m-d');
    $dayName = $date->format('l');

    foreach ($slots as $slot) {
        if (strcasecmp($slot['day_name'], $dayName) !== 0) continue;

        /* Do not create a session before that timetable set existed. */
        $createdDate = substr((string)$slot['timetable_created_at'], 0, 10);
        if ($createdDate !== '' && $dateStr < $createdDate) continue;

        $slotKey = (int)$slot['slot_id'] . '|' . $dateStr;
        if (isset($existing[$slotKey])) continue;

        $rowKey = $dateStr . '|Hour ' . (int)$slot['hour_no'] . '|' . (int)$slot['slot_id'];
        if (isset($sessionKeys[$rowKey])) continue;

        $rows[] = [
            'slot_id' => $slot['slot_id'],
            'attendance_date' => $dateStr,
            'period' => 'Hour ' . (int)$slot['hour_no'],
            'year_name' => $slot['year_name'],
            'batch' => $slot['batch'],
            'section' => $slot['section'],
            'subject' => $slot['subject'],
            'attendance_status' => 'Not Marked'
        ];
        $sessionKeys[$rowKey] = true;
    }
}

usort($rows, function ($a, $b) {
    $dateCompare = strcmp($b['attendance_date'], $a['attendance_date']);
    if ($dateCompare !== 0) return $dateCompare;
    $hourA = (int)filter_var($a['period'], FILTER_SANITIZE_NUMBER_INT);
    $hourB = (int)filter_var($b['period'], FILTER_SANITIZE_NUMBER_INT);
    return $hourB <=> $hourA;
});
?>
<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance History</h3>
            <p class="text-muted mb-0">Attendance sessions for <?= htmlspecialchars($staffId, ENT_QUOTES, 'UTF-8') ?> · Marked and Not Marked</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-success" href="ajax/download_attendance_history.php?format=xls" target="_blank"><i class="fas fa-file-excel me-1"></i>Download XLS</a>
            <a class="btn btn-danger" href="ajax/download_attendance_history.php?format=pdf" target="_blank"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="fw-semibold">Attendance Sessions</div>
            <small class="text-muted">Only sessions belonging to the logged-in staff member are shown.</small>
        </div>
        <div class="table-responsive">
            <table id="attendanceHistoryTable" class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>S.No</th><th>Date</th><th>Hour</th><th>Class</th><th>Batch</th>
                    <th>Section</th><th>Subject</th><th>Attendance</th><th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($row['attendance_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['period'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['year_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['batch'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['section'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($row['attendance_status'] === 'Marked'): ?>
                                <span class="badge bg-success">Marked</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Not Marked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['attendance_status'] === 'Marked'): ?>
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=staff_period&slot_id=<?= (int)$row['slot_id'] ?>&date=<?= urlencode($row['attendance_date']) ?>&edit=1">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-primary" href="index.php?page=staff_period&slot_id=<?= (int)$row['slot_id'] ?>&date=<?= urlencode($row['attendance_date']) ?>">
                                    <i class="fas fa-pen me-1"></i>Mark Attendance
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td></td><td class="text-center py-4 text-muted">No attendance sessions found for this staff member.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(function(){
    if ($.fn.DataTable) {
        $('#attendanceHistoryTable').DataTable({
            pageLength: 10,
            order: [[1, 'desc'], [2, 'desc']],
            scrollX: true
        });
    }
});
</script>
