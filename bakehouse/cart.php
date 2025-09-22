<?php
// cart.php - Unified cart backend + simple frontend UI
// Drop-in replacement for your existing cart.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Ensure guest temp id
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['temp_user_id'] = uniqid('guest_', true);
}
$user_id = $_SESSION['temp_user_id'];

// DB connection (adjust creds if needed)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // If AJAX request, return JSON; otherwise show minimal HTML error
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'DB connection failed: ' . $conn->connect_error]);
        exit;
    } else {
        die('DB connection failed: ' . $conn->connect_error);
    }
}
$conn->set_charset("utf8mb4");

// Helper: fetch raw input JSON or fallback to $_POST
function get_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (is_array($data)) return $data;
    // fallback to form-encoded
    return $_POST ?: [];
}

// Helper: cart summary
function getCartSummary($conn, $user_id) {
    $items = [];
    $total = 0;
    $item_count = 0;

    $sql = "SELECT c.product_id, c.quantity AS qty, p.name, p.price, p.image_path, p.quantity AS stock
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
            'line_total' => $line_total,
            'stock' => intval($row['stock']),
            'image' => $row['image_path'] ?: null,
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

// If AJAX action requested, handle and return JSON
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    try {
        if ($action === 'list_products') {
            $sql = "SELECT id, name, description, price, quantity, image_path FROM products ORDER BY name";
            $res = $conn->query($sql);
            $products = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) $products[] = $r;
            }
            echo json_encode(['ok' => true, 'products' => $products]);
            exit;
        }

        if ($action === 'add_to_cart') {
            $data = get_json_input();
            $product_id = isset($data['product_id']) ? intval($data['product_id']) : (isset($data['id']) ? intval($data['id']) : 0);
            // accept 'qty' or 'quantity'
            $qty = isset($data['qty']) ? intval($data['qty']) : (isset($data['quantity']) ? intval($data['quantity']) : 1);
            if ($product_id <= 0) {
                echo json_encode(['ok'=>false, 'msg'=>'Invalid product id']);
                exit;
            }
            if ($qty < 1) {
                echo json_encode(['ok'=>false, 'msg'=>'Quantity must be at least 1']);
                exit;
            }

            // fetch product & stock
            $stmt = $conn->prepare("SELECT id, name, price, quantity FROM products WHERE id = ?");
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

            // Check existing cart qty
            $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $user_id, $product_id);
                $stmt->execute();
                $r = $stmt->get_result();
                $current_qty = 0;
                if ($r && $r->num_rows > 0) {
                    $current_qty = intval($r->fetch_assoc()['quantity']);
                }
                $stmt->close();
            } else {
                $current_qty = 0;
            }

            $new_qty = $current_qty + $qty;
            if ($new_qty > $stock) {
                echo json_encode(['ok'=>false, 'msg'=>"Only {$stock} items available. You already have {$current_qty} in cart."]);
                exit;
            }

            // Insert / update within transaction
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
                    'product' => ['id' => $product['id'], 'name' => $product['name'], 'price' => floatval($product['price'])]
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['ok'=>false, 'msg'=>'Transaction failed: '.$e->getMessage()]);
            }
            exit;
        }

        if ($action === 'get_cart') {
            $cart_data = getCartSummary($conn, $user_id);
            echo json_encode(['ok'=>true, 'cart' => $cart_data, 'user_id' => $user_id]);
            exit;
        }

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

            // Check stock for product
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
                    // Try update
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    if (!$stmt) throw new Exception('DB prepare failed: '.$conn->error);
                    $stmt->bind_param('isi', $qty, $user_id, $product_id);
                    $stmt->execute();
                    // If no rows updated, insert new
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

// If no action provided, render simple cart HTML page (the UI)
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cart - Golden Treat</title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;background:#fff8e6;color:#2c1810;padding:20px}

    .wrap{max-width:980px;margin:0 auto}
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

 

    /* ✅ Move your nav styles here */
    nav {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border-radius: 50px;
        padding: 10px 30px;
        box-shadow: var(--shadow);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    nav.scrolled {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: var(--shadow-hover);
    }

    nav ul {
        display: flex;
        list-style: none;
        gap: 30px;
        align-items: center;
    }

    nav a {
        text-decoration: none;
        color: var(--dark);
        font-weight: 500;
        transition: all 0.3s ease;
        position: relative;
    }

    nav a:hover {
        color: var(--primary);
        transform: translateY(-2px);
    }

    nav a::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--gradient-1);
        transition: width 0.3s ease;
    }

    nav a:hover::after {
        width: 100%;
    }
</style>
</head>
<body>

 <nav>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="product2.php">Products</a></li>
            <li><a href="untitled-1.php">Table booking</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="profile.php">Orders</a></li>
        </ul>
    </nav>
<div class="wrap">
    <h1>Your Cart</h1>
    <div id="cartContainer">
        <div class="cart-empty">Loading cart...</div>
    </div>
    <p class="small">Tip: Click "Add to Cart" on the products page to add items here. This cart uses a session-based guest id.</p>
</div>

<script>
const api = async (action, opts = {}) => {
    const url = `?action=${action}`;
    const defaultOpts = { credentials: 'same-origin' };
    try {
        const res = await fetch(url, { ...defaultOpts, ...opts });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
    } catch (e) {
        console.error('API error', e);
        throw e;
    }
};

function formatPrice(num) {
    return Number(num).toFixed(2);
}

async function loadCart() {
    const container = document.getElementById('cartContainer');
    container.innerHTML = '<div class="cart-empty">Loading cart...</div>';
    try {
        const data = await api('get_cart');
        if (!data.ok) throw new Error(data.msg || 'Failed to load cart');
        const cart = data.cart;
        if (!cart.items || cart.items.length === 0) {
            container.innerHTML = `<div class="cart-empty">
                <div style="font-size:48px">🛒</div>
                <p>Your cart is empty.</p>
                <p><a href="product.php">Continue shopping</a></p>
            </div>`;
            return;
        }

        // build cart UI
        const itemsDiv = document.createElement('div');
        itemsDiv.className = 'cart-items';
        let total = 0;
        cart.items.forEach(item => {
            total += item.line_total;
            const row = document.createElement('div');
            row.className = 'cart-item';

            const imgWrap = document.createElement('div');
            if (item.image) {
                const img = document.createElement('img');
                img.src = item.image;
                img.alt = item.name;
                imgWrap.appendChild(img);
            } else {
                imgWrap.textContent = item.emoji || '🍰';
                imgWrap.style.fontSize = '40px';
            }

            const info = document.createElement('div');
            info.className = 'info';
            info.innerHTML = `<strong>${item.name}</strong><div class="small">Price: Rs ${formatPrice(item.price)}</div>
                              <div class="small">Stock: ${item.stock}</div>`;

            const controls = document.createElement('div');
            controls.className = 'controls';
            const dec = document.createElement('button');
            dec.textContent = '-';
            dec.onclick = () => updateQty(item.id, Math.max(0, item.qty - 1));
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.className = 'qty';
            qtyInput.value = item.qty;
            qtyInput.min = 0;
            qtyInput.max = item.stock || 999;
            qtyInput.onchange = (e) => {
                let v = parseInt(e.target.value) || 0;
                if (v < 0) v = 0;
                if (v > (item.stock || 999)) v = item.stock || 999;
                updateQty(item.id, v);
            };
            const inc = document.createElement('button');
            inc.textContent = '+';
            inc.onclick = () => updateQty(item.id, Math.min((item.stock || 999), item.qty + 1));
            const remove = document.createElement('button');
            remove.textContent = 'Remove';
            remove.className = 'ghost';
            remove.onclick = () => {
                if (confirm('Remove item from cart?')) updateQty(item.id, 0);
            };

            const subtotal = document.createElement('div');
            subtotal.className = 'small';
            subtotal.style.marginLeft = '12px';
            subtotal.innerHTML = `<strong>Subtotal:</strong> Rs ${formatPrice(item.line_total)}`;

            controls.appendChild(dec);
            controls.appendChild(qtyInput);
            controls.appendChild(inc);
            controls.appendChild(remove);
            controls.appendChild(subtotal);

            row.appendChild(imgWrap);
            row.appendChild(info);
            row.appendChild(controls);

            itemsDiv.appendChild(row);
        });

        // summary
        const summary = document.createElement('div');
        summary.className = 'cart-summary';
        summary.innerHTML = `<div><strong>Total:</strong> Rs ${formatPrice(cart.total)}</div>
                             <div style="margin-top:8px">
                               <button onclick="location.href='product.php'">Continue shopping</button>
                               <button onclick="checkout()" style="margin-left:8px">Checkout</button>
                               <button onclick="clearCart()" style="margin-left:8px;background:#8b4513">Clear cart</button>
                             </div>`;

        container.innerHTML = '';
        container.appendChild(itemsDiv);
        container.appendChild(summary);

    } catch (err) {
        container.innerHTML = `<div class="cart-empty">Error loading cart: ${err.message || err}</div>`;
    }
}

async function updateQty(productId, qty) {
    try {
        await api('set_cart_qty', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: productId, qty: qty })
        });
        await loadCart();
    } catch (e) {
        alert('Update failed: ' + (e.message || e));
    }
}

async function clearCart() {
    if (!confirm('Clear entire cart?')) return;
    try {
        await api('clear_cart');
        await loadCart();
    } catch (e) {
        alert('Clear failed: ' + (e.message || e));
    }
}

function checkout() {
    alert('Proceed to checkout (not implemented).');
    // implement checkout flow here
}

// Load on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    loadCart();
});
</script>
</body>
</html>
<?php
$conn->close();
?>
