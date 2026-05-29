<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false,"error"=>"Please log in first."]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id'] ?? 0);
$start = $data['start'] ?? '';
$end   = $data['end'] ?? '';
$reason = trim($data['remarks'] ?? '');

if (!$id || !$start || !$end || !$reason) {
    echo json_encode(["success"=>false,"error"=>"Please complete the new schedule and remarks."]);
    exit;
}

$start_dt = strtotime($start);
$end_dt = strtotime($end);
if (!$start_dt || !$end_dt || $end_dt <= $start_dt) {
    echo json_encode(["success"=>false,"error"=>"End date must be later than start date."]);
    exit;
}

$durationSeconds = $end_dt - $start_dt;
$days = floor($durationSeconds / 86400);
$hours = floor(($durationSeconds % 86400) / 3600);
$minutes = floor(($durationSeconds % 3600) / 60);
$duration = trim(($days > 0 ? $days . "d " : "") . ($hours > 0 ? $hours . "h " : "") . ($minutes > 0 ? $minutes . "m" : ""));
if ($duration === '') $duration = '0m';

$now = time();
if ($now < $start_dt) {
    $status = "For Implementation";
} elseif ($now >= $start_dt && $now <= $end_dt) {
    $status = "Ongoing";
} else {
    $status = "Implemented";
}

$remarks = "Rescheduled: " . $reason;

$stmt = $conn->prepare("UPDATE events SET start_date_and_time=?, end_date_and_time=?, duration=?, status=?, remarks=? WHERE id=? AND created_by_user_id=?");
$stmt->bind_param("sssssii",$start,$end,$duration,$status,$remarks,$id,$user_id);

if($stmt->execute() && $stmt->affected_rows > 0){
    echo json_encode(["success"=>true]);
}else{
    echo json_encode(["success"=>false,"error"=>"Event not found or not allowed."]);
}
?>
