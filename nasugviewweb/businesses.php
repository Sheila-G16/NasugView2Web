<?php
session_start();

require_once __DIR__ . "/db.php";

/* =============================
   CHECK LOGIN
============================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* =============================
   FETCH ADMIN NAME
============================= */
$id = $_SESSION['user_id'];
$admin_fullname = "Admin";

$stmt = $conn->prepare("SELECT username, fname, lname FROM negosyo_center_users WHERE id = ?");
if (!$stmt) {
    die("Admin Query Prepare Error: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result_admin = $stmt->get_result();

if ($row_admin = $result_admin->fetch_assoc()) {
    $admin_fullname = trim($row_admin['fname'] . ' ' . $row_admin['lname']);
}

$stmt->close();

/* =============================
   DEBUG DATABASE CONNECTION
============================= */
$db_name = "Unknown";
$db_result = $conn->query("SELECT DATABASE() AS db_name");
if ($db_result && $db_row = $db_result->fetch_assoc()) {
    $db_name = $db_row['db_name'];
}

$table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'business_owner'");
if ($table_check && $table_check->num_rows > 0) {
    $table_exists = true;
}

$total_businesses = 0;
if ($table_exists) {
    $count_result = $conn->query("SELECT COUNT(*) AS total FROM business_owner");
    if ($count_result && $count_row = $count_result->fetch_assoc()) {
        $total_businesses = (int)$count_row['total'];
    }
}

/* =============================
   FETCH BUSINESSES
============================= */
$result = false;

if ($table_exists) {
    $sql = "
        SELECT 
            b_id,
            business_name,
            fname,
            lname,
            address,
            gender,
            description,
            phone
        FROM business_owner
        ORDER BY business_name ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        die("Business Query Error: " . $conn->error);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Businesses - NasugView</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary-color:#001a47;
    --secondary-color:#f8f9fa;
    --sidebar-width:250px;
}

body {
    font-family:'Poppins',sans-serif;
    background:var(--secondary-color);
    margin:0;
}

.sidebar {
    position:fixed;
    top:0;
    left:0;
    width:var(--sidebar-width);
    height:100%;
    z-index:1000;
}

.main-content {
    margin-left:var(--sidebar-width);
    padding:2rem;
    min-height:100vh;
    background:var(--secondary-color);
}

.users-table-container{
    background:white;
    border-radius:10px;
    padding:2rem;
    box-shadow:0 5px 25px rgba(0,0,0,0.08);
}

.table-responsive{
    border:1px solid rgba(0,26,71,.08);
    border-radius:10px;
    overflow:hidden;
    background:#fff;
}

.table th,
.table td{
    border:1px solid rgba(15,23,42,.08);
    padding:.62rem .75rem;
    font-size:.88rem;
    vertical-align:top;
}

.table th{
    background:linear-gradient(135deg,#123c73,#1d5ea8);
    color:white;
    font-weight:600;
    line-height:1.25;
}

.table tbody tr:nth-child(even) td{
    background:#f8fafc;
}

.btn-action{
    width:36px;
    height:36px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    color:white;
    text-decoration:none;
}

.btn-view{background:#17a2b8;}

.search-icon{
    position:absolute;
    top:10px;
    left:10px;
    color:#888;
}

.search-box input{
    padding-left:35px;
}

.page-title{
    color:var(--primary-color);
}

.debug-box{
    background:#fff3cd;
    border:1px solid #ffecb5;
    color:#664d03;
    padding:1rem;
    border-radius:10px;
    margin-bottom:1rem;
    font-size:.9rem;
}

@media (max-width:992px){
    .main-content{
        margin-left:0;
        padding:5rem 1rem 2rem;
    }
}
</style>
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h2 class="fw-bold page-title">List of Registered Businesses</h2>
            <p class="text-muted mb-0">Manage and monitor all businesses</p>
        </div>

        <div class="search-box position-relative" style="width:250px;">
            <i class="fas fa-search search-icon"></i>
            <input id="searchInput" type="text" class="form-control" placeholder="Search businesses...">
        </div>
    </div>

    <?php if (!$table_exists || $total_businesses === 0): ?>
        <div class="debug-box">
            <strong>Database Check:</strong><br>
            Connected Database: <strong><?= htmlspecialchars($db_name); ?></strong><br>
            business_owner table exists: <strong><?= $table_exists ? 'YES' : 'NO'; ?></strong><br>
            Total business_owner records: <strong><?= $total_businesses; ?></strong><br><br>
            If your phpMyAdmin has data but this shows 0, your <strong>db.php</strong> is connected to the wrong database.
        </div>
    <?php endif; ?>

    <div class="users-table-container">
        <div class="table-responsive">
            <table class="table table-hover" id="businessTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Business Name</th>
                        <th>Owner Name</th>
                        <th>Address</th>
                        <th>Gender</th>
                        <th>Description</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php $count = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $owner = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
                        ?>

                        <tr>
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($row['business_name'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($owner ?: '-'); ?></td>
                            <td><?= htmlspecialchars($row['address'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($row['gender'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($row['description'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?: '-'); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="view_business.php?id=<?= (int)$row['b_id']; ?>" class="btn-action btn-view" title="View business">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No businesses found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#businessTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});
</script>

</body>
</html>
