<?php
if (!function_exists('nasugviewweb_ensure_notifications_table')) {
    function nasugviewweb_ensure_notifications_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                account_type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notifications_user (user_id, account_type, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('nasugviewweb_insert_notification')) {
    function nasugviewweb_insert_notification(mysqli $conn, int $userId, string $accountType, string $title, string $message): void
    {
        if ($userId <= 0 || $accountType === '' || $title === '' || $message === '') {
            return;
        }

        nasugviewweb_ensure_notifications_table($conn);

        $check = $conn->prepare("
            SELECT id
            FROM notifications
            WHERE user_id=? AND account_type=? AND title=? AND message=?
            LIMIT 1
        ");

        if ($check) {
            $check->bind_param("isss", $userId, $accountType, $title, $message);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                return;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, account_type, title, message, is_read)
            VALUES (?, ?, ?, ?, 0)
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param("isss", $userId, $accountType, $title, $message);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('nasugviewweb_unread_notification_count')) {
    function nasugviewweb_unread_notification_count(mysqli $conn, int $userId): int
    {
        nasugviewweb_ensure_notifications_table($conn);

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM notifications
            WHERE user_id=? AND account_type='negosyo_center' AND is_read=0
        ");

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('nasugviewweb_notify_centers_new_business')) {
    function nasugviewweb_notify_centers_new_business(mysqli $conn, string $businessName, string $ownerName, string $address = ''): void
    {
        nasugviewweb_ensure_notifications_table($conn);

        $businessName = trim($businessName) !== '' ? trim($businessName) : 'A new business';
        $ownerName = trim($ownerName) !== '' ? trim($ownerName) : 'a business owner';
        $address = trim($address);
        $message = $businessName . " was registered by " . $ownerName . ".";

        if ($address !== '') {
            $message .= " Address: " . $address . ".";
        }

        $centers = $conn->query("SELECT id FROM negosyo_center_users");

        if (!$centers) {
            return;
        }

        while ($center = $centers->fetch_assoc()) {
            nasugviewweb_insert_notification(
                $conn,
                (int) $center['id'],
                'negosyo_center',
                'New Business Account',
                $message
            );
        }
    }
}
?>
