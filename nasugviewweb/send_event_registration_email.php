<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";
require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function json_response(bool $success, string $message): void
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

function email_value($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : 'N/A';
}

function email_date($value): string
{
    $time = strtotime((string) $value);
    return $time ? date("F d, Y h:i A", $time) : 'N/A';
}

function ensure_registration_email_log(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_registration_email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            event_code VARCHAR(50) NOT NULL,
            email VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_registration_success (registration_id, status),
            INDEX idx_registration_email_logs_registration (registration_id),
            INDEX idx_registration_email_logs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function log_registration_email(mysqli $conn, array $registration, string $status, string $message): void
{
    ensure_registration_email_log($conn);

    $registration_id = (int) ($registration['id'] ?? 0);
    $event_code = email_value($registration['event_code'] ?? '');
    $email = email_value($registration['email'] ?? '');

    $stmt = $conn->prepare("
        INSERT IGNORE INTO event_registration_email_logs (registration_id, event_code, email, status, message)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("issss", $registration_id, $event_code, $email, $status, $message);
        $stmt->execute();
        $stmt->close();
    }
}

function send_event_details_email(array $registration): array
{
    $recipient_email = trim((string) ($registration['email'] ?? ''));
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Registrant email address is invalid.'];
    }

    $recipient_name = trim((string) ($registration['first_name'] ?? '') . ' ' . (string) ($registration['last_name'] ?? ''));
    $recipient_name = $recipient_name !== '' ? $recipient_name : 'Participant';

    $smtpConfig = require __DIR__ . "/smtp_config.php";
    if (empty($smtpConfig['enabled'])) {
        return ['success' => false, 'message' => 'SMTP is not enabled.'];
    }

    $event_title = email_value($registration['title'] ?? '');
    $event_code = email_value($registration['event_code'] ?? '');
    $mode = email_value($registration['mode_of_delivery'] ?? '');
    $meeting_link = email_value($registration['google_meet_link'] ?? '');
    $venue = email_value($registration['address'] ?? '');
    $start = email_date($registration['start_date_and_time'] ?? '');
    $end = email_date($registration['end_date_and_time'] ?? '');
    $speaker = email_value($registration['speaker'] ?? '');
    $audience = email_value($registration['audience'] ?? '');
    $description = email_value($registration['description'] ?? '');

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = trim((string) ($smtpConfig['host'] ?? ''));
        $mail->SMTPAuth = true;
        $mail->Username = trim((string) ($smtpConfig['username'] ?? ''));
        $mail->Password = (string) ($smtpConfig['password'] ?? '');

        $encryption = strtolower(trim((string) ($smtpConfig['encryption'] ?? 'tls')));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->Port = (int) ($smtpConfig['port'] ?? 587);
        $mail->setFrom(
            trim((string) ($smtpConfig['from_email'] ?? $smtpConfig['username'] ?? '')),
            trim((string) ($smtpConfig['from_name'] ?? 'Department of Trade and Industry - Negosyo Center'))
        );
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $mail->Subject = "Event Details - " . $event_title;

        $safe_name = htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8');
        $safe_title = htmlspecialchars($event_title, ENT_QUOTES, 'UTF-8');
        $safe_code = htmlspecialchars($event_code, ENT_QUOTES, 'UTF-8');
        $safe_mode = htmlspecialchars($mode, ENT_QUOTES, 'UTF-8');
        $safe_start = htmlspecialchars($start, ENT_QUOTES, 'UTF-8');
        $safe_end = htmlspecialchars($end, ENT_QUOTES, 'UTF-8');
        $safe_venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');
        $safe_meeting_link = htmlspecialchars($meeting_link, ENT_QUOTES, 'UTF-8');
        $safe_speaker = htmlspecialchars($speaker, ENT_QUOTES, 'UTF-8');
        $safe_audience = htmlspecialchars($audience, ENT_QUOTES, 'UTF-8');
        $safe_description = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
        $meeting_button = filter_var($meeting_link, FILTER_VALIDATE_URL)
            ? "<p><a href=\"{$safe_meeting_link}\" style=\"display:inline-block;background:#001a47;color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:700;\">Open Meeting Link</a></p>"
            : "";

        $mail->Body = "
            <div style=\"font-family:Arial,sans-serif;color:#1f2937;line-height:1.6;\">
                <h2 style=\"color:#001a47;margin-bottom:8px;\">Event Details</h2>
                <p>Dear <strong>{$safe_name}</strong>,</p>
                <p>Here are the details for your registered event:</p>
                <div style=\"border:1px solid #dbe4f0;border-radius:10px;padding:16px;background:#f8fbff;\">
                    <p><strong>Event:</strong> {$safe_title}</p>
                    <p><strong>Event Code:</strong> {$safe_code}</p>
                    <p><strong>Mode:</strong> {$safe_mode}</p>
                    <p><strong>Start:</strong> {$safe_start}</p>
                    <p><strong>End:</strong> {$safe_end}</p>
                    <p><strong>Venue / Address:</strong> {$safe_venue}</p>
                    <p><strong>Google Meet / Zoom Link:</strong> {$safe_meeting_link}</p>
                    {$meeting_button}
                    <p><strong>Resource Speaker:</strong> {$safe_speaker}</p>
                    <p><strong>Target Audience:</strong> {$safe_audience}</p>
                    <p><strong>Description:</strong><br>{$safe_description}</p>
                </div>
                <p>Please keep this email for your reference.</p>
                <p>Respectfully,<br><strong>DTI Batangas - Negosyo Center</strong></p>
            </div>
        ";

        $mail->AltBody =
            "Event Details\n\n" .
            "Dear {$recipient_name},\n\n" .
            "Event: {$event_title}\n" .
            "Event Code: {$event_code}\n" .
            "Mode: {$mode}\n" .
            "Start: {$start}\n" .
            "End: {$end}\n" .
            "Venue / Address: {$venue}\n" .
            "Google Meet / Zoom Link: {$meeting_link}\n" .
            "Resource Speaker: {$speaker}\n" .
            "Target Audience: {$audience}\n" .
            "Description: {$description}\n";

        $mail->send();
        return ['success' => true, 'message' => 'Event details email sent to ' . $recipient_email . '.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

if (!isset($_SESSION['user_id'])) {
    json_response(false, 'Please log in first.');
}

$admin_id = (int) $_SESSION['user_id'];
$auto_send = isset($_POST['auto_send']) && $_POST['auto_send'] === '1';
$registration_id = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;

ensure_registration_email_log($conn);

if ($auto_send) {
    $pendingStmt = $conn->prepare("
        SELECT
            er.id,
            er.email,
            er.first_name,
            er.last_name,
            er.event_code,
            e.title,
            e.mode_of_delivery,
            e.google_meet_link,
            e.start_date_and_time,
            e.end_date_and_time,
            e.address,
            e.speaker,
            e.audience,
            e.description
        FROM event_registrations er
        INNER JOIN events e
            ON e.event_code = er.event_code
        LEFT JOIN event_registration_email_logs sent_log
            ON sent_log.registration_id = er.id
            AND sent_log.status = 'sent'
        WHERE e.created_by_user_id = ?
            AND sent_log.id IS NULL
            AND er.email IS NOT NULL
            AND TRIM(er.email) <> ''
        ORDER BY er.created_at ASC, er.id ASC
        LIMIT 10
    ");

    if (!$pendingStmt) {
        json_response(false, 'Could not prepare pending emails.');
    }

    $pendingStmt->bind_param("i", $admin_id);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result();
    $sent = 0;
    $failed = 0;

    while ($pending = $pendingResult->fetch_assoc()) {
        $emailResult = send_event_details_email($pending);
        if ($emailResult['success']) {
            $sent++;
            log_registration_email($conn, $pending, 'sent', $emailResult['message']);
        } else {
            $failed++;
            log_registration_email($conn, $pending, 'failed', $emailResult['message']);
        }
    }

    $pendingStmt->close();
    json_response(true, "Automatic email check complete. Sent: {$sent}. Failed: {$failed}.");
}

if ($registration_id <= 0) {
    json_response(false, 'Invalid registration selected.');
}

$stmt = $conn->prepare("
    SELECT
        er.id,
        er.email,
        er.first_name,
        er.last_name,
        er.event_code,
        e.title,
        e.mode_of_delivery,
        e.google_meet_link,
        e.start_date_and_time,
        e.end_date_and_time,
        e.address,
        e.speaker,
        e.audience,
        e.description
    FROM event_registrations er
    INNER JOIN events e
        ON e.event_code = er.event_code
    WHERE er.id = ? AND e.created_by_user_id = ?
    LIMIT 1
");

if (!$stmt) {
    json_response(false, 'Could not prepare email details.');
}

$stmt->bind_param("ii", $registration_id, $admin_id);
$stmt->execute();
$registration = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$registration) {
    json_response(false, 'Registration not found for this Negosyo Center.');
}

$emailResult = send_event_details_email($registration);
log_registration_email($conn, $registration, $emailResult['success'] ? 'sent' : 'failed', $emailResult['message']);
json_response($emailResult['success'], $emailResult['message']);
