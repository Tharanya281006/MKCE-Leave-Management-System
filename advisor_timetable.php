<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (strtolower(trim($_SESSION['role'] ?? '')) !== 'staff') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/academic_helper.php';

$staff_id = trim($_SESSION['staff_id'] ?? '');
$department = trim($_SESSION['department'] ?? '');
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$hours = range(1,7);
$classes = [];

$stmt = $conn->prepare("SELECT batch, section, year_name FROM year_advisor
        WHERE advisor_staff_id=?
            AND department=?
            AND department='IT'
            AND year_name='3rd Year'
            AND batch='2024-2028'
            AND section IN ('A','B')
        ORDER BY FIELD(section,'A','B')");
$stmt->bind_param('ss', $staff_id, $department);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $classes[] = $row;
$stmt->close();

$batches = [];
foreach ($classes as $c) $batches[$c['batch']][] = $c;

$batch = trim($_GET['batch'] ?? ($classes[0]['batch'] ?? ''));
$selected_year = trim($_GET['year_name'] ?? '');
$selected_section = trim($_GET['section'] ?? '');
$semester = 5;
$academic_year = trim($_GET['academic_year'] ?? '');

/* Section and year are resolved internally from the advisor's selected batch.
   They are intentionally not shown as form fields. */
$section = '';
$year_name = '';
if ($batch !== '' && isset($batches[$batch])) {
    foreach ($batches[$batch] as $class) {
        if (($selected_year === '' || $class['year_name'] === $selected_year)
            && ($selected_section === '' || $class['section'] === $selected_section)) {
            $section = $class['section'];
            $year_name = $class['year_name'];
            break;
        }
    }
    if ($section === '') {
        $section = $batches[$batch][0]['section'];
        $year_name = $batches[$batch][0]['year_name'];
    }
}

/* Keep academic year available internally. If the project has a batch/year value
   such as 2024-2028, use it as the default display value. */
if ($batch !== '') {
    $academic_year = getAcademicYear($batch, $semester) ?: $academic_year;
}

$courses = [];
$existing = [];
$status = 'Draft';
$timetable_id = 0;

if ($batch !== '' && $section !== '' && $academic_year !== '') {
    $course_stmt = $conn->prepare("SELECT DISTINCT c.id, c.course_code, c.course_name
        FROM course_master c
        LEFT JOIN course_faculty_assignments a ON a.course_id=c.id AND a.year_name=? AND a.batch=? AND a.department=? AND a.section=? AND a.status IN ('Active','Assigned')
        LEFT JOIN elective_faculty_assignments e ON e.course_id=c.id AND e.batch=? AND e.section=? AND e.semester=? AND e.academic_year=? AND e.status='Active'
        WHERE c.status='Active' AND (a.id IS NOT NULL OR e.id IS NOT NULL)
        ORDER BY c.course_code");
    $course_stmt->bind_param('ssssssis', $year_name, $batch, $department, $section, $batch, $section, $semester, $academic_year);
    $course_stmt->execute();
    $cr = $course_stmt->get_result();
    while ($row = $cr->fetch_assoc()) $courses[(int)$row['id']] = $row;
    $course_stmt->close();

    $set_stmt = $conn->prepare("SELECT id,status FROM timetable_sets WHERE department=? AND batch=? AND section=? AND semester=? AND academic_year=? LIMIT 1");
    $set_stmt->bind_param('sssis', $department, $batch, $section, $semester, $academic_year);
    $set_stmt->execute();
    $set = $set_stmt->get_result()->fetch_assoc();
    $set_stmt->close();
    if ($set) {
        $timetable_id = (int)$set['id'];
        $status = $set['status'];
        $slot_stmt = $conn->prepare('SELECT day_name,hour_no,course_id FROM timetable_slots WHERE timetable_id=?');
        $slot_stmt->bind_param('i', $timetable_id);
        $slot_stmt->execute();
        $sr = $slot_stmt->get_result();
        while ($slot = $sr->fetch_assoc()) $existing[$slot['day_name']][(int)$slot['hour_no']] = (int)$slot['course_id'];
        $slot_stmt->close();
    }
}

$editable = in_array($status, ['Draft','Rejected'], true);
?>
<div class="container-fluid p-4">
    <div class="mb-4">
        <h3><i class="fas fa-calendar-days me-2"></i>Timetable Design</h3>
        <p class="text-muted mb-0">Design, save, submit and re-edit the timetable for your assigned class.</p>
    </div>

    <?php if (!$classes): ?>
        <div class="alert alert-warning">No advisor classes are assigned to this account.</div>
    <?php else: ?>
        <form method="GET" class="card shadow-sm border-0 mb-4">
            <div class="card-body row g-3 align-items-end">
                <input type="hidden" name="page" value="advisor_timetable"><input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year,ENT_QUOTES,'UTF-8') ?>">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Batch</label>
                    <select name="batch" class="form-select" required>
                        <?php foreach (array_keys($batches) as $b): ?>
                            <option value="<?= htmlspecialchars($b,ENT_QUOTES,'UTF-8') ?>" <?= $batch===$b?'selected':'' ?>><?= htmlspecialchars($b,ENT_QUOTES,'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Year</label>
                    <select name="year_name" class="form-select" required>
                        <?php foreach ($batches[$batch] as $class): ?>
                            <option value="<?= htmlspecialchars($class['year_name'], ENT_QUOTES, 'UTF-8') ?>" <?= $year_name === $class['year_name'] ? 'selected' : '' ?>><?= htmlspecialchars($class['year_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Section</label>
                    <select name="section" class="form-select" required>
                        <?php foreach ($batches[$batch] as $class): ?>
                            <option value="<?= htmlspecialchars($class['section'], ENT_QUOTES, 'UTF-8') ?>" <?= $section === $class['section'] ? 'selected' : '' ?>>Section <?= htmlspecialchars($class['section'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Semester</label>
                    <input type="text" name="semester" class="form-control" value="Semester 5" readonly>
                </div>
                <div class="col-md-2"><button class="btn btn-secondary w-100"><i class="fas fa-search me-1"></i>Load</button></div>
            </div>
        </form>

        <?php if ($batch && $section && $academic_year): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div><h5 class="mb-0">Class Timetable</h5><small class="text-muted">Batch <?= htmlspecialchars($batch) ?> · Semester <?= $semester ?></small></div>
                <span class="badge bg-<?= $status==='Approved'?'success':($status==='Rejected'?'danger':($status==='Pending HOD Approval'?'warning text-dark':'secondary')) ?>">Status: <?= htmlspecialchars($status) ?></span>
            </div>

            <form id="timetableForm">
                <input type="hidden" name="year_name" value="<?= htmlspecialchars($year_name, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="batch" value="<?= htmlspecialchars($batch,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="section" value="<?= htmlspecialchars($section,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="semester" value="<?= $semester ?>">
                <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year,ENT_QUOTES,'UTF-8') ?>">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark"><tr><th>Day</th><?php foreach($hours as $h): ?><th>Hour <?= $h ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach($days as $day): ?><tr><th><?= $day ?></th><?php foreach($hours as $h): ?><td>
                            <select name="slots[<?= $day ?>][<?= $h ?>]" class="form-select form-select-sm timetable-slot" <?= $editable?'':'disabled' ?> >
                                <option value="">Free</option>
                                <?php foreach($courses as $course): ?><option value="<?= (int)$course['id'] ?>" <?= (($existing[$day][$h]??0)===(int)$course['id'])?'selected':'' ?>><?= htmlspecialchars($course['course_code'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?>
                            </select>
                        </td><?php endforeach; ?></tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($status==='Approved'): ?>
                        <button type="button" id="editApproved" class="btn btn-outline-primary"><i class="fas fa-pen me-1"></i>Edit Timetable</button>
                    <?php elseif ($status==='Pending HOD Approval' || $status==='Submitted'): ?>
                        <button type="button" class="btn btn-outline-secondary" disabled><i class="fas fa-clock me-1"></i>Waiting for HOD</button>
                    <?php else: ?>
                        <button class="btn btn-primary" name="action" value="save" type="submit"><i class="fas fa-save me-1"></i>Save Draft</button>
                        <button class="btn btn-success" name="action" value="submit" type="submit"><i class="fas fa-paper-plane me-1"></i>Send to HOD</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function(){
 const form=document.getElementById('timetableForm'); if(!form)return;
 const selects=[...form.querySelectorAll('.timetable-slot')];
 const edit=document.getElementById('editApproved');
 if(edit){ edit.addEventListener('click',async()=>{
   const fd=new FormData(form); fd.append('action','edit');
   const r=await fetch('ajax/timetable_save.php',{method:'POST',body:fd}); const j=await r.json();
   Swal.fire({icon:j.status?'success':'error',text:j.message}).then(()=>{if(j.status)location.reload();});
 }); }
 form.addEventListener('submit',async e=>{e.preventDefault(); const action=e.submitter?.value||'save'; const fd=new FormData(form); fd.append('action',action);
   const r=await fetch('ajax/timetable_save.php',{method:'POST',body:fd}); const j=await r.json();
   Swal.fire({icon:j.status?'success':'error',text:j.message}).then(()=>{if(j.status)location.reload();});
 });
})();
</script>
