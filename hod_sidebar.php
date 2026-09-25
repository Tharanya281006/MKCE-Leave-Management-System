<style>

/* =========================================
   SIDEBAR
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

    box-shadow:
        2px 0 12px rgba(0, 0, 0, 0.35);
}


/* Scrollbar */

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.18);
    border-radius: 10px;
}


/* Collapsed Sidebar */

.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}


/* =========================================
   LOGO
========================================= */

.sidebar .logo {

    height: 85px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 8px 15px;

    border-bottom:
        1px solid rgba(255, 255, 255, 0.08);
}


.sidebar .logo img {

    max-height: 65px;
    max-width: 100%;

    width: auto;

    object-fit: contain;
}


.sidebar .s_logo {
    display: none;
}


.sidebar.collapsed .logo img {
    display: none;
}


.sidebar.collapsed .logo .s_logo {

    display: block;

    max-height: 45px;

    width: auto;
}


/* =========================================
   MENU
========================================= */

.sidebar .menu {

    padding: 14px 8px;

}


/* =========================================
   MENU ITEM
========================================= */

.menu-item {

    min-height: 46px;

    padding: 12px 14px;

    margin: 5px 0;

    display: flex;
    align-items: center;

    border-radius: 6px;

    color: rgba(255, 255, 255, 0.72);

    text-decoration: none;

    cursor: pointer;

    transition:
        background 0.25s ease,
        color 0.25s ease,
        transform 0.25s ease;

    position: relative;

}


/* ICON */

.menu-item i {

    min-width: 28px;

    font-size: 16px;

    text-align: center;

}


/* TEXT */

.menu-item span {

    margin-left: 8px;

    font-size: 14px;

    font-weight: 500;

    white-space: nowrap;

}


/* =========================================
   HOVER
========================================= */

.menu-item:hover {

    background: rgba(255, 255, 255, 0.08);

    color: #ffffff;

    transform: translateX(2px);

}


/* =========================================
   ACTIVE MENU
========================================= */

.menu-item.active {

    background: rgba(255, 255, 255, 0.14);

    color: #ffffff;

    font-weight: 600;

}


.menu-item.active::before {

    content: "";

    position: absolute;

    left: 0;

    top: 8px;

    bottom: 8px;

    width: 3px;

    border-radius: 0 5px 5px 0;

    background: #2f80ed;

}


/* =========================================
   ACTIVE ICON
========================================= */

.menu-item.active i {

    color: #2f80ed;

}


/* =========================================
   COLLAPSED MODE
========================================= */

.sidebar.collapsed .menu-item {

    justify-content: center;

    padding: 12px 5px;

}


.sidebar.collapsed .menu-item i {

    min-width: auto;

}


.sidebar.collapsed .menu-item span {

    display: none;

}


/* =========================================
   SUBMENU
========================================= */

.has-submenu::after {

    content: '\f107';

    font-family: 'Font Awesome 6 Free';

    font-weight: 900;

    margin-left: auto;

    font-size: 12px;

    transition: transform 0.3s ease;

}


.has-submenu.active::after {

    transform: rotate(180deg);

}


.sidebar.collapsed .has-submenu::after {

    display: none;

}


.submenu {

    margin-left: 20px;

    display: none;

    padding-top: 3px;

}


.submenu.active {

    display: block;

}


/* =========================================
   MOBILE
========================================= */

.mobile-overlay {

    display: none;

}


@media (max-width: 768px) {

    .sidebar {

        transform: translateX(-100%);

        width: 250px;

    }


    .sidebar.mobile-show {

        transform: translateX(0);

    }


    .mobile-overlay {

        position: fixed;

        inset: 0;

        background: rgba(0, 0, 0, 0.5);

        z-index: 999;

    }


    .mobile-overlay.show {

        display: block;

    }

}


/* =========================================
   ICON COLORS
========================================= */

.icon-basic {

    background:
        linear-gradient(
            45deg,
            #4facfe,
            #00f2fe
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    display: inline-block;

}


.icon-academic {

    background:
        linear-gradient(
            45deg,
            #42f5dd,
            #00d948
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    display: inline-block;

}


.icon-exam {

    background:
        linear-gradient(
            45deg,
            #ff9100,
            #f53b02
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;

    display: inline-block;

}


.icon-bus {

    color: #9C27B0;

}


.icon-feedback {

    color: #E91E63;

}


.icon-password {

    color: #607D8B;

}

</style>
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="image/mkce.png" alt="College Logo">
        <img class='s_logo' src="image/mkce_s.png" alt="College Logo">
    </div>

<div class="menu">

    <a href="index.php?page=dashboard" class="menu-item">

        <i class="fas fa-home"></i>

        <span>Dashboard</span>

    </a>


    <a href="index.php?page=leave_requests" class="menu-item">

        <i class="fas fa-calendar-check"></i>

        <span>Leave Requests</span>

    </a>
<a href="index.php?page=course_faculty_assign" class="menu-item">

    <i class="fas fa-user-tie"></i>

    <span>Course Faculty Assigning</span>

</a>

<a href="index.php?page=attendance_history" class="menu-item">

    <i class="fas fa-clipboard-check"></i>

    <span>Attendance History</span>

</a>
<a href="index.php?page=elective_faculty_assign" class="menu-item">

    <i class="fas fa-graduation-cap"></i>

    <span>Elective Faculty</span>

</a>
</div>
</div>

<script>


        //    automatic loader
        document.addEventListener('DOMContentLoaded', function() {
            const loaderContainer = document.getElementById('loaderContainer');
            const contentWrapper = document.getElementById('contentWrapper');
            let loadingTimeout;

            function hideLoader() {
                loaderContainer.classList.add('hide');
                contentWrapper.classList.add('show');
            }

            function showError() {
                console.error('Page load took too long or encountered an error');
                // You can add custom error handling here
            }

            // Set a maximum loading time (10 seconds)
            loadingTimeout = setTimeout(showError, 10000);

            // Hide loader when everything is loaded
            window.onload = function() {
                clearTimeout(loadingTimeout);

                // Add a small delay to ensure smooth transition
                setTimeout(hideLoader, 500);
            };

            // Error handling

        document.addEventListener("DOMContentLoaded", function() {
            // Cache DOM elements
            const elements = {
                hamburger: document.getElementById('hamburger'),
                sidebar: document.getElementById('sidebar'),
                mobileOverlay: document.getElementById('mobileOverlay'),
                menuItems: document.querySelectorAll('.menu-item'),
                submenuItems: document.querySelectorAll('.submenu-item') // Add submenu items to cache
            };

            // Set active menu item based on current path
           function setActiveMenuItem() {

    const currentPage =
        new URLSearchParams(window.location.search).get("page")
        || "dashboard";

    elements.menuItems.forEach(item => {

        item.classList.remove("active");

        const itemUrl = new URL(
            item.getAttribute("href"),
            window.location.origin
        );

        const itemPage =
            new URLSearchParams(itemUrl.search).get("page")
            || "dashboard";

        if (itemPage === currentPage) {

            item.classList.add("active");

        }

    });

}

                // Check submenu items
                elements.submenuItems.forEach(item => {
                    const itemPath = item.getAttribute('href')?.replace('/', '');
                    if (itemPath === currentPath) {
                        item.classList.add('active');
                        // Activate parent submenu and its trigger
                        const parentSubmenu = item.closest('.submenu');
                        const parentMenuItem = parentSubmenu?.previousElementSibling;
                        if (parentSubmenu && parentMenuItem) {
                            parentSubmenu.classList.add('active');
                            parentMenuItem.classList.add('active');
                        }
                    }
                });
            }

            // Handle mobile sidebar toggle
            function handleSidebarToggle() {
                if (window.innerWidth <= 768) {
                    elements.sidebar.classList.toggle('mobile-show');
                    elements.mobileOverlay.classList.toggle('show');
                    document.body.classList.toggle('sidebar-open');
                } else {
                    elements.sidebar.classList.toggle('collapsed');
                }
            }

            // Handle window resize
            function handleResize() {
                if (window.innerWidth <= 768) {
                    elements.sidebar.classList.remove('collapsed');
                    elements.sidebar.classList.remove('mobile-show');
                    elements.mobileOverlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                } else {
                    elements.sidebar.style.transform = '';
                    elements.mobileOverlay.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            }

            // Toggle User Menu
const userMenu = document.getElementById('userMenu');

if (userMenu) {

    const dropdownMenu =
        userMenu.querySelector('.dropdown-menu');

    userMenu.addEventListener('click', (e) => {

        e.stopPropagation();

        dropdownMenu.classList.toggle('show');

    });


    document.addEventListener('click', () => {

        dropdownMenu.classList.remove('show');

    });

}
           
            // Enhanced Toggle Submenu with active state handling
            const menuItems = document.querySelectorAll('.has-submenu');
            menuItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevent default if it's a link
                    const submenu = item.nextElementSibling;

                    // Toggle active state for the clicked menu item and its submenu
                    item.classList.toggle('active');
                    submenu.classList.toggle('active');

                    // Handle submenu item clicks
                    const submenuItems = submenu.querySelectorAll('.submenu-item');
                    submenuItems.forEach(submenuItem => {
                        submenuItem.addEventListener('click', (e) => {
                            // Remove active class from all submenu items
                            submenuItems.forEach(si => si.classList.remove('active'));
                            // Add active class to clicked submenu item
                            submenuItem.classList.add('active');
                            e.stopPropagation(); // Prevent event from bubbling up
                        });
                    });
                });
            });

            // Initialize event listeners
            function initializeEventListeners() {
                // Sidebar toggle for mobile and desktop
                if (elements.hamburger && elements.mobileOverlay) {
                    elements.hamburger.addEventListener('click', handleSidebarToggle);
                    elements.mobileOverlay.addEventListener('click', handleSidebarToggle);
                }
                // Window resize handler
                window.addEventListener('resize', handleResize);
            }

            // Initialize everything
            setActiveMenuItem();
            initializeEventListeners();
        });
    </script>