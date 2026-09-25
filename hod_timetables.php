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
$stmt = $conn->prepare("SELECT t.id, t.batch, t.section, t.semester, t.academic_year, t.status, t.updated_at, sl.staff_name FROM timetable_sets t LEFT JOIN staff_login sl ON sl.staff_id = t.advisor_staff_id WHERE t.department = ? AND t.status IN ('Submitted','Pending HOD Approval') ORDER BY t.updated_at DESC");
$stmt->bind_param("s", $department);
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="container-fluid p-4">
    <div class="mb-4"><h3>Timetable Approval</h3><p class="text-muted">Review submitted timetables for <?= htmlspecialchars($department, ENT_QUOTES, "UTF-8") ?>.</p></div>
    <div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-dark"><tr><th>Batch</th><th>Section</th><th>Semester</th><th>Academic Year</th><th>Advisor</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php while ($row = $result->fetch_assoc()): ?><tr><td><?= htmlspecialchars($row["batch"], ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($row["section"], ENT_QUOTES, "UTF-8") ?></td><td><?= (int)$row["semester"] ?></td><td><?= htmlspecialchars($row["academic_year"], ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($row["staff_name"] ?? "", ENT_QUOTES, "UTF-8") ?></td><td><span class="badge bg-warning text-dark"><?= htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8") ?></span></td><td><a class="btn btn-secondary btn-sm" href="index.php?page=hod_timetable_view&amp;timetable_id=<?= (int)$row["id"] ?>"><i class="fas fa-eye"></i></a> <button class="btn btn-success btn-sm reviewTimetable" data-id="<?= (int)$row["id"] ?>" data-decision="approve"><i class="fas fa-check"></i></button> <button class="btn btn-danger btn-sm reviewTimetable" data-id="<?= (int)$row["id"] ?>" data-decision="reject"><i class="fas fa-xmark"></i></button></td></tr><?php endwhile; ?>
    </tbody></table></div></div></div>
</div>
<script>
$(document).on("click", ".reviewTimetable", function () {
    $.ajax({ url: "ajax/timetable_review.php", type: "POST", data: { timetable_id: $(this).data("id"), decision: $(this).data("decision") }, dataType: "json", success: function (response) { Swal.fire({ icon: response.status ? "success" : "error", text: response.message }).then(function () { if (response.status) window.location.reload(); }); }, error: function () { Swal.fire({ icon: "error", text: "Unable to review timetable." }); } });
});
</script>
