<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ==============================
// Fetch admin info
// ==============================
if(isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT fname, lname, designation FROM negosyo_center_users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()){
        $admin_fullname = trim($row['fname'] . ' ' . $row['lname']);
        $designation    = $row['designation'];
    } else {
        $admin_fullname = "User";
        $designation    = "Unknown";
    }
} else {
    $admin_fullname = "User";
    $designation    = "Unknown";
}

// ==============================
// Fetch events with status calculation
// ==============================
$sql = "SELECT e.*,
        CASE 
            WHEN e.status = 'Canceled' THEN 'Canceled'
            WHEN NOW() < e.start_date_and_time THEN 'For Implementation'
            WHEN NOW() BETWEEN e.start_date_and_time AND e.end_date_and_time THEN 'Ongoing'
            WHEN NOW() > e.end_date_and_time THEN 'Implemented'
            ELSE e.status
        END AS calculated_status
        FROM events e
        WHERE e.created_by_user_id = ?
        ORDER BY e.start_date_and_time DESC";

$eventsStmt = $conn->prepare($sql);
$eventsStmt->bind_param("i", $user_id);
$eventsStmt->execute();
$events = $eventsStmt->get_result();

// ==============================
// Get Events Count Dynamically
// ==============================
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE created_by_user_id = ?");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$totalEvents = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE created_by_user_id = ? AND start_date_and_time > NOW()");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$upcomingEvents = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE created_by_user_id = ? AND end_date_and_time < NOW()");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$pastEvents = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Events - NasugView</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
body { font-family:'Poppins', sans-serif; background:#f0f4ff; }
.main-content { margin-left:250px; padding:2rem; min-height:100vh; }
.card { border-radius:10px; padding:2rem; background:#fff; border-left:6px solid #001a47; box-shadow:0 8px 25px rgba(0,0,0,0.08); }
.card h3 { color:#001a47; font-weight:700; margin-bottom:1.5rem; }
.event-stat-card {
    background:linear-gradient(180deg,#ffffff 0%, #f8fbff 100%);
    border:1px solid rgba(0,26,71,.08);
    border-left:0;
    padding:1rem;
}
.event-stat-card h4 {
    margin:0 0 .25rem;
    font-size:.82rem;
    color:#6b7280;
    font-weight:600;
}
.event-stat-card h2 {
    margin:0;
    font-size:1.4rem;
    color:#001a47;
    font-weight:700;
}

.form-control, .form-select, textarea { border-radius:10px; border:1px solid #d6e4ff; box-shadow:0 0 0 3px rgba(0,26,71,0.08); padding:8px 10px; height:44px; }
textarea.form-control { height:120px; resize:none; }
.form-control:focus, .form-select:focus, textarea:focus { border-color:#001a47; box-shadow:0 0 0 4px rgba(0,26,71,0.25); }

.btn-submit { background:linear-gradient(135deg,#001a47,#00308a); color:#fff; border-radius:10px; padding:10px 24px; font-weight:600; border:none; box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-submit:hover { background:linear-gradient(135deg,#00308a,#001a47); color:#fff; transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }

.btn-secondary { border-radius:10px; padding:10px 22px; border:none; color:#fff; background:linear-gradient(135deg,#001a47,#00308a); box-shadow:0 10px 22px rgba(0,26,71,0.18); }
.btn-secondary:hover { background:linear-gradient(135deg,#00308a,#001a47); color:#fff; transform:translateY(-2px); box-shadow:0 14px 28px rgba(0,26,71,0.24); }

.row.g-3 > div { margin-bottom:16px; }

.table-responsive { border:1px solid rgba(0,26,71,.08); border-radius:10px; overflow:visible; background:#fff; }
.table { border-collapse:collapse; }
.table th,
.table td { border:1px solid rgba(15,23,42,.08); padding:.62rem .75rem; font-size:0.88rem; vertical-align:top; }
.table th { background:linear-gradient(135deg,#123c73,#1d5ea8); color:white; font-weight:600; line-height:1.25; white-space:normal; }
.table tbody tr:nth-child(even) td { background:#f8fafc; }

.action-buttons { display:flex; gap:0.5rem; align-items:center; }
.btn-action { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; border:none; transition: all 0.3s ease; background: linear-gradient(135deg,#001a47,#00308a); color:white; }
.btn-action:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.2); }
.event-code-btn {border:0;background:#eef4ff;color:#001a47;border-radius:8px;padding:.45rem .7rem;font-weight:800;letter-spacing:.04em;box-shadow:inset 0 0 0 1px rgba(0,26,71,.14);}
.event-code-btn:hover {background:#dbeafe;color:#001a47;}
.code-display-body {background:#001a47;color:#fff;text-align:center;padding:3rem 1.5rem;}
.code-display-label {font-size:1rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#cfe0ff;margin-bottom:1rem;}
.code-display-value {font-size:clamp(3rem, 12vw, 8rem);font-weight:800;line-height:1;letter-spacing:.06em;word-break:break-word;}
.code-display-hint {margin-top:1.4rem;color:#dbeafe;font-size:1rem;}

.dropdown-menu { min-width:150px; }

.table-full { width:100%; margin-top:1rem; }

#searchInput { margin-bottom:1rem; }

.modal-body p { margin-bottom:0.5rem; } /* simpler list look */

@media (max-width:992px) {
    .main-content {
        margin-left:0;
        padding:5rem 1rem 2rem;
    }

    .col-md-6 {
        flex:0 0 100%;
        max-width:100%;
    }
}

@media (max-width:768px) {
    .card {
        padding:1.25rem;
        border-radius:10px;
    }

    .row.mb-4 {
        row-gap:1rem;
    }

    .table-full > .d-flex {
        flex-direction:column;
        gap:.75rem;
    }

    #searchInput {
        margin-bottom:0;
        width:100%;
    }

    .btn-submit {
        width:100%;
        margin-left:0 !important;
    }

    .table-responsive {
        border:0;
        border-radius:0;
        overflow:visible;
        background:transparent;
    }

    #eventsTable thead {
        display:none;
    }

    #eventsTable,
    #eventsTable tbody,
    #eventsTable tr,
    #eventsTable td {
        display:block;
        width:100%;
    }

    #eventsTable tr {
        margin-bottom:1rem;
        border:1px solid #e5e7eb;
        border-radius:8px;
        overflow:hidden;
        background:#fff;
    }

    #eventsTable td {
        display:flex;
        justify-content:space-between;
        gap:1rem;
        padding:.8rem 1rem;
        text-align:right;
        border-bottom:1px solid #f1f3f4;
        overflow-wrap:anywhere;
    }

    #eventsTable td:last-child {
        border-bottom:0;
    }

    #eventsTable td::before {
        content:"";
        color:#001a47;
        font-weight:700;
        text-align:left;
        flex:0 0 42%;
    }

    #eventsTable td:nth-child(1)::before { content:"Event Title"; }
    #eventsTable td:nth-child(2)::before { content:"Start & End Date"; }
    #eventsTable td:nth-child(3)::before { content:"Duration"; }
    #eventsTable td:nth-child(4)::before { content:"Event Code"; }
    #eventsTable td:nth-child(5)::before { content:"Mode of Delivery"; }
    #eventsTable td:nth-child(6)::before { content:"Status"; }
    #eventsTable td:nth-child(7)::before { content:"Remarks"; }
    #eventsTable td:nth-child(8)::before { content:"Actions"; }

    .action-buttons {
        justify-content:flex-end;
    }
}

@media (max-width:576px) {
    .main-content {
        padding-left:.75rem;
        padding-right:.75rem;
    }

    .card h4 {
        font-size:1rem;
    }
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- Events Stats -->
    <div class="row mb-4">
        <div class="col-md-4"><div class="card event-stat-card text-center"><h4>Total Events</h4><h2><?php echo $totalEvents; ?></h2></div></div>
        <div class="col-md-4"><div class="card event-stat-card text-center"><h4>Upcoming Events</h4><h2><?php echo $upcomingEvents; ?></h2></div></div>
        <div class="col-md-4"><div class="card event-stat-card text-center"><h4>Past Events</h4><h2><?php echo $pastEvents; ?></h2></div></div>
    </div>

    <!-- Events Table -->
    <div class="card p-3 table-full">
        <div class="d-flex justify-content-between mb-3">
            <input type="text" id="searchInput" class="form-control" placeholder="Search events...">
            <a href="create_event.php" class="btn btn-submit ms-2"><i class="fas fa-plus"></i> Create Event</a>
        </div>

        <div class="table-responsive">
        <table class="table table-hover mb-0" id="eventsTable">
            <thead>
                <tr>
                    <th>Event Title</th>
                    <th>Start & End Date</th>
                    <th>Duration</th>
                    <th>Event Code</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($event = $events->fetch_assoc()):
                    $start = strtotime($event['start_date_and_time']);
                    $end   = strtotime($event['end_date_and_time']);

                    $duration = "N/A";
                    if($end && $start) {
                        $seconds = $end - $start;
                        $days    = floor($seconds / 86400);
                        $hours   = floor(($seconds % 86400) / 3600);
                        $minutes = floor(($seconds % 3600) / 60);
                        $duration = ($days>0 ? $days."d ":"").($hours>0 ? $hours."h ":"").($minutes>0 ? $minutes."m":"");
                    }

                    $event_code = trim($event['event_code'] ?? '') !== ''
                        ? $event['event_code']
                        : "EVT" . str_pad($event['id'], 4, "0", STR_PAD_LEFT);
                    $status = $event['calculated_status'];
                    $defaultRemarks = ($status == 'For Implementation') ? "Incoming" : (($status=='Ongoing') ? "In Progress" : "Done");
                    $remarks = trim($event['remarks'] ?? '') !== '' ? $event['remarks'] : $defaultRemarks;
                    $storedStatus = trim($event['status'] ?? '');
                    $canCancel = !in_array($status, ['Implemented', 'Done', 'Canceled'], true)
                        && !in_array($storedStatus, ['Implemented', 'Done', 'Canceled'], true)
                        && strcasecmp(trim($remarks), 'Done') !== 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                    <td><?php echo date("M d, Y h:i A", $start) . " - " . date("M d, Y h:i A", $end); ?></td>
                    <td><?php echo $duration; ?></td>
                    <td>
                        <button
                            type="button"
                            class="event-code-btn eventCodeBtn"
                            data-code="<?php echo htmlspecialchars($event_code); ?>"
                            data-title="<?php echo htmlspecialchars($event['title']); ?>"
                            title="Show event code"
                        >
                            <?php echo htmlspecialchars($event_code); ?>
                        </button>
                    </td>
                    <td><?php echo htmlspecialchars($event['mode_of_delivery'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($status); ?></td>
                    <td><?php echo $remarks; ?></td>
                    <td>
                        <div class="action-buttons">
                            <!-- View Button -->
                            <a class="btn-action" href="view_event.php?id=<?php echo (int) $event['id']; ?>" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>

                            <!-- Dropdown Actions -->
                            <div class="dropdown">
                                <button type="button" class="btn-action" data-bs-toggle="dropdown" title="More Actions"><i class="fas fa-ellipsis-h"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item downloadPDF" href="#" data-event-id="<?php echo $event['id']; ?>">Download PDF</a></li>
                                    <li><a class="dropdown-item viewPDF" href="#" data-event-id="<?php echo $event['id']; ?>">View as PDF</a></li>
                                    <li><a class="dropdown-item editEvent" href="edit_event.php?id=<?php echo $event['id']; ?>">Edit</a></li>
                                    <li><a class="dropdown-item rescheduleEvent" href="#"
                                        data-event-id="<?php echo $event['id']; ?>"
                                        data-old-start="<?php echo date('Y-m-d\TH:i', $start); ?>"
                                        data-old-end="<?php echo date('Y-m-d\TH:i', $end); ?>"
                                        data-old-start-display="<?php echo date('M d, Y h:i A', $start); ?>"
                                        data-old-end-display="<?php echo date('M d, Y h:i A', $end); ?>">Reschedule</a></li>
                                    <?php if ($canCancel): ?>
                                    <li><a class="dropdown-item text-warning cancelEvent" href="#" data-event-id="<?php echo $event['id']; ?>" data-status="<?php echo htmlspecialchars($status); ?>">Cancel</a></li>
                                    <?php else: ?>
                                    <li><span class="dropdown-item text-muted disabled">Cancel unavailable</span></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger deleteEvent" href="#" data-event-id="<?php echo $event['id']; ?>">Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Event Code Display Modal -->
<div class="modal fade" id="eventCodeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background:#001a47; color:white;">
        <h5 class="modal-title" id="eventCodeModalTitle">Event Code</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body code-display-body">
        <div class="code-display-label">Event Code</div>
        <div class="code-display-value" id="largeEventCode"></div>
        <div class="code-display-hint">Show this code to participants during the seminar.</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// =====================
// Instant Search
// =====================
const searchInput = document.getElementById('searchInput');
const tableRows = document.querySelectorAll('#eventsTable tbody tr');
searchInput.addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    tableRows.forEach(row => {
        const title = row.cells[0].textContent.toLowerCase();
        row.style.display = title.includes(filter) ? '' : 'none';
    });
});

const eventCodeModal = new bootstrap.Modal(document.getElementById('eventCodeModal'));

document.querySelectorAll('.eventCodeBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('largeEventCode').textContent = btn.dataset.code;
        document.getElementById('eventCodeModalTitle').textContent = btn.dataset.title || 'Event Code';
        eventCodeModal.show();
    });
});

// =====================
// Dropdown Option Actions
// =====================
document.querySelectorAll('.downloadPDF').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const eventId = btn.dataset.eventId;
        window.open(`download_event.php?id=${eventId}`, '_blank');
    });
});

document.querySelectorAll('.viewPDF').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const eventId = btn.dataset.eventId;
        window.open(`view_event_pdf.php?id=${eventId}`, '_blank');
    });
});

document.querySelectorAll('.rescheduleEvent').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const eventId = btn.dataset.eventId;
        const oldStart = btn.dataset.oldStartDisplay;
        const oldEnd = btn.dataset.oldEndDisplay;
        Swal.fire({
            title: 'Reschedule Event',
            html: `
                <div class="text-start">
                    <label class="form-label mb-1"><strong>Old Start Date & Time</strong></label>
                    <input type="text" class="swal2-input" value="${oldStart}" readonly>
                    <label class="form-label mb-1"><strong>Old End Date & Time</strong></label>
                    <input type="text" class="swal2-input" value="${oldEnd}" readonly>
                    <label class="form-label mb-1"><strong>New Start Date & Time</strong></label>
                    <input type="datetime-local" id="newStart" class="swal2-input" value="${btn.dataset.oldStart}">
                    <label class="form-label mb-1"><strong>New End Date & Time</strong></label>
                    <input type="datetime-local" id="newEnd" class="swal2-input" value="${btn.dataset.oldEnd}">
                    <label class="form-label mb-1"><strong>Reason / Remarks</strong></label>
                    <textarea id="rescheduleRemarks" class="swal2-textarea" placeholder="Why is this event being rescheduled?"></textarea>
                </div>
            `,
            confirmButtonText: 'Update',
            showCancelButton: true,
            preConfirm: () => {
                const start = document.getElementById('newStart').value;
                const end = document.getElementById('newEnd').value;
                const remarks = document.getElementById('rescheduleRemarks').value.trim();
                if(!start || !end) {
                    Swal.showValidationMessage('Please fill both dates');
                    return false;
                }
                if(new Date(end) <= new Date(start)) {
                    Swal.showValidationMessage('End date must be later than start date');
                    return false;
                }
                if(!remarks) {
                    Swal.showValidationMessage('Please add the reschedule reason in remarks');
                    return false;
                }
                return {start, end, remarks};
            }
        }).then(result => {
            if(result.isConfirmed){
                fetch('reschedule_event.php', {
                    method:'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id:eventId, start:result.value.start, end:result.value.end, remarks:result.value.remarks})
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) Swal.fire('Updated!','Event rescheduled.','success').then(()=> location.reload());
                    else Swal.fire('Error', data.error, 'error');
                });
            }
        });
    });
});

document.querySelectorAll('.cancelEvent').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const eventId = btn.dataset.eventId;
        const status = btn.dataset.status;
        if(status === 'Implemented' || status === 'Done') {
            Swal.fire('Not allowed', 'This event already happened, so it cannot be canceled.', 'info');
            return;
        }
        Swal.fire({
            title:'Cancel Event?',
            text:'This will mark the event as canceled.',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Yes, Cancel it'
        }).then(result=>{
            if(result.isConfirmed){
                fetch('cancel_event.php', {
                    method:'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id:eventId})
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) Swal.fire('Canceled!','Event marked as canceled.','success').then(()=> location.reload());
                    else Swal.fire('Error', data.error, 'error');
                });
            }
        });
    });
});

document.querySelectorAll('.deleteEvent').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const eventId = btn.dataset.eventId;
        Swal.fire({
            title:'Delete Event?',
            text:'This action is permanent!',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Yes, delete'
        }).then(result=>{
            if(result.isConfirmed){
                fetch('delete_event.php', {
                    method:'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({id:eventId})
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) Swal.fire('Deleted!','Event removed permanently.','success').then(()=> location.reload());
                    else Swal.fire('Error', data.error, 'error');
                });
            }
        });
    });
});
</script>
</body>
</html>
