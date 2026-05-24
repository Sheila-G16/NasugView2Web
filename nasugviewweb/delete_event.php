<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id']);

$stmt = $conn->prepare("DELETE FROM events WHERE id=?");
$stmt->bind_param("i",$id);

if($stmt->execute()){
    echo json_encode(["success"=>true]);
}else{
    echo json_encode(["success"=>false,"error"=>"Delete failed"]);
}
?>
