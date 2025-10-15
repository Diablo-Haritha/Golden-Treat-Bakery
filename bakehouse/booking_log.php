<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";   

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Build SQL query with filters
$sql = "SELECT * FROM booking_log WHERE 1=1";
$params = [];

if (!empty($_GET['from'])) {
    $sql .= " AND DATE(log_timestamp) >= :from";
    $params[':from'] = $_GET['from'];
}
if (!empty($_GET['to'])) {
    $sql .= " AND DATE(log_timestamp) <= :to";
    $params[':to'] = $_GET['to'];
}
if (!empty($_GET['action'])) {
    $sql .= " AND operation = :action";
    $params[':action'] = $_GET['action'];
}

$sql .= " ORDER BY log_timestamp DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="booking_logs_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Booking ID', 'Operation', 'Customer Name', 'Email', 'Phone', 'Date', 'Time', 'Guests', 'Status', 'Timestamp']);
    foreach ($logEntries as $row) {
        fputcsv($output, [
            $row['log_id'],
            $row['booking_id'],
            $row['operation'],
            $row['customer_name'],
            $row['email'],
            $row['phone'],
            $row['date'],
            $row['time'],
            $row['guests'],
            $row['status'],
            date('Y-m-d H:i:s', strtotime($row['log_timestamp']))
        ]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Logs Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; font-family: Arial, sans-serif; }
        .header { background: #000; color: #fff; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; margin: 0; }
        .container { margin-top: 30px; }
        table th { background: #000; color: #fff; cursor: pointer; }
        table td, table th { white-space: nowrap; }
        .badge-insert { background: #198754; }
        .badge-update { background: #ffc107; color: #000; }
        .badge-delete { background: #dc3545; }
    </style>
</head>
<body>

<div class="header">
    <h1>Booking Logs Management</h1>
    <div>
        <a href="?<?= http_build_query(array_merge($_GET, ["export" => "csv"])); ?>" class="btn btn-success btn-sm">⬇ Export CSV</a>
    </div>
</div>

<div class="container">
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <label>From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? ''); ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label>To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? ''); ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label>Action</label>
            <select name="action" class="form-control">
                <option value="">All</option>
                <option <?= (($_GET['action'] ?? '') == "INSERT" ? "selected" : "") ?>>INSERT</option>
                <option <?= (($_GET['action'] ?? '') == "UPDATE" ? "selected" : "") ?>>UPDATE</option>
                <option <?= (($_GET['action'] ?? '') == "DELETE" ? "selected" : "") ?>>DELETE</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Booking ID</th>
                    <th>Operation</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logEntries): ?>
                    <?php foreach ($logEntries as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['log_id']); ?></td>
                            <td><?= htmlspecialchars($row['booking_id']); ?></td>
                            <td>
                                <span class="badge 
                                    <?= strtolower($row['operation']) == 'insert' ? 'badge-insert' : 
                                        (strtolower($row['operation']) == 'update' ? 'badge-update' : 'badge-delete') ?>">
                                    <?= htmlspecialchars($row['operation']); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['customer_name']); ?></td>
                            <td><?= htmlspecialchars($row['email']); ?></td>
                            <td><?= htmlspecialchars($row['phone']); ?></td>
                            <td><?= htmlspecialchars($row['date']); ?></td>
                            <td><?= htmlspecialchars($row['time']); ?></td>
                            <td><?= htmlspecialchars($row['guests']); ?></td>
                            <td><?= htmlspecialchars($row['status']); ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($row['log_timestamp'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center text-muted">No logs found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Column sort on click
    document.querySelectorAll('th').forEach((header, i) => {
        header.addEventListener('click', () => sortTable(i));
    });

    function sortTable(colIndex) {
        const table = document.querySelector('table tbody');
        const rows = Array.from(table.querySelectorAll('tr')).filter(r => r.children.length > 1);
        const sorted = rows.sort((a, b) => a.cells[colIndex].innerText.localeCompare(b.cells[colIndex].innerText));
        sorted.forEach(row => table.appendChild(row));
    }
</script>
</body>
</html>
