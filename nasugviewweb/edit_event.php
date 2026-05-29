<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get event ID safely
$id = intval($_GET['id']);

// Flag for successful update
$updated = false;
$error = "";

// Handle POST request to update event
if($_SERVER["REQUEST_METHOD"] == "POST"){

    $title = $_POST['title'] ?? '';
    $start = $_POST['start'] ?? '';
    $end   = $_POST['end'] ?? '';
    $address = $_POST['address'] ?? '';
    $mode = $_POST['mode'] ?? '';
    $google_meet_link = trim($_POST['google_meet_link'] ?? '');
    if ($mode === 'Webinar' && $google_meet_link === '') {
        $error = "Google Meet / Zoom Link is required for webinar events.";
    } elseif ($mode !== 'Webinar') {
        $google_meet_link = '';
    }

    if ($error === '') {
        $speaker = $_POST['speaker'] ?? '';
        $audience = $_POST['audience'] ?? '';
        $budget = $_POST['budget'] ?? '';
        $funding = $_POST['funding'] ?? '';
        $description = $_POST['description'] ?? '';

        // SQL without 'category' since your DB doesn't have it
        $stmt = $conn->prepare("
            UPDATE events SET
            title=?, start_date_and_time=?, end_date_and_time=?, address=?, 
            mode_of_delivery=?, google_meet_link=?, speaker=?, audience=?, 
            budget=?, funding_source=?, description=?
            WHERE id=? AND created_by_user_id=?
        ");

        // Bind params correctly (11 strings + 2 ints)
        $stmt->bind_param(
            "sssssssssssii",
            $title, $start, $end, $address, $mode, $google_meet_link, $speaker,
            $audience, $budget, $funding, $description, $id, $user_id
        );

        $stmt->execute();

        $updated = $stmt->affected_rows >= 0; // mark successful update
    }
}

// Fetch event safely
$eventStmt = $conn->prepare("SELECT * FROM events WHERE id=? AND created_by_user_id=? LIMIT 1");
$eventStmt->bind_param("ii", $id, $user_id);
$eventStmt->execute();
$event = $eventStmt->get_result()->fetch_assoc() ?? [];
if (!$event) {
    die("Event not found or not allowed.");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Event</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
body { padding: 30px; background: #f0f4ff; font-family: 'Poppins', sans-serif; }
.form-control { border-radius: 10px; }
.btn-primary { background: linear-gradient(135deg,#001a47,#00308a); border: none; box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-primary:hover { background: linear-gradient(135deg,#00308a,#001a47); transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }
.btn-secondary { border-radius: 10px; border: none; color: #fff; background: linear-gradient(135deg,#001a47,#00308a); box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-secondary:hover { background: linear-gradient(135deg,#00308a,#001a47); color: #fff; transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }
form {
    max-width:900px;
}

@media (max-width:576px) {
    body {
        padding:.75rem;
    }

    h3 {
        font-size:1.35rem;
    }

    .btn {
        width:100%;
        margin-top:.5rem;
    }
}
</style>
</head>
<body>

<h3>Edit Event</h3>

<?php if($error !== ''): ?>
<div class="alert alert-danger" style="max-width:900px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

    <input class="form-control mb-2" name="title" value="<?= htmlspecialchars($event['title'] ?? '') ?>" placeholder="Event Title" required>

    <input type="datetime-local" class="form-control mb-2" name="start"
    value="<?= isset($event['start_date_and_time']) ? date('Y-m-d\TH:i', strtotime($event['start_date_and_time'])) : '' ?>">

    <input type="datetime-local" class="form-control mb-2" name="end"
    value="<?= isset($event['end_date_and_time']) ? date('Y-m-d\TH:i', strtotime($event['end_date_and_time'])) : '' ?>">

    <input class="form-control mb-2" name="address" value="<?= htmlspecialchars($event['address'] ?? '') ?>" placeholder="Address / Venue">
    <select class="form-control mb-2" name="mode" id="modeOfDelivery">
        <option value="">Select Mode</option>
        <option value="Seminar" <?= (($event['mode_of_delivery'] ?? '') === 'Seminar') ? 'selected' : '' ?>>Seminar</option>
        <option value="Webinar" <?= (($event['mode_of_delivery'] ?? '') === 'Webinar') ? 'selected' : '' ?>>Webinar</option>
        <option value="Public Event" <?= (($event['mode_of_delivery'] ?? '') === 'Public Event') ? 'selected' : '' ?>>Public Event</option>
    </select>
    <input type="url" class="form-control mb-2" name="google_meet_link" id="googleMeetLink" value="<?= htmlspecialchars($event['google_meet_link'] ?? '') ?>" placeholder="Google Meet / Zoom Link">
    <input class="form-control mb-2" name="speaker" value="<?= htmlspecialchars($event['speaker'] ?? '') ?>" placeholder="Resource Speaker">
    <input class="form-control mb-2" name="audience" value="<?= htmlspecialchars($event['audience'] ?? '') ?>" placeholder="Target Audience">
    <input class="form-control mb-2" name="budget" value="<?= htmlspecialchars($event['budget'] ?? '') ?>" placeholder="Budget">
    <input class="form-control mb-2" name="funding" value="<?= htmlspecialchars($event['funding_source'] ?? '') ?>" placeholder="Funding Source">
    <textarea class="form-control mb-3" name="description" placeholder="Description / Remarks"><?= htmlspecialchars($event['description'] ?? '') ?></textarea>

    <button class="btn btn-primary">Update Event</button>
    <a href="events.php" class="btn btn-secondary">Cancel</a>

</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const modeOfDelivery = document.getElementById('modeOfDelivery');
const googleMeetLink = document.getElementById('googleMeetLink');

function toggleGoogleMeetLink() {
    const isWebinar = modeOfDelivery.value === 'Webinar';
    googleMeetLink.style.display = isWebinar ? '' : 'none';
    googleMeetLink.required = isWebinar;
    if (!isWebinar) googleMeetLink.value = '';
}

modeOfDelivery.addEventListener('change', toggleGoogleMeetLink);
toggleGoogleMeetLink();
</script>
<?php if($updated): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Event Updated!',
    text: 'The event has been successfully updated.',
    confirmButtonColor: '#001a47'
}).then(() => {
    window.location.href = 'events.php';
});
</script>
<?php endif; ?>

</body>
</html>
