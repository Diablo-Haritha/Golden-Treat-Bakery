<?php
require 'db.php';

// Fetch all stock records
$res = $conn->query("SELECT * FROM stock ORDER BY date DESC");

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock_export.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers (added 'Unit')
fputcsv($output, ['Part Number','Date','Description','Quantity','Unit','Category','Status']);

// Add rows
while($row = $res->fetch_assoc()){
    fputcsv($output, [
        $row['partNumber'],
        $row['date'],
        $row['description'],
        $row['quantity'],
        $row['unit'],        // New column
        $row['category'],
        $row['status']
    ]);
}

fclose($output);
exit;
