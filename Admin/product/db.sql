-- Create database
CREATE DATABASE golden_treat_bakery;
USE golden_treat_bakery;

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255) DEFAULT 'default.jpg',
    category VARCHAR(100),
    is_daily_special BOOLEAN DEFAULT 0,
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    visibility BOOLEAN DEFAULT 1,
    stock_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customizations table (Admin adds these)
CREATE TABLE customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price_adjustment DECIMAL(10, 2) DEFAULT 0,
    category VARCHAR(100) DEFAULT 'General',
    is_active BOOLEAN DEFAULT 1
);

-- Product Customizations (Link products to available customizations)
CREATE TABLE product_customizations (
    product_id INT,
    customization_id INT,
    PRIMARY KEY (product_id, customization_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customization_id) REFERENCES customizations(id) ON DELETE CASCADE
);

-- Cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    selected_customizations TEXT,  -- JSON array of selected customization IDs
    total_price DECIMAL(10, 2),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert sample products
INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES
('Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'Pastries', 1, 0, 1, 50),
('Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'Muffins', 0, 10, 1, 30),
('Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'Pastries', 0, 0, 1, 25),
('Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'Cupcakes', 0, 0, 1, 40),
('Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'Tarts', 0, 15, 1, 20);

-- Insert customization options
INSERT INTO customizations (name, price_adjustment, category, is_active) VALUES
('Extra Chocolate', 0.75, 'Toppings', 1),
('Almond Topping', 0.50, 'Toppings', 1),
('Walnut Topping', 0.75, 'Toppings', 1),
('Sprinkles', 0.25, 'Toppings', 1),
('Chocolate Drizzle', 0.50, 'Toppings', 1),
('Gluten-Free', 1.00, 'Dietary', 1),
('Vegan', 1.50, 'Dietary', 1),
('Extra Large', 2.00, 'Size', 1),
('Birthday Message', 1.00, 'Special', 1),
('Wedding Decoration', 3.00, 'Special', 1);

-- Link products to customizations
INSERT INTO product_customizations (product_id, customization_id) VALUES
-- Chocolate Croissant (id=1)
(1, 1), (1, 2), (1, 6), (1, 7), (1, 8),
-- Blueberry Muffin (id=2)
(2, 3), (2, 4), (2, 6), (2, 7), (2, 8),
-- Cinnamon Roll (id=3)
(3, 1), (3, 3), (3, 5), (3, 6), (3, 7), (3, 8),
-- Vanilla Cupcake (id=4)
(4, 4), (4, 5), (4, 6), (4, 7), (4, 8), (4, 9),
-- Strawberry Tart (id=5)
(5, 4), (5, 5), (5, 6), (5, 10);