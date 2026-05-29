<?php
if (!function_exists('nasugviewweb_ensure_password_security_columns')) {
    function nasugviewweb_ensure_password_security_columns(mysqli $conn): void
    {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM negosyo_center_users");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
            $result->close();
        }

        if (!isset($columns['must_change_password'])) {
            $conn->query("ALTER TABLE negosyo_center_users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!isset($columns['password_changed_at'])) {
            $conn->query("ALTER TABLE negosyo_center_users ADD COLUMN password_changed_at DATETIME NULL");
        }
    }
}
?>
