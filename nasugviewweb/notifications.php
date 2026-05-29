<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/negosyo_notifications_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$admin_fullname = "User";
$designation = "Admin";

nasugviewweb_ensure_notifications_table($conn);

$userStmt = $conn->prepare("SELECT username, fname, lname, designation FROM negosyo_center_users WHERE id=? LIMIT 1");
if ($userStmt) {
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    if ($row = $userResult->fetch_assoc()) {
        $name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
        $admin_fullname = $name !== '' ? $name : (trim((string) ($row['username'] ?? '')) ?: 'User');
        $designation = trim((string) ($row['designation'] ?? '')) ?: 'Admin';
    }

    $userStmt->close();
}

if (isset($_GET['read'])) {
    $update = $conn->prepare("
        UPDATE notifications
        SET is_read=1
        WHERE user_id=? AND account_type='negosyo_center'
    ");

    if ($update) {
        $update->bind_param("i", $user_id);
        $update->execute();
        $update->close();
    }

    header("Location: notifications.php");
    exit();
}

$notifCount = nasugviewweb_unread_notification_count($conn, $user_id);
$notifications = [];
$stmt = $conn->prepare("
    SELECT id, title, message, is_read, created_at
    FROM notifications
    WHERE user_id=? AND account_type='negosyo_center'
    ORDER BY created_at DESC, id DESC
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
}

function negosyoNotificationGroupLabel(string $date): string
{
    $today = date("Y-m-d");
    $yesterday = date("Y-m-d", strtotime("-1 day"));

    if ($date === $today) {
        return "TODAY";
    }

    if ($date === $yesterday) {
        return "YESTERDAY";
    }

    return date("F d, Y", strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - NasugView</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary-color:#001a47;--secondary-color:#f8f9fa;--sidebar-width:250px;}
body{margin:0;font-family:'Poppins',sans-serif;background:#f0f4ff;min-height:100vh;}
.main-content{margin-left:var(--sidebar-width);min-height:100vh;padding:2rem;}
.content-wrapper{max-width:1000px;margin:0 auto;}
.page-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.5rem;}
.page-title h1{font-size:1.8rem;font-weight:700;color:var(--primary-color);margin:0;}
.page-title p{color:#64748b;margin:.35rem 0 0;}
.read-btn{background:linear-gradient(135deg,#001a47,#00308a);color:#fff;text-decoration:none;border-radius:8px;padding:.75rem 1rem;font-weight:600;white-space:nowrap;}
.read-btn:hover{color:#fff;filter:brightness(1.05);}
.group-label{font-weight:700;margin:1.25rem 0 .75rem;color:#475569;font-size:.78rem;letter-spacing:.08em;}
.notif-card{display:block;background:#fff;border:1px solid rgba(0,26,71,.08);border-radius:10px;padding:1rem 1.15rem;margin-bottom:.75rem;text-decoration:none;color:inherit;box-shadow:0 6px 18px rgba(0,0,0,.05);}
.notif-card.unread{background:#eef4ff;border-color:#b7cdf7;}
.notif-title{font-weight:700;color:var(--primary-color);margin-bottom:.35rem;}
.notif-message{color:#334155;line-height:1.45;}
.notif-footer{display:flex;justify-content:space-between;gap:1rem;color:#64748b;font-size:.82rem;margin-top:.75rem;}
.empty-state{background:#fff;border-radius:10px;padding:3rem 1rem;text-align:center;color:#64748b;box-shadow:0 6px 18px rgba(0,0,0,.05);}
@media(max-width:992px){.main-content{margin-left:0;padding:5rem 1rem 2rem;}.page-header{align-items:flex-start;flex-direction:column;}.read-btn{width:100%;text-align:center;}}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <div class="page-title">
                <h1>Notifications</h1>
                <p>New business account alerts and other updates for your Negosyo Center.</p>
            </div>
            <?php if ($notifCount > 0): ?>
                <a href="notifications.php?read=1" class="read-btn"><i class="fas fa-check-double me-2"></i>Mark all as read</a>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">No notifications yet.</div>
        <?php else: ?>
            <?php $currentGroup = ''; ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                $createdAt = (string) ($notification['created_at'] ?? '');
                $dateOnly = $createdAt !== '' ? date("Y-m-d", strtotime($createdAt)) : date("Y-m-d");
                $group = negosyoNotificationGroupLabel($dateOnly);
                ?>
                <?php if ($group !== $currentGroup): ?>
                    <div class="group-label"><?php echo htmlspecialchars($group); ?></div>
                    <?php $currentGroup = $group; ?>
                <?php endif; ?>

                <a href="businesses.php" class="notif-card <?php echo ((int) $notification['is_read'] === 0) ? 'unread' : ''; ?>">
                    <div class="notif-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                    <div class="notif-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                    <div class="notif-footer">
                        <span><?php echo htmlspecialchars(date("h:i A", strtotime($createdAt))); ?></span>
                        <span><?php echo htmlspecialchars(date("m/d/Y", strtotime($createdAt))); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
