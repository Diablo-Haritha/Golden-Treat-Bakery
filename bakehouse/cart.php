<?php
// cart.php - adapted to your golden_treat DB schema (products.image, cart.user_id varchar)
// Put this file in your project (replace existing cart.php). Adjust DB creds if needed.

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Use session-based guest id (stored as string - matches cart.user_id varchar(100))
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['temp_user_id'] = uniqid('guest_', true);
}
$user_id = $_SESSION['temp_user_id'];

// DB connection - update credentials as necessary
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'DB connection failed: ' . $conn->connect_error]);
        exit;
    } else {
        die('DB connection failed: ' . $conn->connect_error);
    }
}
$conn->set_charset("utf8mb4");

// Read JSON body or fallback to $_POST
function get_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (is_array($data)) return $data;
    return $_POST ?: [];
}

// Summarize cart for a given user_id
function getCartSummary($conn, $user_id) {
    $items = [];
    $total = 0;
    $item_count = 0;

    $sql = "SELECT c.product_id, c.quantity AS qty, p.name, p.price, p.image, p.quantity AS stock
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
            ORDER BY c.id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['items'=>[], 'total'=>0, 'item_count'=>0];
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $line_total = floatval($row['price']) * intval($row['qty']);
        $items[] = [
            'id' => intval($row['product_id']),
            'name' => $row['name'],
            'qty' => intval($row['qty']),
            'price' => floatval($row['price']),
            'line_total' => round($line_total, 2),
            'stock' => intval($row['stock']),
            'image' => $row['image'] ?: null,
            'emoji' => '🍰'
        ];
        $total += $line_total;
        $item_count += intval($row['qty']);
    }
    $stmt->close();

    return [
        'items' => $items,
        'total' => round($total, 2),
        'item_count' => $item_count,
        'user_id' => $user_id
    ];
}

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    try {
        // list products (useful for product listing pages)
        if ($action === 'list_products') {
            $sql = "SELECT id, name, description, price, quantity, image FROM products ORDER BY name";
            $res = $conn->query($sql);
            $products = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) $products[] = $r;
            }
            echo json_encode(['ok' => true, 'products' => $products]);
            exit;
        }

        // add item(s) to cart
        if ($action === 'add_to_cart') {
            $data = get_json_input();
            $product_id = isset($data['product_id']) ? intval($data['product_id']) : (isset($data['id']) ? intval($data['id']) : 0);
            $qty = isset($data['qty']) ? intval($data['qty']) : (isset($data['quantity']) ? intval($data['quantity']) : 1);

            if ($product_id <= 0) {
                echo json_encode(['ok'=>false, 'msg'=>'Invalid product id']);
                exit;
            }
            if ($qty < 1) {
                echo json_encode(['ok'=>false, 'msg'=>'Quantity must be at least 1']);
                exit;
            }

            // fetch product & stock (products.image, products.quantity)
            $stmt = $conn->prepare("SELECT id, name, price, quantity, image FROM products WHERE id = ?");
            if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'DB error: '.$conn->error]); exit; }
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) { $stmt->close(); echo json_encode(['ok'=>false,'msg'=>'Product not found']); exit; }
            $product = $res->fetch_assoc();
            $stock = intval($product['quantity']);
            $stmt->close();

            if ($stock <= 0) {
                echo json_encode(['ok'=>false, 'msg'=>'Product is out of stock']);
                exit;
            }

            // Check existing cart qty for this user/product
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $current_qty = 0;
            if ($stmt) {
                $stmt->bind_param('si', $user_id, $product_id);
                $stmt->execute();
                $r = $stmt->get_result();
                if ($r && $r->num_rows > 0) {
                    $current_qty = intval($r->fetch_assoc()['quantity']);
                }
                $stmt->close();
            }

            $new_qty = $current_qty + $qty;
            if ($new_qty > $stock) {
                echo json_encode(['ok'=>false, 'msg'=>"Only {$stock} items available. You already have {$current_qty} in cart."]);
                exit;
            }

            // Insert or update cart inside transaction
            $conn->begin_transaction();
            try {
                if ($current_qty > 0) {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                    $stmt->bind_param('isi', $new_qty, $user_id, $product_id);
                    if (!$stmt->execute()) throw new Exception('Failed update cart: '.$stmt->error);
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                    $stmt->bind_param('sii', $user_id, $product_id, $qty);
                    if (!$stmt->execute()) throw new Exception('Failed insert cart: '.$stmt->error);
                    $stmt->close();
                }

                $conn->commit();

                $cart_data = getCartSummary($conn, $user_id);
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Added to cart',
                    'cart' => $cart_data,
                    'product' => ['id' => intval($product['id']), 'name' => $product['name'], 'price' => floatval($product['price']), 'image' => $product['image']]
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['ok'=>false, 'msg'=>'Transaction failed: '.$e->getMessage()]);
            }
            exit;
        }

        // return current cart
        if ($action === 'get_cart') {
            $cart_data = getCartSummary($conn, $user_id);
            echo json_encode(['ok'=>true, 'cart' => $cart_data, 'user_id' => $user_id]);
            exit;
        }

        // set cart quantity (update or delete if qty = 0)
        if ($action === 'set_cart_qty') {
            $data = get_json_input();
            $product_id = isset($data['id']) ? intval($data['id']) : (isset($data['product_id']) ? intval($data['product_id']) : 0);
            $qty = isset($data['qty']) ? intval($data['qty']) : (isset($data['quantity']) ? intval($data['quantity']) : null);
            if ($product_id <= 0 || $qty === null) {
                echo json_encode(['ok'=>false,'msg'=>'Invalid input']);
                exit;
            }
            if ($qty < 0) {
                echo json_encode(['ok'=>false,'msg'=>'Quantity cannot be negative']);
                exit;
            }

            // Check product stock
            $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'DB error: '.$conn->error]); exit; }
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) { $stmt->close(); echo json_encode(['ok'=>false,'msg'=>'Product not found']); exit; }
            $stock = intval($res->fetch_assoc()['quantity']);
            $stmt->close();

            if ($qty > $stock) {
                echo json_encode(['ok'=>false,'msg'=>"Only {$stock} items available in stock"]);
                exit;
            }

            $conn->begin_transaction();
            try {
                if ($qty === 0) {
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                    $stmt->bind_param('si', $user_id, $product_id);
                    if (!$stmt->execute()) throw new Exception('Failed delete: '.$stmt->error);
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                    $stmt->bind_param('isi', $qty, $user_id, $product_id);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                        $stmt->bind_param('sii', $user_id, $product_id, $qty);
                        if (!$stmt->execute()) throw new Exception('Failed insert: '.$stmt->error);
                        $stmt->close();
                    } else {
                        $stmt->close();
                    }
                }

                $conn->commit();
                $cart_data = getCartSummary($conn, $user_id);
                echo json_encode(['ok'=>true, 'cart' => $cart_data]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['ok'=>false, 'msg'=>'Transaction failed: '.$e->getMessage()]);
            }
            exit;
        }

        // clear cart for this user
        if ($action === 'clear_cart') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                $stmt->bind_param('s', $user_id);
                if (!$stmt->execute()) throw new Exception('Failed to clear cart: '.$stmt->error);
                $stmt->close();
                $conn->commit();
                $cart_data = getCartSummary($conn, $user_id);
                echo json_encode(['ok'=>true, 'cart'=>$cart_data, 'msg'=>'Cart cleared']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
            }
            exit;
        }

        // unknown action
        echo json_encode(['ok'=>false, 'msg'=>'Unknown action']);
        exit;

    } catch (Exception $e) {
        echo json_encode(['ok'=>false, 'msg'=>'Server error: '.$e->getMessage()]);
        exit;
    }
}

// No action => render cart page with front-end JS that calls the endpoints above
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cart - Golden Treat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
 integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
<link rel="stylesheet" href="css/style.css">
<style>
    body{font-family:Arial,Helvetica,sans-serif;background:#fff8e6;color:#2c1810;padding:20px}
    .wrap{max-width:980px;margin:80px auto 0}
    h1{font-family: 'Poppins', sans-serif;color:#8b4513}
    .cart-empty{padding:40px;border:2px dashed #ffdca8;border-radius:10px;text-align:center;background:#fffdf7}
    .cart-items{display:flex;flex-direction:column;gap:12px;margin-top:20px}
    .cart-item{display:flex;gap:12px;align-items:center;padding:12px;background:#fffaf0;border-radius:8px;border-left:4px solid #d4af37}
    .cart-item img{width:84px;height:84px;object-fit:cover;border-radius:6px}
    .cart-item .info{flex:1}
    .cart-item .controls{display:flex;gap:8px;align-items:center}
    button{cursor:pointer;padding:8px 12px;border-radius:8px;border:0;background:#d4af37;color:#fff;font-weight:600}
    button.ghost{background:#8b4513}
    .cart-summary{margin-top:20px;padding:16px;background:#fffaf0;border-radius:10px;border:1px solid #ffe8c4;text-align:right}
    input.qty{width:60px;padding:6px;border-radius:6px;border:1px solid #ffdca8}
    .small{font-size:0.9rem;color:#6b4b3a}
    #sub-pages { max-width:980px;margin:20px auto; text-align:center }
    .subpages_box { display:flex; gap:12px; justify-content:center; }
    .subpage-btn { background:#f7c07b; color:#fff; padding:8px 12px; border-radius:6px; text-decoration:none; }
</style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+N5gGL7jI1QKNpa+5aY3E9oUsggR2" crossorigin="anonymous"></script>

<div id="navbar-placeholder"></div>

<section id="sub-pages">
  <div class="subpages_box">
    <a href="order_edit.php" class="btn subpage-btn">Edit Orders</a>
    <a href="order_tracking.php" class="btn subpage-btn">Track Order</a>
    <a href="order_history.php" class="btn subpage-btn">History</a>
  </div>
</section>

<div class="wrap">
    <h1>Your Cart</h1>
    <div id="cartContainer">
        <div class="cart-empty">Loading cart...</div>
    </div>
    <p class="small">Tip: Click \"Add to Cart\" on the existing products page to add items here. This cart uses a session-based guest id.</p>
</div>

<div id="footer-placeholder"></div>
<script src="js/script.js"></script>
</body>
</html>