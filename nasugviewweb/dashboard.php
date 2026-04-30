<?php
session_start();

/* DATABASE CONNECTION */
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "nasugview2";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

/* GET LOGGED IN ADMIN INFO */
if (isset($_SESSION['user_id'])) {
    $id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, fname, lname FROM negosyo_center_users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fname = trim($row['fname']);
        $lname = trim($row['lname']);
        $username = trim($row['username']);
        $admin_fullname = ($fname || $lname) ? trim($fname.' '.$lname) : $username;
    }
}

/* DASHBOARD COUNTS */
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM negosyo_center_users")->fetch_assoc()['total'] ?? 0;
$totalAdmins = $conn->query("SELECT COUNT(*) as total FROM negosyo_center_users WHERE designation='Admin'")->fetch_assoc()['total'] ?? 0;
$totalStaff = $conn->query("SELECT COUNT(*) as total FROM negosyo_center_users WHERE designation='Staff'")->fetch_assoc()['total'] ?? 0;
$totalAttendees = $conn->query("SELECT COUNT(*) as total FROM event_registrations")
                       ->fetch_assoc()['total'] ?? 0; 
/* ===== MEETING ATTENDEES DATA ===== */
$meetingLabels = [];
$meetingCounts = [];

$meetingQuery = "
    SELECT DATE(created_at) as meeting_date, COUNT(*) as total
    FROM event_registrations
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
";

$meetingResult = $conn->query($meetingQuery);

if ($meetingResult) {
    while ($row = $meetingResult->fetch_assoc()) {
        $meetingLabels[] = date("M d", strtotime($row['meeting_date']));
        $meetingCounts[] = (int)$row['total'];
    }
}

/* ===== ATTENDEES BY CITY DATA ===== */
$municipalityLabels = [];
$municipalityCounts = [];

$municipalityQuery = "
    SELECT city, COUNT(*) AS total
    FROM event_registrations
    WHERE city IS NOT NULL
        AND TRIM(city) <> ''
    GROUP BY city
    ORDER BY total DESC, city ASC
";

$municipalityResult = $conn->query($municipalityQuery);

if ($municipalityResult) {
    while ($row = $municipalityResult->fetch_assoc()) {
        $municipalityLabels[] = $row['city'];
        $municipalityCounts[] = (int) $row['total'];
    }
}
$municipalityChartMinWidth = max(600, count($municipalityLabels) * 110);
/* EVENTS FILTERS */
$year = isset($_GET['year']) ? trim((string) $_GET['year']) : '';
$month = isset($_GET['month']) ? trim((string) $_GET['month']) : '';
$day = isset($_GET['day']) ? trim((string) $_GET['day']) : '';

$year = preg_match('/^\d{4}$/', $year) ? $year : '';
$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : '';
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : '';

$filterType = 'year';
$filterValue = $year !== '' ? $year : date('Y');

if ($day !== '') {
    $filterType = 'day';
    $filterValue = $day;
} elseif ($month !== '') {
    $filterType = 'month';
    $filterValue = $month;
}

$chartTitle = 'Events Created (Monthly)';
$monthLabels = [];
$counts = [];
$query = '';
$params = [];
$paramTypes = '';

if ($day !== '') {
    $chartTitle = 'Events Created on ' . date('F j, Y', strtotime($day));
    $query = "SELECT DATE(created_at) AS label, COUNT(*) AS total
              FROM events
              WHERE DATE(created_at) = ?
              GROUP BY DATE(created_at)";
    $params[] = $day;
    $paramTypes = 's';
    $monthLabels = [date('M j, Y', strtotime($day))];
    $counts = [0];
} elseif ($month !== '') {
    $chartTitle = 'Events Created in ' . date('F Y', strtotime($month . '-01'));
    $daysInMonth = (int) date('t', strtotime($month . '-01'));
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $monthLabels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $counts[] = 0;
    }

    $query = "SELECT DAY(created_at) AS label, COUNT(*) AS total
              FROM events
              WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
              GROUP BY DAY(created_at)
              ORDER BY DAY(created_at) ASC";
    $params[] = $month;
    $paramTypes = 's';
} else {
    $selectedYear = $year !== '' ? $year : date('Y');
    $chartTitle = 'Events Created in ' . $selectedYear;

    for ($m = 1; $m <= 12; $m++) {
        $monthLabels[] = date('M', mktime(0, 0, 0, $m, 1));
        $counts[] = 0;
    }

    $query = "SELECT MONTH(created_at) AS label, COUNT(*) AS total
              FROM events
              WHERE YEAR(created_at) = ?
              GROUP BY MONTH(created_at)
              ORDER BY MONTH(created_at) ASC";
    $params[] = $selectedYear;
    $paramTypes = 'i';
}

$eventStmt = $conn->prepare($query);
if ($eventStmt) {
    $eventStmt->bind_param($paramTypes, ...$params);
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();

    while ($row = $eventResult->fetch_assoc()) {
        $index = (int) $row['label'] - 1;

        if ($day !== '') {
            $counts[0] = (int) $row['total'];
            continue;
        }

        if (isset($counts[$index])) {
            $counts[$index] = (int) $row['total'];
        }
    }

    $eventStmt->close();
}

/* LEADING BUSINESSES */
$leadingBusinesses = [];
$availableLeadingYears = [];
$currentYear = (int) date('Y');
$selectedLeadingYear = isset($_GET['leading_year']) && preg_match('/^\d{4}$/', (string) $_GET['leading_year'])
    ? (int) $_GET['leading_year']
    : $currentYear;

$leadingYearResult = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS review_year
    FROM reviews
    WHERE created_at IS NOT NULL
    ORDER BY review_year DESC
");

if ($leadingYearResult) {
    while ($row = $leadingYearResult->fetch_assoc()) {
        if (!empty($row['review_year'])) {
            $availableLeadingYears[] = (int) $row['review_year'];
        }
    }
}

if (empty($availableLeadingYears)) {
    $availableLeadingYears[] = $currentYear;
}

if (!in_array($selectedLeadingYear, $availableLeadingYears, true)) {
    $selectedLeadingYear = $availableLeadingYears[0];
}

$leadingBusinessStmt = $conn->prepare("
    SELECT
        b.business_name,
        b.address,
        b.business_photo,
        ROUND(AVG(r.experience_rating), 1) AS avg_rating,
        COUNT(r.id) AS total_reviews
    FROM business_owner b
    INNER JOIN reviews r
        ON r.business_id = b.b_id
    WHERE r.is_hidden = 0
        AND r.experience_rating IS NOT NULL
        AND YEAR(r.created_at) = ?
    GROUP BY b.b_id, b.business_name, b.address, b.business_photo
    ORDER BY avg_rating DESC, total_reviews DESC, b.business_name ASC
    LIMIT 3
");

if ($leadingBusinessStmt) {
    $leadingBusinessStmt->bind_param("i", $selectedLeadingYear);
    $leadingBusinessStmt->execute();
    $leadingBusinessResult = $leadingBusinessStmt->get_result();

    while ($row = $leadingBusinessResult->fetch_assoc()) {
        $leadingBusinesses[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - NasugView</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary-color: #001a47; 
    --secondary-color: #f8f9fa; 
    --gradient-start: #001a47; 
    --gradient-end: #00308a;
}
body { margin:0; padding:0; font-family:Poppins,sans-serif; min-height:100vh; overflow-x:hidden; background:linear-gradient(135deg,var(--gradient-start)0%,var(--gradient-end)100%); }
.main-content { margin-left:250px; background:var(--secondary-color); min-height:100vh; padding:3rem 2rem; }
.content-wrapper{
    width:100%;
    max-width:100%;
    margin:0;
    padding:0 10px;
}
.welcome-card { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); color:white; border-radius:20px; padding:2.5rem; margin-bottom:2rem; box-shadow:0 10px 30px rgba(0,26,71,0.3); position:relative; overflow:hidden; }
.welcome-card::before { content:''; position:absolute; top:-50%; right:-20%; width:200px; height:200px; background:rgba(255,255,255,0.1); border-radius:50%; }
.welcome-card::after { content:''; position:absolute; bottom:-30%; left:-10%; width:150px; height:150px; background:rgba(255,255,255,0.05); border-radius:50%; }
.dashboard-card { background:white; border-radius:20px; padding:2rem; margin-bottom:1.5rem; box-shadow:0 5px 25px rgba(0,0,0,0.08); border:none; transition:all 0.3s ease; position:relative; overflow:hidden; }
.dashboard-card:hover { transform:translateY(-8px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
.card-icon { width:70px; height:70px; border-radius:16px; display:flex; align-items:center; justify-content:center; margin-bottom:1.5rem; font-size:1.8rem; background:rgba(0,26,71,0.1); color:#001a47; }
.card-value { font-size:2.2rem; font-weight:700; margin:0.5rem 0; background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.card-title { font-weight:600; color:var(--primary-color); margin-bottom:1rem; font-size:1.2rem; }
.quick-action-btn { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border:none; border-radius:12px; padding:1rem 1.5rem; font-weight:600; color:white; transition:all 0.3s ease; width:100%; margin-bottom:0.5rem; font-size:0.95rem; position:relative; overflow:hidden; }
.quick-action-btn:hover { transform:translateY(-3px); box-shadow:0 8px 25px rgba(0,26,71,0.3); }
.filter-input { border:1px solid rgba(0,26,71,0.2); color:var(--primary-color); background:#fff; }
.filter-input:focus { border-color:var(--primary-color); box-shadow:0 0 0 0.2rem rgba(0,26,71,0.15); }
.filter-btn { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border:none; color:#fff; }
.filter-btn:hover,
.filter-btn:focus,
.filter-btn:active,
.filter-btn.show { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)) !important; border-color:transparent !important; color:#fff !important; box-shadow:0 0 0 0.2rem rgba(0,26,71,0.15) !important; }
.filter-reset { border:1px solid rgba(0,26,71,0.2); color:var(--primary-color); background:#fff; }
.filter-reset:hover,
.filter-reset:focus,
.filter-reset:active { border-color:var(--primary-color) !important; color:var(--primary-color) !important; background:#fff !important; box-shadow:0 0 0 0.2rem rgba(0,26,71,0.15) !important; }
.filter-wrap { display:flex; justify-content:flex-end; }
.filter-toggle { width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:12px; }
.filter-menu { min-width:220px; padding:1rem; border:none; border-radius:16px; box-shadow:0 12px 32px rgba(0,0,0,0.12); background:#fff; }
.filter-form { display:flex; flex-direction:column; gap:0.75rem; }
.filter-options { display:flex; gap:0.5rem; }
.filter-option { flex:1; border:1px solid rgba(0,26,71,0.2); background:#fff; color:var(--primary-color); border-radius:10px; padding:0.45rem 0.5rem; font-size:0.85rem; font-weight:500; }
.filter-option.active { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border-color:transparent; color:#fff; }
.filter-value { width:100%; }
.filter-actions { display:flex; gap:0.5rem; }
.filter-actions .btn { flex:1; }
.stats-grid{
    display:grid;
    grid-template-columns: repeat(5, 1fr);
    gap:1.5rem;
    margin-bottom:2rem;
}
.floating-shapes { position:absolute; top:0; left:0; right:0; bottom:0; pointer-events:none; overflow:hidden; z-index:0; }
.shape { position:absolute; border-radius:50%; background: rgba(189, 187, 219, 0.14); animation: float 6s ease-in-out infinite; }
.shape-1 { width: 80px; height: 80px; top: 10%; right: 10%; animation-delay: 0s; }
.shape-2 { width: 60px; height: 60px; bottom: 5%; right: 80%; animation-delay: 1s; }
@keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-20px) rotate(180deg); } }

.chart-wrapper { overflow:hidden; padding-bottom: 10px; }
#eventsChart{
    height:250px;
    width:100% !important;
    display:block;
    background:#fff;
}

.chart-small{
    min-height:320px;
    width:100%;
    background:#fff;
    overflow:hidden;
    position:relative;
}

.events-chart-scroll{
    width:100%;
    overflow:hidden;
    padding-bottom:8px;
    background:#fff;
    border-radius:16px;
}

.municipality-card{
    overflow:hidden;
}

.municipality-chart-scroll{
    width:100%;
    overflow:hidden;
    padding-bottom:6px;
}

#municipalityChart{
    min-height:250px;
    width:100% !important;
    display:block;
    background:#fff;
}

.blue-card{
    background: linear-gradient(135deg,#0d2f6b,#001a47);
    color:white;
    border-radius:18px;
    padding:22px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.blue-card h2{
    font-size:42px;
    font-weight:700;
    margin:0;
}

.stats-row{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:1.5rem;
    margin-bottom:2rem;
}

.dashboard-split{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:1.5rem;
    margin-bottom:2rem;
}

#municipalityChart,
#meetingChart{
    height:250px !important;
}

.white-card{
    background:white;
    border-radius:18px;
    padding:20px;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
}

.leading-card{
    background:
        radial-gradient(circle at top right, rgba(0, 48, 138, 0.10), transparent 34%),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    border:1px solid rgba(0, 26, 71, 0.08);
    position:relative;
    overflow:hidden;
}

.leading-card::before{
    content:'';
    position:absolute;
    top:-32px;
    right:-32px;
    width:120px;
    height:120px;
    border-radius:50%;
    background:rgba(0, 48, 138, 0.06);
}

.leading-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    font-weight:700;
    color:var(--primary-color);
    margin-bottom:16px;
    position:relative;
    z-index:1;
}

.leading-title-main{
    display:flex;
    align-items:center;
    gap:10px;
}

.leading-title-main i{
    width:36px;
    height:36px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, #001a47, #00308a);
    color:#fff;
    box-shadow:0 10px 18px rgba(0, 26, 71, 0.18);
}

.leading-filter{
    width:110px;
    border-radius:12px;
    border:1px solid rgba(0, 26, 71, 0.12);
    box-shadow:none;
    font-size:13px;
    position:relative;
    z-index:1;
}

.leading-item{
    display:flex;
    align-items:flex-start;
    gap:14px;
    margin-bottom:14px;
    padding:14px;
    border-radius:18px;
    background:rgba(255,255,255,0.88);
    border:1px solid rgba(226, 232, 240, 0.95);
    box-shadow:0 8px 22px rgba(15, 23, 42, 0.06);
    position:relative;
    z-index:1;
    transition:transform .25s ease, box-shadow .25s ease;
}

.leading-item:hover{
    transform:translateY(-3px);
    box-shadow:0 14px 28px rgba(15, 23, 42, 0.10);
}

.leading-item:last-child{
    margin-bottom:0;
}

.leading-photo{
    width:58px;
    height:58px;
    border-radius:50%;
    object-fit:cover;
    flex-shrink:0;
    border:3px solid rgba(255,255,255,0.95);
    background:#e5e7eb;
    box-shadow:0 10px 22px rgba(15, 23, 42, 0.12);
}

.leading-photo-fallback{
    width:58px;
    height:58px;
    border-radius:50%;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, #001a47, #00308a);
    color:#fff;
    font-size:18px;
    font-weight:700;
    text-transform:uppercase;
    box-shadow:0 10px 22px rgba(15, 23, 42, 0.12);
}

.leading-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 11px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    margin-bottom:6px;
    border:1px solid transparent;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.55);
}

.leading-badge.top-1{
    background:linear-gradient(135deg, #fff7cc, #ffe08a);
    color:#8a5a00;
    border-color:#f4cf66;
}

.leading-badge.top-2{
    background:linear-gradient(135deg, #f5f7fa, #dce3ea);
    color:#556171;
    border-color:#cfd7e1;
}

.leading-badge.top-3{
    background:linear-gradient(135deg, #fde9df, #f6c6a8);
    color:#95552b;
    border-color:#ebb188;
}

.leading-details{
    flex:1;
    min-width:0;
}

.leading-header{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:4px;
}

.leading-name{
    font-size:15px;
    font-weight:700;
    color:#0f172a;
}

.leading-meta{
    font-size:13px;
    color:#9ca3af;
    line-height:1.5;
}

.leading-meta div + div{
    margin-top:2px;
}

.leading-meta i{
    margin-right:6px;
    color:#94a3b8;
}

.counter{
    transition: all .4s ease;
}

@media (max-width:1400px){
    .stats-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }

    .dashboard-split{
        grid-template-columns:1fr;
    }
}

@media (max-width:992px){
    .main-content{
        margin-left:0;
        padding:5rem 1rem 2rem;
    }

    .content-wrapper{
        padding:0;
    }

    .stats-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:1rem;
    }

    .welcome-card,
    .dashboard-card,
    .white-card{
        border-radius:16px;
        padding:1.25rem;
    }

    .welcome-card .text-end{
        text-align:left !important;
        margin-top:1rem;
    }

    .dashboard-card:hover,
    .leading-item:hover,
    .quick-action-btn:hover{
        transform:none;
    }

    #eventsChart{
        min-width:0;
    }
}

@media (max-width:576px){
    .main-content{
        padding-left:.75rem;
        padding-right:.75rem;
    }

    .stats-grid{
        grid-template-columns:1fr;
    }

    .welcome-card h3{
        font-size:1.35rem;
    }

    .card-icon{
        width:56px;
        height:56px;
        margin-bottom:1rem;
        font-size:1.45rem;
    }

    .card-value{
        font-size:1.85rem;
    }

    .quick-action-btn{
        padding:.85rem 1rem;
    }

    .leading-title,
    .leading-item,
    .filter-actions{
        flex-direction:column;
        align-items:stretch;
    }

    .leading-filter{
        width:100%;
    }

    .leading-header{
        gap:6px;
    }

    .leading-badge{
        width:fit-content;
    }

    .row.align-items-center.mb-3 > [class*="col-"]{
        width:100%;
        text-align:left !important;
    }

    .filter-wrap{
        justify-content:flex-start;
        margin-top:.75rem;
    }

    #eventsChart{
        min-width:0;
    }
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="content-wrapper">

        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center position-relative" style="z-index:1;">
                <div class="col-md-8">
                    <h3 class="fw-bold mb-2">Welcome, <?php echo $fname; ?>! 👋</h3>
                    <p class="mb-0 opacity-90">Monitor and manage your system efficiently</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white bg-opacity-10 rounded-pill px-3 py-2 d-inline-block">
                        <small><i class="fas fa-clock me-1"></i><?php echo date('l, F j, Y'); ?></small>
                    </div>
                </div>
            </div>
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">

    <div class="dashboard-card users-card">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <h6 class="card-title">Total Users</h6>
        <div class="card-value"><?php echo number_format($totalUsers); ?></div>
        <a href="manage_users.php" class="quick-action-btn mt-3 d-block text-center">
            <i class="fas fa-user-cog"></i> Manage Users
        </a>                     
    </div>

    <div class="dashboard-card admins-card">
        <div class="card-icon"><i class="fas fa-user-shield"></i></div>
        <h6 class="card-title">Total Admins</h6>
        <div class="card-value"><?php echo number_format($totalAdmins); ?></div>
        <a href="add_admin.php" class="quick-action-btn mt-3 d-block text-center">
            <i class="fas fa-user-plus"></i> Add Admin
        </a>
    </div>

    <div class="dashboard-card staff-card">
        <div class="card-icon"><i class="fas fa-user-tie"></i></div>
        <h6 class="card-title">Total Staff</h6>
        <div class="card-value"><?php echo number_format($totalStaff); ?></div>
        <a href="view_staff.php" class="quick-action-btn mt-3 d-block text-center">
            <i class="fas fa-search"></i> View Staff
        </a>
    </div>

    <div class="dashboard-card">
        <div class="card-icon">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <h6 class="card-title">Total Events</h6>
        <div class="card-value counter" data-target="4">0</div>
    </div>

    <div class="dashboard-card">
    <div class="card-icon">
        <i class="fas fa-user-check"></i>
    </div>
    <h6 class="card-title">Total Attendees</h6>
    <div class="card-value counter" data-target="<?php echo $totalAttendees; ?>">0</div>
</div>

</div>

<div class="stats-grid">

  

</div>
</div>


<div class="dashboard-split">

    <div class="white-card municipality-card">
        <div class="d-flex justify-content-between mb-2">
            <h6>Number of invited Attendees by Municipality</h6>

        </div>

        <div class="municipality-chart-scroll">
            <canvas id="municipalityChart"></canvas>
        </div>
    </div>


    <div>

        <div class="white-card leading-card mb-3">
            <div class="leading-title">
                <div class="leading-title-main">
                    <i class="fas fa-trophy"></i>
                    <span>Leading Businesses</span>
                </div>
                <form method="GET" class="m-0">
                    <?php if ($month !== ''): ?>
                        <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                    <?php endif; ?>
                    <select name="leading_year" class="form-select form-select-sm leading-filter" onchange="this.form.submit()">
                        <?php foreach ($availableLeadingYears as $yearOption): ?>
                            <option value="<?php echo $yearOption; ?>" <?php echo $selectedLeadingYear === $yearOption ? 'selected' : ''; ?>>
                                <?php echo $yearOption; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if (!empty($leadingBusinesses)): ?>
                <?php
                $badgeLabels = [
                    1 => 'Top 1 Business',
                    2 => 'Top 2 Business',
                    3 => 'Top 3 Business'
                ];
                $badgeIcons = [
                    1 => 'fas fa-crown',
                    2 => 'fas fa-medal',
                    3 => 'fas fa-award'
                ];
                ?>
                <?php foreach ($leadingBusinesses as $index => $business): ?>
                    <?php
                    $rank = $index + 1;
                    $businessPhoto = trim((string) ($business['business_photo'] ?? ''));
                    $businessPhotoSrc = '';
                    $businessInitial = strtoupper(substr(trim((string) $business['business_name']), 0, 1));

                    if ($businessPhoto !== '') {
                        $photoCandidates = [
                            [
                                'src' => '../NasugView2/uploads/business_cover/' . $businessPhoto,
                                'path' => __DIR__ . '/../NasugView2/uploads/business_cover/' . $businessPhoto
                            ],
                            [
                                'src' => 'uploads/business_cover/' . $businessPhoto,
                                'path' => __DIR__ . '/uploads/business_cover/' . $businessPhoto
                            ],
                            [
                                'src' => 'uploads/' . $businessPhoto,
                                'path' => __DIR__ . '/uploads/' . $businessPhoto
                            ]
                        ];

                        foreach ($photoCandidates as $candidate) {
                            if (file_exists($candidate['path'])) {
                                $businessPhotoSrc = $candidate['src'];
                                break;
                            }
                        }
                    }
                    ?>
                    <div class="leading-item">
                        <?php if ($businessPhotoSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($businessPhotoSrc); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?>" class="leading-photo">
                        <?php else: ?>
                            <div class="leading-photo-fallback"><?php echo htmlspecialchars($businessInitial !== '' ? $businessInitial : 'B'); ?></div>
                        <?php endif; ?>
                        <div class="leading-details">
                            <div class="leading-header">
                                <div class="leading-name"><?php echo htmlspecialchars($business['business_name']); ?></div>
                                <div class="leading-badge top-<?php echo $rank; ?>">
                                    <i class="<?php echo htmlspecialchars($badgeIcons[$rank] ?? 'fas fa-award'); ?>"></i>
                                    <?php echo $badgeLabels[$rank] ?? 'Top Business'; ?>
                                </div>
                            </div>
                            <div class="leading-meta">
                                <div><i class="fas fa-location-dot"></i><?php echo htmlspecialchars($business['address']); ?></div>
                                <div><i class="fas fa-star"></i><?php echo number_format((float) $business['avg_rating'], 1); ?> rating</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="leading-meta mb-0">No business ratings available for <?php echo $selectedLeadingYear; ?> yet.</p>
            <?php endif; ?>

        </div>


        <div class="white-card">
            <div class="d-flex justify-content-between mb-2">
                <h6>Meeting attendees</h6>

                <select class="form-select form-select-sm" style="width:150px">
                    <option>Month</option>
                </select>
            </div>

            <canvas id="meetingChart"></canvas>
        </div>

    </div>

</div>
<div class="white-card chart-small">
                <div class="row align-items-center mb-3">
                <div class="col-md-6">
                    <h6 class="card-title"><?php echo htmlspecialchars($chartTitle); ?></h6>
                </div>
                <div class="col-md-6 text-end">
                    <div class="dropdown filter-wrap">
                        <button id="filterToggle" class="btn btn-sm filter-btn filter-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filter chart">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end filter-menu">
                            <form id="filterForm" class="filter-form">
                                <div class="filter-options">
                                    <button type="button" class="filter-option <?php echo $filterType === 'year' ? 'active' : ''; ?>" data-type="year">Year</button>
                                    <button type="button" class="filter-option <?php echo $filterType === 'month' ? 'active' : ''; ?>" data-type="month">Month</button>
                                    <button type="button" class="filter-option <?php echo $filterType === 'day' ? 'active' : ''; ?>" data-type="day">Day</button>
                                </div>
                                <input
                                    id="filterValue"
                                    class="form-control form-control-sm filter-input filter-value"
                                    value="<?php echo htmlspecialchars($filterValue); ?>"
                                    data-year="<?php echo htmlspecialchars($year !== '' ? $year : date('Y')); ?>"
                                    data-month="<?php echo htmlspecialchars($month); ?>"
                                    data-day="<?php echo htmlspecialchars($day); ?>"
                                >
                                <input type="hidden" id="filterType" value="<?php echo htmlspecialchars($filterType); ?>">
                                <?php if ($selectedLeadingYear > 0): ?>
                                    <input type="hidden" id="leadingYearValue" value="<?php echo (int) $selectedLeadingYear; ?>">
                                <?php endif; ?>
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-sm filter-btn">Apply</button>
                                    <a href="dashboard.php<?php echo $selectedLeadingYear > 0 ? '?leading_year=' . (int) $selectedLeadingYear : ''; ?>" class="btn btn-sm filter-reset">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="events-chart-scroll">
                <canvas id="eventsChart"></canvas>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('eventsChart').getContext('2d');

const eventsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($monthLabels); ?>,
        datasets: [{
            label: 'Events Created',
            data: <?php echo json_encode($counts); ?>,
            backgroundColor: '#001a47',
            borderColor: '#001a47',
            borderWidth: 1,
            maxBarThickness: 40
        }]
    },
    options: {
    responsive: true,
    maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#001a47', titleColor: '#fff', bodyColor: '#fff' }
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#001a47', font: { weight: 500 }, maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { color: '#001a47', stepSize: 1 }, grid: { color: 'rgba(0,26,71,0.1)' } }
        }
    },
    plugins: [{
        id: 'bar3d',
        afterDatasetDraw(chart) {
            const {ctx, chartArea} = chart;
            if (!chartArea) {
                return;
            }

            ctx.save();
            ctx.beginPath();
            ctx.rect(chartArea.left, chartArea.top, chartArea.right - chartArea.left, chartArea.bottom - chartArea.top);
            ctx.clip();
            chart.getDatasetMeta(0).data.forEach(bar => {
                const barWidth = bar.width;
                const barHeight = bar.height;
                const xPos = bar.x - barWidth/2;
                const yPos = bar.y;
                const depth = 10;

                // Top face
                ctx.fillStyle = '#00308a';
                ctx.beginPath();
                ctx.moveTo(xPos, yPos);
                ctx.lineTo(xPos + depth, yPos - depth);
                ctx.lineTo(xPos + barWidth + depth, yPos - depth);
                ctx.lineTo(xPos + barWidth, yPos);
                ctx.closePath();
                ctx.fill();

                // Side face
                ctx.fillStyle = '#001a47';
                ctx.beginPath();
                ctx.moveTo(xPos + barWidth, yPos);
                ctx.lineTo(xPos + barWidth + depth, yPos - depth);
                ctx.lineTo(xPos + barWidth + depth, yPos - depth + barHeight);
                ctx.lineTo(xPos + barWidth, yPos + barHeight);
                ctx.closePath();
                ctx.fill();
            });
            ctx.restore();
        }
    }]
});

const filterType = document.getElementById('filterType');
const filterValue = document.getElementById('filterValue');
const filterOptions = document.querySelectorAll('.filter-option');
const leadingYearValue = document.getElementById('leadingYearValue');

function syncFilterInput() {
    const selectedType = filterType.value;
    filterValue.name = selectedType;

    filterOptions.forEach(option => {
        option.classList.toggle('active', option.dataset.type === selectedType);
    });

    if (selectedType === 'year') {
        filterValue.type = 'number';
        filterValue.min = '2000';
        filterValue.max = '2100';
        filterValue.placeholder = 'Year';
        filterValue.value = filterValue.dataset.year || new Date().getFullYear();
    } else if (selectedType === 'month') {
        filterValue.type = 'month';
        filterValue.removeAttribute('min');
        filterValue.removeAttribute('max');
        filterValue.placeholder = '';
        filterValue.value = filterValue.dataset.month || '';
    } else {
        filterValue.type = 'date';
        filterValue.removeAttribute('min');
        filterValue.removeAttribute('max');
        filterValue.placeholder = '';
        filterValue.value = filterValue.dataset.day || '';
    }
}

filterOptions.forEach(option => {
    option.addEventListener('click', function () {
        filterType.value = this.dataset.type;
        syncFilterInput();
    });
});

syncFilterInput();

document.getElementById('filterForm').addEventListener('submit', function(e){
    e.preventDefault();

    filterValue.dataset.year = filterType.value === 'year' ? filterValue.value : filterValue.dataset.year;
    filterValue.dataset.month = filterType.value === 'month' ? filterValue.value : filterValue.dataset.month;
    filterValue.dataset.day = filterType.value === 'day' ? filterValue.value : filterValue.dataset.day;

    const params = new URLSearchParams();
    if (filterValue.value) {
        params.set(filterType.value, filterValue.value);
    }
    if (leadingYearValue && leadingYearValue.value) {
        params.set('leading_year', leadingYearValue.value);
    }

    window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
});

/* ===== ANIMATION COUNTER ===== */
const counters = document.querySelectorAll('.counter');

counters.forEach(counter => {
    const updateCount = () => {
        const target = +counter.getAttribute('data-target');
        const count = +counter.innerText;
        const increment = target / 80;

        if(count < target){
            counter.innerText = Math.ceil(count + increment);
            setTimeout(updateCount, 15);
        } else {
            counter.innerText = target;
        }
    };
    updateCount();
});


/* ===== MUNICIPALITY CHART ===== */
new Chart(document.getElementById('municipalityChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($municipalityLabels); ?>,
        datasets: [
            {
                label:'',
                data: <?php echo json_encode($municipalityCounts); ?>,
                borderColor:'#0d47a1',
                backgroundColor:'rgba(13, 71, 161, 0.12)',
                pointBackgroundColor:'#0d47a1',
                pointBorderColor:'#ffffff',
                pointRadius:4,
                pointHoverRadius:6,
                fill:true,
                tension:.35
            }
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{
                display:true,
                position:'top',
                labels:{
                    generateLabels(chart){
                        const labels = chart.data.labels || [];
                        return labels.map((label, index) => ({
                            text: label,
                            fillStyle: '#0d47a1',
                            strokeStyle: '#0d47a1',
                            lineWidth: 1,
                            hidden: false,
                            index: index
                        }));
                    }
                }
            },
            tooltip:{
                backgroundColor:'#001a47',
                titleColor:'#fff',
                bodyColor:'#fff',
                callbacks:{
                    label:function(context){
                        return 'Count: ' + context.parsed.y;
                    }
                }
            }
        },
        scales:{
            x:{
                grid:{display:false},
                ticks:{color:'#001a47', autoSkip:false, maxRotation:0, minRotation:0}
            },
            y:{
                beginAtZero:true,
                ticks:{stepSize:1, color:'#001a47'},
                grid:{color:'rgba(0,26,71,0.1)'}
            }
        }
    }
});


/* ===== MEETING CHART (REAL DATA) ===== */
new Chart(document.getElementById('meetingChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($meetingLabels); ?>,
        datasets: [{
            label:'Attendees',
            data: <?php echo json_encode($meetingCounts); ?>,
            backgroundColor:[
                '#0d2f6b',
                '#00308a',
                '#001a47',
                '#021f5a',
                '#123a8c',
                '#0b2c63'
            ]
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{display:false}
        },
        scales:{
            y:{
                beginAtZero:true,
                ticks:{stepSize:1}
            }
        }
    }
});
</script>

</body>
</html>
