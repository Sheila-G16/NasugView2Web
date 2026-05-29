<?php
require "db.php";
require_once __DIR__ . "/../nasugviewweb/account_security.php";

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . "/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/PHPMailer/src/SMTP.php";
require __DIR__ . "/PHPMailer/src/Exception.php";

header('Content-Type: application/json');

try {
    nasugviewweb_ensure_password_security_columns($conn);
    $conn->begin_transaction();

    $required = ['province','municipality','username','fname','lname','designation','account_email'];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing field: $field");
        }
    }

    $province      = trim($_POST['province']);
    $municipality  = trim($_POST['municipality']);
    $negosyocenter = "Negosyo Center - " . $municipality;
    $username      = trim($_POST['username']);
    $fname         = trim($_POST['fname']);
    $lname         = trim($_POST['lname']);
    $designation   = trim($_POST['designation']);
    $contact       = trim($_POST['contact'] ?? '');
    $email         = trim($_POST['account_email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }

    $allowedProvinces = [
        'Abra',
        'Agusan del Norte',
        'Agusan del Sur',
        'Aklan',
        'Albay',
        'Antique',
        'Apayao',
        'Aurora',
        'Basilan',
        'Bataan',
        'Batanes',
        'Batangas',
        'Benguet',
        'Biliran',
        'Bohol',
        'Bukidnon',
        'Bulacan',
        'Cagayan',
        'Camarines Norte',
        'Camarines Sur',
        'Camiguin',
        'Capiz',
        'Catanduanes',
        'Cavite',
        'Cebu',
        'Cotabato',
        'Davao de Oro',
        'Davao del Norte',
        'Davao del Sur',
        'Davao Occidental',
        'Davao Oriental',
        'Dinagat Islands',
        'Eastern Samar',
        'Guimaras',
        'Ifugao',
        'Ilocos Norte',
        'Ilocos Sur',
        'Iloilo',
        'Isabela',
        'Kalinga',
        'La Union',
        'Laguna',
        'Lanao del Norte',
        'Lanao del Sur',
        'Leyte',
        'Maguindanao del Norte',
        'Maguindanao del Sur',
        'Marinduque',
        'Masbate',
        'Misamis Occidental',
        'Misamis Oriental',
        'Mountain Province',
        'Negros Occidental',
        'Negros Oriental',
        'Northern Samar',
        'Nueva Ecija',
        'Nueva Vizcaya',
        'Occidental Mindoro',
        'Oriental Mindoro',
        'Palawan',
        'Pampanga',
        'Pangasinan',
        'Quezon',
        'Quirino',
        'Rizal',
        'Romblon',
        'Samar',
        'Sarangani',
        'Siquijor',
        'Sorsogon',
        'South Cotabato',
        'Southern Leyte',
        'Sultan Kudarat',
        'Sulu',
        'Surigao del Norte',
        'Surigao del Sur',
        'Tarlac',
        'Tawi-Tawi',
        'Zambales',
        'Zamboanga del Norte',
        'Zamboanga del Sur',
        'Zamboanga Sibugay'
    ];

    $allowedMunicipalities = [
        'Batangas' => [
            'Agoncillo',
            'Alitagtag',
            'Balayan',
            'Balete',
            'Batangas City',
            'Bauan',
            'Calaca City',
            'Calatagan',
            'Cuenca',
            'Ibaan',
            'Laurel',
            'Lemery',
            'Lian',
            'Lipa City',
            'Lobo',
            'Mabini',
            'Malvar',
            'Mataasnakahoy',
            'Nasugbu',
            'Padre Garcia',
            'Rosario',
            'San Jose',
            'San Juan',
            'San Luis',
            'San Nicolas',
            'San Pascual',
            'Santa Teresita',
            'Santo Tomas City',
            'Taal',
            'Talisay',
            'Tanauan City',
            'Taysan',
            'Tingloy',
            'Tuy'
        ]
    ];

    if (!in_array($province, $allowedProvinces, true)) {
        throw new Exception("Please select a valid province.");
    }

    if (isset($allowedMunicipalities[$province]) && !in_array($municipality, $allowedMunicipalities[$province], true)) {
        throw new Exception("Please select a valid municipality or city.");
    }

    if (!preg_match('/^[A-Za-z0-9 .,\-()]+$/', $municipality)) {
        throw new Exception("Please enter a valid municipality or city.");
    }

    // check duplicate center
    $checkCenter = $conn->prepare("
        SELECT id FROM negosyo_center_users
        WHERE LOWER(negosyocenter)=LOWER(?)
        LIMIT 1
    ");
    $checkCenter->bind_param("s", $negosyocenter);
    $checkCenter->execute();
    $checkCenter->store_result();

    if ($checkCenter->num_rows > 0) {
        throw new Exception("This Negosyo Center already has an account.");
    }
    $checkCenter->close();

    // check duplicate user
    $checkUser = $conn->prepare("
        SELECT id FROM negosyo_center_users
        WHERE username=? OR (email=? AND email <> '')
        LIMIT 1
    ");
    $checkUser->bind_param("ss", $username, $email);
    $checkUser->execute();
    $checkUser->store_result();

    if ($checkUser->num_rows > 0) {
        throw new Exception("Username or email already exists.");
    }
    $checkUser->close();

    // temp password
    $temp = substr(bin2hex(random_bytes(8)), 0, 10);
    $password = password_hash($temp, PASSWORD_DEFAULT);

    $profile_img = '';

    // insert
    $stmt = $conn->prepare("
        INSERT INTO negosyo_center_users
        (email, username, password, fname, lname, designation, negosyocenter, contact, profile_img, branch_name, municipality, province, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        "ssssssssssss",
        $email,
        $username,
        $password,
        $fname,
        $lname,
        $designation,
        $negosyocenter,
        $contact,
        $profile_img,
        $negosyocenter,
        $municipality,
        $province
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    // SMTP CONFIG
    $config = require "smtp_config.php";

    $emailSent = false;

    // SEND EMAIL
    if ($config['enabled']) {

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($email, $fname . ' ' . $lname);

        $mail->isHTML(true);
        $mail->Subject = "NasugView Account Credentials";

        $loginUrl = "https://nasugview.com/negosyocenter/";
        $safeName = htmlspecialchars(trim($fname . ' ' . $lname), ENT_QUOTES, 'UTF-8');
        $safeCenter = htmlspecialchars($negosyocenter, ENT_QUOTES, 'UTF-8');
        $safeMunicipality = htmlspecialchars($municipality, ENT_QUOTES, 'UTF-8');
        $safeProvince = htmlspecialchars($province, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($temp, ENT_QUOTES, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <h2>NasugView Account Created</h2>
            <p>Hello {$safeName},</p>
            <p>Your Negosyo Center account has been created. You can now sign in using these credentials:</p>
            <p><b>Negosyo Center:</b> {$safeCenter}</p>
            <p><b>Municipality / City:</b> {$safeMunicipality}</p>
            <p><b>Province:</b> {$safeProvince}</p>
            <p><b>Username:</b> {$safeUsername}</p>
            <p><b>Email:</b> {$safeEmail}</p>
            <p><b>Temporary Password:</b> {$safePassword}</p>
            <p><b>Login Link:</b> <a href=\"{$safeLoginUrl}\">{$safeLoginUrl}</a></p>
            <p>Please keep these credentials secure.</p>
        ";
        $mail->AltBody = "NasugView Account Created\n\n"
            . "Negosyo Center: {$negosyocenter}\n"
            . "Municipality / City: {$municipality}\n"
            . "Province: {$province}\n"
            . "Username: {$username}\n"
            . "Email: {$email}\n"
            . "Temporary Password: {$temp}\n"
            . "Login Link: {$loginUrl}\n";

        $mail->send();
        $emailSent = true;
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $emailSent ? "Account created and email sent." : "Account created. Email sending is disabled.",
        "center" => $negosyocenter,
        "municipality" => $municipality,
        "province" => $province,
        "username" => $username,
        "email" => $email,
        "temp" => $temp,
        "email_sent" => $emailSent
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
