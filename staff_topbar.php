<?php

$displayRole = "Staff";

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

    background-blend-mode:

        multiply,

        multiply;

    box-shadow:

        var(--card-shadow);

    display: flex;

    align-items: center;

    padding: 0 20px;

    transition: all 0.3s ease;

    z-index: 999;

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


/* USER MENU */

.user-menu {

    position: relative;

    cursor: pointer;

}


/* IMPORTANT */

.user-menu > span {

    color: white;

    display: flex;

    align-items: center;

    gap: 8px;

}


.dropdown-menu {

    position: absolute;

    top: 100%;

    right: 0;

    margin-top: 10px;

    background: white;

    box-shadow:

        0 4px 15px

        rgba(0,0,0,0.2);

    border-radius: 6px;

    display: none;

    min-width: 160px;

    overflow: hidden;

}


.dropdown-menu.show {

    display: block;

}


/* LOGOUT */

.dropdown-menu a {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 12px 16px;

    text-decoration: none;

    color: #dc3545;

    font-weight: 500;

}


.dropdown-menu a:hover {

    background: #f8f9fa;

    color: #b02a37;

}


</style>


<div class="topbar">


    <!-- HAMBURGER -->

    <div

        class="hamburger"

        id="hamburger"

    >

        <i

            class="fas fa-bars"

        ></i>

    </div>


    <!-- USER PROFILE -->

    <div

        class="user-profile"

    >


        <div

            id="userMenu"

            class="user-menu"

        >


            <span>


                <?php

                echo $displayRole;

                ?>


                <i

                    class="fas fa-chevron-down"

                ></i>


            </span>


            <div

                class="dropdown-menu"

            >


                <a

                    href="logout.php"

                >


                    <i

                        class="fas fa-sign-out-alt"

                    ></i>


                    Logout


                </a>


            </div>


        </div>


    </div>


</div>


