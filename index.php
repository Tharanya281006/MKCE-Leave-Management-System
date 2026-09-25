<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Login Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["role"])) {
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Common Session Values
|--------------------------------------------------------------------------
*/

$page = $_GET["page"] ?? "dashboard";

$role = strtolower(trim($_SESSION["role"] ?? ""));

$staff_username = strtoupper(
    trim($_SESSION["staff_id"] ?? "")
);

// Student navigation is limited to Dashboard, Apply Leave and My Timetable.
if ($role === 'student' && $page === 'my_leaves') {
    $page = 'dashboard';
}

/*
|--------------------------------------------------------------------------
| Allowed Roles
|--------------------------------------------------------------------------
*/

$allowed_roles = [
    "student",
    "staff",
    "hod"
];

if (!in_array($role, $allowed_roles, true)) {
    session_destroy();

    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Breadcrumb Name
|--------------------------------------------------------------------------
*/

$breadcrumbName = "";

if ($role === "student") {

    if ($page === "dashboard") {
        $breadcrumbName = "Dashboard";
    }

    elseif ($page === "apply_leave") {
        $breadcrumbName = "Apply Leave";
    }

    elseif ($page === "student_timetable") {
        $breadcrumbName = "My Timetable";
    }

}

elseif ($role === "staff") {

    if ($page === "dashboard") {
        $breadcrumbName = "Dashboard";
    }

    elseif ($page === "leave_requests") {
        $breadcrumbName = "Leave Requests";
    }

    elseif ($page === "staff_timetable") {
        $breadcrumbName = "Timetable";
    }

    elseif ($page === "course_faculty") {
        $breadcrumbName = "Course Faculty";
    }

    elseif ($page === "advisor_timetable") {
        $breadcrumbName = "Timetable Design";
    }

    elseif ($page === "attendance_history") {
        $breadcrumbName = "Attendance History";
    }

}

elseif ($role === "hod") {

    if ($page === "dashboard") {
        $breadcrumbName = "Dashboard";
    }

    elseif ($page === "leave_requests") {
        $breadcrumbName = "Leave Requests";
    }

    elseif ($page === "course_faculty_assign") {
        $breadcrumbName = "Course Faculty Assignment";
    }

    elseif ($page === "timetable_approval") {
        $breadcrumbName = "Timetable Approval";
    }

    elseif ($page === "hod_timetable_view") {
        $breadcrumbName = "Timetable Details";
    }

    elseif ($page === "elective_faculty_assign") {
        $breadcrumbName = "Elective Faculty Assignment";
    }

    elseif ($page === "attendance_history") {
        $breadcrumbName = "Attendance History";
    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>MIC</title>

    <link
        rel="icon"
        type="image/png"
        sizes="32x32"
        href="image/icons/mkce_s.png"
    >

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="style.css"
    >

    <!-- Bootstrap CSS -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        rel="stylesheet"
    >

    <!-- DataTables CSS -->
    <link
        href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"
        rel="stylesheet"
    >

    <!-- SweetAlert Theme -->
    <link
        href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-5/bootstrap-5.css"
        rel="stylesheet"
    >

    <!-- Alertify CSS -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"
    >

    <!-- jQuery -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js">
    </script>

    <!-- Bootstrap JS -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js">
    </script>

    <!-- SweetAlert JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
    </script>

    <!-- DataTables JS -->
    <script
        src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js">
    </script>

    <script
        src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js">
    </script>

    <style>

        :root {

            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 60px;
            --footer-height: 60px;

            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --dark-bg: #1a1c23;
            --light-bg: #f8f9fc;

            --card-shadow:
                0 4px 6px rgba(0, 0, 0, 0.1);

            --transition:
                all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

        }

        /*
        |--------------------------------------------------------------------------
        | Main Content
        |--------------------------------------------------------------------------
        */

        .content {

            margin-left: var(--sidebar-width);

            padding-top: var(--topbar-height);

            min-height: 100vh;

            transition: var(--transition);

        }

        .sidebar.collapsed + .content {

            margin-left: var(--sidebar-collapsed-width);

        }

        /*
        |--------------------------------------------------------------------------
        | Breadcrumb
        |--------------------------------------------------------------------------
        */

        .breadcrumb-area {

            background-image:
                linear-gradient(
                    to top,
                    #fff1eb 0%,
                    #ace0f9 100%
                );

            border-radius: 10px;

            box-shadow: var(--card-shadow);

            margin: 20px;

            padding: 15px 20px;

        }

        .breadcrumb-item a {

            color: var(--primary-color);

            text-decoration: none;

            transition: var(--transition);

        }

        .breadcrumb-item a:hover {

            color: #224abe;

        }

        /*
        |--------------------------------------------------------------------------
        | Content Navigation
        |--------------------------------------------------------------------------
        */

        .content-nav {

            background:
                linear-gradient(
                    45deg,
                    #4e73df,
                    #1cc88a
                );

            padding: 15px;

            border-radius: 10px;

            margin-bottom: 20px;

        }

        .content-nav ul {

            list-style: none;

            padding: 0;

            margin: 0;

            display: flex;

            gap: 20px;

            overflow-x: auto;

        }

        .content-nav li a {

            color: white;

            text-decoration: none;

            padding: 8px 15px;

            border-radius: 20px;

            background: rgba(255, 255, 255, 0.1);

            transition: var(--transition);

            white-space: nowrap;

        }

        .content-nav li a:hover {

            background: rgba(255, 255, 255, 0.2);

        }

        /*
        |--------------------------------------------------------------------------
        | Table Styles
        |--------------------------------------------------------------------------
        */

        .gradient-header {

            --bs-table-bg: transparent;

            --bs-table-color: white;

            background:
                linear-gradient(
                    135deg,
                    #4CAF50,
                    #2196F3
                ) !important;

            text-align: center;

            font-size: 0.9em;

        }

        td {

            text-align: left;

            font-size: 0.9em;

            vertical-align: middle;

        }

        /*
        |--------------------------------------------------------------------------
        | Container
        |--------------------------------------------------------------------------
        */

        .container-fluid {

            padding: 20px;

        }

        /*
        |--------------------------------------------------------------------------
        | Responsive Design
        |--------------------------------------------------------------------------
        */

        @media (max-width: 768px) {

            .sidebar {

                transform: translateX(-100%);

                width: var(--sidebar-width) !important;

            }

            .sidebar.mobile-show {

                transform: translateX(0);

            }

            .topbar {

                left: 0 !important;

            }

            .mobile-overlay {

                position: fixed;

                top: 0;

                left: 0;

                right: 0;

                bottom: 0;

                background: rgba(0, 0, 0, 0.5);

                z-index: 999;

                display: none;

            }

            .mobile-overlay.show {

                display: block;

            }

            .content {

                margin-left: 0 !important;

            }

            .brand-logo {

                display: block;

            }

            .user-profile {

                margin-left: 0;

            }

            .sidebar .logo {

                justify-content: center;

            }

            .sidebar .menu-item span,
            .sidebar .has-submenu::after {

                display: block !important;

            }

            body.sidebar-open {

                overflow: hidden;

            }

            .footer {

                left: 0 !important;

            }

            .content-nav ul {

                flex-wrap: nowrap;

                overflow-x: auto;

                padding-bottom: 5px;

            }

            .content-nav ul::-webkit-scrollbar {

                height: 4px;

            }

            .content-nav ul::-webkit-scrollbar-thumb {

                background: rgba(255, 255, 255, 0.3);

                border-radius: 2px;

            }

        }

    </style>

</head>

<body>

    <!-- Sidebar -->
    <?php include __DIR__ . "/sidebar.php"; ?>

    <!-- Topbar -->
    <?php include __DIR__ . "/topbar.php"; ?>

    <!-- Main Content -->
    <div class="content">

        <!-- Breadcrumb -->
        <?php if ($breadcrumbName !== "") { ?>

            <div class="breadcrumb-area">

                <nav aria-label="breadcrumb">

                    <ol class="breadcrumb mb-0">

                        <li class="breadcrumb-item">

                            <a href="index.php?page=dashboard">
                                Home
                            </a>

                        </li>

                        <li
                            class="breadcrumb-item active"
                            aria-current="page"
                        >

                            <?php
                            echo htmlspecialchars(
                                $breadcrumbName,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </li>

                    </ol>

                </nav>

            </div>

        <?php } ?>

        <!-- Page Content -->
        <div class="container-fluid">

            <?php

            /*
            |--------------------------------------------------------------------------
            | Student Pages
            |--------------------------------------------------------------------------
            */

            if ($role === "student") {

                if ($page === "dashboard") {

                    include __DIR__ . "/student_dashboard.php";

                }

                elseif ($page === "apply_leave") {

                    include __DIR__ . "/apply_leave.php";

                }

                elseif ($page === "student_timetable") {

                    include __DIR__ . "/student_timetable.php";

                }

            }

            /*
            |--------------------------------------------------------------------------
            | Staff Pages
            |--------------------------------------------------------------------------
            */

            elseif ($role === "staff") {

                if ($page === "dashboard") {
                    include __DIR__ . "/staff_dashboard.php";
                }

                elseif ($page === "leave_requests") {
                    include __DIR__ . "/staff_leave_requests.php";
                }

                elseif ($page === "course_faculty") {
                    include __DIR__ . "/advisor_course_faculty.php";
                }

                elseif ($page === "staff_timetable") {
                    include __DIR__ . "/staff_timetable.php";
                }

                elseif ($page === "staff_period") {
                    include __DIR__ . "/staff_period.php";
                }

                elseif ($page === "advisor_timetable") {
                    include __DIR__ . "/advisor_timetable.php";
                }

                elseif ($page === "attendance_history") {
                    include __DIR__ . "/attendance_history.php";
                }

            }

            /*
            |--------------------------------------------------------------------------
            | HOD Pages
            |--------------------------------------------------------------------------
            */

            elseif ($role === "hod") {

                if ($page === "dashboard") {

                    include __DIR__ . "/hod_dashboard.php";

                }

                elseif ($page === "leave_requests") {

                    include __DIR__ . "/hod_leave_requests.php";

                }

                elseif ($page === "course_faculty_assign") {

                    include __DIR__ . "/hod/course_faculty_assign.php";

                }

                elseif ($page === "timetable_approval") {

                    include __DIR__ . "/hod_timetables.php";

                }

                elseif ($page === "hod_timetable_view") {

                    include __DIR__ . "/hod_timetable_view.php";

                }

                elseif ($page === "elective_faculty_assign") {

                    include __DIR__ . "/hod/elective_faculty_assign.php";

                }

                elseif ($page === "attendance_history") {

                    include __DIR__ . "/attendance_history.php";

                }

            }

            ?>

        </div>

        <!-- Footer -->
        <?php include __DIR__ . "/footer.php"; ?>

    </div>

    <!-- Alertify JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js">
    </script>

</body>

</html>