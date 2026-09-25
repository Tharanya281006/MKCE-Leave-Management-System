<style>

/* =========================================
   STAFF SIDEBAR
========================================= */

.sidebar {

    position: fixed;

    top: 0;

    left: 0;

    height: 100vh;

    width: var(--sidebar-width);

    background-color: #1f232d;

    background-image:

        linear-gradient(

            rgba(31, 35, 45, 0.92),

            rgba(20, 23, 31, 0.95)

        ),

        url('image/pattern_h.png');

    background-size: cover;

    background-position: center;

    transition: var(--transition);

    z-index: 1000;

    overflow-y: auto;

}


/* LOGO */

.sidebar .logo {

    height: 85px;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 8px 15px;

}


.sidebar .logo img {

    max-height: 65px;

    max-width: 100%;

    width: auto;

    object-fit: contain;

}


/* MENU */

.sidebar .menu {

    padding: 14px 8px;

}


/* MENU ITEM */

.menu-item {

    min-height: 46px;

    padding: 12px 14px;

    margin: 5px 0;

    display: flex;

    align-items: center;

    border-radius: 6px;

    color: rgba(255,255,255,0.72);

    text-decoration: none;

    transition: 0.25s ease;

    position: relative;

}


.menu-item i {

    min-width: 28px;

    font-size: 16px;

    text-align: center;

}


.menu-item span {

    margin-left: 8px;

    font-size: 14px;

    font-weight: 500;

}


/* HOVER */

.menu-item:hover {

    background: rgba(255,255,255,0.08);

    color: white;

}


/* ACTIVE */

.menu-item.active {

    background: rgba(255,255,255,0.14);

    color: white;

}


.menu-item.active::before {

    content: "";

    position: absolute;

    left: 0;

    top: 8px;

    bottom: 8px;

    width: 3px;

    background: #2f80ed;

}

</style>
<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="sidebar" id="sidebar">


    <!-- LOGO -->

    <div class="logo">

        <img
            src="image/mkce.png"
            alt="College Logo"
        >

    </div>


    <!-- MENU -->

    <div class="menu">

    <a href="index.php?page=dashboard" class="menu-item">

        <i class="fas fa-home"></i>

        <span>Dashboard</span>

    </a>


    <a href="index.php?page=leave_requests" class="menu-item">

        <i class="fas fa-calendar-check"></i>

        <span>Leave Requests</span>

    </a>
    <!-- Course Faculty -->

<a href="index.php?page=course_faculty" class="menu-item">

    <i class="fas fa-users"></i>

    <span>Course Faculty</span>

</a>

<a href="index.php?page=attendance_history" class="menu-item">

    <i class="fas fa-clipboard-check"></i>

    <span>Attendance History</span>

</a>

</div>


</div>


<script>


document.addEventListener(

    "DOMContentLoaded",

    function () {


        const currentPage =

            window.location.pathname

            .split("/")

            .pop();


        const menuItems =

            document.querySelectorAll(

                ".menu-item"

            );


        menuItems.forEach(

            function (item) {


                let page =

                    item

                    .getAttribute(

                        "href"

                    );


                if (

                    page === currentPage

                ) {


                    item.classList.add(

                        "active"

                    );


                }


            }

        );


    }

);


</script>