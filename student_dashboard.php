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

$raw_reg_no = trim($_SESSION['reg_no'] ?? $_SESSION['student_id'] ?? '');
$student_profile = cfa_student_profile($conn, $raw_reg_no);

if (!$student_profile) {
    echo '<div class="container-fluid p-4"><div class="alert alert-danger">Student profile not found.</div></div>';
    exit;
}

$advisor_name = 'Not assigned';
$advisor_year = '3rd Year';
$current_semester = (int)($_SESSION['semester'] ?? 5);

$advisor_stmt = $conn->prepare(
    'SELECT ya.year_name,
            COALESCE(NULLIF(sl.staff_name, ""), ya.advisor_staff_id) AS advisor_name
     FROM year_advisor ya
     LEFT JOIN staff_login sl ON sl.staff_id = ya.advisor_staff_id
     WHERE ya.department = ? AND ya.batch = ? AND ya.section = ?
     ORDER BY CASE WHEN ya.year_name = "3rd Year" THEN 0 ELSE 1 END, ya.id DESC
     LIMIT 1'
);
if ($advisor_stmt) {
    $advisor_stmt->bind_param('sss', $student_profile['department'], $student_profile['batch'], $student_profile['section']);
    $advisor_stmt->execute();
    $advisor = $advisor_stmt->get_result()->fetch_assoc();
    if ($advisor) {
        $advisor_name = $advisor['advisor_name'] ?: $advisor_name;
        $advisor_year = $advisor['year_name'] ?: $advisor_year;
    }
    $advisor_stmt->close();
}

$cards = [
    ['class' => 'card-purple', 'icon' => 'fa-user', 'label' => 'Name', 'value' => $student_profile['student_name']],
    ['class' => 'card-teal', 'icon' => 'fa-graduation-cap', 'label' => 'Batch', 'value' => $student_profile['batch']],
    ['class' => 'card-orange', 'icon' => 'fa-building', 'label' => 'Department', 'value' => $student_profile['department'] === 'IT' ? 'Information Technology' : $student_profile['department']],
    ['class' => 'card-red', 'icon' => 'fa-user-tie', 'label' => 'Advisor', 'value' => $advisor_name],
    ['class' => 'card-blue', 'icon' => 'fa-id-card', 'label' => 'Register Number', 'value' => $student_profile['reg_no']],
    ['class' => 'card-slate', 'icon' => 'fa-users', 'label' => 'Section / Semester', 'value' => $student_profile['section'] . ' / Semester ' . $current_semester],
];
?>

<style>
.student-dashboard-wrap { padding: 6px 0 20px; }
.student-dashboard-title { margin-bottom: 20px; }
.student-dashboard-title h3 { font-weight: 700; margin-bottom: 4px; }
.student-dashboard-title p { color:#6c757d; margin:0; }
.student-info-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:18px; }
.student-info-card { position:relative; min-height:136px; border-radius:12px; overflow:hidden; color:#fff; padding:22px 24px; box-shadow:0 6px 18px rgba(0,0,0,.12); display:flex; flex-direction:column; justify-content:center; text-align:center; }
.student-info-card::after { content:""; position:absolute; right:-38px; top:-38px; width:105px; height:105px; transform:rotate(45deg); background:rgba(255,255,255,.10); }
.student-info-card::before { content:""; position:absolute; left:-48px; bottom:-55px; width:110px; height:110px; transform:rotate(45deg); background:rgba(0,0,0,.08); }
.student-info-card .icon { position:relative; z-index:1; font-size:26px; margin-bottom:9px; }
.student-info-card .label { position:relative; z-index:1; font-size:15px; font-weight:600; margin-bottom:7px; }
.student-info-card .value { position:relative; z-index:1; font-size:19px; font-weight:700; word-break:break-word; }
.card-purple{background:linear-gradient(135deg,#5b6ee1,#4930b7)}
.card-teal{background:linear-gradient(135deg,#42c9bb,#18cba5)}
.card-orange{background:linear-gradient(135deg,#ffab2d,#ff8a00)}
.card-red{background:linear-gradient(135deg,#ef4d61,#e9193e)}
.card-blue{background:linear-gradient(135deg,#56bde7,#4399e8)}
.card-slate{background:linear-gradient(135deg,#98a5b9,#69778e)}
@media (max-width: 900px){.student-info-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width: 600px){.student-info-grid{grid-template-columns:1fr;}.student-info-card{min-height:120px;}}
</style>

<div class="container-fluid p-4 student-dashboard-wrap">
    <div class="student-dashboard-title">
        <h3>Welcome, <?= htmlspecialchars($student_profile['student_name'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p>Student Profile</p>
    </div>

    <div class="student-info-grid">
        <?php foreach ($cards as $card): ?>
            <div class="student-info-card <?= htmlspecialchars($card['class'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="icon"><i class="fas <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></div>
                <div class="label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="value"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
