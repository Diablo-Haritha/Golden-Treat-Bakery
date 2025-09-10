<?php
require_once 'config.php';

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getProducts':
            getProducts();
            break;
        case 'addProduct':
            addProduct();
            break;
        case 'updateProduct':
            updateProduct();
            break;
        case 'deleteProduct':
            deleteProduct();
            break;
        case 'getProduct':
            getProduct();
            break;
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

function getProducts() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll();
    
    jsonResponse($products);
}

function getProduct() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        jsonResponse(['error' => 'Product ID is required'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(['error' => 'Product not found'], 404);
    }
    
    jsonResponse($product);
}

function addProduct() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    // Get form data
    $title = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $specialOffer = sanitize_input($_POST['specialOffer'] ?? '');
    
    // Validate required fields
    if (empty($title) || empty($category) || $price <= 0) {
        jsonResponse(['error' => 'Title, category, and price are required'], 400);
    }
    
    // Handle image upload
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
        if (!$imageUrl) {
            jsonResponse(['error' => 'Failed to upload image'], 400);
        }
    }
    
    // Insert product
    $stmt = $pdo->prepare("
        INSERT INTO products (title, category, description, price, special_offer, image_url) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $title, 
        $category, 
        $description, 
        $price, 
        $specialOffer ?: null, 
        $imageUrl
    ]);
    
    $productId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true, 
        'message' => 'Product added successfully',
        'id' => $productId
    ]);
}

function updateProduct() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['error' => 'Product ID is required'], 400);
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $existingProduct = $stmt->fetch();
    
    if (!$existingProduct) {
        jsonResponse(['error' => 'Product not found'], 404);
    }
    
    // Get form data
    $title = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $specialOffer = sanitize_input($_POST['specialOffer'] ?? '');
    
    // Validate required fields
    if (empty($title) || empty($category) || $price <= 0) {
        jsonResponse(['error' => 'Title, category, and price are required'], 400);
    }
    
    // Handle image upload
    $imageUrl = $existingProduct['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $newImageUrl = handleImageUpload($_FILES['image']);
        if ($newImageUrl) {
            // Delete old image file if it exists and is a local file
            if ($imageUrl && file_exists($imageUrl)) {
                unlink($imageUrl);
            }
            $imageUrl = $newImageUrl;
        }
    }
    
    // Update product
    $stmt = $pdo->prepare("
        UPDATE products 
        SET title = ?, category = ?, description = ?, price = ?, special_offer = ?, image_url = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $title, 
        $category, 
        $description, 
        $price, 
        $specialOffer ?: null, 
        $imageUrl,
        $id
    ]);
    
    jsonResponse([
        'success' => true, 
        'message' => 'Product updated successfully'
    ]);
}

function deleteProduct() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['error' => 'Product ID is required'], 400);
    }
    
    // Get product info for image deletion
    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(['error' => 'Product not found'], 404);
    }
    
    // Delete product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    
    // Delete associated image file if it exists and is a local file
    if ($product['image_url'] && file_exists($product['image_url'])) {
        unlink($product['image_url']);
    }
    
    jsonResponse([
        'success' => true, 
        'message' => 'Product deleted successfully'
    ]);
}
?>