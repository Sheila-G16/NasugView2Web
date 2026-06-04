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
                target_type VARCHAR(50) NULL,
                target_id INT NULL,
                target_url VARCHAR(255) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notifications_user (user_id, account_type, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        foreach ([
            'target_type' => "ALTER TABLE notifications ADD COLUMN target_type VARCHAR(50) NULL AFTER message",
            'target_id' => "ALTER TABLE notifications ADD COLUMN target_id INT NULL AFTER target_type",
            'target_url' => "ALTER TABLE notifications ADD COLUMN target_url VARCHAR(255) NULL AFTER target_id",
        ] as $column => $sql) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE '" . $conn->real_escape_string($column) . "'");
            if ($columnCheck && $columnCheck->num_rows === 0) {
                $conn->query($sql);
            }
        }
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

if (!function_exists('nasugviewweb_insert_targeted_notification')) {
    function nasugviewweb_insert_targeted_notification(mysqli $conn, int $userId, string $accountType, string $title, string $message, string $targetType, int $targetId, string $targetUrl): void
    {
        if ($userId <= 0 || $accountType === '' || $title === '' || $message === '' || $targetType === '' || $targetId <= 0) {
            return;
        }

        nasugviewweb_ensure_notifications_table($conn);

        $check = $conn->prepare("
            SELECT id
            FROM notifications
            WHERE user_id=? AND account_type=? AND target_type=? AND target_id=?
            LIMIT 1
        ");

        if ($check) {
            $check->bind_param("issi", $userId, $accountType, $targetType, $targetId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                return;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, account_type, title, message, target_type, target_id, target_url, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param("issssis", $userId, $accountType, $title, $message, $targetType, $targetId, $targetUrl);
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

if (!function_exists('nasugviewweb_sync_business_owner_notifications')) {
    function nasugviewweb_sync_business_owner_notifications(mysqli $conn): void
    {
        nasugviewweb_ensure_notifications_table($conn);

        $businesses = $conn->query("
            SELECT b_id, business_name, fname, lname, address
            FROM business_owner
            ORDER BY b_id ASC
        ");

        if (!$businesses) {
            return;
        }

        while ($business = $businesses->fetch_assoc()) {
            $businessName = trim((string) ($business['business_name'] ?? ''));
            $ownerName = trim((string) ($business['fname'] ?? '') . ' ' . (string) ($business['lname'] ?? ''));
            $address = trim((string) ($business['address'] ?? ''));

            nasugviewweb_notify_centers_new_business($conn, $businessName, $ownerName, $address);
        }
    }
}

if (!function_exists('nasugviewweb_sync_evaluation_notifications')) {
    function nasugviewweb_sync_evaluation_notifications(mysqli $conn, int $centerUserId = 0): void
    {
        nasugviewweb_ensure_notifications_table($conn);

        $sql = "
            SELECT
                ee.id,
                ee.full_name,
                ee.email,
                ee.event_code,
                ee.created_at,
                e.title,
                e.created_by_user_id
            FROM event_evaluations ee
            INNER JOIN events e
                ON e.id = ee.event_id
            WHERE e.created_by_user_id IS NOT NULL
        ";

        if ($centerUserId > 0) {
            $sql .= " AND e.created_by_user_id = ?";
        }

        $sql .= " ORDER BY ee.created_at DESC, ee.id DESC LIMIT 100";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }

        if ($centerUserId > 0) {
            $stmt->bind_param("i", $centerUserId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $evaluationId = (int) ($row['id'] ?? 0);
            $eventCode = trim((string) ($row['event_code'] ?? ''));
            $eventTitle = trim((string) ($row['title'] ?? 'Untitled Event'));
            $evaluatorName = trim((string) ($row['full_name'] ?? ''));
            $evaluatorEmail = trim((string) ($row['email'] ?? ''));
            $evaluator = $evaluatorName !== '' ? $evaluatorName : ($evaluatorEmail !== '' ? $evaluatorEmail : 'A participant');
            $targetUrl = 'dashboard.php?evaluation_event_code=' . rawurlencode($eventCode) . '&highlight_evaluation=' . $evaluationId . '#evaluation-responses';

            nasugviewweb_insert_targeted_notification(
                $conn,
                (int) $row['created_by_user_id'],
                'negosyo_center',
                'New Event Evaluation',
                $evaluator . ' submitted an evaluation for ' . $eventTitle . ' (' . $eventCode . ').',
                'event_evaluation',
                $evaluationId,
                $targetUrl
            );
        }

        $stmt->close();
    }
}
?>
