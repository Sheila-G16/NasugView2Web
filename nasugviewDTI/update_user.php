<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "db.php";
header('Content-Type: application/json');

try {

    // Validate inputs
    if(empty($_POST['id'])){
        throw new Exception("Missing user ID");
    }

    $stmt = $conn->prepare("
        UPDATE negosyo_center_users
        SET fname=?, lname=?, username=?, email=?, designation=?, contact=?, negosyocenter=?
        WHERE id=?
    ");

    if(!$stmt){
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $negosyocenter = trim($_POST['negosyocenter'] ?? '');
    $id = (int) $_POST['id'];

    $stmt->bind_param("sssssssi", $fname, $lname, $username, $email, $designation, $contact, $negosyocenter, $id);

    if(!$stmt->execute()){
        throw new Exception("Execute failed: " . $stmt->error);
    }

    echo json_encode(["success"=>true]);

} catch(Exception $e){
    echo json_encode(["success"=>false,"error"=>$e->getMessage()]);
}
