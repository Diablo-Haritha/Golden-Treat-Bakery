<?php
// billing/print_receipt.php
require_once 'db.php';

$bill_id = $_GET['bill_id'] ?? die('Bill ID required');
$bill_id = (int)$bill_id;

// Fetch data (same as receipt.php)
// ... [same queries as above] ...

// Generate ESC/POS plain text
header('Content-Type: text/plain');
header('Content-Disposition: inline; filename="receipt.txt"');

function escPosText($text, $center = false, $bold = false) {
    $out = '';
    if ($bold) $out .= "\x1B\x45\x01"; // Bold ON
    if ($center) $out .= "\x1B\x61\x01"; // Center align
    $out .= $text . "\n";
    if ($bold || $center) $out .= "\x1B\x45\x00\x1B\x61\x00"; // Reset
    return $out;
}

echo escPosText($settings['shop_name'], true, true);
echo escPosText($settings['shop_address'], true);
echo escPosText("Tel: " . $settings['shop_tel'], true);
echo escPosText("Date: " . date('Y-m-d H:i', strtotime($billData['created_at'])), true);
echo "--------------------------------\n";

printf("%-20s %10s\n", "Item", "Total");
echo "--------------------------------\n";

foreach ($billItems as $item) {
    $name = substr($item['item_name'], 0, 18);
    $total = number_format($item['price'] * $item['qty'], 2);
    printf("%-20s %10s\n", $name, $total);
}

echo "--------------------------------\n";
printf("%-20s %10s\n", "Subtotal:", number_format($subtotal, 2));
printf("%-20s %10s\n", "VAT ({$vatPercent}%):", number_format($vat, 2));
echo escPosText(str_repeat("=", 32), false, true);
printf("%-20s %10s\n", "TOTAL:", number_format($total, 2));
echo escPosText(str_repeat("=", 32), false, true);

echo "\n" . wordwrap($settings['thank_note'], 32, "\n", true) . "\n";
echo "Thank you! Visit again!\n";
echo "\x1B\x64\x05"; // Feed 5 lines
echo "\x1D\x56\x41\x03"; // Full cut
?>