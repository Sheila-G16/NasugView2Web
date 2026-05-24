<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(["success"=>false,"error"=>"Invalid event."]);
    exit;
}

$check = $conn->prepare("
    SELECT
        status,
        remarks,
        CASE
            WHEN status = 'Canceled' THEN 'Canceled'
            WHEN NOW() < start_date_and_time THEN 'For Implementation'
            WHEN NOW() BETWEEN start_date_and_time AND end_date_and_time THEN 'Ongoing'
            WHEN NOW() > end_date_and_time THEN 'Implemented'
            ELSE status
        END AS calculated_status
    FROM events
    WHERE id=?
    LIMIT 1
");
$check->bind_param("i", $id);
$check->execute();
$event = $check->get_result()->fetch_assoc();

if (!$event) {
    echo json_encode(["success"=>false,"error"=>"Event not found."]);
    exit;
}

if (
    in_array($event['calculated_status'], ['Implemented', 'Done'], true) ||
    in_array($event['status'], ['Implemented', 'Done'], true) ||
    strcasecmp(trim($event['remarks'] ?? ''), 'Done') === 0
) {
    echo json_encode(["success"=>false,"error"=>"This event already happened, so it cannot be canceled."]);
    exit;
}

$stmt = $conn->prepare("UPDATE events SET status='Canceled', remarks='Canceled' WHERE id=?");
$stmt->bind_param("i",$id);

if($stmt->execute()){
    echo json_encode(["success"=>true]);
}else{
    echo json_encode(["success"=>false,"error"=>"Cancel failed"]);
}
?>
