<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$admin_fullname = "User";
$designation = "Unknown";

$admin_stmt = $conn->prepare("SELECT username, fname, lname, designation FROM negosyo_center_users WHERE id=? LIMIT 1");
if ($admin_stmt) {
    $admin_stmt->bind_param("i", $user_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($admin = $admin_result->fetch_assoc()) {
        $name = trim((string) ($admin['fname'] ?? '') . ' ' . (string) ($admin['lname'] ?? ''));
        $admin_fullname = $name !== '' ? $name : (trim((string) ($admin['username'] ?? '')) ?: 'User');
        $designation = trim((string) ($admin['designation'] ?? '')) ?: 'Unknown';
    }

    $admin_stmt->close();
}

$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = null;

if ($event_id > 0) {
    $stmt = $conn->prepare("
        SELECT e.*,
            CASE
                WHEN e.status = 'Canceled' THEN 'Canceled'
                WHEN NOW() < e.start_date_and_time THEN 'For Implementation'
                WHEN NOW() BETWEEN e.start_date_and_time AND e.end_date_and_time THEN 'Ongoing'
                WHEN NOW() > e.end_date_and_time THEN 'Implemented'
                ELSE e.status
            END AS calculated_status
        FROM events e
        WHERE e.id=? AND e.created_by_user_id=?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("ii", $event_id, $user_id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$current_page = "events.php";

function event_value($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : '-';
}

function event_date_value($value): string
{
    $time = strtotime((string) $value);
    return $time ? date("M d, Y h:i A", $time) : '-';
}

function event_duration_value($startValue, $endValue, $storedDuration): string
{
    $storedDuration = trim((string) ($storedDuration ?? ''));
    if ($storedDuration !== '') {
        return $storedDuration;
    }

    $start = strtotime((string) $startValue);
    $end = strtotime((string) $endValue);

    if (!$start || !$end || $end <= $start) {
        return '-';
    }

    $seconds = $end - $start;
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $duration = ($days > 0 ? $days . "d " : "") . ($hours > 0 ? $hours . "h " : "") . ($minutes > 0 ? $minutes . "m" : "");
    return trim($duration) !== '' ? trim($duration) : '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Information - NasugView</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --primary-color:#001a47;
    --secondary-color:#f8f9fa;
    --sidebar-width:250px;
}

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:var(--secondary-color);
}

.main-content{
    margin-left:var(--sidebar-width);
    min-height:100vh;
    padding:2rem;
}

.page-title{
    color:var(--primary-color);
}

.details-panel{
    background:#fff;
    border-radius:10px;
    box-shadow:0 5px 25px rgba(0,0,0,.08);
    overflow:hidden;
}

.details-header{
    padding:1.5rem 2rem;
    background:linear-gradient(135deg,#123c73,#1d5ea8);
    color:#fff;
}

.details-body{
    padding:2rem;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}

.info-item{
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    padding:1rem;
    background:#fff;
}

.info-label{
    margin-bottom:.35rem;
    color:#64748b;
    font-size:.78rem;
    font-weight:700;
    text-transform:uppercase;
}

.info-value{
    margin:0;
    color:#0f172a;
    overflow-wrap:anywhere;
}

.full-width{
    grid-column:1 / -1;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    padding:.7rem 1rem;
    border-radius:8px;
    background:var(--primary-color);
    color:#fff;
    text-decoration:none;
    font-weight:600;
}

.back-btn:hover{
    color:#fff;
    filter:brightness(1.08);
}

.empty-state{
    background:#fff;
    border-radius:10px;
    padding:3rem 1rem;
    text-align:center;
    color:#64748b;
    box-shadow:0 5px 25px rgba(0,0,0,.08);
}

@media(max-width:992px){
    .main-content{
        margin-left:0;
        padding:5rem 1rem 2rem;
    }

    .info-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="fw-bold page-title mb-1">Event Information</h2>
            <p class="text-muted mb-0">View event details and schedule information</p>
        </div>
        <a href="events.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (!$event): ?>
        <div class="empty-state">Event not found.</div>
    <?php else: ?>
        <?php
        $event_code = event_value($event['event_code'] ?? '');
        if ($event_code === '-') {
            $event_code = "EVT" . str_pad((string) ($event['id'] ?? 0), 4, "0", STR_PAD_LEFT);
        }

        $status = event_value($event['calculated_status'] ?? $event['status'] ?? '');
        $default_remarks = ($status === 'For Implementation') ? 'Incoming' : (($status === 'Ongoing') ? 'In Progress' : 'Done');
        $remarks = trim((string) ($event['remarks'] ?? '')) !== '' ? $event['remarks'] : $default_remarks;
        ?>
        <section class="details-panel">
            <div class="details-header">
                <h3 class="mb-1"><?php echo htmlspecialchars(event_value($event['title'] ?? '')); ?></h3>
                <p class="mb-0"><?php echo htmlspecialchars($event_code); ?></p>
            </div>

            <div class="details-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Event Title</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['title'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Event Code</div>
                        <p class="info-value"><?php echo htmlspecialchars($event_code); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Start Date & Time</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_date_value($event['start_date_and_time'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">End Date & Time</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_date_value($event['end_date_and_time'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_duration_value($event['start_date_and_time'] ?? '', $event['end_date_and_time'] ?? '', $event['duration'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <p class="info-value"><?php echo htmlspecialchars($status); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Mode of Delivery</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['mode_of_delivery'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Resource Speaker</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['speaker'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Target Audience</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['audience'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Client</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['client'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Budget</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['budget'] ?? '')); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Funding Source</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['funding_source'] ?? '')); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Address / Venue</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['address'] ?? '')); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Google Meet / Zoom Link</div>
                        <p class="info-value"><?php echo htmlspecialchars(event_value($event['google_meet_link'] ?? '')); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Remarks</div>
                        <p class="info-value"><?php echo nl2br(htmlspecialchars(event_value($remarks))); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Description</div>
                        <p class="info-value"><?php echo nl2br(htmlspecialchars(event_value($event['description'] ?? ''))); ?></p>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
