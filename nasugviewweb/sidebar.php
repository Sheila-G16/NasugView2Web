<?php
// ==============================
// Sidebar.php (FIXED FOR NAME/DESIGNATION)
// DISPLAY ONLY — NO DB / NO SESSION
// ==============================

// Prevent undefined variable warnings
$admin_fullname = $admin_fullname ?? "User";
$designation    = $designation    ?? "Admin";
$current_page   = basename($_SERVER['PHP_SELF']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/negosyo_notifications_helper.php";

if (!function_exists('nasugviewweb_sidebar_initials')) {
    function nasugviewweb_sidebar_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $initials = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper(substr($part, 0, 1));
            }

            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('nasugviewweb_sidebar_profile_src')) {
    function nasugviewweb_sidebar_profile_src(string $image, string $baseDir): string
    {
        $image = trim($image);

        if ($image === '') {
            return '';
        }

        $cleanImage = ltrim(str_replace('\\', '/', $image), '/');
        $candidates = [
            $cleanImage,
            'uploads/' . basename($cleanImage)
        ];

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate))) {
                return $candidate;
            }
        }

        return '';
    }
}

$sidebar_profile_image = $sidebar_profile_image ?? ($admin['profile_img'] ?? '');

if (isset($_SESSION['user_id'])) {
    $sidebar_conn = null;

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->query("DO 1");
            $sidebar_conn = $conn;
        } catch (Throwable $e) {
            $sidebar_conn = null;
        }
    }

    if (!$sidebar_conn) {
        require __DIR__ . "/db.php";
        $sidebar_conn = $conn;
    }

    if ($sidebar_conn instanceof mysqli) {
        $stmt = $sidebar_conn->prepare("SELECT username, fname, lname, designation, profile_img FROM negosyo_center_users WHERE id=? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $sidebar_user_result = $stmt->get_result();

            if ($row = $sidebar_user_result->fetch_assoc()) {
                $fname = trim($row['fname'] ?? '');
                $lname = trim($row['lname'] ?? '');
                $username = trim($row['username'] ?? '');

                $admin_fullname = ($fname !== '' || $lname !== '')
                    ? trim($fname . ' ' . $lname)
                    : ($username !== '' ? $username : $admin_fullname);
                $designation = trim($row['designation'] ?? '') ?: $designation;
                $sidebar_profile_image = trim($row['profile_img'] ?? '') ?: $sidebar_profile_image;
            }

            $stmt->close();
        }
    }
}

$sidebar_profile_src = nasugviewweb_sidebar_profile_src($sidebar_profile_image, __DIR__);
$sidebar_initials = nasugviewweb_sidebar_initials($admin_fullname);
$sidebar_notification_count = 0;

if (isset($_SESSION['user_id']) && isset($sidebar_conn) && $sidebar_conn instanceof mysqli) {
    nasugviewweb_sync_business_owner_notifications($sidebar_conn);
    $sidebar_notification_count = nasugviewweb_unread_notification_count($sidebar_conn, (int) $_SESSION['user_id']);
}
?>

<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<!-- Sidebar -->
<button class="mobile-sidebar-toggle" type="button" aria-label="Open menu" aria-expanded="false">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-backdrop" aria-hidden="true"></div>

<div class="sidebar">

    <div class="sidebar-header">

        <div class="logo">
            <?php if ($sidebar_profile_src !== ''): ?>
                <img src="<?php echo htmlspecialchars($sidebar_profile_src); ?>" alt="<?php echo htmlspecialchars($admin_fullname); ?> profile photo" class="profile-photo">
            <?php else: ?>
                <div class="profile-photo profile-photo-fallback" aria-label="<?php echo htmlspecialchars($admin_fullname); ?> profile photo">
                    <?php echo htmlspecialchars($sidebar_initials); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="user-info">
            <h6><?php echo htmlspecialchars($admin_fullname); ?></h6>
            <small><?php echo htmlspecialchars($designation); ?></small>
        </div>

    </div>

    <div class="sidebar-menu">
        <ul>

            <li>
                <a href="dashboard.php" class="<?php echo $current_page=='dashboard.php'?'active':''; ?>">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>

            <li>
                <a href="events.php" class="<?php echo $current_page=='events.php'?'active':''; ?>">
                    <i class="fas fa-calendar-alt"></i> Events
                </a>
            </li>

            <li>
                <a href="certificate.php" class="<?php echo $current_page=='certificate.php'?'active':''; ?>">
                    <i class="fas fa-certificate"></i> Certificates
                </a>
            </li>

            <li>
                <a href="businesses.php" class="<?php echo $current_page=='businesses.php'?'active':''; ?>">
                    <i class="fas fa-briefcase"></i> Businesses
                </a>
            </li>

            <li>
                <a href="notifications.php" class="<?php echo $current_page=='notifications.php'?'active':''; ?>">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($sidebar_notification_count > 0): ?>
                        <span class="sidebar-badge"><?php echo $sidebar_notification_count > 99 ? '99+' : (int) $sidebar_notification_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li>
                <a href="settings.php" class="<?php echo $current_page=='settings.php'?'active':''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>

            <li style="margin-top:2rem;">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>

        </ul>
    </div>
</div>

<style>
:root {
    --primary-color: #001a47;
    --secondary-color: #f8f9fa;
    --gradient-start: #001a47;
    --gradient-end: #00308a;
    --sidebar-width: 250px;
}

body, .sidebar, .sidebar a, .user-info {
    font-family: 'Poppins', sans-serif;
}

/* Sidebar layout */
.sidebar {
    background: linear-gradient(180deg, var(--gradient-start), var(--gradient-end));
    color: white;
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 0;
    overflow-y: auto;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    z-index: 1000;
}

/* Header */
.sidebar-header {
    background: #002565;
    padding: 2.5rem 1.5rem 2rem;  /* <-- Reduced top padding to match other pages */
    position: sticky;
    top: 0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Profile Photo */
.logo {
    display:flex;
    justify-content:center;
    align-items:center;
    margin-bottom:1rem;  /* <-- smaller margin */
}

.profile-photo {
    width:104px;
    height:104px;
    border-radius:50%;
    object-fit:cover;
    display:flex;
    align-items:center;
    justify-content:center;
    border:4px solid rgba(255,255,255,0.88);
    box-shadow:0 14px 28px rgba(0,26,71,0.28);
    background:#fff;
}

.profile-photo-fallback {
    background:linear-gradient(135deg,#5b8be0,#001a47);
    color:#fff;
    font-size:34px;
    font-weight:700;
    letter-spacing:0;
}

/* User Info */
.user-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin:0;
}

.user-info h6 { 
    margin:0; 
    font-weight:600; 
    font-size:16px; 
    line-height:1.2;
}

.user-info small { 
    font-size:13px; 
    opacity:0.8; 
    line-height:1.2;
}

/* Menu */
.sidebar-menu ul {
    list-style:none;
    padding:1.5rem 0 0 0;
    margin:0;
}

.sidebar-menu ul li a {
    display:flex;
    align-items:center;
    position:relative;
    padding:1rem 1.5rem;
    margin:0.25rem 0;
    color:rgba(255,255,255,0.85);
    text-decoration:none;
    border-left:4px solid transparent;
    border-radius:0 8px 8px 0;
    font-weight:500;
    transition:0.3s;
}

.sidebar-menu ul li a i {
    width:24px;
    margin-right:12px;
    font-size:1.2rem;
}

.sidebar-menu ul li a:hover,
.sidebar-menu ul li a.active {
    background: rgba(255,255,255,0.12);
    border-left-color:#00d4ff;
    color:white;
    transform: translateX(4px);
}

/* Scrollbar */
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.3); border-radius:3px; }

body.sidebar-open {
    overflow:hidden;
}

.sidebar-badge {
    margin-left:auto;
    min-width:22px;
    height:22px;
    padding:0 6px;
    border-radius:999px;
    background:#dc3545;
    color:#fff;
    font-size:.72rem;
    font-weight:700;
    line-height:22px;
    text-align:center;
}

.mobile-sidebar-toggle {
    display:none;
    position:fixed;
    top:14px;
    left:14px;
    width:44px;
    height:44px;
    border:0;
    border-radius:8px;
    background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end));
    color:#fff;
    box-shadow:0 10px 24px rgba(0,26,71,0.24);
    z-index:1201;
    align-items:center;
    justify-content:center;
}

.sidebar-backdrop {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.45);
    z-index:999;
}

@media (max-width:992px){
    .mobile-sidebar-toggle {
        display:flex;
    }

    .sidebar {
        transform:translateX(-100%);
        width:min(280px, 86vw);
        transition:transform .25s ease;
    }

    body.sidebar-open .sidebar {
        transform:translateX(0);
    }

    body.sidebar-open .sidebar-backdrop {
        display:block;
    }

    .main-content {
        margin-left:0 !important;
        padding-top:76px !important;
    }
}

@media (max-width:576px){
    .sidebar-header {
        padding:2rem 1rem 1.5rem;
    }

    .profile-photo {
        width:92px;
        height:92px;
    }

    .sidebar-menu ul li a {
        padding:.9rem 1.1rem;
    }
}
</style>

<script>
(function () {
    const toggle = document.querySelector('.mobile-sidebar-toggle');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');

    if (!toggle || !backdrop) {
        return;
    }

    function setSidebar(open) {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.innerHTML = open ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
    }

    toggle.addEventListener('click', function () {
        setSidebar(!document.body.classList.contains('sidebar-open'));
    });

    backdrop.addEventListener('click', function () {
        setSidebar(false);
    });

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            setSidebar(false);
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            setSidebar(false);
        }
    });
})();
</script>
