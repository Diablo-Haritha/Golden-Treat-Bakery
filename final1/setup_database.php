<?php
// Standalone database setup - don't require config.php yet
$host = 'localhost';
$dbname = 'golden_treat_bakery';
$username = 'root';
$password = '';

echo "<h2>Setting up Golden Treat Bakery Database</h2>";

try {
    // Create connection WITHOUT specifying database first
    $pdo_temp = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "<p>✓ Database '$dbname' created/verified</p>";
    
    // Use the database
    $pdo_temp->exec("USE `$dbname`");
    
    // Create products table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `products` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `category` enum('food','cake') NOT NULL,
        `description` text,
        `price` decimal(10,2) NOT NULL,
        `special_offer` varchar(255) DEFAULT NULL,
        `image_url` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo_temp->exec($createTableSQL);
    echo "<p>✓ Products table created/verified</p>";
    
    // Check if table has data, if not insert sample data
    $stmt = $pdo_temp->query("SELECT COUNT(*) as count FROM products");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $insertSampleData = "
        INSERT INTO `products` (`title`, `category`, `description`, `price`, `special_offer`, `image_url`) VALUES
        ('Premium Chicken Fried Rice', 'food', 'Exquisite fried rice featuring tender chicken, fresh vegetables, and aromatic spices, masterfully prepared.', 8.99, '10% off today', 'https://cdn.pixabay.com/photo/2017/09/02/13/16/fried-rice-2703294_1280.jpg'),
        ('Decadent Chocolate Cake', 'cake', 'Rich, moist chocolate cake layered with velvety ganache and premium cocoa, a true indulgence.', 15.00, NULL, 'https://cdn.pixabay.com/photo/2017/01/20/00/30/chocolate-1991266_1280.jpg'),
        ('Classic Vanilla Delight', 'cake', 'Elegant vanilla sponge cake with smooth Swiss meringue buttercream, crafted with Madagascar vanilla.', 14.50, NULL, 'https://cdn.pixabay.com/photo/2016/03/05/20/07/cakes-1238127_1280.jpg'),
        ('Signature Vegetable Kottu', 'food', 'Traditional chopped roti blended with premium vegetables, fragrant spices, and chef special blend.', 7.50, NULL, 'https://cdn.pixabay.com/photo/2019/04/06/09/29/kottu-4107304_1280.jpg'),
        ('Artisanal Butter Buns', 'food', 'Fluffy handcrafted buns filled with premium European butter, baked to golden perfection.', 2.99, NULL, 'https://cdn.pixabay.com/photo/2018/05/22/21/52/bread-3428528_1280.jpg');
        ";
        
        $pdo_temp->exec($insertSampleData);
        echo "<p>✓ Sample data inserted</p>";
    } else {
        echo "<p>✓ Products table already contains data (" . $result['count'] . " records)</p>";
    }
    
    echo "<h3 style='color: green;'>✓ Database setup completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='admin1.php' target='_blank'>Open Admin Panel</a> - Manage products</li>";
    echo "<li><a href='p.php' target='_blank'>Open Customer Page</a> - View products</li>";
    echo "</ul>";
    
} catch(PDOException $e) {
    echo "<h3 style='color: red;'>✗ Error: " . $e->getMessage() . "</h3>";
    echo "<p><strong>Make sure:</strong></p>";
    echo "<ul>";
    echo "<li>XAMPP/WAMP is running</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>Database credentials in config.php are correct</li>";
    echo "</ul>";
}
?>