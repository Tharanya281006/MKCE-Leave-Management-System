<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db/connection.php';

if (
    strtolower(trim($_SESSION['role'] ?? '')) !== 'hod'
) {

    header('Location: index.php?page=dashboard');

    exit;

}


$department =
    trim($_SESSION['department'] ?? '');


/* ---------------------------------------------------------
   LEAVE REQUEST COUNT
--------------------------------------------------------- */

$pendingLeaves = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM leave_requests
    WHERE advisor_status = 1
      AND forwarded_to_hod = 1
      AND hod_status = 0
");

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $pendingLeaves = (int)$row['total'];
}

$stmt->close();


/* ---------------------------------------------------------
   COURSE ASSIGNMENT COUNT
--------------------------------------------------------- */

$courseAssignments = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_faculty_assignments
    WHERE department = ?
      AND status IN ('Active','Assigned')
");

$stmt->bind_param(
    's',
    $department
);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $courseAssignments = (int)$row['total'];
}

$stmt->close();


/* ---------------------------------------------------------
   ELECTIVE ASSIGNMENT COUNT
--------------------------------------------------------- */

$electiveAssignments = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM elective_faculty_assignments
    WHERE department = ?
      AND status = 'Active'
");

$stmt->bind_param(
    's',
    $department
);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $electiveAssignments = (int)$row['total'];
}

$stmt->close();


/* ---------------------------------------------------------
   TIMETABLE COUNT
--------------------------------------------------------- */

$timetableCount = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM timetable_sets
    WHERE department = ?
");

$stmt->bind_param(
    's',
    $department
);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $timetableCount = (int)$row['total'];
}

$stmt->close();

?>


<div class="container-fluid p-4">


    <!-- WELCOME -->

    <div
        class="mb-4"
        style="
            background:linear-gradient(
                135deg,
                #dff4ff,
                #ffffff
            );
            border-radius:16px;
            padding:22px 26px;
            box-shadow:0 6px 18px rgba(0,0,0,.08);
        "
    >

        <h3
            class="fw-bold mb-1"
            style="color:#40577a;"
        >

            Dashboard

            <small
                class="fw-normal"
                style="
                    font-size:15px;
                    color:#555;
                "
            >

                (HOD)

            </small>

        </h3>

        <p class="text-muted mb-0">

            <?= htmlspecialchars($department) ?>

            Department Academic Management

        </p>

    </div>


    <!-- DASHBOARD CARDS -->

    <div class="row g-4 mb-4">


        <!-- LEAVE -->

        <div class="col-xl-3 col-md-6">

            <a
                href="index.php?page=leave_requests"
                class="text-decoration-none"
            >

                <div
                    style="
                        background:linear-gradient(
                            135deg,
                            #ef4758,
                            #d91e36
                        );
                        color:white;
                        border-radius:16px;
                        min-height:145px;
                        padding:25px;
                        box-shadow:
                            0 8px 18px
                            rgba(0,0,0,.12);
                    "
                >

                    <i class="fas fa-calendar-check fa-2x mb-3"></i>

                    <div class="fw-semibold">
                        Leave Requests
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $pendingLeaves ?>
                    </div>

                </div>

            </a>

        </div>


        <!-- COURSE FACULTY -->

        <div class="col-xl-3 col-md-6">

            <a
                href="index.php?page=course_faculty_assign"
                class="text-decoration-none"
            >

                <div
                    style="
                        background:linear-gradient(
                            135deg,
                            #5368ea,
                            #4d24b8
                        );
                        color:white;
                        border-radius:16px;
                        min-height:145px;
                        padding:25px;
                        box-shadow:
                            0 8px 18px
                            rgba(0,0,0,.12);
                    "
                >

                    <i class="fas fa-book fa-2x mb-3"></i>

                    <div class="fw-semibold">
                        Course Faculty
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $courseAssignments ?>
                    </div>

                </div>

            </a>

        </div>


        <!-- ELECTIVE -->

        <div class="col-xl-3 col-md-6">

            <a
                href="index.php?page=elective_faculty_assign"
                class="text-decoration-none"
            >

                <div
                    style="
                        background:linear-gradient(
                            135deg,
                            #42cbb8,
                            #16b89d
                        );
                        color:white;
                        border-radius:16px;
                        min-height:145px;
                        padding:25px;
                        box-shadow:
                            0 8px 18px
                            rgba(0,0,0,.12);
                    "
                >

                    <i class="fas fa-graduation-cap fa-2x mb-3"></i>

                    <div class="fw-semibold">
                        Elective Faculty
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $electiveAssignments ?>
                    </div>

                </div>

            </a>

        </div>


        <!-- TIMETABLE -->

        <div class="col-xl-3 col-md-6">

            <a
                href="index.php?page=timetable_approval"
                class="text-decoration-none"
            >

                <div
                    style="
                        background:linear-gradient(
                            135deg,
                            #ff9d20,
                            #ff7518
                        );
                        color:white;
                        border-radius:16px;
                        min-height:145px;
                        padding:25px;
                        box-shadow:
                            0 8px 18px
                            rgba(0,0,0,.12);
                    "
                >

                    <i class="fas fa-calendar-alt fa-2x mb-3"></i>

                    <div class="fw-semibold">
                        Timetable
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $timetableCount ?>
                    </div>

                </div>

            </a>

        </div>

    </div>


    <!-- QUICK ACTIONS -->

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">

                <i class="fas fa-bolt me-2 text-primary"></i>

                Quick Actions

            </h5>

        </div>


        <div class="card-body">

            <div class="row g-3">


                <div class="col-md-4">

                    <a
                        href="index.php?page=course_faculty_assign"
                        class="btn btn-outline-primary w-100 py-3"
                    >

                        <i class="fas fa-users me-2"></i>

                        Manage Course Faculty

                    </a>

                </div>


                <div class="col-md-4">

                    <a
                        href="index.php?page=elective_faculty_assign"
                        class="btn btn-outline-success w-100 py-3"
                    >

                        <i class="fas fa-graduation-cap me-2"></i>

                        Manage Elective Faculty

                    </a>

                </div>


                <div class="col-md-4">

                    <a
                        href="index.php?page=timetable_approval"
                        class="btn btn-outline-warning w-100 py-3"
                    >

                        <i class="fas fa-calendar-check me-2"></i>

                        Review Timetable

                    </a>

                </div>

            </div>

        </div>

    </div>


</div>