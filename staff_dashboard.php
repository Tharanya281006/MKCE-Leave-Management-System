<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (strtolower(trim($_SESSION['role'] ?? '')) !== 'staff') {
    header('Location: /leave_tih_1/index.php?page=dashboard'); exit;
}
require_once __DIR__ . '/db/connection.php';
$staff_id=trim($_SESSION['staff_id']??'');
$department=trim($_SESSION['department']??'');
if($staff_id===''||$department===''){echo '<div class="alert alert-danger">Staff session details are missing.</div>';exit;}

$assigned_courses=[];
$stmt=$conn->prepare("SELECT a.year_name,a.batch,a.department,a.section,c.course_code,c.course_name,c.credits,
        COALESCE(NULLIF(sl.department,''), '') AS faculty_department
    FROM course_faculty_assignments a
    INNER JOIN course_master c ON c.id=a.course_id
    LEFT JOIN staff_login sl ON sl.staff_id=a.faculty_id
    WHERE a.faculty_id=?
      AND a.status IN ('Active','Assigned')
      AND c.status='Active'
    ORDER BY a.batch,a.section,c.course_code");
$stmt->bind_param('s',$staff_id);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$assigned_courses[]=$row;$stmt->close();

$assigned_classes = [];

$stmt = $conn->prepare("
    SELECT
        ya.year_name,
        ya.batch,
        ya.department,
        ya.section,
        ya.advisor_staff_id,
        COALESCE(NULLIF(sl.staff_name, ''), ya.advisor_staff_id) AS advisor_name
    FROM year_advisor ya
    LEFT JOIN staff_login sl
        ON sl.staff_id = ya.advisor_staff_id
    WHERE ya.advisor_staff_id = ?
      AND ya.batch = '2024-2028'
      AND ya.year_name = '3rd Year'
    ORDER BY FIELD(ya.section, 'A', 'B')
");

$stmt->bind_param('s', $staff_id);
$stmt->execute();

$r = $stmt->get_result();

while ($row = $r->fetch_assoc()) {
    $assigned_classes[] = $row;
}

$stmt->close();
?>
<div class="container-fluid p-4">

    <!-- DASHBOARD HEADER -->

    <div
        class="dashboard-welcome-card mb-4"
        style="
            background: linear-gradient(135deg,#dff4ff,#ffffff);
            border-radius: 16px;
            padding: 22px 26px;
            box-shadow: 0 6px 18px rgba(0,0,0,.08);
        "
    >

        <h3
            class="fw-bold mb-1"
            style="color:#40577a;"
        >

            Dashboard

            <small
                class="fw-normal"
                style="font-size:15px;color:#555;"
            >
                (Welcome <?= htmlspecialchars($staff_id, ENT_QUOTES, 'UTF-8') ?>)
            </small>

        </h3>

        <p class="text-muted mb-0">
            Faculty and academic assignment overview
        </p>

    </div>


    <!-- COLORFUL CARDS -->

    <div class="row g-4 mb-4">


        <!-- ASSIGNED SUBJECTS -->

        <div class="col-xl-4 col-md-6">

            <div
                class="dashboard-color-card"
                style="
                    background:linear-gradient(135deg,#5368ea,#4d24b8);
                    color:white;
                    border-radius:16px;
                    min-height:135px;
                    padding:25px;
                    position:relative;
                    overflow:hidden;
                    box-shadow:0 8px 18px rgba(0,0,0,.12);
                "
            >

                <div class="d-flex align-items-center">

                    <i
                        class="fas fa-book fa-2x me-3"
                    ></i>

                    <div>

                        <div class="fw-semibold">
                            Assigned Subjects
                        </div>

                        <div class="fs-3 fw-bold">
                            <?= count($assigned_courses) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- CLASS ADVISORS -->

        <div class="col-xl-4 col-md-6">

            <div
                style="
                    background:linear-gradient(135deg,#42cbb8,#16b89d);
                    color:white;
                    border-radius:16px;
                    min-height:135px;
                    padding:25px;
                    box-shadow:0 8px 18px rgba(0,0,0,.12);
                "
            >

                <div class="d-flex align-items-center">

                    <i
                        class="fas fa-users fa-2x me-3"
                    ></i>

                    <div>

                        <div class="fw-semibold">
                            Class Advisors
                        </div>

                        <div class="fs-3 fw-bold">
                            <?= count($assigned_classes) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- DEPARTMENT -->

        <div class="col-xl-4 col-md-12">

            <div
                style="
                    background:linear-gradient(135deg,#ff9d20,#ff7518);
                    color:white;
                    border-radius:16px;
                    min-height:135px;
                    padding:25px;
                    box-shadow:0 8px 18px rgba(0,0,0,.12);
                "
            >

                <div class="d-flex align-items-center">

                    <i
                        class="fas fa-building fa-2x me-3"
                    ></i>

                    <div>

                        <div class="fw-semibold">
                            Department
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= htmlspecialchars($department) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ASSIGNED SUBJECTS -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">

                <i class="fas fa-book me-2 text-primary"></i>

                Assigned Subjects

            </h5>

        </div>

        <div class="card-body">

            <?php if ($assigned_courses): ?>

                <div class="table-responsive">

                    <table
                        class="table table-hover align-middle mb-0"
                    >

                        <thead class="table-light">

                            <tr>

                                <th>Year</th>
                                <th>Batch</th>
                                <th>Class Department</th>
                                <th>Section</th>
                                <th>Course</th>
                                <th>Credits</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($assigned_courses as $c): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($c['year_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($c['batch']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($c['department']) ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars($c['section']) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($c['course_code']) ?>
                                        </strong>

                                        <br>

                                        <small class="text-muted">
                                            <?= htmlspecialchars($c['course_name']) ?>
                                        </small>

                                    </td>

                                    <td>
                                        <?= (int)$c['credits'] ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="text-center text-muted py-4">

                    <i class="fas fa-book-open fa-2x mb-2"></i>

                    <p class="mb-0">
                        No subjects have been assigned to you yet.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- CLASS ADVISOR -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">

                <i class="fas fa-users me-2 text-primary"></i>

                Class Advisor Assignments

            </h5>

        </div>

        <div class="card-body">

            <div class="row g-3">

                <?php if ($assigned_classes): ?>

                    <?php foreach ($assigned_classes as $c): ?>

                        <div class="col-xl-4 col-md-6">

                            <div
                                class="p-3 h-100"
                                style="
                                    border-radius:14px;
                                    background:#f8f9fc;
                                    border:1px solid #e7eaf0;
                                "
                            >

                                <div
                                    class="fw-bold text-primary mb-2"
                                >

                                    <?= htmlspecialchars($c['department']) ?>

                                    · Section

                                    <?= htmlspecialchars($c['section']) ?>

                                </div>

                                <div class="small text-muted">
                                    Year
                                </div>

                                <div class="fw-semibold mb-2">
                                    <?= htmlspecialchars($c['year_name']) ?>
                                </div>

                                <div class="small text-muted">
                                    Batch
                                </div>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($c['batch']) ?>
                                </div>

                                <div class="small text-muted mt-2">
                                    Advisor
                                </div>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($c['advisor_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="small text-muted mt-2">
                                    Faculty ID
                                </div>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($c['advisor_staff_id'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="col-12">

                        <p class="text-muted mb-0">
                            No class advisor assignment found.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>
