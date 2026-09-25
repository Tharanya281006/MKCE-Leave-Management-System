<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$displayRole = ucfirst($_SESSION["role"] ?? "Staff");

?>

<style>

/* Topbar Styles */

.topbar {
    position: fixed;
    top: 0;
    right: 0;
    left: var(--sidebar-width);
    height: var(--topbar-height);

    background:
        linear-gradient(
            to bottom,
            rgba(255,255,255,0.15) 0%,
            rgba(0,0,0,0.15) 100%
        ),
        radial-gradient(
            at top center,
            rgba(255,255,255,0.40) 0%,
            rgba(0,0,0,0.40) 120%
        )
        #989898;

    background-blend-mode: multiply, multiply;

    box-shadow: var(--card-shadow);

    display: flex;

    align-items: center;

    padding: 0 20px;

    transition: all 0.3s ease;

    /* IMPORTANT: Keep topbar below Bootstrap modal */

    z-index: 1000;
}
.hamburger {
    cursor: pointer;
    font-size: 20px;
    color: white;
}

.user-profile {
    margin-left: auto;
    color: white;
    display: flex;
    align-items: center;
}

/* User Menu */

.user-menu {
    position: relative;
    cursor: pointer;
}

.user-menu > span {
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
}

/* Dropdown */

.dropdown-menu {
    position: absolute;

    top: 100%;

    right: 0;

    margin-top: 10px;

    background: white;

    box-shadow: 0 4px 15px rgba(0,0,0,0.2);

    border-radius: 6px;

    display: none;

    min-width: 160px;

    overflow: hidden;

    z-index: 1010;
}

.dropdown-menu.show {
    display: block !important;
}

/* Logout */

.dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    text-decoration: none;
    color: #dc3545;
    font-weight: 500;
    background: white;
}

.dropdown-menu a:hover {
    background: #f8f9fa;
    color: #b02a37;
}
/* =========================================
   BOOTSTRAP MODAL MUST APPEAR ABOVE TOPBAR
========================================= */

.modal-backdrop {
    z-index: 1050 !important;
}

.modal {
    z-index: 1055 !important;
}

/* Keep topbar below modal */

.topbar {
    z-index: 1000 !important;
}
body.modal-open .topbar {
    z-index: 1000 !important;
}

body.modal-open .sidebar {
    z-index: 1000 !important;
}
</style>


<div class="topbar">

    <!-- HAMBURGER -->

    <div class="hamburger" id="hamburger">
        <i class="fas fa-bars"></i>
    </div>

    <!-- USER PROFILE -->

    <div class="user-profile">

        <div id="userMenu" class="user-menu">

            <span id="userMenuButton">

                <?php echo htmlspecialchars($displayRole); ?>

                <i class="fas fa-chevron-down"></i>

            </span>

            <div id="staffDropdown" class="dropdown-menu">

                <a href="logout.php">

                    <i class="fas fa-sign-out-alt"></i>

                    Logout

                </a>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const userMenuButton = document.getElementById("userMenuButton");
    const staffDropdown = document.getElementById("staffDropdown");
    const userMenu = document.getElementById("userMenu");

    if (!userMenuButton || !staffDropdown || !userMenu) {
        console.log("User menu elements not found");
        return;
    }

    userMenuButton.addEventListener("click", function (event) {

        event.stopPropagation();

        staffDropdown.classList.toggle("show");

    });

    document.addEventListener("click", function (event) {

        if (!userMenu.contains(event.target)) {

            staffDropdown.classList.remove("show");

        }

    });

});

</script>