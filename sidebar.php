<?php

/*
==================================================
START SESSION
==================================================
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
==================================================
GET CURRENT USER DETAILS
==================================================
*/

$current_role = strtolower(
    trim($_SESSION["role"] ?? "")
);

$current_page = $_GET["page"] ?? "dashboard";

$staff_username = strtoupper(
    trim($_SESSION["staff_id"] ?? "")
);

$isStaffAdvisor = false;
$unreadNotifications = [];
if ($current_role === "student" && !empty($_SESSION["reg_no"])) {
    require_once __DIR__ . "/db/connection.php";
    require_once __DIR__ . "/notification_helper.php";
    ensureNotificationTable($conn);
    $notificationStmt = $conn->prepare("SELECT id, title, message, notification_type, created_at FROM notifications WHERE reg_no = ? AND is_read = 0 ORDER BY id DESC LIMIT 10");
    if ($notificationStmt) {
        $notificationStmt->bind_param("s", $_SESSION["reg_no"]);
        $notificationStmt->execute();
        $notificationResult = $notificationStmt->get_result();
        while ($n = $notificationResult->fetch_assoc()) $unreadNotifications[] = $n;
        $notificationStmt->close();
    }
}

if ($current_role === "staff" && $staff_username !== "") {
    require_once __DIR__ . "/db/connection.php";
    $advisorCheck = $conn->prepare(
        "SELECT 1 FROM year_advisor WHERE advisor_staff_id = ? LIMIT 1"
    );
    if ($advisorCheck) {
        $advisorCheck->bind_param("s", $staff_username);
        $advisorCheck->execute();
        $advisorCheck->store_result();
        $isStaffAdvisor = $advisorCheck->num_rows > 0;
        $advisorCheck->close();
    }
}

?>

<style>

/* =========================================
   SIDEBAR MAIN STYLE
========================================= */

.sidebar {

    position: fixed;

    top: 0;

    left: 0;

    height: 100vh;

    width: 250px;

    min-width: 250px;

    background: var(--dark-bg, #343a40);

    transition:
        width 0.3s ease,
        transform 0.3s ease;

    z-index: 1000;

    overflow-y: auto;

    overflow-x: hidden;

    box-shadow:
        2px 0 10px rgba(0, 0, 0, 0.1);

    background-image: url("image/pattern_h.png");

    background-size: cover;

    background-position: center;

    box-sizing: border-box;

}


/* =========================================
   SIDEBAR SCROLLBAR
========================================= */

.sidebar::-webkit-scrollbar {

    width: 6px;

}

.sidebar::-webkit-scrollbar-thumb {

    background: rgba(255, 255, 255, 0.2);

    border-radius: 3px;

}


/* =========================================
   COLLAPSED SIDEBAR
========================================= */

.sidebar.collapsed {

    width: 80px;

    min-width: 80px;

}


/* Hide menu text when collapsed */

.sidebar.collapsed .menu-item span {

    display: none;

}


/* Hide submenu arrow when collapsed */

.sidebar.collapsed .has-submenu::after {

    display: none;

}


/* Center icons when collapsed */

.sidebar.collapsed .menu-item {

    justify-content: center;

    padding: 12px 10px;

}

.sidebar.collapsed .menu-item i {

    margin: 0;

    min-width: auto;

}


/* =========================================
   LOGO STYLE
========================================= */

.sidebar .logo {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 0 20px;

    color: white;

    border-bottom:
        2px solid rgba(255, 255, 255, 0.1);

}

.sidebar .logo img {

    max-height: 90px;

    width: auto;

}

.sidebar .s_logo {

    display: none;

}


/* Small logo when collapsed */

.sidebar.collapsed .logo img {

    display: none;

}

.sidebar.collapsed .logo .s_logo {

    display: flex;

    max-height: 50px;

    width: auto;

    align-items: center;

    justify-content: center;

}


/* =========================================
   MENU CONTAINER
========================================= */

.sidebar .menu {

    width: 100%;

    padding: 10px;

    box-sizing: border-box;

}


/* =========================================
   MENU ITEM
========================================= */

.menu-item {

    width: 100%;

    padding: 12px 15px;

    color: rgba(255, 255, 255, 0.7);

    display: flex;

    align-items: center;

    cursor: pointer;

    border-radius: 5px;

    margin: 4px 0;

    transition: all 0.3s ease;

    position: relative;

    text-decoration: none;

    box-sizing: border-box;

    white-space: nowrap;

}


/* Hover */

.menu-item:hover {

    background: rgba(255, 255, 255, 0.1);

    color: white;

}


/* Icon */

.menu-item i {

    min-width: 30px;

    font-size: 18px;

    text-align: center;

}


/* Text */

.menu-item span {

    margin-left: 10px;

    transition: all 0.3s ease;

    flex-grow: 1;

}


/* Active item */

.menu-item.active {

    background: rgba(255, 255, 255, 0.2);

    color: white;

    font-weight: bold;

}

.menu-item.active i {

    color: white;

}


/* =========================================
   ICON COLORS
========================================= */

.icon-basic {

    background:
        linear-gradient(45deg, #4facfe, #00f2fe);

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    background-clip: text;

    display: inline-block;

}

.icon-academic {

    background:
        linear-gradient(
            45deg,
            rgb(66, 245, 221),
            #00d948
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    background-clip: text;

    display: inline-block;

}

.icon-exam {

    background:
        linear-gradient(
            45deg,
            rgb(255, 145, 0),
            rgb(245, 59, 2)
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    background-clip: text;

    display: inline-block;

}

.icon-bus {

    color: #9C27B0;

    display: inline-block;

}

.icon-feedback {

    color: #E91E63;

    display: inline-block;

}

.icon-password {

    color: #607D8B;

    display: inline-block;

}


/* =========================================
   MOBILE SIDEBAR
========================================= */

@media (max-width: 768px) {

    .sidebar {

        width: 250px;

        min-width: 250px;

        transform: translateX(-100%);

    }

    .sidebar.mobile-show {

        transform: translateX(0);

    }

    .sidebar.collapsed {

        width: 250px;

        min-width: 250px;

    }

    .sidebar.collapsed .menu-item span {

        display: inline-block;

    }

    .sidebar.collapsed .menu-item {

        justify-content: flex-start;

        padding: 12px 15px;

    }

    .sidebar.collapsed .menu-item i {

        min-width: 30px;

    }

}


/* =========================================
   MOBILE OVERLAY
========================================= */

#mobileOverlay {

    display: none;

    position: fixed;

    inset: 0;

    background: rgba(0, 0, 0, 0.5);

    z-index: 999;

}

#mobileOverlay.show {

    display: block;

}


/* =========================================
   CONTENT ALIGNMENT
========================================= */

.sidebar + .main-content {

    margin-left: 250px;

    width: calc(100% - 250px);

    box-sizing: border-box;

    transition:
        margin-left 0.3s ease,
        width 0.3s ease;

}

.sidebar.collapsed + .main-content {

    margin-left: 80px;

    width: calc(100% - 80px);

}

@media (max-width: 768px) {

    .sidebar + .main-content,

    .sidebar.collapsed + .main-content {

        margin-left: 0;

        width: 100%;

    }

}

</style>


<!-- =========================================
     SIDEBAR START
========================================= -->

<div id="sidebar" class="sidebar">

    <div class="menu">
        <div class="logo">
            <img
            src="image/mkce.png"
            alt="College Logo">
        </div>
        <br><br>

        <!-- =========================================
             DASHBOARD - ALL LOGGED-IN USERS
        ========================================= -->

        <a
            href="index.php?page=dashboard"
            class="menu-item
            <?php
                echo ($current_page === "dashboard")
                    ? "active"
                    : "";
            ?>"
        >

            <i class="fas fa-home text-primary"></i>

            <span>Dashboard</span>

        </a>



        <!-- =========================================
             STUDENT MENU
        ========================================= -->

        <?php if ($current_role === "student"): ?>


            <!-- Apply Leave -->

            <a
                href="index.php?page=apply_leave"
                class="menu-item
                <?php
                    echo ($current_page === "apply_leave")
                        ? "active"
                        : "";
                ?>"
            >

                <i class="fas fa-file-signature icon-academic"></i>

                <span>Apply Leave</span>

            </a>



            <!-- My Timetable -->

            <a
                href="index.php?page=student_timetable"
                class="menu-item
                <?php
                    echo ($current_page === "student_timetable")
                        ? "active"
                        : "";
                ?>"
            >

                <i class="fas fa-calendar-days icon-academic"></i>

                <span>My Timetable</span>

            </a>


            <div class="notification-menu-wrap">
                <button type="button" class="menu-item notification-menu-item" id="notificationBell" aria-expanded="false">
                    <i class="fas fa-bell text-warning"></i>
                    <span>Notifications</span>
                    <?php if (count($unreadNotifications) > 0): ?>
                        <span class="notification-count-badge" id="notificationCount"><?= count($unreadNotifications) ?></span>
                    <?php endif; ?>
                </button>
                <div id="notificationPanel" class="notification-panel" style="display:none;">
                    <?php if (!$unreadNotifications): ?>
                        <div class="notification-empty">No new notifications.</div>
                    <?php else: ?>
                        <?php foreach ($unreadNotifications as $n): ?>
                            <div class="notification-item">
                                <div class="notification-title"><?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="notification-message"><?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="notification-time"><?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="notification-read-btn" id="markNotificationsRead">Mark all as read</button>
                    <?php endif; ?>
                </div>
            </div>
            <style>
                .notification-menu-wrap{width:100%;margin:4px 0;position:relative;}
                .notification-menu-item{appearance:none;-webkit-appearance:none;width:100%;height:auto;padding:12px 15px !important;margin:0 !important;border:0 !important;outline:none;background:transparent !important;color:rgba(255,255,255,.7) !important;box-shadow:none !important;display:flex;align-items:center;gap:0;text-align:left;cursor:pointer;position:relative;font:inherit;line-height:normal;border-radius:5px;}
                .notification-menu-item:hover,.notification-menu-item:focus{background:rgba(255,255,255,.1) !important;color:#fff !important;outline:none;}
                .notification-menu-item i{min-width:30px;font-size:18px;text-align:center;margin:0 !important;}
                .notification-menu-item span:not(.notification-count-badge){margin-left:10px;flex-grow:1;}
                .notification-count-badge{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#dc3545 !important;color:#fff !important;font-size:11px;display:inline-flex;align-items:center;justify-content:center;}
                .notification-panel{margin:6px 0 0;padding:6px;border-radius:10px;background:#20242d;border:1px solid rgba(255,255,255,.10);box-shadow:0 8px 22px rgba(0,0,0,.28);max-height:300px;overflow-y:auto;}
                .notification-item{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.08);}
                .notification-title{color:#fff;font-size:13px;font-weight:600;}
                .notification-message{color:#c9ced8;font-size:12px;margin-top:3px;line-height:1.4;}
                .notification-time{color:#858c99;font-size:10px;margin-top:4px;}
                .notification-empty{color:#aeb4bf;font-size:12px;padding:10px;text-align:center;}
                .notification-read-btn{width:100%;margin-top:6px;border:1px solid rgba(255,255,255,.15);background:transparent;color:#dbe4ff;border-radius:7px;padding:6px;font-size:12px;}
                .notification-read-btn:hover{background:rgba(255,255,255,.06);}
                .sidebar.collapsed .notification-menu-wrap{margin:4px 0;width:100%;}
                .sidebar.collapsed .notification-menu-item{justify-content:center;padding:12px 10px !important;}
                .sidebar.collapsed .notification-menu-item span:not(.notification-count-badge){display:none;}
                .sidebar.collapsed .notification-menu-item i{min-width:auto;margin:0 !important;}
            </style>
            <script>
            $(function(){
                $('#notificationBell').on('click',function(e){e.preventDefault();$('#notificationPanel').stop(true,true).slideToggle(150);});
                $('#markNotificationsRead').on('click',function(){$.post('ajax/mark_notifications_read.php',{},function(r){if(r.status){$('#notificationCount').remove();$('#notificationPanel').html('<div class="notification-empty text-success">All notifications marked as read.</div>');}},'json');});
            });
            </script>


        <?php endif; ?>



        <!-- =========================================
             STAFF MENU
        ========================================= -->

        <?php if ($current_role === "staff"): ?>


            <!-- =========================================
                 ADVISOR MENU
                 Dashboard
                 Leave Requests
                 Course Faculty
            ========================================= -->

            <?php if ($isStaffAdvisor): ?>


                <!-- Leave Requests -->

                <a
                    href="index.php?page=leave_requests"
                    class="menu-item
                    <?php
                        echo ($current_page === "leave_requests")
                            ? "active"
                            : "";
                    ?>"
                >

                    <i class="fas fa-file-alt icon-academic"></i>

                    <span>Leave Requests</span>

                </a>



                <!-- Course Faculty -->

                <a
                    href="index.php?page=course_faculty"
                    class="menu-item
                    <?php
                        echo ($current_page === "course_faculty")
                            ? "active"
                            : "";
                    ?>"
                >

                    <i class="fas fa-users icon-basic"></i>

                    <span>Course Faculty</span>

                </a>

                <a
                    href="index.php?page=advisor_timetable"
                    class="menu-item
                    <?php echo ($current_page === "advisor_timetable") ? "active" : ""; ?>"
                >
                    <i class="fas fa-calendar-days icon-academic"></i>
                    <span>Timetable Design</span>
                </a>


            <?php endif; ?>



            <!-- =========================================
                 STAFF TIMETABLE MENU
            ========================================= -->

                <!-- Timetable -->

                <a
                    href="index.php?page=staff_timetable"
                    class="menu-item
                    <?php
                        echo ($current_page === "staff_timetable")
                            ? "active"
                            : "";
                    ?>"
                >

                    <i class="fas fa-calendar-alt icon-academic"></i>

                    <span>Timetable</span>

                </a>

                <a
                    href="index.php?page=attendance_history"
                    class="menu-item
                    <?php echo ($current_page === "attendance_history") ? "active" : ""; ?>"
                >
                    <i class="fas fa-clipboard-check icon-academic"></i>
                    <span>Attendance History</span>
                </a>

        <?php endif; ?>



        <!-- =========================================
             HOD MENU
        ========================================= -->

        <?php if ($current_role === "hod"): ?>


            <!-- Leave Requests -->

            <a
                href="index.php?page=leave_requests"
                class="menu-item
                <?php
                    echo ($current_page === "leave_requests")
                        ? "active"
                        : "";
                ?>"
            >

                <i class="fas fa-calendar-check icon-academic"></i>

                <span>Leave Requests</span>

            </a>



            <!-- Course Faculty Assigning -->

            <a
                href="index.php?page=course_faculty_assign"
                class="menu-item
                <?php
                    echo ($current_page === "course_faculty_assign")
                        ? "active"
                        : "";
                ?>"
            >

                <i class="fas fa-user-tie icon-academic"></i>

                <span>3rd Year Advisor</span>

            </a>

            <a
                href="index.php?page=timetable_approval"
                class="menu-item
                <?php echo ($current_page === "timetable_approval") ? "active" : ""; ?>"
            >
                <i class="fas fa-calendar-check"></i>
                <span>Timetable Approval</span>
            </a>

            <a
                href="index.php?page=elective_faculty_assign"
                class="menu-item
                <?php echo ($current_page === "elective_faculty_assign") ? "active" : ""; ?>"
            >
                <i class="fas fa-book-open"></i>
                <span>Elective Faculty</span>
            </a>


        <?php endif; ?>


    </div>

</div>



<!-- =========================================
     MOBILE OVERLAY
========================================= -->

<div id="mobileOverlay"></div>



<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const hamburger =
            document.getElementById("hamburger");

        const sidebar =
            document.getElementById("sidebar");

        const mobileOverlay =
            document.getElementById("mobileOverlay");



        /* =========================================
           SIDEBAR TOGGLE
        ========================================= */

        function handleSidebarToggle() {


            if (!sidebar) {

                return;

            }


            if (window.innerWidth <= 768) {


                sidebar.classList.toggle(
                    "mobile-show"
                );


                if (mobileOverlay) {

                    mobileOverlay.classList.toggle(
                        "show"
                    );

                }


                document.body.classList.toggle(
                    "sidebar-open"
                );


            }

            else {


                sidebar.classList.toggle(
                    "collapsed"
                );

            }

        }



        /* =========================================
           CLOSE MOBILE SIDEBAR
        ========================================= */

        function closeMobileSidebar() {


            if (sidebar) {

                sidebar.classList.remove(
                    "mobile-show"
                );

            }


            if (mobileOverlay) {

                mobileOverlay.classList.remove(
                    "show"
                );

            }


            document.body.classList.remove(
                "sidebar-open"
            );

        }



        /* =========================================
           RESIZE HANDLER
        ========================================= */

        function handleResize() {


            if (!sidebar) {

                return;

            }


            if (window.innerWidth <= 768) {


                sidebar.classList.remove(
                    "collapsed"
                );

                sidebar.classList.remove(
                    "mobile-show"
                );


                if (mobileOverlay) {

                    mobileOverlay.classList.remove(
                        "show"
                    );

                }


                document.body.classList.remove(
                    "sidebar-open"
                );


            }

            else {


                sidebar.classList.remove(
                    "mobile-show"
                );


                if (mobileOverlay) {

                    mobileOverlay.classList.remove(
                        "show"
                    );

                }


                document.body.classList.remove(
                    "sidebar-open"
                );

            }

        }



        /* =========================================
           EVENT LISTENERS
        ========================================= */

        if (hamburger) {

            hamburger.addEventListener(
                "click",
                handleSidebarToggle
            );

        }


        if (mobileOverlay) {

            mobileOverlay.addEventListener(
                "click",
                closeMobileSidebar
            );

        }


        window.addEventListener(
            "resize",
            handleResize
        );



        /* =========================================
           CLOSE SIDEBAR AFTER MOBILE MENU CLICK
        ========================================= */

        const menuItems =
            document.querySelectorAll(
                ".menu-item"
            );


        menuItems.forEach(
            function (item) {


                item.addEventListener(
                    "click",
                    function () {


                        if (window.innerWidth <= 768) {

                            closeMobileSidebar();

                        }

                    }
                );

            }
        );


    }
);

</script>