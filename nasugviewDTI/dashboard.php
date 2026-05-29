<?php
session_start();
require_once __DIR__ . "/db.php";

$admin_fullname = "User";
$designation = "DTI Admin";
$fname = "User";

if (isset($_SESSION['user_id'])) {
    $id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, fname, lname, designation FROM dti_user WHERE dti_id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fname = trim((string) $row['fname']);
        $lname = trim((string) $row['lname']);
        $username = trim((string) $row['username']);
        $admin_fullname = ($fname !== '' || $lname !== '') ? trim($fname . ' ' . $lname) : $username;
        $designation = trim((string) $row['designation']) ?: "DTI Admin";
    }

    $stmt->close();
}

$totalCenters = (int) ($conn->query("SELECT COUNT(DISTINCT negosyocenter) AS total FROM negosyo_center_users WHERE TRIM(negosyocenter) <> ''")->fetch_assoc()['total'] ?? 0);
$totalCenterUsers = (int) ($conn->query("SELECT COUNT(*) AS total FROM negosyo_center_users")->fetch_assoc()['total'] ?? 0);
$totalEvents = (int) ($conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc()['total'] ?? 0);
$implementedEvents = (int) ($conn->query("SELECT COUNT(*) AS total FROM events WHERE status='Implemented' OR remarks='Done' OR end_date_and_time < NOW()")->fetch_assoc()['total'] ?? 0);

$year = isset($_GET['year']) ? trim((string) $_GET['year']) : '';
$month = isset($_GET['month']) ? trim((string) $_GET['month']) : '';
$day = isset($_GET['day']) ? trim((string) $_GET['day']) : '';
$year = preg_match('/^\d{4}$/', $year) ? $year : '';
$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : '';
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : '';
$selectedCenter = isset($_GET['center']) ? trim((string) $_GET['center']) : '';
$filterType = 'year';
$filterValue = $year !== '' ? $year : date('Y');

if ($day !== '') {
    $filterType = 'day';
    $filterValue = $day;
} elseif ($month !== '') {
    $filterType = 'month';
    $filterValue = $month;
}

$dateColumn = "COALESCE(e.created_at, e.start_date_and_time)";
$whereParts = [];
$params = [];
$paramTypes = '';
$dateWhereParts = [];
$dateParams = [];
$dateParamTypes = '';

$centerOptions = [];
$centerOptionsResult = $conn->query("
    SELECT DISTINCT negosyocenter
    FROM negosyo_center_users
    WHERE TRIM(negosyocenter) <> ''
    ORDER BY negosyocenter ASC
");
if ($centerOptionsResult) {
    while ($row = $centerOptionsResult->fetch_assoc()) {
        $centerOptions[] = (string) $row['negosyocenter'];
    }
}

if ($selectedCenter !== '' && !in_array($selectedCenter, $centerOptions, true)) {
    $selectedCenter = '';
}

if ($day !== '') {
    $dateWhereParts[] = "DATE($dateColumn) = ?";
    $dateParams[] = $day;
    $dateParamTypes .= 's';
} elseif ($month !== '') {
    $dateWhereParts[] = "DATE_FORMAT($dateColumn, '%Y-%m') = ?";
    $dateParams[] = $month;
    $dateParamTypes .= 's';
} else {
    $dateWhereParts[] = "YEAR($dateColumn) = ?";
    $dateParams[] = (int) $filterValue;
    $dateParamTypes .= 'i';
}

$whereParts = $dateWhereParts;
$params = $dateParams;
$paramTypes = $dateParamTypes;

if ($selectedCenter !== '') {
    $whereParts[] = "u.negosyocenter = ?";
    $params[] = $selectedCenter;
    $paramTypes .= 's';
}

$whereSql = implode(' AND ', $whereParts);
$dateWhereSql = implode(' AND ', $dateWhereParts);

if ($day !== '') {
    $chartTitle = 'Events on ' . date('F j, Y', strtotime($day));
    $chartLabels = [date('M j, Y', strtotime($day))];
    $chartCounts = [0];
    $labelExpression = "DATE($dateColumn)";
} elseif ($month !== '') {
    $chartTitle = 'Events in ' . date('F Y', strtotime($month . '-01'));
    $chartLabels = [];
    $chartCounts = [];
    for ($d = 1; $d <= (int) date('t', strtotime($month . '-01')); $d++) {
        $chartLabels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $chartCounts[] = 0;
    }
    $labelExpression = "DAY($dateColumn)";
} else {
    $chartTitle = 'Events in ' . $filterValue;
    $chartLabels = [];
    $chartCounts = [];
    for ($m = 1; $m <= 12; $m++) {
        $chartLabels[] = date('M', mktime(0, 0, 0, $m, 1));
        $chartCounts[] = 0;
    }
    $labelExpression = "MONTH($dateColumn)";
}

$chartQuery = "
    SELECT $labelExpression AS label, COUNT(e.id) AS total
    FROM events e
    LEFT JOIN negosyo_center_users u ON u.id = e.created_by_user_id
    WHERE $whereSql
    GROUP BY $labelExpression
    ORDER BY $labelExpression ASC
";
$stmt = $conn->prepare($chartQuery);
if ($stmt) {
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $index = $day !== '' ? 0 : ((int) $row['label'] - 1);
        if (isset($chartCounts[$index])) {
            $chartCounts[$index] = (int) $row['total'];
        }
    }
    $stmt->close();
}

$centerEventRows = [];
$centerEventLabels = [];
$centerEventCounts = [];
$centerRankingQuery = "
    SELECT
        COALESCE(NULLIF(TRIM(u.negosyocenter), ''), 'Unassigned / Legacy Events') AS center_name,
        COUNT(e.id) AS total_events,
        SUM(CASE
            WHEN e.status='Implemented' OR e.remarks='Done' OR e.end_date_and_time < NOW()
            THEN 1 ELSE 0
        END) AS implemented_events,
        MAX(COALESCE(e.created_at, e.start_date_and_time)) AS latest_event_at
    FROM events e
    LEFT JOIN negosyo_center_users u ON u.id = e.created_by_user_id
    WHERE $whereSql
    GROUP BY center_name
    ORDER BY total_events DESC, center_name ASC
";
$centerRankingStmt = $conn->prepare($centerRankingQuery);
if ($centerRankingStmt) {
    if ($paramTypes !== '') {
        $centerRankingStmt->bind_param($paramTypes, ...$params);
    }
    $centerRankingStmt->execute();
    $centerRankingResult = $centerRankingStmt->get_result();
    while ($row = $centerRankingResult->fetch_assoc()) {
        $centerEventRows[] = $row;
    }
    $centerRankingStmt->close();
}

foreach (array_slice($centerEventRows, 0, 8) as $row) {
    $centerEventLabels[] = $row['center_name'];
    $centerEventCounts[] = (int) $row['total_events'];
}

$topCenterName = $centerEventRows[0]['center_name'] ?? 'No data';
$topCenterEvents = (int) ($centerEventRows[0]['total_events'] ?? 0);

$centerAccounts = [];
$centerQuery = "
    SELECT negosyocenter, fname, lname, email, designation, contact
    FROM negosyo_center_users
    ORDER BY negosyocenter ASC, lname ASC, fname ASC
    LIMIT 10
";
$centerResult = $conn->query($centerQuery);
if ($centerResult) {
    while ($row = $centerResult->fetch_assoc()) {
        $centerAccounts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DTI Admin Dashboard - NasugView</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary-color:#001a47;--gradient-end:#00308a;--secondary-color:#f8f9fa;}
body{margin:0;font-family:Poppins,sans-serif;background:linear-gradient(135deg,var(--primary-color),var(--gradient-end));min-height:100vh;}
.main-content{margin-left:250px;background:var(--secondary-color);min-height:100vh;padding:2rem;}
.content-wrapper{max-width:1400px;margin:0 auto;}
.welcome-card,.dashboard-card{background:#fff;border-radius:10px;box-shadow:0 5px 25px rgba(0,0,0,.08);}
.welcome-card{background:linear-gradient(135deg,var(--primary-color),var(--gradient-end));color:#fff;border-radius:10px;padding:2.5rem;margin-bottom:2rem;box-shadow:0 10px 30px rgba(0,26,71,.3);position:relative;overflow:hidden;}
.welcome-card::before{content:'';position:absolute;top:-50%;right:-20%;width:200px;height:200px;background:rgba(255,255,255,.1);border-radius:50%;}
.welcome-card::after{content:'';position:absolute;bottom:-30%;left:-10%;width:150px;height:150px;background:rgba(255,255,255,.05);border-radius:50%;}
.floating-shapes{position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;overflow:hidden;z-index:0;}
.shape{position:absolute;border-radius:50%;background:rgba(189,187,219,.14);animation:float 6s ease-in-out infinite;}
.shape-1{width:80px;height:80px;top:10%;right:10%;animation-delay:0s;}
.shape-2{width:60px;height:60px;bottom:5%;right:80%;animation-delay:1s;}
@keyframes float{0%,100%{transform:translateY(0) rotate(0deg);}50%{transform:translateY(-20px) rotate(180deg);}}
.dashboard-card{padding:1.5rem;margin-bottom:1.5rem;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;}
.stat-card{background:#fff;border-radius:10px;padding:1.35rem;box-shadow:0 5px 22px rgba(0,0,0,.08);border-left:5px solid var(--primary-color);}
.stat-icon{width:48px;height:48px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:rgba(0,26,71,.1);color:var(--primary-color);font-size:1.25rem;margin-bottom:.9rem;}
.stat-label{font-size:.88rem;color:#64748b;font-weight:600;margin:0;}
.stat-value{font-size:2rem;font-weight:700;color:var(--primary-color);margin:0;}
.card-title{font-weight:700;color:var(--primary-color);margin:0;}
.btn-primary-nv{background:linear-gradient(135deg,var(--primary-color),var(--gradient-end));border:0;color:#fff;border-radius:8px;font-weight:600;}
.btn-primary-nv:hover{color:#fff;filter:brightness(1.05);}
.form-control,.form-select{border-radius:8px;border:1px solid rgba(0,26,71,.2);}
.table th{background:linear-gradient(135deg,var(--primary-color),var(--gradient-end));color:#fff;border:0;}
.table td{vertical-align:middle;}
.notice{border-left:5px solid #f59e0b;background:#fff8eb;color:#633b00;border-radius:8px;padding:1rem;margin-bottom:1.5rem;}
.chart-shell{height:320px;}
@media(max-width:992px){.main-content{margin-left:0;padding:5rem 1rem 2rem;}}
@media(max-width:768px){.dashboard-card,.welcome-card{padding:1rem}.chart-shell{height:280px}.filter-row{gap:.75rem}.filter-row>*{width:100%;}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="content-wrapper">
    <div class="welcome-card">
        <div class="row align-items-center position-relative" style="z-index:1;">
            <div class="col-md-8">
                <h3 class="fw-bold mb-2">Welcome, <?php echo htmlspecialchars($fname ?: $admin_fullname); ?>!</h3>
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

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <p class="stat-label">Negosyo Centers</p>
            <p class="stat-value"><?php echo number_format($totalCenters); ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <p class="stat-label">Center Accounts</p>
            <p class="stat-value"><?php echo number_format($totalCenterUsers); ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <p class="stat-label">Total Events</p>
            <p class="stat-value"><?php echo number_format($totalEvents); ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
            <p class="stat-label">Implemented Events</p>
            <p class="stat-value"><?php echo number_format($implementedEvents); ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ranking-star"></i></div>
            <p class="stat-label">Top Event Creator</p>
            <p class="stat-value"><?php echo number_format($topCenterEvents); ?></p>
            <small class="text-muted"><?php echo htmlspecialchars($topCenterName); ?></small>
        </div>
    </div>

    <div class="dashboard-card">
        <form class="row align-items-end filter-row" method="GET">
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?php echo htmlspecialchars($year !== '' ? $year : date('Y')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Day</label>
                <input type="date" name="day" class="form-control" value="<?php echo htmlspecialchars($day); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Negosyo Center</label>
                <select name="center" class="form-select">
                    <option value="">All centers</option>
                    <?php foreach ($centerOptions as $centerName): ?>
                        <option value="<?php echo htmlspecialchars($centerName); ?>" <?php echo $selectedCenter === $centerName ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($centerName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 d-flex gap-2 mt-3">
                <button class="btn btn-primary-nv px-4" type="submit"><i class="fas fa-filter me-1"></i> Apply</button>
                <a class="btn btn-outline-secondary px-4" href="dashboard.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="card-title"><?php echo htmlspecialchars($chartTitle); ?></h6>
            <span class="text-muted small"><?php echo $selectedCenter !== '' ? htmlspecialchars($selectedCenter) : 'All centers'; ?></span>
        </div>
        <div class="chart-shell">
            <canvas id="eventsChart"></canvas>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title">Events by Negosyo Center</h6>
                    <span class="text-muted small">Highest first</span>
                </div>
                <div class="chart-shell">
                    <canvas id="centerEventsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dashboard-card">
                <h6 class="card-title mb-3">Negosyo Center Event Counts</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Negosyo Center</th>
                                <th>Total Events</th>
                                <th>Implemented</th>
                                <th>Latest Event</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$centerEventRows): ?>
                            <tr><td colspan="5" class="text-muted">No events found for this filter.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($centerEventRows as $index => $row): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                <td><strong><?php echo number_format((int) $row['total_events']); ?></strong></td>
                                <td><?php echo number_format((int) $row['implemented_events']); ?></td>
                                <td>
                                    <?php echo !empty($row['latest_event_at']) ? htmlspecialchars(date('M d, Y', strtotime($row['latest_event_at']))) : 'N/A'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="dashboard-card">
                <h6 class="card-title mb-3">Issued Negosyo Center Accounts</h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Center</th><th>Account Holder</th><th>Email</th><th>Designation</th><th>Contact</th></tr></thead>
                        <tbody>
                        <?php if (!$centerAccounts): ?>
                        <tr><td colspan="5" class="text-muted">No issued accounts yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($centerAccounts as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['negosyocenter']); ?></td>
                            <td><?php echo htmlspecialchars(trim($row['fname'] . ' ' . $row['lname'])); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['designation']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('eventsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Events',
            data: <?php echo json_encode($chartCounts); ?>,
            backgroundColor: '#001a47',
            borderRadius: 6,
            maxBarThickness: 44
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#001a47', maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0, color: '#001a47' }, grid: { color: 'rgba(0,26,71,.1)' } }
        }
    }
});

const centerCtx = document.getElementById('centerEventsChart').getContext('2d');
new Chart(centerCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($centerEventLabels); ?>,
        datasets: [{
            label: 'Events',
            data: <?php echo json_encode($centerEventCounts); ?>,
            backgroundColor: '#00308a',
            borderRadius: 6,
            maxBarThickness: 32
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0, color: '#001a47' }, grid: { color: 'rgba(0,26,71,.1)' } },
            y: { grid: { display: false }, ticks: { color: '#001a47' } }
        }
    }
});
</script>
</body>
</html>
