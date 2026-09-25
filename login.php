<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Leave Management System - Login</title>


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


    <!-- jQuery -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js">
    </script>


    <!-- Bootstrap JS -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js">
    </script>


    <!-- SweetAlert2 -->

    <script
        src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
    </script>


    <style>

        body {

            min-height: 100vh;

            background: linear-gradient(
                rgba(20, 40, 80, 0.95),
                rgba(20, 40, 80, 0.95)
            );

            background-size: cover;

            background-position: center;

            background-repeat: no-repeat;

        }


        .login-container {

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

        }


        .login-card {

            width: 100%;

            max-width: 480px;

            background: white;

            border-radius: 15px;

            padding: 35px;

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);

        }


        .login-title {

            text-align: center;

            margin-bottom: 25px;

        }


        .role-container {

            display: flex;

            gap: 10px;

            margin-bottom: 25px;

        }


        .role-btn {

            flex: 1;

            border: none;

            padding: 12px;

            border-radius: 8px;

            background: #e9ecef;

            cursor: pointer;

            transition: 0.3s;

            font-weight: 500;

        }


        .role-btn.active {

            background: #0d6efd;

            color: white;

        }


        .login-btn {

            width: 100%;

            padding: 12px;

            font-size: 16px;

        }

    </style>

</head>


<body>


    <div class="login-container">


        <div class="login-card">


            <!-- TITLE -->

            <div class="login-title">

                <h3>

                    Leave Management System

                </h3>

                <p class="text-muted">

                    Login to continue

                </p>

            </div>



            <!-- ROLE BUTTONS -->

            <div class="role-container">


                <button
                    type="button"
                    class="role-btn active"
                    data-role="student"
                >

                    <i class="fas fa-user-graduate"></i>

                    Student

                </button>


                <button
                    type="button"
                    class="role-btn"
                    data-role="staff"
                >

                    <i class="fas fa-user"></i>

                    Faculty

                </button>


                <button
                    type="button"
                    class="role-btn"
                    data-role="hod"
                >

                    <i class="fas fa-user-tie"></i>

                    HOD

                </button>


            </div>



            <!-- LOGIN FORM -->

            <form id="loginForm">


                <!-- HIDDEN ROLE -->

                <input
                    type="hidden"
                    name="role"
                    id="role"
                    value="student"
                >



                <!-- LOGIN ID -->

                <div class="mb-3">


                    <label
                        class="form-label"
                        id="loginLabel"
                    >

                        Register Number

                    </label>


                    <input
                        type="text"
                        class="form-control"
                        name="login_id"
                        id="login_id"
                        placeholder="Enter Register Number"
                        required
                    >


                </div>



                <!-- PASSWORD -->

                <div class="mb-3">


                    <label class="form-label">

                        Password

                    </label>


                    <div class="input-group">


                        <input
                            type="password"
                            class="form-control"
                            name="password"
                            id="password"
                            placeholder="Enter Password"
                            required
                        >


                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="togglePassword"
                        >

                            <i class="fas fa-eye"></i>

                        </button>


                    </div>


                </div>



                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="btn btn-primary login-btn"
                    id="loginBtn"
                >

                    Login

                </button>


            </form>


        </div>


    </div>



    <script>


        // =====================================
        // ROLE CHANGE
        // =====================================


        $(".role-btn").click(function () {


            // Remove active from all buttons

            $(".role-btn").removeClass("active");


            // Add active to selected button

            $(this).addClass("active");


            // Get selected role

            let selectedRole = $(this).data("role");


            // Store selected role

            $("#role").val(selectedRole);


            // Clear input fields

            $("#login_id").val("");

            $("#password").val("");



            // Change Login ID label and placeholder

            if (selectedRole === "student") {


                $("#loginLabel").text("Register Number");


                $("#login_id").attr(
                    "placeholder",
                    "Enter Register Number"
                );


            }


            else if (selectedRole === "staff") {


                $("#loginLabel").text("Staff ID");


                $("#login_id").attr(
                    "placeholder",
                    "Enter Staff ID"
                );


            }


            else if (selectedRole === "hod") {


                $("#loginLabel").text("HOD ID");


                $("#login_id").attr(
                    "placeholder",
                    "Enter HOD ID"
                );


            }


        });



        // =====================================
        // SHOW / HIDE PASSWORD
        // =====================================


        $("#togglePassword").click(function () {


            let passwordField = $("#password");


            let type = passwordField.attr("type");


            if (type === "password") {


                passwordField.attr(
                    "type",
                    "text"
                );


                $(this).html(

                    '<i class="fas fa-eye-slash"></i>'

                );


            }


            else {


                passwordField.attr(
                    "type",
                    "password"
                );


                $(this).html(

                    '<i class="fas fa-eye"></i>'

                );


            }


        });



        // =====================================
        // LOGIN AJAX
        // =====================================


        $("#loginForm").submit(function (e) {


            // Stop normal form submission

            e.preventDefault();


            // Disable login button

            $("#loginBtn")

                .prop("disabled", true)

                .text("Logging in...");



            // Send form data to backend

            $.ajax({


                // Backend login file

                url: "/leave_tih_1/ajax/login.php",


                type: "POST",


                data: $(this).serialize(),


                dataType: "json",



                // SUCCESS

                success: function (response) {


                    console.log(
                        "Server Response:",
                        response
                    );


                    if (response.status === true) {


                        console.log(
                            "Redirecting to:",
                            response.redirect
                        );


                        Swal.fire({

                            icon: "success",

                            title: "Login Successful",

                            text: response.message,

                            timer: 1500,

                            showConfirmButton: false

                        });



                        setTimeout(function () {


                            window.location.href =
                                response.redirect;


                        }, 1500);


                    }


                    else {


                        Swal.fire({

                            icon: "error",

                            title: "Login Failed",

                            text: response.message

                        });


                    }


                },



                // ERROR

                error: function (xhr, status, error) {


                    console.log(
                        "HTTP Status:",
                        xhr.status
                    );


                    console.log(
                        "AJAX Status:",
                        status
                    );


                    console.log(
                        "Error:",
                        error
                    );


                    console.log(
                        "Server Response:",
                        xhr.responseText
                    );



                    Swal.fire({

                        icon: "error",

                        title: "Login Error",

                        html:

                            "<pre style='text-align:left; white-space:pre-wrap;'>" +

                            xhr.responseText +

                            "</pre>"

                    });


                },



                // COMPLETE

                complete: function () {


                    $("#loginBtn")

                        .prop("disabled", false)

                        .text("Login");


                }


            });


        });


    </script>


</body>

</html>