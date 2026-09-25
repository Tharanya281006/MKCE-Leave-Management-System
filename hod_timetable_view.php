<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (strtolower(trim($_SESSION["role"] ?? "")) !== "hod") {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit;
}
require_once __DIR__ . "/db/connection.php";
$department = trim($_SESSION["department"] ?? "");
$timetable_id = (int)($_GET["timetable_id"] ?? 0);
$stmt = $conn->prepare("SELECT t.batch, t.section, t.semester, t.academic_year, t.status, ts.day_name, ts.hour_no, c.course_code, c.course_name, ts.faculty_id FROM timetable_sets t INNER JOIN timetable_slots ts ON ts.timetable_id = t.id INNER JOIN course_master c ON c.id = ts.course_id WHERE t.id = ? AND t.department = ? AND t.status IN ('Submitted','Pending HOD Approval') ORDER BY FIELD(ts.day_name,'Monday','Tuesday','Wednesday','Thursday','Friday'), ts.hour_no");
$stmt->bind_param("is", $timetable_id, $department);
$stmt->execute();
$result = $stmt->get_result();
$header = $result->fetch_assoc();
$result->data_seek(0);
?>
<div class="container-fluid p-4"><div class="mb-3"><a href="index.php?page=timetable_approval" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a></div><?php if (!$header): ?><div class="alert alert-warning">Timetable not found or is no longer pending.</div><?php else: ?><h3><?= htmlspecialchars($header["batch"] . " / " . $header["section"], ENT_QUOTES, "UTF-8") ?> timetable</h3><p class="text-muted">Semester <?= (int)$header["semester"] ?> | <?= htmlspecialchars($header["academic_year"], ENT_QUOTES, "UTF-8") ?> | <?= htmlspecialchars($header["status"], ENT_QUOTES, "UTF-8") ?></p><div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-bordered"><thead class="table-dark"><tr><th>Day</th><th>Hour</th><th>Subject</th><th>Faculty</th></tr></thead><tbody><?php while ($row = $result->fetch_assoc()): ?><tr><td><?= htmlspecialchars($row["day_name"], ENT_QUOTES, "UTF-8") ?></td><td><?= (int)$row["hour_no"] ?></td><td><?= htmlspecialchars($row["course_code"] . " - " . $row["course_name"], ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($row["faculty_id"], ENT_QUOTES, "UTF-8") ?></td></tr><?php endwhile; ?></tbody></table></div></div></div><?php endif; ?></div>
