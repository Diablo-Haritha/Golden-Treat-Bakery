-- ============================================================
-- DATABASE: Golden Treat Bakery Management System
-- ============================================================

CREATE DATABASE golden_treat;

USE golden_treat;

-- ============================================================
-- TABLE: bookings
-- Stores customer table reservations
-- ============================================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookingId VARCHAR(50) UNIQUE NOT NULL,
    customerName VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    tableNumber INT NOT NULL,
    status VARCHAR(20) NOT NULL
);

-- ============================================================
-- BILLING SYSTEM
-- ============================================================

-- Drop old tables if exist
DROP TABLE IF EXISTS bill_items;
DROP TABLE IF EXISTS bills;

-- TABLE: bills
-- Stores bill header information
CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TABLE: bill_items
-- Stores individual items in each bill
CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample Data: Bills
INSERT INTO bills (customer_name) VALUES 
('Kasun Perera'),
('Nimali Silva'),
('Ruwan Jayasinghe');

-- Sample Data: Bill Items
INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES
(1, 'Chocolate Cake', 1500.00, 1),
(1, 'Soft Drink', 200.00, 2),
(2, 'Butter Bread', 120.00, 3),
(2, 'Egg Puff', 80.00, 5),
(3, 'Pizza Large', 2500.00, 1),
(3, 'Iced Coffee', 450.00, 2);

-- ============================================================
-- TABLE: settings
-- Stores shop configuration and preferences
-- ============================================================
DROP TABLE IF EXISTS settings;

CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_name VARCHAR(255) NOT NULL,
    shop_slogan VARCHAR(255),
    shop_tel VARCHAR(50),
    shop_email VARCHAR(255),
    shop_address TEXT,
    thank_note TEXT,
    vat_percent DECIMAL(5,2) DEFAULT 0.00
);

-- Sample Data: Settings
INSERT INTO settings (id, shop_name, shop_slogan, shop_tel, shop_email, shop_address, thank_note, vat_percent)
VALUES 
(1, 'Golden Treat Bakery', 'Fresh & Tasty Every Day', '011-2345678', 'golden@example.com', '123 Main Street, Colombo', 'Thank you for visiting Golden Treat!', 8.00);

-- ============================================================
-- TABLE: stock
-- Manages inventory and stock levels
-- ============================================================
CREATE TABLE stock (
  id INT(11) NOT NULL AUTO_INCREMENT,
  partNumber VARCHAR(50) NOT NULL,
  date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity INT(11) DEFAULT 0,
  category VARCHAR(100) NOT NULL,
  status ENUM('In Stock','Low','Out of Stock') DEFAULT 'In Stock',
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- USER MANAGEMENT SYSTEM
-- ============================================================

-- Drop existing tables if needed
DROP TABLE IF EXISTS users_log;
DROP TABLE IF EXISTS users;

-- TABLE: users
-- Stores user accounts and profiles
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(20),
    address VARCHAR(255),
    district VARCHAR(100),
    role ENUM('customer','manager','admin') DEFAULT 'customer',
    date_joined DATE NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    profile_picture VARCHAR(255),
    last_login TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TABLE: users_log
-- Audit trail for user changes
CREATE TABLE IF NOT EXISTS users_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    operation VARCHAR(50) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    mobile VARCHAR(20),
    address TEXT,
    district VARCHAR(100),
    role ENUM('customer', 'admin', 'manager'),
    date_joined DATE,
    profile_picture VARCHAR(255),
    last_login TIMESTAMP NULL,
    log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add foreign key
ALTER TABLE users_log
ADD CONSTRAINT fk_users_log_user_id
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL;

-- Drop existing triggers if any
DROP TRIGGER IF EXISTS after_users_insert;
DROP TRIGGER IF EXISTS after_users_update;
DROP TRIGGER IF EXISTS after_users_delete;

-- TRIGGERS: User Audit Trail
DELIMITER //

CREATE TRIGGER after_users_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'INSERT', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END //

CREATE TRIGGER after_users_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'UPDATE', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END //

CREATE TRIGGER after_users_delete
AFTER DELETE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NULL, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.profile_picture, OLD.last_login);
END //

DELIMITER ;

-- Cleanup orphaned logs
UPDATE users_log 
SET user_id = NULL 
WHERE user_id IS NOT NULL 
AND user_id NOT IN (SELECT id FROM users);

-- Sample Data: Users
INSERT INTO users (full_name, email, mobile, address, district, role, date_joined, status, password)
VALUES 
('Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', CURDATE(), 'Active', 'admin123'),
('Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', CURDATE(), 'Active', 'manager123'),
('Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', CURDATE(), 'Active', 'customer123');

-- ============================================================
-- SALES MANAGEMENT SYSTEM
-- ============================================================

-- TABLE: sales
-- Stores sales transactions
CREATE TABLE IF NOT EXISTS sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  customer VARCHAR(100),
  quantity INT,
  total DECIMAL(10,2),
  status VARCHAR(50),
  user VARCHAR(50)
) ENGINE=InnoDB;

-- Drop old triggers/logs
DROP TRIGGER IF EXISTS trg_sales_after_insert;
DROP TRIGGER IF EXISTS trg_sales_after_update;
DROP TRIGGER IF EXISTS trg_sales_before_delete;
DROP TABLE IF EXISTS sales_log;

-- TABLE: sales_log
-- Audit trail for sales changes
CREATE TABLE sales_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NULL,
  operation VARCHAR(50) NOT NULL,
  date DATE,
  customer VARCHAR(100),
  quantity INT,
  total DECIMAL(10,2),
  status VARCHAR(50),
  user VARCHAR(50),
  log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TRIGGERS: Sales Audit Trail
DELIMITER $$

CREATE TRIGGER trg_sales_after_insert AFTER INSERT ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
  VALUES (NEW.id, 'INSERT', NEW.date, NEW.customer, NEW.quantity, NEW.total, NEW.status, NEW.user);
END$$

CREATE TRIGGER trg_sales_after_update AFTER UPDATE ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
  VALUES (NEW.id, 'UPDATE', NEW.date, NEW.customer, NEW.quantity, NEW.total, NEW.status, NEW.user);
END$$

CREATE TRIGGER trg_sales_before_delete BEFORE DELETE ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
  VALUES (OLD.id, 'DELETE', OLD.date, OLD.customer, OLD.quantity, OLD.total, OLD.status, OLD.user);
END$$

DELIMITER ;

-- ============================================================
-- PRODUCT & CUSTOMIZATION SYSTEM
-- ============================================================

-- TABLE: products
-- Stores bakery products catalog
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

-- TABLE: customizations
-- Stores available product customization options
CREATE TABLE customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price_adjustment DECIMAL(10, 2) DEFAULT 0,
    category VARCHAR(100) DEFAULT 'General',
    is_active BOOLEAN DEFAULT 1
);

-- TABLE: product_customizations
-- Links products to available customizations
CREATE TABLE product_customizations (
    product_id INT,
    customization_id INT,
    PRIMARY KEY (product_id, customization_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customization_id) REFERENCES customizations(id) ON DELETE CASCADE
);

-- TABLE: cart
-- Stores customer shopping cart items
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    selected_customizations TEXT,
    total_price DECIMAL(10, 2),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Sample Data: Products
INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES
('Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'Pastries', 1, 0, 1, 50),
('Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'Muffins', 0, 10, 1, 30),
('Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'Pastries', 0, 0, 1, 25),
('Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'Cupcakes', 0, 0, 1, 40),
('Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'Tarts', 0, 15, 1, 20);

-- Sample Data: Customizations
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

-- Sample Data: Product-Customization Links
INSERT INTO product_customizations (product_id, customization_id) VALUES
(1, 1), (1, 2), (1, 6), (1, 7), (1, 8),
(2, 3), (2, 4), (2, 6), (2, 7), (2, 8),
(3, 1), (3, 3), (3, 5), (3, 6), (3, 7), (3, 8),
(4, 4), (4, 5), (4, 6), (4, 7), (4, 8), (4, 9),
(5, 4), (5, 5), (5, 6), (5, 10);

-- ============================================================
-- ORDER MANAGEMENT SYSTEM
-- ============================================================

-- TABLE: orders
-- Stores customer orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- TABLE: order_items
-- Stores individual items in each order
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    customizations TEXT,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);



CREATE TABLE otps (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  otp varchar(6) NOT NULL,
  expires_at datetime NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;