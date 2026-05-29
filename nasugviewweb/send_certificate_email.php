<?php
header('Content-Type: application/json');

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'smtp_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $recipient_name = $_POST['recipient_name'] ?? '';
    $recipient_email = $_POST['recipient_email'] ?? '';
    $event_title = $_POST['event_title'] ?? 'Certificate';
    $image = $_POST['image'] ?? '';

    $mail = new PHPMailer(true);

    try {

        // =====================
        // SMTP CONFIG
        // =====================
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'DTI Batangas - Negosyo Center');
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);

        // =====================
        // SUBJECT
        // =====================
        $mail->Subject = "Certificate of Participation – $event_title";

        // =====================
        // EMAIL BODY
        // =====================
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; color:#1f2937; line-height:1.7;'>

            <h2 style='color:#17356f;'>
                Certificate of Participation
            </h2>

            <p>Dear <b>$recipient_name</b>,</p>

            <p>Greetings!</p>

            <p>
                This is to formally acknowledge your participation in the event:
            </p>

            <p style='font-weight:bold; color:#111827;'>
                $event_title
            </p>

            <p>
                We sincerely appreciate your active participation and valuable contribution.
                Your involvement greatly contributed to the success of the program.
            </p>

            <p>
                Kindly find attached your official Certificate of Participation.
            </p>

            <p>
                Thank you for your support and engagement.
            </p>

            <br>

            <p>
                Respectfully yours,<br>
                <b>DTI Batangas – Negosyo Center</b>
            </p>

        </div>
        ";

        // =====================
        // ATTACH CERTIFICATE IMAGE
        // =====================
        if (!empty($image)) {
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = base64_decode($image);
            $mail->addStringAttachment($image, "certificate.png", "base64", "image/png");
        }

        // =====================
        // SEND EMAIL
        // =====================
        $mail->send();

        echo json_encode([
            "status" => "sent",
            "message" => "Email sent successfully"
        ]);

    } catch (Exception $e) {

        echo json_encode([
            "status" => "error",
            "message" => $mail->ErrorInfo
        ]);
    }
}
?>