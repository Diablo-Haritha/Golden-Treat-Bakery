<?php
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// ---------- FETCH SHOP SETTINGS ----------
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$shop_name    = $settings['shop_name'];
$shop_slogan  = $settings['shop_slogan'];
$shop_tel     = $settings['shop_tel'];
$shop_email   = $settings['shop_email'];
$shop_address = $settings['shop_address'];
$thank_note   = $settings['thank_note'];
$vat_percent  = $settings['vat_percent'];

// ---------- SEARCH BILLS ----------
$search = "";
if (isset($_GET['search']) && $_GET['search'] != "") {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM bills 
            WHERE customer_name LIKE '%$search%' 
               OR id LIKE '%$search%' 
            ORDER BY created_at DESC";
} else {
    $sql = "SELECT * FROM bills ORDER BY created_at DESC";
}
$billsResult = $conn->query($sql);

// Handle search
$search = "";
if (isset($_GET['search']) && $_GET['search'] != "") {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM bills 
            WHERE customer_name LIKE '%$search%' 
               OR id LIKE '%$search%' 
            ORDER BY created_at DESC";
} else {
    $sql = "SELECT * FROM bills ORDER BY created_at DESC";
}
$billsResult = $conn->query($sql);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Bills</title>
<link rel="stylesheet" href="style1.css">
<style>
body { font-family: Arial, sans-serif; }
.container { max-width: 800px; margin: 30px auto; }
</style>
<script>
function printBill(id){
    const billContent = document.getElementById('bill-'+id).innerHTML;
    const win = window.open('', '', 'height=700,width=800');
    win.document.write('<html><head><title>Print Bill</title><style>');
    win.document.write('body{font-family:Courier New, monospace; padding:20px;}');
    win.document.write('table{width:100%; border-collapse: collapse;} th, td{padding:8px; text-align:left; border-bottom:1px dashed #ccc;} th{color:#30b6a2;} .total-row td{font-weight:bold;} .shop-info{text-align:center; margin-bottom:15px;}');
    win.document.write('</style></head><body>');
    win.document.write(billContent);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    win.print();
    win.close();
}
</script>
</head>
<body> <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Payment Management</div>
      <div class="search-bar">
  <form method="GET" action="save_bill.php" style="display:flex; align-items:center;">
    <input id="globalSearch" type="text" name="search" 
           placeholder="🔍 Search by customer, ID or date..." 
           value="<?= htmlspecialchars($search) ?>">
    <button type="submit" 
            style="margin-left:8px; padding:8px 14px; border:none; border-radius:6px; background:#00000089; color:#fff; cursor:pointer;">
      Search
    </button>
  </form>
</div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn " onclick="window.location.href='index.php'">Bill🧾</button>
          <button class="tab-btn active" onclick="window.location.href='save_bill.php'">All Bills</button>
          <button class="tab-btn " onclick="window.location.href='setting.php'">⚙️Setting</button>

        </div>
      </nav>
    </aside>
<div class="container">
<h1>All Bills</h1>

<a href="setting.php" style="float:right; margin-bottom:10px;">⚙️ Edit Shop Settings</a>
<?php
if ($billsResult->num_rows > 0) {
    while ($bill = $billsResult->fetch_assoc()) {
        echo '<div class="card" id="bill-'.$bill['id'].'">';
        echo '<button class="print-btn" onclick="printBill('.$bill['id'].')">🖨️ Print</button>';
        echo '<div class="shop-info">
                <h2>'.$shop_name.'</h2>
                <p>'.$shop_slogan.'</p>
                <p>Tel: '.$shop_tel.' | Email: '.$shop_email.'</p>
                <p>'.$shop_address.'</p>
              </div>';
        echo "<p><strong>Bill ID:</strong> {$bill['id']} | <strong>Customer:</strong> ".htmlspecialchars($bill['customer_name'])." | <strong>Date:</strong> {$bill['created_at']}</p>";

        // Fetch bill items
        $itemsResult = $conn->query("SELECT * FROM bill_items WHERE bill_id=".$bill['id']);
        echo '<table><thead><tr>
                <th>Item Name</th>
                <th>Price (Rs.)</th>
                <th>Qty</th>
                <th>Subtotal (Rs.)</th>
              </tr></thead><tbody>';

        $total = 0;
        while ($item = $itemsResult->fetch_assoc()) {
            $subtotal = $item['price'] * $item['qty'];
            echo "<tr>
                    <td>".htmlspecialchars($item['item_name'])."</td>
                    <td>".number_format($item['price'],2)."</td>
                    <td>{$item['qty']}</td>
                    <td>".number_format($subtotal,2)."</td>
                  </tr>";
            $total += $subtotal;
        }

        $vat = $total * ($vat_percent / 100);
        $grandTotal = $total + $vat;

        echo "<tr class='total-row'><td colspan='3'>Subtotal</td><td>Rs. ".number_format($total,2)."</td></tr>";
        echo "<tr class='total-row'><td colspan='3'>VAT ({$vat_percent}%)</td><td>Rs. ".number_format($vat,2)."</td></tr>";
        echo "<tr class='total-row'><td colspan='3'>Grand Total</td><td>Rs. ".number_format($grandTotal,2)."</td></tr>";
        echo '</tbody></table>';
        echo '<p style="text-align:center;">'.$thank_note.'</p>';
        echo '</div>';
    }
} else {
    echo "<p>No bills found.</p>";
}
$conn->close();
?>
</div>
</body>
</html>
