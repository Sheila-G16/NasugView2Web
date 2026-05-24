<?php
session_start();

require_once __DIR__ . "/db.php";

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
/* ===== ATTENDEES BY CITY DATA ===== */
$municipalityYear = isset($_GET['municipality_year']) ? trim((string) $_GET['municipality_year']) : '';
$municipalityMonth = isset($_GET['municipality_month']) ? trim((string) $_GET['municipality_month']) : '';
$municipalityDay = isset($_GET['municipality_day']) ? trim((string) $_GET['municipality_day']) : '';

$municipalityYear = preg_match('/^\d{4}$/', $municipalityYear) ? $municipalityYear : '';
$municipalityMonth = preg_match('/^\d{4}-\d{2}$/', $municipalityMonth) ? $municipalityMonth : '';
$municipalityDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', $municipalityDay) ? $municipalityDay : '';

$municipalityFilterType = 'year';
$municipalityFilterValue = $municipalityYear !== '' ? $municipalityYear : date('Y');
$municipalityLabelExpression = "DATE_FORMAT(created_at, '%Y-%m')";
$municipalityWhereClause = 'YEAR(created_at) = ?';
$municipalityParams = [$municipalityFilterValue];
$municipalityParamTypes = 'i';
$municipalityXAxisTitle = 'Month';

if ($municipalityDay !== '') {
    $municipalityFilterType = 'day';
    $municipalityFilterValue = $municipalityDay;
    $municipalityLabelExpression = 'DATE(created_at)';
    $municipalityWhereClause = 'DATE(created_at) = ?';
    $municipalityParams = [$municipalityDay];
    $municipalityParamTypes = 's';
    $municipalityXAxisTitle = 'Date';
} elseif ($municipalityMonth !== '') {
    $municipalityFilterType = 'month';
    $municipalityFilterValue = $municipalityMonth;
    $municipalityLabelExpression = 'DATE(created_at)';
    $municipalityWhereClause = "DATE_FORMAT(created_at, '%Y-%m') = ?";
    $municipalityParams = [$municipalityMonth];
    $municipalityParamTypes = 's';
    $municipalityXAxisTitle = 'Day';
}

$municipalityLabels = [];
$municipalityMonthLabels = [];
$municipalitySeries = [];
$municipalityMonthKeys = [];
$municipalityData = [];
$municipalityTotals = [];

$municipalityQuery = "
    SELECT
        city,
        {$municipalityLabelExpression} AS date_key,
        COUNT(*) AS total
    FROM event_registrations
    WHERE city IS NOT NULL
        AND TRIM(city) <> ''
        AND created_at IS NOT NULL
        AND {$municipalityWhereClause}
    GROUP BY city, {$municipalityLabelExpression}
    ORDER BY date_key ASC, city ASC
";

$municipalityStmt = $conn->prepare($municipalityQuery);

$municipalityResult = null;
if ($municipalityStmt) {
    $municipalityStmt->bind_param($municipalityParamTypes, ...$municipalityParams);
    $municipalityStmt->execute();
    $municipalityResult = $municipalityStmt->get_result();
}

if ($municipalityResult) {
    while ($row = $municipalityResult->fetch_assoc()) {
        $city = trim((string) $row['city']);
        $monthKey = (string) $row['date_key'];
        $total = (int) $row['total'];

        $municipalityMonthKeys[$monthKey] = true;
        $municipalityData[$city][$monthKey] = $total;
        $municipalityTotals[$city] = ($municipalityTotals[$city] ?? 0) + $total;
    }
}

if ($municipalityStmt) {
    $municipalityStmt->close();
}

if ($municipalityFilterType === 'year') {
    $municipalityMonthKeys = [];
    for ($m = 1; $m <= 12; $m++) {
        $municipalityMonthKeys[] = $municipalityFilterValue . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
} elseif ($municipalityFilterType === 'month') {
    $municipalityMonthKeys = [];
    $daysInMunicipalityMonth = (int) date('t', strtotime($municipalityFilterValue . '-01'));
    for ($d = 1; $d <= $daysInMunicipalityMonth; $d++) {
        $municipalityMonthKeys[] = $municipalityFilterValue . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
    }
} elseif ($municipalityFilterType === 'day') {
    $municipalityMonthKeys = [$municipalityFilterValue];
}

uksort($municipalityTotals, function ($a, $b) use ($municipalityTotals) {
    $totalCompare = $municipalityTotals[$b] <=> $municipalityTotals[$a];
    return $totalCompare !== 0 ? $totalCompare : strcasecmp($a, $b);
});

foreach ($municipalityMonthKeys as $monthKey) {
    if ($municipalityFilterType === 'year') {
        $municipalityMonthLabels[] = date('M Y', strtotime($monthKey . '-01'));
    } elseif ($municipalityFilterType === 'month') {
        $municipalityMonthLabels[] = date('M j', strtotime($monthKey));
    } else {
        $municipalityMonthLabels[] = date('M j, Y', strtotime($monthKey));
    }
}

foreach (array_keys($municipalityTotals) as $city) {
    $municipalityLabels[] = $city;
    $municipalitySeries[] = [
        'label' => $city,
        'data' => array_map(function ($monthKey) use ($municipalityData, $city) {
            return $municipalityData[$city][$monthKey] ?? 0;
        }, $municipalityMonthKeys)
    ];
}
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

$csfOverview = [
    'office' => 'Bureau/Office',
    'process' => 'Business Advisory and Client Support',
    'period' => 'For the period [Month, DD, YYYY]',
    'total_responses' => 31,
    'total_clients' => 35,
    'retrieval_rate' => '88.57%',
    'overall_satisfaction' => '91.90%',
    'adjectival_rating' => 'Very Satisfactory'
];

$tabulationRows = [
    [1, 'Client 1', '20-34', 'M', 'Citizen', 1, 1, 1, 5, 5, 5, 5, 4, '-', 4, 5, 4],
    [2, 'Client 2', '19 or lower', 'F', 'Business', 1, 1, 1, 5, 5, 5, 5, 4, '-', 4, 5, 4],
    [3, 'Client 3', '50-64', 'M', 'Citizen', 1, 1, 1, 5, 5, 5, 5, 4, '-', 4, 5, 4],
    [4, 'Client 4', '20-34', 'M', 'Business', 1, 1, 1, 5, 5, 5, 5, 4, '-', 4, 5, 4],
    [5, 'Client 5', '20-34', 'M', 'Citizen', 1, 1, 1, 5, 5, 5, 5, 5, '-', 4, 5, 5],
    [6, 'Client 6', '50-64', 'M', 'Business', 1, 1, 1, 5, 5, 5, 5, 5, '-', 4, 5, 5],
    [7, 'Client 7', '50-64', 'F', 'Business', 1, 3, 1, 5, 5, 5, 5, 5, '-', 4, 5, 5],
    [8, 'Client 8', '35-49', 'F', 'Business', 1, 1, 1, 5, 5, 5, 5, 5, '-', 4, 5, 5]
];

$speakerRows = [
    [1, 'Client 1', '20-34', 'M', 'Citizen', 4, 5, 5, 5, 5, 4, 4, 4],
    [2, 'Client 2', '19 or lower', 'F', 'Business', 4, 5, 5, 5, 5, 4, 4, 4],
    [3, 'Client 3', '50-64', 'M', 'Citizen', 4, 5, 5, 5, 5, 4, 4, 4],
    [4, 'Client 4', '20-34', 'M', 'Business', 4, 5, 5, 5, 5, 4, 4, 4],
    [5, 'Client 5', '20-34', 'M', 'Citizen', 5, 5, 5, 5, 5, 5, 5, 5],
    [6, 'Client 6', '50-64', 'M', 'Business', 4, 5, 5, 5, 5, 5, 5, 5]
];

$csfTables = [
    [
        'title' => "CC1 - Awareness of the Citizen's Charter",
        'theme' => 'green',
        'rows' => [
            ['(1) I know what a CC is and I saw this office’s CC.', 30, '96.77%'],
            ['(2) I know what a CC is but I did not see this office’s CC.', 0, '0.00%'],
            ['(3) I learned of the CC only when I saw this office’s CC.', 0, '0.00%'],
            ['(4) I do not know what a CC is and I did not see one.', 0, '0.00%'],
            ['(-) Not Applicable', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ],
    [
        'title' => 'Sex Disaggregation',
        'theme' => 'blue',
        'rows' => [
            ['Male', 17, '54.84%'],
            ['Female', 13, '41.94%'],
            ['Did not specify', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ],
    [
        'title' => 'Level of Satisfaction',
        'theme' => 'blue',
        'rows' => [
            ['Strongly Agree', '', '55.24%'],
            ['Agree', '', '36.67%'],
            ['Neither Agree nor Disagree', '', '7.62%'],
            ['Disagree', '', '0.48%'],
            ['Strongly Disagree', '', '0.00%']
        ],
        'summary' => [
            ['Overall Satisfaction', '91.90%'],
            ['Adjectival Rating', 'Very Satisfactory']
        ]
    ],
    [
        'title' => "CC2 - Visibility of the Citizen's Charter",
        'theme' => 'green',
        'rows' => [
            ['(1) Easy to see', 29, '93.55%'],
            ['(2) Somewhat easy to see', 0, '0.00%'],
            ['(3) Difficult to see', 1, '3.23%'],
            ['(4) Not visible', 0, '0.00%'],
            ['(5) N/A', 0, '0.00%'],
            ['(-) Not Applicable', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ],
    [
        'title' => 'Age',
        'theme' => 'blue',
        'rows' => [
            ['19 or lower', 2, '6.45%'],
            ['20-34', 13, '41.94%'],
            ['35-49', 9, '29.03%'],
            ['50-64', 6, '19.35%'],
            ['65 or higher', 0, '0.00%'],
            ['Did not specify', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ],
    [
        'title' => "CC3 - Usefulness of the Citizen's Charter",
        'theme' => 'green',
        'rows' => [
            ['(1) Helped very much', 30, '96.77%'],
            ['(2) Somewhat helped', 0, '0.00%'],
            ['(3) Did not help', 0, '0.00%'],
            ['(-) Not Applicable', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ],
    [
        'title' => 'Client Type',
        'theme' => 'blue',
        'rows' => [
            ['Citizen', 6, '19.35%'],
            ['Business', 18, '58.06%'],
            ['Government', 6, '19.35%'],
            ['Did not specify', 1, '3.23%']
        ],
        'total' => [31, '100.00%']
    ]
];

$dimensionSummaries = [
    ['Responsiveness', '93.55%', 'Very Satisfactory'],
    ['Reliability', '67.74%', 'Fair'],
    ['Access & Facilities', '96.77%', 'Outstanding'],
    ['Communication', '93.55%', 'Very Satisfactory'],
    ['Costs', '0.00%', 'N/A'],
    ['Integrity', '77.41%', 'Fair'],
    ['Assurance', '96.77%', 'Outstanding'],
    ['Outcome', '96.77%', 'Outstanding']
];
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
.welcome-card { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); color:white; border-radius:10px; padding:2.5rem; margin-bottom:2rem; box-shadow:0 10px 30px rgba(0,26,71,0.3); position:relative; overflow:hidden; }
.welcome-card::before { content:''; position:absolute; top:-50%; right:-20%; width:200px; height:200px; background:rgba(255,255,255,0.1); border-radius:50%; }
.welcome-card::after { content:''; position:absolute; bottom:-30%; left:-10%; width:150px; height:150px; background:rgba(255,255,255,0.05); border-radius:50%; }
.dashboard-card { background:white; border-radius:10px; padding:2rem; margin-bottom:1.5rem; box-shadow:0 5px 25px rgba(0,0,0,0.08); border:none; transition:all 0.3s ease; position:relative; overflow:hidden; }
.dashboard-card:hover { transform:translateY(-8px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
.card-icon { width:70px; height:70px; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:1.5rem; font-size:1.8rem; background:rgba(0,26,71,0.1); color:#001a47; }
.card-value { font-size:2.2rem; font-weight:700; margin:0.5rem 0; background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.card-title { font-weight:600; color:var(--primary-color); margin-bottom:1rem; font-size:1.2rem; }
.quick-action-btn { background:linear-gradient(135deg,var(--primary-color),var(--gradient-end)); border:none; border-radius:8px; padding:1rem 1.5rem; font-weight:600; color:white; transition:all 0.3s ease; width:100%; margin-bottom:0.5rem; font-size:0.95rem; position:relative; overflow:hidden; }
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
.filter-toggle { width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:8px; }
.filter-menu { min-width:220px; padding:1rem; border:none; border-radius:10px; box-shadow:0 12px 32px rgba(0,0,0,0.12); background:#fff; }
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
    gap:1rem;
    margin-bottom:1.5rem;
}
.stats-grid .dashboard-card {
    border-radius:10px;
    padding:1rem;
    margin-bottom:0;
}
.stats-grid .dashboard-card:hover {
    transform:translateY(-4px);
}
.stats-grid .card-icon {
    width:44px;
    height:44px;
    border-radius:8px;
    margin-bottom:.75rem;
    font-size:1.15rem;
}
.stats-grid .card-title {
    margin-bottom:.35rem;
    font-size:.88rem;
    line-height:1.2;
}
.stats-grid .card-value {
    font-size:1.55rem;
    margin:.2rem 0 .75rem;
    line-height:1;
}
.stats-grid .quick-action-btn {
    border-radius:8px;
    padding:.55rem .7rem;
    margin-bottom:0;
    font-size:.78rem;
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
    border-radius:10px;
}

.municipality-card{
    overflow:hidden;
    padding:14px 16px 12px;
}

.municipality-chart-scroll{
    width:100%;
    overflow:hidden;
    padding-bottom:2px;
}

#municipalityChart{
    min-height:180px;
    width:100% !important;
    display:block;
    background:#fff;
}

.blue-card{
    background: linear-gradient(135deg,#0d2f6b,#001a47);
    color:white;
    border-radius:10px;
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
    grid-template-columns:1.7fr 1fr;
    align-items:start;
    gap:1.5rem;
    margin-bottom:2rem;
}

#municipalityChart{
    height:180px !important;
}

.white-card{
    background:white;
    border-radius:10px;
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
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, #001a47, #00308a);
    color:#fff;
    box-shadow:0 10px 18px rgba(0, 26, 71, 0.18);
}

.leading-filter{
    width:110px;
    border-radius:8px;
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
    border-radius:10px;
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

.section-block{
    margin-top:2rem;
}

.section-heading{
    margin-bottom:1rem;
}

.section-heading h4{
    margin:0;
    color:var(--primary-color);
    font-weight:700;
    font-size:1.25rem;
}

.section-heading p{
    margin:.25rem 0 0;
    color:#6b7280;
    font-size:.92rem;
}

.sheet-meta{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:1rem;
    margin-bottom:1rem;
}

.meta-strip,
.summary-strip,
.report-matrix,
.mini-report,
.narrative-card,
.action-table-wrap{
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    overflow:hidden;
    background:#fff;
}

.meta-strip table,
.summary-strip table,
.report-matrix table,
.mini-report table,
.sheet-table table,
.action-table{
    width:100%;
    border-collapse:collapse;
}

.meta-strip td,
.summary-strip td,
.report-matrix td,
.report-matrix th,
.mini-report td,
.mini-report th,
.sheet-table td,
.sheet-table th,
.action-table td,
.action-table th{
    border:1px solid rgba(15,23,42,.08);
    padding:.55rem .7rem;
    font-size:.84rem;
    vertical-align:top;
}

.meta-strip td:first-child,
.summary-strip td:first-child{
    font-weight:600;
    color:#334155;
    width:34%;
}

.summary-strip td:first-child{
    background:#123c73;
    color:#fff;
}

.sheet-table{
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    overflow:auto;
    background:#fff;
}

.sheet-table table{
    min-width:1320px;
}

.sheet-table thead th,
.report-matrix thead th,
.mini-report thead th,
.action-table thead th{
    background:linear-gradient(135deg,#123c73,#235f48);
    color:#fff;
    font-weight:600;
    white-space:nowrap;
}

.sheet-table tbody tr:nth-child(even),
.report-matrix tbody tr:nth-child(even),
.mini-report tbody tr:nth-child(even),
.action-table tbody tr:nth-child(even){
    background:#f8fafc;
}

.sheet-table td{
    white-space:nowrap;
}

.report-layout{
    display:grid;
    grid-template-columns:1fr;
    align-items:start;
    gap:1.5rem;
    margin-top:1.5rem;
}

.report-matrix{
    overflow:auto;
}

.report-matrix table{
    min-width:1100px;
}

.report-matrix td,
.report-matrix th{
    font-size:.88rem;
    padding:.6rem .75rem;
    white-space:nowrap;
}

.report-matrix .total-row{
    background:#e7f6eb;
    font-weight:700;
}

.report-matrix .rating-row{
    background:#f0faf2;
}

.mini-report-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:1rem;
}

.mini-report table{
    table-layout:fixed;
}

.mini-report th,
.mini-report td{
    font-size:.88rem;
    padding:.62rem .75rem;
}

.mini-report thead th{
    white-space:normal;
    line-height:1.25;
}

.mini-report th:first-child,
.mini-report td:first-child{
    width:58%;
    white-space:normal;
    word-break:break-word;
    line-height:1.45;
}

.mini-report th:nth-child(2),
.mini-report td:nth-child(2){
    width:18%;
    text-align:center;
}

.mini-report th:nth-child(3),
.mini-report td:nth-child(3){
    width:24%;
    text-align:right;
}

.mini-report.green thead th{
    background:linear-gradient(135deg,#20584a,#2d7c66);
}

.mini-report.blue thead th{
    background:linear-gradient(135deg,#123c73,#1d5ea8);
}

.mini-report .total-cell{
    font-weight:700;
    background:#f8fafc;
}

.summary-panels{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:1rem;
    margin-bottom:1rem;
}

.summary-kpi{
    background:linear-gradient(180deg,#ffffff 0%, #f8fbff 100%);
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    padding:1rem;
}

.summary-kpi h5{
    margin:0 0 .25rem;
    font-size:.82rem;
    color:#6b7280;
    font-weight:600;
}

.summary-kpi strong{
    font-size:1.4rem;
    color:var(--primary-color);
}

.graphs-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:1rem;
}

.dimension-grid{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:1rem;
}

.graph-card{
    background:#fff;
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    padding:1rem;
    min-height:300px;
}

.graph-card h6{
    margin:0 0 .75rem;
    color:var(--primary-color);
    font-weight:700;
    font-size:.95rem;
}

.graph-canvas{
    position:relative;
    height:220px;
}

.narrative-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:1rem;
    margin-top:1.5rem;
}

.narrative-card{
    padding:1rem;
}

.narrative-card h6{
    margin:0 0 .75rem;
    color:var(--primary-color);
    font-weight:700;
}

.narrative-card p{
    margin:0;
    color:#334155;
    line-height:1.7;
    font-size:.92rem;
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

    .dimension-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
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

    .sheet-meta,
    .report-layout,
    .narrative-grid,
    .graphs-grid{
        grid-template-columns:1fr;
    }

    .summary-panels,
    .mini-report-grid,
    .dimension-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .welcome-card,
    .white-card{
        border-radius:10px;
        padding:1.25rem;
    }

    .dashboard-card{
        border-radius:10px;
        padding:1.25rem;
    }

    .stats-grid .dashboard-card{
        border-radius:9px;
        padding:.95rem;
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

    .summary-panels,
    .mini-report-grid,
    .dimension-grid{
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

    .stats-grid .card-icon{
        width:42px;
        height:42px;
        margin-bottom:.65rem;
        font-size:1.05rem;
    }

    .card-value{
        font-size:1.85rem;
    }

    .stats-grid .card-value{
        font-size:1.45rem;
    }

    .quick-action-btn{
        padding:.85rem 1rem;
    }

    .stats-grid .quick-action-btn{
        padding:.5rem .65rem;
        font-size:.76rem;
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
            <div class="dropdown filter-wrap">
                <button class="btn btn-sm filter-btn filter-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filter municipality chart">
                    <i class="fas fa-calendar-alt"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end filter-menu">
                    <form id="municipalityFilterForm" class="filter-form">
                        <div class="filter-options">
                            <button type="button" class="filter-option municipality-filter-option <?php echo $municipalityFilterType === 'year' ? 'active' : ''; ?>" data-type="year">Year</button>
                            <button type="button" class="filter-option municipality-filter-option <?php echo $municipalityFilterType === 'month' ? 'active' : ''; ?>" data-type="month">Month</button>
                            <button type="button" class="filter-option municipality-filter-option <?php echo $municipalityFilterType === 'day' ? 'active' : ''; ?>" data-type="day">Day</button>
                        </div>
                        <input
                            id="municipalityFilterValue"
                            class="form-control form-control-sm filter-input filter-value"
                            value="<?php echo htmlspecialchars($municipalityFilterValue); ?>"
                            data-year="<?php echo htmlspecialchars($municipalityYear !== '' ? $municipalityYear : date('Y')); ?>"
                            data-month="<?php echo htmlspecialchars($municipalityMonth); ?>"
                            data-day="<?php echo htmlspecialchars($municipalityDay); ?>"
                        >
                        <input type="hidden" id="municipalityFilterType" value="<?php echo htmlspecialchars($municipalityFilterType); ?>">
                        <?php if ($year !== ''): ?>
                            <input type="hidden" data-preserve-param="year" value="<?php echo htmlspecialchars($year); ?>">
                        <?php endif; ?>
                        <?php if ($month !== ''): ?>
                            <input type="hidden" data-preserve-param="month" value="<?php echo htmlspecialchars($month); ?>">
                        <?php endif; ?>
                        <?php if ($day !== ''): ?>
                            <input type="hidden" data-preserve-param="day" value="<?php echo htmlspecialchars($day); ?>">
                        <?php endif; ?>
                        <?php if ($selectedLeadingYear > 0): ?>
                            <input type="hidden" data-preserve-param="leading_year" value="<?php echo (int) $selectedLeadingYear; ?>">
                        <?php endif; ?>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-sm filter-btn">Apply</button>
                            <a href="<?php echo htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')); ?>" class="btn btn-sm filter-reset">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
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

        <div class="section-block">
            <div class="section-heading">
                <h4>Customer Satisfaction Dashboard</h4>
                <p>Static dashboard preview for the customer satisfaction tabulation, report, and graph views.</p>
            </div>

            <div class="summary-panels">
                <div class="summary-kpi">
                    <h5>Total Responses</h5>
                    <strong><?php echo $csfOverview['total_responses']; ?></strong>
                </div>
                <div class="summary-kpi">
                    <h5>Total Clients</h5>
                    <strong><?php echo $csfOverview['total_clients']; ?></strong>
                </div>
                <div class="summary-kpi">
                    <h5>Retrieval Rate</h5>
                    <strong><?php echo $csfOverview['retrieval_rate']; ?></strong>
                </div>
                <div class="summary-kpi">
                    <h5>Overall Satisfaction</h5>
                    <strong><?php echo $csfOverview['overall_satisfaction']; ?></strong>
                </div>
            </div>

            <div class="sheet-meta">
                <div class="meta-strip">
                    <table>
                        <tbody>
                            <tr><td>Office</td><td><?php echo htmlspecialchars($csfOverview['office']); ?></td></tr>
                            <tr><td>Process</td><td><?php echo htmlspecialchars($csfOverview['process']); ?></td></tr>
                            <tr><td>Period</td><td><?php echo htmlspecialchars($csfOverview['period']); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="summary-strip">
                    <table>
                        <tbody>
                            <tr><td>Adjectival Rating</td><td><?php echo htmlspecialchars($csfOverview['adjectival_rating']); ?></td></tr>
                            <tr><td>Responses Collected</td><td><?php echo $csfOverview['total_responses']; ?></td></tr>
                            <tr><td>Target Clients</td><td><?php echo $csfOverview['total_clients']; ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="white-card mb-4">
                <h6 class="card-title mb-3">Customer Satisfaction Feedback - Tabulation Sheet</h6>
                <div class="sheet-table">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name/Codename</th>
                                <th>Age Group</th>
                                <th>Sex</th>
                                <th>Client Type</th>
                                <th>CC1</th>
                                <th>CC2</th>
                                <th>CC3</th>
                                <th>Overall Rating/SQD 0</th>
                                <th>Responsiveness</th>
                                <th>Reliability</th>
                                <th>Access and Facilities</th>
                                <th>Communication</th>
                                <th>Costs</th>
                                <th>Integrity</th>
                                <th>Assurance</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabulationRows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars((string) $cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="white-card mb-4">
                <h6 class="card-title mb-3">Speaker Assessment Snapshot</h6>
                <div class="sheet-table">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name/Codename</th>
                                <th>Age Group</th>
                                <th>Sex</th>
                                <th>Client Type</th>
                                <th>Speaker 1</th>
                                <th>Speaker 2</th>
                                <th>Speaker 3</th>
                                <th>Speaker 4</th>
                                <th>Speaker 5</th>
                                <th>Speaker 6</th>
                                <th>Speaker 7</th>
                                <th>Speaker 8</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($speakerRows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars((string) $cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-layout">
                <div class="report-matrix">
                    <table>
                        <thead>
                            <tr>
                                <th>Level of Satisfaction</th>
                                <th>Overall Scoring</th>
                                <?php foreach ($dimensionSummaries as $dimension): ?>
                                    <th><?php echo htmlspecialchars($dimension[0]); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Strongly Agree</td>
                                <td>244</td>
                                <td>10</td>
                                <td>16</td>
                                <td>30</td>
                                <td>11</td>
                                <td>0</td>
                                <td>0</td>
                                <td>30</td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>Agree</td>
                                <td>77</td>
                                <td>0</td>
                                <td>5</td>
                                <td>0</td>
                                <td>18</td>
                                <td>0</td>
                                <td>24</td>
                                <td>0</td>
                                <td>30</td>
                            </tr>
                            <tr>
                                <td>Neither Agree nor Disagree</td>
                                <td>16</td>
                                <td>0</td>
                                <td>9</td>
                                <td>1</td>
                                <td>1</td>
                                <td>0</td>
                                <td>6</td>
                                <td>0</td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>Disagree</td>
                                <td>1</td>
                                <td>1</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                                <td>0</td>
                            </tr>
                            <tr class="total-row">
                                <td>Total</td>
                                <td>338</td>
                                <td>11</td>
                                <td>30</td>
                                <td>31</td>
                                <td>30</td>
                                <td>31</td>
                                <td>30</td>
                                <td>30</td>
                                <td>30</td>
                            </tr>
                            <tr class="rating-row">
                                <td>CSF Rating</td>
                                <td><?php echo htmlspecialchars($csfOverview['overall_satisfaction']); ?></td>
                                <?php foreach ($dimensionSummaries as $dimension): ?>
                                    <td><?php echo htmlspecialchars($dimension[1]); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="rating-row">
                                <td>Adjectival Rating</td>
                                <td><?php echo htmlspecialchars($csfOverview['adjectival_rating']); ?></td>
                                <?php foreach ($dimensionSummaries as $dimension): ?>
                                    <td><?php echo htmlspecialchars($dimension[2]); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mini-report-grid">
                    <?php foreach ($csfTables as $table): ?>
                        <div class="mini-report <?php echo htmlspecialchars($table['theme']); ?>">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars($table['title']); ?></th>
                                        <th># of Responses</th>
                                        <th>% Distribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table['rows'] as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row[0]); ?></td>
                                            <td><?php echo htmlspecialchars((string) $row[1]); ?></td>
                                            <td><?php echo htmlspecialchars((string) $row[2]); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (isset($table['total'])): ?>
                                        <tr class="total-cell">
                                            <td>Total Responses</td>
                                            <td><?php echo htmlspecialchars((string) $table['total'][0]); ?></td>
                                            <td><?php echo htmlspecialchars((string) $table['total'][1]); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if (isset($table['summary'])): ?>
                                        <?php foreach ($table['summary'] as $summaryRow): ?>
                                            <tr class="total-cell">
                                                <td><?php echo htmlspecialchars($summaryRow[0]); ?></td>
                                                <td colspan="2"><?php echo htmlspecialchars($summaryRow[1]); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (($table['title'] ?? '') === 'Client Type'): ?>
                            <div class="narrative-card">
                                <h6>Descriptive Analysis</h6>
                                <p>During the period of <?php echo htmlspecialchars($csfOverview['period']); ?>, a total of <?php echo $csfOverview['total_clients']; ?> clients received a Client Satisfaction Feedback form related to the <?php echo htmlspecialchars($csfOverview['process']); ?> process. The office successfully collected <?php echo $csfOverview['total_responses']; ?> forms, resulting in a retrieval efficiency of <?php echo htmlspecialchars($csfOverview['retrieval_rate']); ?>. The computed CSF rating is <?php echo htmlspecialchars($csfOverview['overall_satisfaction']); ?>, which corresponds to a <?php echo htmlspecialchars($csfOverview['adjectival_rating']); ?> level of client satisfaction. Based on the static profile shown below, business clients make up the largest segment, while the 20-34 age group accounts for the highest share of respondents.</p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="narrative-grid">
                <div class="action-table-wrap">
                    <table class="action-table">
                        <thead>
                            <tr>
                                <th>Source of Improvement</th>
                                <th>Improvement Action</th>
                                <th>Responsibility</th>
                                <th>Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Reliability</td><td>Review service turnaround checkpoints</td><td>Process Owner</td><td>Q2</td></tr>
                            <tr><td>Integrity</td><td>Refresh transparency reminders for frontline staff</td><td>Admin Office</td><td>Q2</td></tr>
                            <tr><td>Communication</td><td>Update public advisories and response scripts</td><td>Support Team</td><td>Q3</td></tr>
                            <tr><td>Clients Comments</td><td>Create monthly improvement review meeting</td><td>Management</td><td>Monthly</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-block">
                <div class="section-heading">
                    <h4>Demographic Profile</h4>
                    <p>Static preview of the demographic charts from the spreadsheet graphs sheet.</p>
                </div>
                <div class="graphs-grid">
                    <div class="graph-card">
                        <h6>Sex Disaggregation</h6>
                        <div class="graph-canvas"><canvas id="sexChart"></canvas></div>
                    </div>
                    <div class="graph-card">
                        <h6>Age</h6>
                        <div class="graph-canvas"><canvas id="ageChart"></canvas></div>
                    </div>
                    <div class="graph-card">
                        <h6>Client Type</h6>
                        <div class="graph-canvas"><canvas id="clientTypeChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="section-block">
                <div class="section-heading">
                    <h4>Service Quality Dimensions</h4>
                    <p>Static donut charts for the service quality dimensions shown in the sample workbook.</p>
                </div>
                <div class="dimension-grid">
                    <div class="graph-card"><h6>Responsiveness</h6><div class="graph-canvas"><canvas id="dimensionResponsiveness"></canvas></div></div>
                    <div class="graph-card"><h6>Reliability</h6><div class="graph-canvas"><canvas id="dimensionReliability"></canvas></div></div>
                    <div class="graph-card"><h6>Access & Facilities</h6><div class="graph-canvas"><canvas id="dimensionAccess"></canvas></div></div>
                    <div class="graph-card"><h6>Communication</h6><div class="graph-canvas"><canvas id="dimensionCommunication"></canvas></div></div>
                    <div class="graph-card"><h6>Costs</h6><div class="graph-canvas"><canvas id="dimensionCosts"></canvas></div></div>
                    <div class="graph-card"><h6>Integrity</h6><div class="graph-canvas"><canvas id="dimensionIntegrity"></canvas></div></div>
                    <div class="graph-card"><h6>Assurance</h6><div class="graph-canvas"><canvas id="dimensionAssurance"></canvas></div></div>
                    <div class="graph-card"><h6>Outcome</h6><div class="graph-canvas"><canvas id="dimensionOutcome"></canvas></div></div>
                </div>
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
const filterOptions = document.querySelectorAll('#filterForm .filter-option');
const leadingYearValue = document.getElementById('leadingYearValue');
const municipalityFilterTypeInput = document.getElementById('municipalityFilterType');
const municipalityFilterValueInput = document.getElementById('municipalityFilterValue');
const municipalityFilterOptions = document.querySelectorAll('#municipalityFilterForm .municipality-filter-option');

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
    if (municipalityFilterTypeInput && municipalityFilterValueInput && municipalityFilterValueInput.value) {
        params.set('municipality_' + municipalityFilterTypeInput.value, municipalityFilterValueInput.value);
    }

    window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
});

function syncMunicipalityFilterInput() {
    const selectedType = municipalityFilterTypeInput.value;
    municipalityFilterValueInput.name = 'municipality_' + selectedType;

    municipalityFilterOptions.forEach(option => {
        option.classList.toggle('active', option.dataset.type === selectedType);
    });

    if (selectedType === 'year') {
        municipalityFilterValueInput.type = 'number';
        municipalityFilterValueInput.min = '2000';
        municipalityFilterValueInput.max = '2100';
        municipalityFilterValueInput.placeholder = 'Year';
        municipalityFilterValueInput.value = municipalityFilterValueInput.dataset.year || new Date().getFullYear();
    } else if (selectedType === 'month') {
        municipalityFilterValueInput.type = 'month';
        municipalityFilterValueInput.removeAttribute('min');
        municipalityFilterValueInput.removeAttribute('max');
        municipalityFilterValueInput.placeholder = '';
        municipalityFilterValueInput.value = municipalityFilterValueInput.dataset.month || '';
    } else {
        municipalityFilterValueInput.type = 'date';
        municipalityFilterValueInput.removeAttribute('min');
        municipalityFilterValueInput.removeAttribute('max');
        municipalityFilterValueInput.placeholder = '';
        municipalityFilterValueInput.value = municipalityFilterValueInput.dataset.day || '';
    }
}

if (municipalityFilterTypeInput && municipalityFilterValueInput) {
    municipalityFilterOptions.forEach(option => {
        option.addEventListener('click', function () {
            municipalityFilterTypeInput.value = this.dataset.type;
            syncMunicipalityFilterInput();
        });
    });

    syncMunicipalityFilterInput();

    document.getElementById('municipalityFilterForm').addEventListener('submit', function(e){
        e.preventDefault();

        municipalityFilterValueInput.dataset.year = municipalityFilterTypeInput.value === 'year' ? municipalityFilterValueInput.value : municipalityFilterValueInput.dataset.year;
        municipalityFilterValueInput.dataset.month = municipalityFilterTypeInput.value === 'month' ? municipalityFilterValueInput.value : municipalityFilterValueInput.dataset.month;
        municipalityFilterValueInput.dataset.day = municipalityFilterTypeInput.value === 'day' ? municipalityFilterValueInput.value : municipalityFilterValueInput.dataset.day;

        const params = new URLSearchParams();
        this.querySelectorAll('[data-preserve-param]').forEach(input => {
            if (input.value) {
                params.set(input.dataset.preserveParam, input.value);
            }
        });

        if (municipalityFilterValueInput.value) {
            params.set('municipality_' + municipalityFilterTypeInput.value, municipalityFilterValueInput.value);
        }

        window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    });
}

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
const municipalityMonthLabels = <?php echo json_encode($municipalityMonthLabels); ?>;
const municipalitySeries = <?php echo json_encode($municipalitySeries); ?>;
const municipalityColors = [
    '#0d47a1',
    '#d97706',
    '#15803d',
    '#be123c',
    '#7c3aed',
    '#0891b2',
    '#b45309',
    '#4338ca',
    '#c026d3',
    '#047857',
    '#dc2626',
    '#2563eb'
];
function getMunicipalityColor(index) {
    return municipalityColors[index % municipalityColors.length];
}

const municipalityChart = new Chart(document.getElementById('municipalityChart'), {
    type: 'line',
    data: {
        labels: municipalityMonthLabels,
        datasets: municipalitySeries.map((series, index) => ({
            label: series.label,
            data: series.data,
            borderColor: getMunicipalityColor(index),
            backgroundColor: 'rgba(255,255,255,0)',
            pointBackgroundColor: '#ffffff',
            pointBorderColor: getMunicipalityColor(index),
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 2,
            fill: false,
            tension: .35
        }))
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
                        return chart.data.datasets.map((dataset, index) => ({
                            text: dataset.label,
                            fillStyle: 'rgba(255,255,255,0)',
                            strokeStyle: dataset.borderColor,
                            lineWidth: 2,
                            hidden: !chart.isDatasetVisible(index),
                            datasetIndex: index
                        }));
                    }
                },
                onClick(e, legendItem, legend) {
                    const datasetIndex = legendItem.datasetIndex;
                    legend.chart.setDatasetVisibility(datasetIndex, !legend.chart.isDatasetVisible(datasetIndex));
                    legend.chart.update();
                }
            },
            tooltip:{
                backgroundColor:'#001a47',
                titleColor:'#fff',
                bodyColor:'#fff',
                callbacks:{
                    label:function(context){
                        return context.dataset.label + ': ' + context.parsed.y;
                    }
                }
            }
        },
        scales:{
            x:{
                title:{display:true, text:<?php echo json_encode($municipalityXAxisTitle); ?>, color:'#001a47', font:{weight:600}},
                grid:{display:false},
                ticks:{color:'#001a47', autoSkip:false, maxRotation:0, minRotation:0}
            },
            y:{
                beginAtZero:true,
                title:{display:true, text:'Invited Attendees Count', color:'#001a47', font:{weight:600}},
                ticks:{stepSize:1, color:'#001a47'},
                grid:{color:'rgba(0,26,71,0.1)'}
            }
        }
    }
});

const csfPalette = ['#001a47', '#00308a', '#1d5ea8', '#20584a', '#2d7c66', '#64748b'];
const chartTextColor = '#001a47';
const chartBorderColor = '#ffffff';

function getThemePalette(count) {
    return Array.from({ length: count }, (_, index) => csfPalette[index % csfPalette.length]);
}

function percentLabel(context) {
    const values = context.dataset.data || [];
    const total = values.reduce((sum, value) => sum + Number(value || 0), 0);
    const value = Number(context.parsed || 0);
    const percent = total ? ((value / total) * 100).toFixed(1) : '0.0';
    return `${context.label}: ${value} (${percent}%)`;
}

function createStaticDoughnutChart(id, labels, data) {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: getThemePalette(data.length),
                borderColor: chartBorderColor,
                borderWidth: 3,
                hoverBorderColor: chartBorderColor,
                hoverBorderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            layout: {
                padding: 8
            },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        color: chartTextColor,
                        font: { size: 11, weight: 600 }
                    }
                },
                tooltip: {
                    backgroundColor: chartTextColor,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 10,
                    displayColors: true,
                    callbacks: {
                        label: percentLabel
                    }
                }
            }
        }
    });
}

function createStaticPieChart(id, labels, data) {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    new Chart(canvas, {
        type: 'pie',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: getThemePalette(data.length),
                borderColor: chartBorderColor,
                borderWidth: 3,
                hoverBorderColor: chartBorderColor,
                hoverBorderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: 8
            },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        color: chartTextColor,
                        font: { size: 11, weight: 600 }
                    }
                },
                tooltip: {
                    backgroundColor: chartTextColor,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 10,
                    displayColors: true,
                    callbacks: {
                        label: percentLabel
                    }
                }
            }
        }
    });
}

createStaticPieChart('sexChart', ['Male', 'Female', 'Did not specify'], [17, 13, 1]);
createStaticPieChart('ageChart', ['19 or lower', '20-34', '35-49', '50-64', '65 or higher'], [2, 13, 9, 6, 0]);
createStaticPieChart('clientTypeChart', ['Citizen', 'Business', 'Government', 'Did not specify'], [6, 18, 6, 1]);

const satisfactionLabels = ['Strongly Agree', 'Agree', 'Neither Agree nor Disagree', 'Disagree', 'Strongly Disagree'];
createStaticDoughnutChart('dimensionResponsiveness', satisfactionLabels, [29, 0, 1, 1, 0]);
createStaticDoughnutChart('dimensionReliability', satisfactionLabels, [16, 5, 9, 0, 0]);
createStaticDoughnutChart('dimensionAccess', satisfactionLabels, [30, 0, 1, 0, 0]);
createStaticDoughnutChart('dimensionCommunication', satisfactionLabels, [11, 18, 1, 0, 0]);
createStaticDoughnutChart('dimensionCosts', satisfactionLabels, [0, 0, 0, 0, 31]);
createStaticDoughnutChart('dimensionIntegrity', satisfactionLabels, [0, 24, 6, 0, 0]);
createStaticDoughnutChart('dimensionAssurance', satisfactionLabels, [30, 0, 1, 0, 0]);
createStaticDoughnutChart('dimensionOutcome', satisfactionLabels, [0, 30, 0, 0, 0]);
</script>

</body>
</html>
