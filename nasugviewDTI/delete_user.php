<?php
require "db.php";
header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($payload['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception("Missing user ID.");
    }

    $stmt = $conn->prepare("DELETE FROM negosyo_center_users WHERE id=?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Delete failed: " . $stmt->error);
    }

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
