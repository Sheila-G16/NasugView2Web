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

$stmt = $conn->prepare("DELETE FROM events WHERE id=? AND created_by_user_id=?");
$stmt->bind_param("ii",$id,$user_id);

if($stmt->execute() && $stmt->affected_rows > 0){
    echo json_encode(["success"=>true]);
}else{
    echo json_encode(["success"=>false,"error"=>"Event not found or not allowed."]);
}
?>
