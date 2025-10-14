<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$data = [];

// Validate required fields for add/edit
if (in_array($action, ['add', 'edit'])) {
    $required = ['partNumber','date','description','quantity','unit','category','status'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'msg' => "Field '$field' is required"]);
            exit;
        }
    }

    // Unit letters only
    $allowed_units = ['pcs','kg','g','ltr','ml','box','pack'];
    if(!in_array($_POST['unit'], $allowed_units)){
    echo json_encode(['success'=>false, 'msg'=>'Invalid unit selected']);
    exit;
}


    // Description must NOT contain numbers
    if (preg_match("/[0-9]/", $_POST['description'])) {
        echo json_encode(['success' => false, 'msg' => 'Description must not contain numbers']);
        exit;
    }

    // Quantity must be a non-negative number
    if (!is_numeric($_POST['quantity']) || $_POST['quantity'] < 0) {
        echo json_encode(['success' => false, 'msg' => 'Quantity must be a non-negative number']);
        exit;
    }
}

// ADD
if ($action == 'add') {
    $stmt = $conn->prepare("INSERT INTO stock (partNumber,date,description,quantity,unit,category,status) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssisss", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['unit'], $_POST['category'], $_POST['status']);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// EDIT
if ($action == 'edit') {
    $stmt = $conn->prepare("UPDATE stock SET partNumber=?,date=?,description=?,quantity=?,unit=?,category=?,status=? WHERE id=?");
    $stmt->bind_param("sssisssi", $_POST['partNumber'], $_POST['date'], $_POST['description'], $_POST['quantity'], $_POST['unit'], $_POST['category'], $_POST['status'], $_POST['id']);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// DELETE
if ($action == 'delete' && isset($_POST['id'])) {
    $stmt = $conn->prepare("DELETE FROM stock WHERE id=?");
    $stmt->bind_param("i", $_POST['id']);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// DELETE MULTIPLE
if ($action == 'delete_multiple' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    if (count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM stock WHERE id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

// FILTER
if ($action == 'filter') {
    $where = [];
    $params = [];
    $types = '';

    if (!empty($_POST['date'])) {
        $where[] = "date=?";
        $params[] = $_POST['date'];
        $types .= 's';
    }
    if (!empty($_POST['category'])) {
        $where[] = "category LIKE ?";
        $params[] = "%{$_POST['category']}%";
        $types .= 's';
    }
    if (!empty($_POST['status'])) {
        $where[] = "status=?";
        $params[] = $_POST['status'];
        $types .= 's';
    }

    $sql = "SELECT * FROM stock";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY date DESC";

    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) $data[] = $row;

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// IMPORT CSV
if ($action == 'import_csv' && isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] == 0) {
    $file = $_FILES['csvFile']['tmp_name'];
    $rows = array_map('str_getcsv', file($file));
    $header = array_map('trim', array_shift($rows)); // e.g. ['Part Number','Date','Description','Quantity','Unit','Category','Status']
    $imported = 0;
    $data = [];

    foreach ($rows as $row) {
        $row = array_map('trim', $row);
        $row = array_combine($header, $row);

        if (empty($row['Part Number']) || empty($row['Date']) || empty($row['Description']) || empty($row['Quantity']) || empty($row['Unit']) || empty($row['Category']) || empty($row['Status'])) continue;
        if (!preg_match('/^[a-zA-Z]+$/', $row['Unit'])) continue;
        if (preg_match("/[0-9]/", $row['Description'])) continue;

        $stmt = $conn->prepare("INSERT INTO stock (partNumber,date,description,quantity,unit,category,status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssisss", $row['Part Number'], $row['Date'], $row['Description'], $row['Quantity'], $row['Unit'], $row['Category'], $row['Status']);
        $stmt->execute();
        $imported++;
        $data[] = $conn->query("SELECT * FROM stock ORDER BY id DESC LIMIT 1")->fetch_assoc();
    }

    echo json_encode(['success' => true, 'imported' => $imported, 'data' => $data]);
    exit;
}

// DEFAULT: INVALID ACTION
echo json_encode(['success' => false, 'msg' => 'Invalid action']);
exit;
?>
