<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","nasugview2");
if ($conn->connect_error) {
    echo json_encode(["success"=>false,"error"=>"DB connection failed"]);
    exit;
}

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

$stmt = $conn->prepare("UPDATE events SET start_date_and_time=?, end_date_and_time=?, duration=?, status=?, remarks=? WHERE id=?");
$stmt->bind_param("sssssi",$start,$end,$duration,$status,$remarks,$id);

if($stmt->execute()){
    echo json_encode(["success"=>true]);
}else{
    echo json_encode(["success"=>false,"error"=>"Reschedule failed"]);
}
?>
