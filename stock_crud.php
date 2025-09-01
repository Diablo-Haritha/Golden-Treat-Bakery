<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if($action === 'add'){
    $stmt = $conn->prepare("INSERT INTO stock (partNumber, date, description, quantity, category, status) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssis", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['category'], $_POST['status']);
    $stmt->execute();
    echo json_encode(['success'=>true, 'id'=>$stmt->insert_id]);
    exit;
}

if($action === 'edit'){
    $stmt = $conn->prepare("UPDATE stock SET partNumber=?, date=?, description=?, quantity=?, category=?, status=? WHERE id=?");
    $stmt->bind_param("sssisii", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['category'], $_POST['status'], $_POST['id']);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if($action === 'delete'){
    $stmt = $conn->prepare("DELETE FROM stock WHERE id=?");
    $stmt->bind_param("i", $_POST['id']);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if($action === 'filter'){
    $date = $_POST['date'] ?? '';
    $category = $_POST['category'] ?? '';
    $status = $_POST['status'] ?? '';

    $query = "SELECT * FROM stock WHERE 1=1";
    $params = [];
    $types = "";

    if($date !== ""){
        $query .= " AND date = ?";
        $params[] = $date;
        $types .= "s";
    }
    if($category !== ""){
        $query .= " AND category LIKE ?";
        $params[] = "%$category%";
        $types .= "s";
    }
    if($status !== ""){
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $stmt = $conn->prepare($query);
    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while($row = $res->fetch_assoc()){
        $data[] = $row;
    }
    echo json_encode(['success'=>true, 'data'=>$data]);
    exit;
}

echo json_encode(['success'=>false, 'msg'=>'Invalid action']);
