<?php
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bakehouse";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// Fetch all bills, newest first
$billsResult = $conn->query("SELECT * FROM bills ORDER BY created_at DESC");

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Bills</title>
<link rel="stylesheet" href="style1.css">
<style>
body { font-family: 'Righteous', sans-serif; }
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
<body>
       <!-- Header -->
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
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Payment Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Payment</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
          <button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn " onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn active" onclick="window.location.href='save_bill.php'">All Bills</button>
          <button class="tab-btn " onclick="window.location.href='bill_edit.php'">Sales Analysis</button>

        </div>
      </nav>
    </aside>
<div class="container">
<h1>All Bills</h1>

<?php
if ($billsResult->num_rows > 0) {
    while ($bill = $billsResult->fetch_assoc()) {
        echo '<div class="card" id="bill-'.$bill['id'].'">';
        echo '<button class="print-btn" onclick="printBill('.$bill['id'].')">🖨️ Print</button>';
        echo '<div class="shop-info">
                <h2>Golden Treat</h2>
                <p>Tel: 00000000 | Email: gol@gmail.com</p>
                <p>Address: Adurkku Vidiya, Jampata Street</p>
              </div>';
        echo "<p><strong>Bill ID:</strong> {$bill['id']} | <strong>Customer:</strong> ".htmlspecialchars($bill['customer_name'])." | <strong>Date:</strong> {$bill['created_at']}</p>";

        // Fetch bill items
        $itemsResult = $conn->query("SELECT * FROM bill_items WHERE bill_id=".$bill['id']);
        echo '<div class="table-wrap"><table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Price (Rs.)</th>
                        <th>Quantity</th>
                        <th>Subtotal (Rs.)</th>
                    </tr>
                </thead><tbody>';

        $total = 0;
        if ($itemsResult->num_rows > 0) {
            while ($item = $itemsResult->fetch_assoc()) {
                $subtotal = $item['price'] * $item['qty']; // ✅ calculate here
                echo "<tr>
                        <td>".htmlspecialchars($item['item_name'])."</td>
                        <td>".number_format($item['price'],2)."</td>
                        <td>{$item['qty']}</td>
                        <td>".number_format($subtotal,2)."</td>
                      </tr>";
                $total += $subtotal;
            }
        }

        $vat = $total * 0.08; // 8% VAT
        $grandTotal = $total + $vat;

        echo "<tr class='total-row'><td colspan='3'>Subtotal</td><td>Rs. ".number_format($total,2)."</td></tr>";
        echo "<tr class='total-row'><td colspan='3'>VAT (8%)</td><td>Rs. ".number_format($vat,2)."</td></tr>";
        echo "<tr class='total-row'><td colspan='3'>Grand Total</td><td>Rs. ".number_format($grandTotal,2)."</td></tr>";

        echo '</tbody></table></div>';
        echo '<p style="text-align:center; margin-top:10px;">💛 Thank you for shopping with us! 💛</p>';
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
