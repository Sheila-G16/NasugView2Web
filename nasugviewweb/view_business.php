<?php
session_start();

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$admin_fullname = "Admin";
$designation = "Admin";

$admin_stmt = $conn->prepare("SELECT username, fname, lname, designation FROM negosyo_center_users WHERE id=? LIMIT 1");
if ($admin_stmt) {
    $admin_stmt->bind_param("i", $user_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();

    if ($admin = $admin_result->fetch_assoc()) {
        $name = trim((string) ($admin['fname'] ?? '') . ' ' . (string) ($admin['lname'] ?? ''));
        $admin_fullname = $name !== '' ? $name : (trim((string) ($admin['username'] ?? '')) ?: 'Admin');
        $designation = trim((string) ($admin['designation'] ?? '')) ?: 'Admin';
    }

    $admin_stmt->close();
}

$business_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$business = null;
$permit = null;

if ($business_id > 0) {
    $stmt = $conn->prepare("
        SELECT b_id, username, email, business_name, fname, lname, gender, birthday, age,
               address, bio, followers, following, profile_picture, cover_photo,
               business_photo, description, phone
        FROM business_owner
        WHERE b_id=?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $business = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $permit_table = $conn->query("SHOW TABLES LIKE 'business_permits'");
    if ($permit_table && $permit_table->num_rows > 0) {
        $permit_stmt = $conn->prepare("
            SELECT permit_id, owner_id, permit_number, file_name, file_path,
                   original_file_name, mime_type, uploaded_at, updated_at
            FROM business_permits
            WHERE owner_id=?
            ORDER BY updated_at DESC, uploaded_at DESC, permit_id DESC
            LIMIT 1
        ");

        if ($permit_stmt) {
            $permit_stmt->bind_param("i", $business_id);
            $permit_stmt->execute();
            $permit = $permit_stmt->get_result()->fetch_assoc();
            $permit_stmt->close();
        }
    }
}

$current_page = "businesses.php";

function business_value($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : '-';
}

function business_file_url(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    $cleanPath = ltrim($path, '/');
    $candidates = [
        [$cleanPath, $cleanPath],
        ['../NasugView2/' . $cleanPath, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NasugView2' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath)],
    ];

    foreach ($candidates as [$url, $file]) {
        if (is_file($file)) {
            return $url;
        }
    }

    return $cleanPath;
}

function business_is_image_permit(?array $permit): bool
{
    if (!$permit) {
        return false;
    }

    $mimeType = strtolower(trim((string) ($permit['mime_type'] ?? '')));
    $filePath = strtolower(trim((string) ($permit['file_path'] ?? '')));

    return str_starts_with($mimeType, 'image/')
        || preg_match('/\.(jpe?g|png|gif|webp)$/i', $filePath);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Information - NasugView</title>
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

.permit-preview{
    margin-top:1rem;
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    overflow:hidden;
    background:#f8fafc;
}

.permit-preview img{
    display:block;
    width:100%;
    max-height:720px;
    object-fit:contain;
    background:#fff;
}

.permit-actions{
    display:flex;
    flex-wrap:wrap;
    gap:.75rem;
    margin-top:1rem;
}

.permit-link{
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    padding:.65rem .9rem;
    border-radius:8px;
    background:#001a47;
    color:#fff;
    text-decoration:none;
    font-weight:600;
}

.permit-link:hover{
    color:#fff;
    filter:brightness(1.08);
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
            <h2 class="fw-bold page-title mb-1">Business Information</h2>
            <p class="text-muted mb-0">View registered business owner details</p>
        </div>
        <a href="businesses.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (!$business): ?>
        <div class="empty-state">Business not found.</div>
    <?php else: ?>
        <?php $owner_name = trim((string) ($business['fname'] ?? '') . ' ' . (string) ($business['lname'] ?? '')); ?>
        <section class="details-panel">
            <div class="details-header">
                <h3 class="mb-1"><?php echo htmlspecialchars(business_value($business['business_name'])); ?></h3>
                <p class="mb-0"><?php echo htmlspecialchars($owner_name !== '' ? $owner_name : '-'); ?></p>
            </div>

            <div class="details-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Business Name</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['business_name'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Owner Name</div>
                        <p class="info-value"><?php echo htmlspecialchars($owner_name !== '' ? $owner_name : '-'); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['username'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['email'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['phone'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['gender'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Birthday</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['birthday'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['age'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Followers</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['followers'])); ?></p>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Following</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['following'])); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Address</div>
                        <p class="info-value"><?php echo htmlspecialchars(business_value($business['address'])); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Description</div>
                        <p class="info-value"><?php echo nl2br(htmlspecialchars(business_value($business['description']))); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Bio</div>
                        <p class="info-value"><?php echo nl2br(htmlspecialchars(business_value($business['bio']))); ?></p>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Business Permit</div>
                        <?php if ($permit): ?>
                            <?php
                            $permit_url = business_file_url((string) ($permit['file_path'] ?? ''));
                            $permit_name = business_value($permit['original_file_name'] ?? $permit['file_name'] ?? '');
                            ?>
                            <p class="info-value"><strong>Permit Number:</strong> <?php echo htmlspecialchars(business_value($permit['permit_number'] ?? '')); ?></p>
                            <p class="info-value"><strong>File:</strong> <?php echo htmlspecialchars($permit_name); ?></p>
                            <p class="info-value"><strong>Uploaded:</strong> <?php echo htmlspecialchars(business_value($permit['uploaded_at'] ?? '')); ?></p>

                            <?php if ($permit_url !== '' && business_is_image_permit($permit)): ?>
                                <div class="permit-preview">
                                    <img src="<?php echo htmlspecialchars($permit_url); ?>" alt="Business permit image">
                                </div>
                            <?php endif; ?>

                            <?php if ($permit_url !== ''): ?>
                                <div class="permit-actions">
                                    <a class="permit-link" href="<?php echo htmlspecialchars($permit_url); ?>" target="_blank" rel="noopener">
                                        <i class="fas fa-file-arrow-down"></i> Open Permit
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="info-value">No business permit uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
