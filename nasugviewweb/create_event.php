<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$created_by_user_id = (int) $_SESSION['user_id'];

function buildEventCode($eventId) {
    return "EVT" . str_pad((string)$eventId, 4, "0", STR_PAD_LEFT);
}

function saveEventCode($conn, $eventId) {
    $event_code = buildEventCode($eventId);
    $stmt = $conn->prepare("UPDATE events SET event_code=? WHERE id=?");
    $stmt->bind_param("si", $event_code, $eventId);
    $stmt->execute();
    $stmt->close();
    return $event_code;
}

// ==============================
// Initialize variables
// ==============================
$success = $error = "";

// Event form fields
$title = $mode = $google_meet_link = $start_date = $end_date = $speaker = $budget = $address = $audience = $funding = $description = "";

// ==============================
// Handle form submission
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $created_at = date("Y-m-d H:i:s"); // current date and time

    if(isset($_POST['form_type']) && $_POST['form_type'] == 'event') {
        $title       = $conn->real_escape_string($_POST['title']);
        $mode        = $conn->real_escape_string($_POST['mode']);
        $google_meet_link = $conn->real_escape_string(trim($_POST['google_meet_link'] ?? ''));
        $start_date  = $conn->real_escape_string($_POST['start_date']);
        $end_date    = $conn->real_escape_string($_POST['end_date']);
        $speaker     = $conn->real_escape_string($_POST['resource_speaker']);
        $budget      = $conn->real_escape_string($_POST['budget']);
        $address     = $conn->real_escape_string($_POST['address']);
        $audience    = $conn->real_escape_string($_POST['audience']);
        $funding     = $conn->real_escape_string($_POST['funding']);
        $description = $conn->real_escape_string($_POST['description']);

        if ($title && $mode && $start_date && $end_date && ($mode !== 'Webinar' || $google_meet_link !== '')) {
            if ($mode !== 'Webinar') {
                $google_meet_link = '';
            }

            $start_dt = new DateTime($start_date);
            $end_dt   = new DateTime($end_date);

            if ($end_dt <= $start_dt) {
                $error = "End date and time must be later than the start date and time.";
            } else {
                $interval = $start_dt->diff($end_dt);

                if ($interval->days > 0) {
                    $hours = $interval->h;
                    $duration = $interval->days . " day" . ($interval->days > 1 ? "s" : "");
                    if ($hours > 0) $duration .= " " . $hours . " hr" . ($hours > 1 ? "s" : "");
                } else {
                    $duration = $interval->h . " hr" . ($interval->h > 1 ? "s" : "") . " " . $interval->i . " min";
                }

                $now = new DateTime();
                if ($now < $start_dt) { $status = "For Implementation"; $remarks = "For Future"; }
                elseif ($now >= $start_dt && $now <= $end_dt) { $status = "Ongoing"; $remarks = "In Progress"; }
                else { $status = "Implemented"; $remarks = "Done"; }

                $stmt = $conn->prepare("
                    INSERT INTO events
                    (event_code, created_by_user_id, title, mode_of_delivery, google_meet_link, start_date_and_time, end_date_and_time, speaker, budget, address, audience, funding_source, description, duration, status, remarks, created_at)
                    VALUES ('PENDING', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "isssssssssssssss",
                    $created_by_user_id, $title, $mode, $google_meet_link, $start_date, $end_date, $speaker, $budget,
                    $address, $audience, $funding, $description, $duration, $status, $remarks, $created_at
                );

                if ($stmt->execute()) {
                    saveEventCode($conn, $stmt->insert_id);
                    $success = "Event created successfully!";
                    $title = $mode = $google_meet_link = $start_date = $end_date = $speaker = $budget = $address = $audience = $funding = $description = "";
                } else {
                    $error = "Error creating event: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $error = "Please fill all required fields.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Event - NasugView</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background-color: #f0f4ff; font-family: 'Poppins', sans-serif; padding: 30px 0; }
.card { max-width: 950px; margin: 0 auto; padding: 2rem; border-radius: 10px; background: #fff; border-left: 7px solid #001a47; box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
.card h3 { color:#001a47; font-weight:700; margin-bottom:1.5rem; }
.row.g-3 > div { margin-bottom:16px; }
.form-control, .form-select, textarea { border-radius: 10px; border:1px solid #d6e4ff; box-shadow: 0 0 0 3px rgba(0,26,71,0.08); height:44px; padding: 8px 10px; }
textarea.form-control { height:120px; resize:none; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #001a47 !important; box-shadow: 0 0 0 4px rgba(0,26,71,0.25) !important; }
.schedule-helper { background:#f8fbff; border:1px solid #d6e4ff; border-radius:10px; padding:1rem; margin:-.35rem 0 1rem; }
.schedule-helper-title { color:#001a47; font-weight:700; font-size:.95rem; margin-bottom:.75rem; }
.quick-date-buttons { display:flex; flex-wrap:wrap; gap:.5rem; }
.quick-date-btn { border:1px solid rgba(0,26,71,.18); background:#fff; color:#001a47; border-radius:8px; padding:.5rem .75rem; font-weight:600; }
.quick-date-btn:hover { background:#eaf1ff; }
.datetime-builder { display:grid; grid-template-columns:1.35fr .72fr .72fr .8fr; gap:.5rem; }
.datetime-builder input,
.datetime-builder select { box-shadow:0 0 0 3px rgba(0,26,71,0.08); }
.datetime-builder input[type="number"] { text-align:center; }
.schedule-helper .form-select { box-shadow:none; }
.schedule-note { margin:.6rem 0 0; color:#64748b; font-size:.88rem; }
.btn-submit { background: linear-gradient(135deg,#001a47,#00308a) !important; color:#fff !important; border-radius:10px; padding:10px 24px; font-weight:600; border:none; box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-submit:hover { background: linear-gradient(135deg,#00308a,#001a47) !important; color:#fff !important; transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }
.btn-secondary { background: linear-gradient(135deg,#001a47,#00308a); border-radius:10px; padding:10px 22px; border:none; color:#fff; box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-secondary:hover { background: linear-gradient(135deg,#00308a,#001a47); color:#fff; transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }
@media (max-width:992px) {
    body {
        padding:1rem;
    }

    .col-md-6 {
        flex:0 0 100%;
        max-width:100%;
    }
}

@media (max-width:576px) {
    body {
        padding:.75rem;
    }

    .card {
        padding:1.25rem;
        border-radius:10px;
    }

    .card h3 {
        font-size:1.35rem;
    }

    .mt-3 .btn {
        width:100%;
        margin-left:0 !important;
        margin-top:.5rem;
    }
}
</style>
</head>
<body>

<div class="card">
    <h3><i class="fas fa-calendar-plus"></i> Create Event</h3>

    <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="form_type" value="event">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mode of Delivery <span class="text-danger">*</span></label>
                        <select name="mode" id="modeOfDelivery" class="form-select" required>
                            <option value="">Select Mode</option>
                            <option value="Seminar" <?php echo ($mode=="Seminar")?"selected":""; ?>>Seminar</option>
                            <option value="Webinar" <?php echo ($mode=="Webinar")?"selected":""; ?>>Webinar</option>
                            <option value="Public Event" <?php echo ($mode=="Public Event")?"selected":""; ?>>Public Event</option>
                        </select>
                    </div>
                    <div class="col-12" id="googleMeetLinkGroup" style="display:none;">
                        <label class="form-label">Google Meet / Zoom Link <span class="text-danger">*</span></label>
                        <input type="url" name="google_meet_link" id="googleMeetLink" class="form-control" value="<?php echo htmlspecialchars($google_meet_link); ?>" placeholder="https://meet.google.com/xxx-xxxx-xxx or https://zoom.us/j/...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                        <input type="hidden" name="start_date" id="eventStartDate" class="schedule-start" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        <div class="datetime-builder" data-target="eventStartDate">
                            <input type="date" class="form-control date-part" required>
                            <input type="number" class="form-control hour-part" min="1" max="12" placeholder="HH" required>
                            <input type="number" class="form-control minute-part" min="0" max="59" step="5" placeholder="MM" required>
                            <select class="form-select ampm-part" required>
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date & Time <span class="text-danger">*</span></label>
                        <input type="hidden" name="end_date" id="eventEndDate" class="schedule-end" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        <div class="datetime-builder" data-target="eventEndDate">
                            <input type="date" class="form-control date-part" required>
                            <input type="number" class="form-control hour-part" min="1" max="12" placeholder="HH" required>
                            <input type="number" class="form-control minute-part" min="0" max="59" step="5" placeholder="MM" required>
                            <select class="form-select ampm-part" required>
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="schedule-helper" data-start="eventStartDate" data-end="eventEndDate">
                            <div class="schedule-helper-title"><i class="fas fa-clock me-2"></i>Quick schedule setup</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-7">
                                    <label class="form-label small mb-1">Quick date</label>
                                    <div class="quick-date-buttons">
                                        <button type="button" class="quick-date-btn" data-offset-days="0">Today</button>
                                        <button type="button" class="quick-date-btn" data-offset-days="1">Tomorrow</button>
                                        <button type="button" class="quick-date-btn" data-offset-days="7">Next Week</button>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Duration</label>
                                    <select class="form-select schedule-duration">
                                        <option value="60">1 hour</option>
                                        <option value="120" selected>2 hours</option>
                                        <option value="180">3 hours</option>
                                        <option value="240">4 hours</option>
                                        <option value="480">Whole day</option>
                                    </select>
                                </div>
                            </div>
                            <p class="schedule-note">Type the time or use the small arrows. The end time fills automatically.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resource Speaker</label>
                        <input type="text" name="resource_speaker" class="form-control" value="<?php echo htmlspecialchars($speaker); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Budget</label>
                        <input type="number" name="budget" class="form-control" value="<?php echo htmlspecialchars($budget); ?>" min="0" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Target Audience</label>
                        <input type="text" name="audience" class="form-control" value="<?php echo htmlspecialchars($audience); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address / Venue</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($address); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Funding Source / Resource</label>
                        <input type="text" name="funding" class="form-control" value="<?php echo htmlspecialchars($funding); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description / Remarks</label>
                        <textarea name="description" class="form-control"><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-submit"><i class="fas fa-plus"></i> Create Event</button>
                    <a href="events.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
            </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modeOfDelivery = document.getElementById('modeOfDelivery');
const googleMeetLinkGroup = document.getElementById('googleMeetLinkGroup');
const googleMeetLink = document.getElementById('googleMeetLink');

function toggleGoogleMeetLink() {
    const isWebinar = modeOfDelivery.value === 'Webinar';
    googleMeetLinkGroup.style.display = isWebinar ? '' : 'none';
    googleMeetLink.required = isWebinar;
    if (!isWebinar) googleMeetLink.value = '';
}

modeOfDelivery.addEventListener('change', toggleGoogleMeetLink);
toggleGoogleMeetLink();

function padDatePart(value) {
    return String(value).padStart(2, '0');
}

function toDatetimeLocalValue(date) {
    return [
        date.getFullYear(),
        padDatePart(date.getMonth() + 1),
        padDatePart(date.getDate())
    ].join('-') + 'T' + [
        padDatePart(date.getHours()),
        padDatePart(date.getMinutes())
    ].join(':');
}

function roundToNextHour(date) {
    const rounded = new Date(date);
    rounded.setMinutes(0, 0, 0);
    if (rounded <= date) {
        rounded.setHours(rounded.getHours() + 1);
    }
    return rounded;
}

function addMinutes(date, minutes) {
    return new Date(date.getTime() + minutes * 60000);
}

const datetimeBuilders = new Map();

function clampNumber(value, min, max) {
    const number = Number(value);
    if (!Number.isFinite(number)) return min;
    return Math.min(max, Math.max(min, number));
}

function dateFromLocalValue(value) {
    if (!value) return null;
    const [datePart, timePart] = value.split('T');
    if (!datePart || !timePart) return null;
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    return new Date(year, month - 1, day, hour, minute);
}

function updateBuilderFromHidden(targetId) {
    const builder = datetimeBuilders.get(targetId);
    const hiddenInput = document.getElementById(targetId);
    if (!builder || !hiddenInput || !hiddenInput.value) return;

    const date = dateFromLocalValue(hiddenInput.value);
    if (!date || Number.isNaN(date.getTime())) return;

    let hour = date.getHours();
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    builder.date.value = hiddenInput.value.slice(0, 10);
    builder.hour.value = hour;
    builder.minute.value = padDatePart(date.getMinutes());
    builder.ampm.value = ampm;
}

function updateHiddenFromBuilder(targetId) {
    const builder = datetimeBuilders.get(targetId);
    const hiddenInput = document.getElementById(targetId);
    if (!builder || !hiddenInput) return;

    const date = builder.date.value;
    const hour12 = clampNumber(builder.hour.value, 1, 12);
    const minute = clampNumber(builder.minute.value, 0, 59);
    let hour24 = hour12 % 12;

    if (builder.ampm.value === 'PM') {
        hour24 += 12;
    }

    builder.hour.value = hour12;
    builder.minute.value = padDatePart(minute);
    hiddenInput.value = date ? `${date}T${padDatePart(hour24)}:${padDatePart(minute)}` : '';
    hiddenInput.dispatchEvent(new Event('change'));
}

function setupDatetimeBuilder(builderElement) {
    const targetId = builderElement.dataset.target;
    const controls = {
        date: builderElement.querySelector('.date-part'),
        hour: builderElement.querySelector('.hour-part'),
        minute: builderElement.querySelector('.minute-part'),
        ampm: builderElement.querySelector('.ampm-part')
    };

    if (!targetId || Object.values(controls).some(control => !control)) return;

    datetimeBuilders.set(targetId, controls);
    Object.values(controls).forEach(control => {
        control.addEventListener('change', () => updateHiddenFromBuilder(targetId));
        control.addEventListener('input', () => updateHiddenFromBuilder(targetId));
    });
    updateBuilderFromHidden(targetId);
}

function setupScheduleHelper(helper) {
    const startInput = document.getElementById(helper.dataset.start);
    const endInput = document.getElementById(helper.dataset.end);
    const durationInput = helper.querySelector('.schedule-duration');
    const quickButtons = helper.querySelectorAll('.quick-date-btn');

    if (!startInput || !endInput || !durationInput) return;

    const now = new Date();
    const minValue = toDatetimeLocalValue(now);
    startInput.min = minValue;
    endInput.min = minValue;

    function syncEndTime(force = false) {
        if (!startInput.value) return;
        const start = new Date(startInput.value);
        const currentEnd = endInput.value ? new Date(endInput.value) : null;
        const duration = Number(durationInput.value || 120);

        if (force || !currentEnd || currentEnd <= start) {
            endInput.value = toDatetimeLocalValue(addMinutes(start, duration));
            updateBuilderFromHidden(endInput.id);
        }

        endInput.min = startInput.value;
    }

    startInput.addEventListener('change', () => syncEndTime(false));
    durationInput.addEventListener('change', () => syncEndTime(true));

    quickButtons.forEach(button => {
        button.addEventListener('click', () => {
            const offsetDays = Number(button.dataset.offsetDays || 0);
            const start = roundToNextHour(new Date());
            start.setDate(start.getDate() + offsetDays);
            startInput.value = toDatetimeLocalValue(start);
            updateBuilderFromHidden(startInput.id);
            syncEndTime(true);
            const builder = datetimeBuilders.get(startInput.id);
            if (builder) builder.hour.focus();
        });
    });

    syncEndTime(false);
}

document.querySelectorAll('.datetime-builder').forEach(setupDatetimeBuilder);
document.querySelectorAll('.schedule-helper').forEach(setupScheduleHelper);
</script>
</body>
</html>
