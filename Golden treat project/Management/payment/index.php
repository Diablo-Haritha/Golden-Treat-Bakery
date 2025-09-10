<?php
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bakehouse";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_name = $_POST['customer_name'];
    $created_at    = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO bills (customer_name, created_at) VALUES (?, ?)");
    $stmt->bind_param("ss", $customer_name, $created_at);
    $stmt->execute();
    $bill_id = $stmt->insert_id;
    $stmt->close();

    if (!empty($_POST['item_name'])) {
        for ($i = 0; $i < count($_POST['item_name']); $i++) {
            $name = $_POST['item_name'][$i];
            $price = floatval($_POST['item_price'][$i]);
            $qty = intval($_POST['item_qty'][$i]);
            if ($name != "" && $price > 0 && $qty > 0) {
                $stmt = $conn->prepare("INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES (?,?,?,?)");
                $stmt->bind_param("isdi", $bill_id, $name, $price, $qty);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    header("Location: save_bill.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Create New Bill</title>
  <link rel="stylesheet" href="style1.css">
 <style></style>
  <script>
    function addRow() {
      let table = document.getElementById("itemsTable");
      let row = table.insertRow();
      row.innerHTML = `
        <td><input type="text" name="item_name[]" placeholder="Item name" required></td>
        <td><input type="number" name="item_price[]" step="0.01" placeholder="0.00" required></td>
        <td><input type="number" name="item_qty[]" min="1" placeholder="1" required></td>
    `;
    }

    function calculateTotal() {
      let rows = document.querySelectorAll("#itemsTable tr");
      let subtotal = 0;
      rows.forEach((row, index) => {
        if (index === 0) return; // skip header row
        let price = row.querySelector('input[name="item_price[]"]')?.value || 0;
        let qty = row.querySelector('input[name="item_qty[]"]')?.value || 0;
        subtotal += (parseFloat(price) * parseInt(qty)) || 0;
      });
      let vat = subtotal * 0.08;
      let grand = subtotal + vat;

      document.getElementById("subtotal").innerText = subtotal.toFixed(2);
      document.getElementById("vat").innerText = vat.toFixed(2);
      document.getElementById("grandtotal").innerText = grand.toFixed(2);
    }

    // recalc when typing
    document.addEventListener("input", function (e) {
      if (e.target.name === "item_price[]" || e.target.name === "item_qty[]") {
        calculateTotal();
      }
    });

  </script>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Payment Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
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
      <h1>Payment Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Payment</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn active" onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='save_bill.php'">All Bills</button>
          <button class="tab-btn " onclick="window.location.href='bill_edit.php'">Sales Analysis</button>

        </div>
      </nav>
    </aside>
    <div class="container">
      <h1>🧾 Create New Bill</h1>
      <div class="totals-box">
        <p><strong>Subtotal:</strong> Rs. <span id="subtotal">0.00</span></p>
        <p><strong>VAT (8%):</strong> Rs. <span id="vat">0.00</span></p>
        <p><strong>Grand Total:</strong> Rs. <span id="grandtotal">0.00</span></p>
      </div>

      <form method="POST">
        <label>Customer Name</label>
        <input type="text" name="customer_name" placeholder="Enter customer name" required>

        <h3 style="margin-top:20px; color:#2c7a7b;">Bill Items</h3>
        <table id="itemsTable">
          <tr>
            <th>Item Name</th>
            <th>Price (Rs.)</th>
            <th>Quantity</th>
          </tr>
          <tr>
            <td><input type="text" name="item_name[]" placeholder="Item name" required></td>
            <td><input type="number" name="item_price[]" step="0.01" placeholder="0.00" required></td>
            <td><input type="number" name="item_qty[]" min="1" placeholder="1" required></td>
          </tr>
        </table>
        <button type="button" class="add-btn" onclick="addRow()">➕ </button>


        <button type="submit" class="save-btn">💾 </button>
      </form>
    </div>
</body>

</html>