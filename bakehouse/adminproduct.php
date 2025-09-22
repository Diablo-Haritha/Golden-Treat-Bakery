<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle add product
if (isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $price = floatval(str_replace(['$', ','], '', $_POST['price']));
    $description = $_POST['description'];
    $quantity = intval($_POST['quantity']);
    $image_path = '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, description, image_path, quantity) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdssi", $name, $price, $description, $image_path, $quantity);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']); // Reload page
    exit();
}

// Handle edit product
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $price = floatval(str_replace(['$', ','], '', $_POST['price']));
    $description = $_POST['description'];
    $quantity = intval($_POST['quantity']);
    $image_path = $_POST['current_image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
            // Optionally delete old image if ($image_path != $_POST['current_image']) unlink($_POST['current_image']);
        }
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, description = ?, image_path = ?, quantity = ? WHERE id = ?");
    $stmt->bind_param("sdssii", $name, $price, $description, $image_path, $quantity, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']); // Reload page
    exit();
}

// Handle delete product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Optionally get image_path and unlink
    $result = $conn->query("SELECT image_path FROM products WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        if ($row['image_path']) {
            // unlink($row['image_path']);
        }
    }
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']); // Reload page
    exit();
}

// Fetch products
$products_result = $conn->query("SELECT * FROM products ORDER BY id ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Righteous&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FFE8B7;
            --primary: #D4AF37;
            --secondary: #8B4513;
            --accent: #FFE5B4;
            --dark: #2C1810;
            --light: #FFF8F0;
            --white: #FFFFFF;
            --gradient-1: linear-gradient(135deg, #D4AF37, #FFE5B4);
            --gradient-2: linear-gradient(135deg, #8B4513, #D2691E);
            --shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
            --shadow-hover: 0 15px 40px rgba(212, 175, 55, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg);
            font-family: 'Righteous', sans-serif;
            color: var(--dark);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

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

        .section {
            padding: 100px 20px;
            max-width: 1200px;
            margin: 0 auto;
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .section h2 {
            font-family: 'Dancing Script', cursive;
            font-size: 3rem;
            text-align: center;
            margin-bottom: 60px;
            color: var(--secondary);
            position: relative;
        }

        .section h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--gradient-1);
            border-radius: 2px;
        }

        .form-container {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-container h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            color: var(--secondary);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--accent);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:invalid,
        .form-group textarea:invalid {
            border-color: #ff4d4d;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            text-align: center;
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn-danger {
            background: #ff4d4d;
        }

        .btn-danger:hover {
            background: #d43f3f;
        }

        .products-table {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-top: 40px;
        }

        .products-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th,
        .products-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--accent);
            font-family: 'Poppins', sans-serif;
        }

        .products-table th {
            background: var(--gradient-1);
            color: var(--white);
        }

        .products-table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 600px;
            width: 90%;
            position: relative;
        }

        .modal-content h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
        }

        #currentImagePreview {
            margin-top: 10px;
        }

        #currentImagePreview img {
            max-width: 100px;
            height: auto;
        }

        @media (max-width: 768px) {
            nav ul { gap: 15px; }
            .section { padding: 60px 20px; }
            .section h2 { font-size: 2.5rem; }
            .form-container { padding: 20px; }
            .products-table { overflow-x: auto; }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <nav>
        <ul>
            
            <li><a href="product.php">Products</a></li>
            <li><a href="logout.php">Logout</a></li>
            <li><a href="login2formanage.php">Login</a></li>
        </ul>
    </nav>

    <section class="section" id="admin">
        <h2>Admin Panel</h2>
        <div class="form-container">
            <h3>Add New Product</h3>
            <form id="productForm" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (Rs)</label>
                    <input type="text" id="price" name="price" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Product Image (optional)</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity in Stock</label>
                    <input type="number" id="quantity" name="quantity" min="0" required>
                </div>
                <button type="submit" name="add_product" class="btn">Add Product</button>
            </form>
        </div>

        <div class="products-table">
            <h3>Existing Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Image</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTable">
                    <?php while ($row = $products_result->fetch_assoc()): ?>
                    <tr data-id="<?php echo $row['id']; ?>"
                        data-name="<?php echo htmlspecialchars($row['name']); ?>"
                        data-price="<?php echo $row['price']; ?>"
                        data-description="<?php echo htmlspecialchars($row['description']); ?>"
                        data-image_path="<?php echo htmlspecialchars($row['image_path']); ?>"
                        data-quantity="<?php echo $row['quantity']; ?>">
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>$<?php echo number_format($row['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo $row['image_path'] ? '<img src="' . htmlspecialchars($row['image_path']) . '" alt="' . htmlspecialchars($row['name']) . '">' : 'No Image'; ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td>
                            <button class="btn edit-btn">Edit</button>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-btn" id="closeModal">&times;</button>
            <h3>Edit Product</h3>
            <form id="editProductForm" method="post" enctype="multipart/form-data">
                <input type="hidden" id="editId" name="id">
                <input type="hidden" id="editCurrentImage" name="current_image">
                <div class="form-group">
                    <label for="editName">Product Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editPrice">Price ($)</label>
                    <input type="text" id="editPrice" name="price" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="editImage">Product Image (optional)</label>
                    <input type="file" id="editImage" name="image" accept="image/*">
                    <div id="currentImagePreview"></div>
                </div>
                <div class="form-group">
                    <label for="editQuantity">Quantity in Stock</label>
                    <input type="number" id="editQuantity" name="quantity" min="0" required>
                </div>
                <button type="submit" name="edit_product" class="btn">Update Product</button>
            </form>
        </div>
    </div>

    <script>
        // Scroll animations
        function animateOnScroll() {
            document.querySelectorAll('.section').forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) section.classList.add('show');
            });
        }

        // Nav scroll effect
        function updateNav() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 100) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }

        // Edit product - show modal
        document.getElementById('productsTable').addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-btn')) {
                const row = e.target.closest('tr');
                document.getElementById('editId').value = row.getAttribute('data-id');
                document.getElementById('editName').value = row.getAttribute('data-name');
                document.getElementById('editPrice').value = row.getAttribute('data-price');
                document.getElementById('editDescription').value = row.getAttribute('data-description');
                document.getElementById('editQuantity').value = row.getAttribute('data-quantity');
                const imagePath = row.getAttribute('data-image_path');
                document.getElementById('editCurrentImage').value = imagePath;
                document.getElementById('currentImagePreview').innerHTML = imagePath ? `<img src="${imagePath}" alt="Current Image">` : 'No Image';
                document.getElementById('editModal').classList.add('show');
            }
        });

        // Close modal
        document.getElementById('closeModal').addEventListener('click', function() {
            document.getElementById('editModal').classList.remove('show');
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            animateOnScroll();
            window.addEventListener('scroll', () => {
                animateOnScroll();
                updateNav();
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>