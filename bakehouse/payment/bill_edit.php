<?php
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bakehouse";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// Handle update POST
if(isset($_POST['update_bill'])){
    $bill_id = intval($_POST['bill_id']);
    $customer_name = $_POST['customer_name'];
    $store_name = $_POST['store_name'];
    $store_tel = $_POST['store_tel'];
    $store_email = $_POST['store_email'];
    $store_address = $_POST['store_address'];
    $store_slogan = $_POST['store_slogan'];
    $discount = floatval($_POST['discount']); // manual discount
    $today_special = floatval($_POST['today_special']); // percentage discount
    $items = json_decode($_POST['items'], true);

    // Calculate totals
    $subtotal = 0;
    foreach($items as $item) $subtotal += $item['subtotal'];
    $vat = $subtotal * 0.08;

    // Apply today's special discount on subtotal
    $special_discount = ($subtotal * $today_special / 100);
    $grand_total = $subtotal + $vat - $discount - $special_discount;

    // Update bill header
    $stmt = $conn->prepare("UPDATE bills SET customer_name=?, total=?, subtotal=?, vat=?, discount=?, today_special=?, store_name=?, store_tel=?, store_email=?, store_address=?, store_slogan=? WHERE id=?");
    $stmt->bind_param("sddddddsssssi", $customer_name, $grand_total, $subtotal, $vat, $discount, $today_special, $store_name, $store_tel, $store_email, $store_address, $store_slogan, $bill_id);
    $stmt->execute();
    $stmt->close();

    // Delete old items
    $conn->query("DELETE FROM bill_items WHERE bill_id=$bill_id");

    // Insert new items
    $stmt = $conn->prepare("INSERT INTO bill_items (bill_id, item_name, price, qty, subtotal) VALUES (?, ?, ?, ?, ?)");
    foreach($items as $item){
        $stmt->bind_param("isidd", $bill_id, $item['item_name'], $item['price'], $item['qty'], $item['subtotal']);
        $stmt->execute();
    }
    $stmt->close();

    header("Location: admin_bills.php");
    exit;
}

// Fetch bill to edit
$bill_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$bill = $conn->query("SELECT * FROM bills WHERE id=$bill_id")->fetch_assoc();
$bill_items = $conn->query("SELECT * FROM bill_items WHERE bill_id=$bill_id")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Bill #<?= $bill_id ?></title>
<style>
body { font-family: Arial, sans-serif; background:#f4f4f4; padding:20px; }
.container { max-width:900px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1);}
h2 { color:#30b6a2; text-align:center; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:8px; border:1px solid #ccc; text-align:left; }
th { background:#30b6a2; color:#fff; }
input[type="text"], input[type="number"], input[type="email"] { width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; }
button { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; }
.add-btn { background:#30b6a2; color:#fff; margin-top:10px; }
.remove-btn { background:#ff4d4f; color:#fff; }
.save-btn { background:#28a28c; color:#fff; width:100%; margin-top:20px; padding:10px; font-size:16px; }
.total-display { text-align:right; margin-top:10px; font-weight:bold; }
</style>
<script>
function addItemRow(){
    const table = document.getElementById('items-table').getElementsByTagName('tbody')[0];
    const row = table.insertRow();
    row.innerHTML = `
        <td><input type="text" class="item_name" placeholder="Item Name"></td>
        <td><input type="number" class="price" value="0" oninput="updateSubtotal(this)"></td>
        <td><input type="number" class="qty" value="1" oninput="updateSubtotal(this)"></td>
        <td><input type="number" class="subtotal" value="0" readonly></td>
        <td><button type="button" class="remove-btn" onclick="removeRow(this)">❌</button></td>
    `;
}
function removeRow(btn){ btn.closest('tr').remove(); updateTotal(); }
function updateSubtotal(elem){
    const row = elem.closest('tr');
    const price = parseFloat(row.querySelector('.price').value);
    const qty = parseFloat(row.querySelector('.qty').value);
    row.querySelector('.subtotal').value = (price * qty).toFixed(2);
    updateTotal();
}
function updateTotal(){
    const rows = document.querySelectorAll('#items-table tbody tr');
    let subtotal = 0;
    rows.forEach(r => subtotal += parseFloat(r.querySelector('.subtotal').value) || 0);
    let discount = parseFloat(document.getElementById('discount').value) || 0;
    let today_special = parseFloat(document.getElementById('today_special').value) || 0;
    let special_discount = subtotal * today_special / 100;
    let vat = subtotal * 0.08;
    let grandtotal = subtotal + vat - discount - special_discount;
    document.getElementById('subtotal_display').innerText = subtotal.toFixed(2);
    document.getElementById('vat_display').innerText = vat.toFixed(2);
    document.getElementById('special_display').innerText = special_discount.toFixed(2);
    document.getElementById('grandtotal_display').innerText = grandtotal.toFixed(2);
}
function submitForm(){
    const rows = document.querySelectorAll('#items-table tbody tr');
    const items = [];
    rows.forEach(r=>{
        const name = r.querySelector('.item_name').value;
        const price = parseFloat(r.querySelector('.price').value) || 0;
        const qty = parseFloat(r.querySelector('.qty').value) || 0;
        const subtotal = parseFloat(r.querySelector('.subtotal').value) || 0;
        if(name) items.push({item_name:name, price:price, qty:qty, subtotal:subtotal});
    });
    document.getElementById('items_input').value = JSON.stringify(items);
    document.getElementById('billForm').submit();
}
</script>
</head>
<body>
<div class="container">
<h2>Edit Bill #<?= $bill_id ?></h2>
<form id="billForm" method="post">
<input type="hidden" name="bill_id" value="<?= $bill_id ?>">
<input type="hidden" name="items" id="items_input">

<h3>Store Details</h3>
<p>Store Name: <input type="text" name="store_name" value="<?= htmlspecialchars($bill['store_name']??'Golden Treat') ?>"></p>
<p>Tel: <input type="text" name="store_tel" value="<?= htmlspecialchars($bill['store_tel']??'00000000') ?>"></p>
<p>Email: <input type="email" name="store_email" value="<?= htmlspecialchars($bill['store_email']??'gol@gmail.com') ?>"></p>
<p>Address: <input type="text" name="store_address" value="<?= htmlspecialchars($bill['store_address']??'Adurkku Vidiya, Jampata Street') ?>"></p>
<p>Slogan: <input type="text" name="store_slogan" value="<?= htmlspecialchars($bill['store_slogan']??'💛 Thank you for shopping with us! 💛') ?>"></p>

<h3>Customer Details</h3>
<p>Customer Name: <input type="text" name="customer_name" value="<?= htmlspecialchars($bill['customer_name']) ?>" required></p>

<h3>Bill Items</h3>
<table id="items-table">
<thead><tr><th>Item Name</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($bill_items as $item): ?>
<tr>
<td><input type="text" class="item_name" value="<?= htmlspecialchars($item['item_name']) ?>"></td>
<td><input type="number" class="price" value="<?= $item['price'] ?>" oninput="updateSubtotal(this)"></td>
<td><input type="number" class="qty" value="<?= $item['qty'] ?>" oninput="updateSubtotal(this)"></td>
<td><input type="number" class="subtotal" value="<?= $item['subtotal'] ?>" readonly></td>
<td><button type="button" class="remove-btn" onclick="removeRow(this)">❌</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<button type="button" class="add-btn" onclick="addItemRow()">➕ Add Item</button>

<h3>Discounts</h3>
<p>Manual Discount (Rs.): <input type="number" id="discount" name="discount" value="<?= $bill['discount']??0 ?>" oninput="updateTotal()"></p>
<p>Today's Special Discount (%): <input type="number" id="today_special" name="today_special" value="<?= $bill['today_special']??0 ?>" oninput="updateTotal()"></p>

<div class="total-display">
Subtotal: Rs. <span id="subtotal_display">0</span> | VAT (8%): Rs. <span id="vat_display">0</span> | Special Discount: Rs. <span id="special_display">0</span> | Grand Total: Rs. <span id="grandtotal_display">0</span>
</div>

<button type="button" class="save-btn" onclick="submitForm()">💾 Save Bill</button>
</form>
</div>
<script>updateTotal();</script>
</body>
</html>
