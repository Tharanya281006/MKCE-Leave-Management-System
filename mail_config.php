<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/* =========================================
   LOAD PHPMailer USING COMPOSER
========================================= */

require_once __DIR__ . "/vendor/autoload.php";


/* =========================================
   SEND LEAVE EMAIL FUNCTION
========================================= */

function sendLeaveEmail(
    $toEmail,
    $studentName,
    $subject,
    $message
) {

    $mail = new PHPMailer(true);

    try {

        /* =========================================
           SMTP SETTINGS
        ========================================= */

        $mail->isSMTP();

        $mail->Host = "smtp.gmail.com";

        $mail->SMTPAuth = true;

        $mail->Username = "tharanyam20@gmail.com";

        /*
         * IMPORTANT:
         * Use a NEW Gmail App Password here.
         * Do NOT use your old exposed password.
         */
        $mail->Password = "khpmgkqkmsjgnofk";

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = "UTF-8";


        /* =========================================
           EMAIL SETTINGS
        ========================================= */

        $mail->setFrom(
            "tharanyam20@gmail.com",
            "Leave Management System"
        );

        $mail->addAddress(
            $toEmail,
            $studentName
        );

        $mail->isHTML(true);

        $mail->Subject = $subject;


        /* =========================================
           EMAIL BODY
        ========================================= */

        $mail->Body = "

            <h2>Leave Management System</h2>

            <p>
                Hello <b>" . htmlspecialchars($studentName) . "</b>,
            </p>

            " . $message . "

            <br><br>

            <p>
                Thank You,<br>
                Leave Management System
            </p>

        ";


        /* =========================================
           SEND EMAIL
        ========================================= */

        $mail->send();

        return true;

    }

    catch (Exception $e) {

        error_log(
            "Mail Error: " .
            $mail->ErrorInfo .
            " | Exception: " .
            $e->getMessage()
        );

        return false;
    }
}

?>