<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$data = [];

// Validate required fields for add/edit
if(in_array($action,['add','edit'])){
    $required = ['partNumber','date','description','quantity','unit','category','status'];
    foreach($required as $field){
        if(empty($_POST[$field])){
            echo json_encode(['success'=>false, 'msg'=>"Field '$field' is required"]);
            exit;
        }
    }
    // Unit letters only
    if(!preg_match("/^[A-Za-z]+$/", $_POST['unit'])){
        echo json_encode(['success'=>false, 'msg'=>'Unit can contain letters only']);
        exit;
    }
}

if($action=='add'){
    $stmt = $conn->prepare("INSERT INTO stock (partNumber,date,description,quantity,unit,category,status) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssisss", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['unit'], $_POST['category'], $_POST['status']);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if($action=='edit'){
    $stmt = $conn->prepare("UPDATE stock SET partNumber=?,date=?,description=?,quantity=?,unit=?,category=?,status=? WHERE id=?");
    $stmt->bind_param("sssisssi", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['unit'], $_POST['category'], $_POST['status'], $_POST['id']);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if($action=='delete'){
    $stmt = $conn->prepare("DELETE FROM stock WHERE id=?");
    $stmt->bind_param("i", $_POST['id']);
    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if($action=='delete_multiple' && isset($_POST['ids'])){
    $ids = $_POST['ids'];
    if(count($ids) > 0){
        $placeholders = implode(',', array_fill(0,count($ids),'?'));
        $stmt = $conn->prepare("DELETE FROM stock WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
    }
    echo json_encode(['success'=>true]);
    exit;
}

if($action=='filter'){
    $where = [];
    if(!empty($_POST['date'])) $where[] = "date='{$_POST['date']}'";
    if(!empty($_POST['category'])) $where[] = "category LIKE '%{$_POST['category']}%'";
    if(!empty($_POST['status'])) $where[] = "status='{$_POST['status']}'";
    $sql = "SELECT * FROM stock";
    if($where) $sql .= " WHERE ".implode(" AND ", $where);
    $sql .= " ORDER BY date DESC";
    $res = $conn->query($sql);
    $data=[];
    while($row=$res->fetch_assoc()) $data[]=$row;
    echo json_encode(['success'=>true,'data'=>$data]);
    exit;
}

if($action=='import_csv' && isset($_FILES['csvFile'])){
    $file = $_FILES['csvFile']['tmp_name'];
    $rows = array_map('str_getcsv', file($file));
    $header = array_map('trim', array_shift($rows)); // ['Part Number','Date','Description','Quantity','Unit','Category','Status']
    $imported = 0;
    $data = [];

    foreach($rows as $row){
        $row = array_map('trim', $row);
        $row = array_combine($header, $row);

        // Validation: all fields must be filled
        if(empty($row['Part Number']) || empty($row['Date']) || empty($row['Description']) || empty($row['Quantity']) || empty($row['Unit']) || empty($row['Category']) || empty($row['Status'])){
            continue; // Skip invalid row
        }

        // Validation: Unit must be letters only
        if(!preg_match('/^[a-zA-Z]+$/', $row['Unit'])){
            continue;
        }

        $stmt = $conn->prepare("INSERT INTO stock (partNumber,date,description,quantity,unit,category,status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "sssisss",
            $row['Part Number'],
            $row['Date'],
            $row['Description'],
            $row['Quantity'],
            $row['Unit'],
            $row['Category'],
            $row['Status']
        );
        $stmt->execute();
        $imported++;
        $data[] = $conn->query("SELECT * FROM stock ORDER BY id DESC LIMIT 1")->fetch_assoc();
    }

    echo json_encode(['success'=>true,'imported'=>$imported,'data'=>$data]);
    exit;
}


echo json_encode(['success'=>false,'msg'=>'Invalid action']);
?>
