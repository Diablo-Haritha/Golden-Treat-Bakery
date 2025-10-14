-- ============================================================
-- DATABASE: Golden Treat Bakery Management System (Updated & Merged)
-- ============================================================

CREATE DATABASE IF NOT EXISTS golden_treat;
USE golden_treat;

-- ============================================================
-- TABLE: bookings
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
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
DROP TABLE IF EXISTS bill_items;
DROP TABLE IF EXISTS bills;

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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

INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES
(1, 'Chocolate Cake', 1500.00, 1),
(1, 'Soft Drink', 200.00, 2),
(2, 'Butter Bread', 120.00, 3),
(2, 'Egg Puff', 80.00, 5),
(3, 'Pizza Large', 2500.00, 1),
(3, 'Iced Coffee', 450.00, 2);

-- ============================================================
-- TABLE: settings
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

INSERT INTO settings (shop_name, shop_slogan, shop_tel, shop_email, shop_address, thank_note, vat_percent)
VALUES 
('Golden Treat Bakery', 'Fresh & Tasty Every Day', '011-2345678', 'golden@example.com', '123 Main Street, Colombo', 'Thank you for visiting Golden Treat!', 8.00);

-- ============================================================
-- TABLE: stock
-- ============================================================
CREATE TABLE IF NOT EXISTS stock (
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
-- USER MANAGEMENT SYSTEM (UPDATED)
-- ============================================================
DROP TABLE IF EXISTS users_log;
DROP TABLE IF EXISTS users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,  -- Updated: Shorter length to match simpler schema
    email VARCHAR(100) UNIQUE NOT NULL,  -- Updated: Shorter length
    mobile VARCHAR(20),
    address VARCHAR(255),
    district VARCHAR(100),
    role ENUM('admin','manager','customer') NOT NULL DEFAULT 'customer',  -- Updated: Order and NOT NULL DEFAULT
    date_joined DATE DEFAULT CURDATE(),  -- Updated: DEFAULT CURDATE(), removed NOT NULL
    status ENUM('Active','Inactive') DEFAULT 'Active',
    profile_picture VARCHAR(255),  -- Retained from original
    last_login TIMESTAMP NULL,  -- Retained from original
    password VARCHAR(255) NOT NULL,  -- ⚠️ MUST BE HASHED IN APPLICATION (e.g., bcrypt)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- Retained from original
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP  -- Retained from original
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    operation VARCHAR(50) NOT NULL,
    full_name VARCHAR(100),  -- Updated: Shorter length
    email VARCHAR(100),  -- Updated: Shorter length
    mobile VARCHAR(20),
    address VARCHAR(255),
    district VARCHAR(100),
    role ENUM('admin', 'manager', 'customer'),  -- Updated: Order to match users
    date_joined DATE,
    status ENUM('Active','Inactive'),  -- Added to match simpler schema
    profile_picture VARCHAR(255),  -- Retained
    last_login TIMESTAMP NULL,  -- Retained
    log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Triggers for user audit (Updated: Prefix and structure to match simpler schema)
DELIMITER //

CREATE TRIGGER trg_users_after_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (NEW.id, 'INSERT', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.status, NEW.profile_picture, NEW.last_login);
END //

CREATE TRIGGER trg_users_after_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (NEW.id, 'UPDATE', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.status, NEW.profile_picture, NEW.last_login);
END //

CREATE TRIGGER trg_users_before_delete
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (OLD.id, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.status, OLD.profile_picture, OLD.last_login);
END //

DELIMITER ;

-- Sample Users (Updated: Use PASSWORD() for hashing example, but note to use bcrypt in app; adjusted fields)
INSERT INTO users (full_name, email, mobile, address, district, role, date_joined, status, password)
VALUES 
('Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', CURDATE(), 'Active', PASSWORD('admin123')),
('Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', CURDATE(), 'Active', PASSWORD('manager123')),
('Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', CURDATE(), 'Active', PASSWORD('customer123'));

-- Cleanup orphaned logs
UPDATE users_log 
SET user_id = NULL 
WHERE user_id IS NOT NULL 
AND user_id NOT IN (SELECT id FROM users);

-- ============================================================
-- SALES MANAGEMENT SYSTEM (UPDATED)
-- ============================================================
DROP TRIGGER IF EXISTS trg_sales_after_insert;
DROP TRIGGER IF EXISTS trg_sales_after_update;
DROP TRIGGER IF EXISTS trg_sales_before_delete;
DROP TABLE IF EXISTS sales_log;
DROP TABLE IF EXISTS sales;

-- ✅ UPDATED: Incorporated changes - customer NOT NULL, quantity/total NOT NULL, added 'Returned' to status, added staff field, user_id NULL with FK
CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  customer VARCHAR(100) NOT NULL,  -- Updated: NOT NULL
  user_id INT NULL,               -- Links to users.id (for integrity)
  quantity INT NOT NULL,  -- Updated: NOT NULL, removed DEFAULT
  total DECIMAL(10,2) NOT NULL,  -- Updated: NOT NULL
  status ENUM('Pending', 'Paid', 'Cancelled', 'Returned') DEFAULT 'Pending',  -- Updated: Added 'Returned'
  staff VARCHAR(100) DEFAULT 'Admin',  -- Added: From simpler schema
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- Retained
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sales audit log (Updated: Added staff field)
CREATE TABLE sales_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NULL,
  operation VARCHAR(50) NOT NULL,
  date DATE,
  customer VARCHAR(100),
  user_id INT NULL,
  quantity INT,
  total DECIMAL(10,2),
  status ENUM('Pending','Paid','Cancelled','Returned'),  -- Updated: Added 'Returned'
  staff VARCHAR(100),  -- Added
  log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DELIMITER $$

CREATE TRIGGER trg_sales_after_insert AFTER INSERT ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (NEW.id, 'INSERT', NEW.date, NEW.customer, NEW.user_id, NEW.quantity, NEW.total, NEW.status, NEW.staff);
END$$

CREATE TRIGGER trg_sales_after_update AFTER UPDATE ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (NEW.id, 'UPDATE', NEW.date, NEW.customer, NEW.user_id, NEW.quantity, NEW.total, NEW.status, NEW.staff);
END$$

CREATE TRIGGER trg_sales_before_delete BEFORE DELETE ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (OLD.id, 'DELETE', OLD.date, OLD.customer, OLD.user_id, OLD.quantity, OLD.total, OLD.status, OLD.staff);
END$$

DELIMITER ;

-- Sample Sales Data (Updated: Adjusted to match new structure, using sample from simpler schema)
INSERT INTO sales (date, customer, user_id, quantity, total, status, staff) VALUES
(CURDATE(), 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User'),
(CURDATE(), 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User'),
(CURDATE(), 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User');

-- ============================================================
-- PRODUCT & CUSTOMIZATION SYSTEM
-- ============================================================
-- (No changes needed — already well-structured)

CREATE TABLE IF NOT EXISTS products (
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

CREATE TABLE IF NOT EXISTS customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price_adjustment DECIMAL(10, 2) DEFAULT 0,
    category VARCHAR(100) DEFAULT 'General',
    is_active BOOLEAN DEFAULT 1
);

CREATE TABLE IF NOT EXISTS product_customizations (
    product_id INT,
    customization_id INT,
    PRIMARY KEY (product_id, customization_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customization_id) REFERENCES customizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    selected_customizations TEXT,
    total_price DECIMAL(10, 2),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Sample data (unchanged)
INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES
('Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'Pastries', 1, 0, 1, 50),
('Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'Muffins', 0, 10, 1, 30),
('Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'Pastries', 0, 0, 1, 25),
('Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'Cupcakes', 0, 0, 1, 40),
('Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'Tarts', 0, 15, 1, 20);

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

INSERT INTO product_customizations (product_id, customization_id) VALUES
(1, 1), (1, 2), (1, 6), (1, 7), (1, 8),
(2, 3), (2, 4), (2, 6), (2, 7), (2, 8),
(3, 1), (3, 3), (3, 5), (3, 6), (3, 7), (3, 8),
(4, 4), (4, 5), (4, 6), (4, 7), (4, 8), (4, 9),
(5, 4), (5, 5), (5, 6), (5, 10);

-- ============================================================
-- ORDER MANAGEMENT SYSTEM
-- ============================================================
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

-- ============================================================
-- OTP SYSTEM (FIXED & RETAINED)
-- ============================================================
DROP TABLE IF EXISTS otps;

-- ✅ FIXED: Added PRIMARY KEY + AUTO_INCREMENT (retained from original)
CREATE TABLE otps (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;










-- ============================================================
-- NEW TRIGGER: Auto-create Sale after New Order
-- ============================================================

-- This trigger fires AFTER INSERT on the 'orders' table.
-- It automatically creates a corresponding entry in the 'sales' table.
-- Assumptions:
-- - Quantity is set to 1 (representing the entire order as a single "sale unit").
-- - Status is mapped from order status (e.g., 'pending' -> 'Pending'; defaults to 'Pending' if no match).
-- - User_id is set to NULL (no specific user linked; can be updated later).
-- - Staff is set to 'Admin' (default).
-- - Date is derived from the order's created_at.
-- - If order_items are inserted after the order, the sale total is based on order.total_amount.
-- - Note: For more accuracy (e.g., summing quantities from order_items), consider a stored procedure or application-level logic.
-- - Additional triggers could be added for order updates to sync sales status.

DELIMITER $$

CREATE TRIGGER trg_orders_after_insert_auto_sale
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE sale_status VARCHAR(20) DEFAULT 'Pending';
    
    -- Map order status to sales status (simple mapping)
    CASE NEW.status
        WHEN 'pending' THEN SET sale_status = 'Pending';
        WHEN 'confirmed' THEN SET sale_status = 'Paid';  -- Assuming confirmed means paid/processed
        WHEN 'preparing' THEN SET sale_status = 'Pending';
        WHEN 'ready' THEN SET sale_status = 'Paid';
        WHEN 'completed' THEN SET sale_status = 'Paid';
        ELSE SET sale_status = 'Pending';
    END CASE;
    
    -- Insert into sales
    INSERT INTO sales (date, customer, user_id, quantity, total, status, staff, created_at)
    VALUES (
        DATE(NEW.created_at),  -- Use date from order creation
        NEW.customer_name,     -- Customer from order
        NULL,                  -- No specific user_id; can be linked later
        1,                     -- Quantity: 1 for the entire order (adjust if needed)
        NEW.total_amount,      -- Total from order
        sale_status,           -- Mapped status
        'Admin',               -- Default staff
        NEW.created_at         -- Same timestamp as order
    );
END$$

DELIMITER ;

-- ============================================================
-- OPTIONAL: Trigger for Order Status Updates (to sync Sales Status)
-- ============================================================

-- This trigger fires AFTER UPDATE on 'orders' to update the corresponding sale's status.
-- Assumption: There is one sale per order (based on the insert trigger above).
-- Links via a potential order_id in sales? Wait, sales doesn't have order_id.
-- To make this work properly, we need to add an 'order_id' field to sales table for linking.
-- For now, this is a placeholder; recommend adding 'order_id INT NULL FOREIGN KEY REFERENCES orders(id)' to sales.

-- First, ALTER sales table to add order_id (if not exists)
-- ALTER TABLE sales ADD COLUMN IF NOT EXISTS order_id INT NULL AFTER customer,
-- ADD FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;

-- Then, the trigger (uncomment after ALTER):
/*
DELIMITER $$

CREATE TRIGGER trg_orders_after_update_sync_sale
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    DECLARE sale_status VARCHAR(20) DEFAULT 'Pending';
    
    -- Map updated order status to sales status
    CASE NEW.status
        WHEN 'pending' THEN SET sale_status = 'Pending';
        WHEN 'confirmed' THEN SET sale_status = 'Paid';
        WHEN 'preparing' THEN SET sale_status = 'Pending';
        WHEN 'ready' THEN SET sale_status = 'Paid';
        WHEN 'completed' THEN SET sale_status = 'Paid';
        ELSE SET sale_status = 'Pending';
    END CASE;
    
    -- Update the linked sale (assumes order_id in sales)
    UPDATE sales 
    SET status = sale_status, 
        updated_at = CURRENT_TIMESTAMP  -- If you add updated_at to sales
    WHERE order_id = NEW.id;
END$$

DELIMITER ;
*/

-- ============================================================
-- SAMPLE USAGE
-- ============================================================

-- Test: Insert a new order (this will auto-trigger a sale)
INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, total_amount, status) 
VALUES ('ORD-001', 'Test Customer', 'test@example.com', '0770000000', 2500.00, 'pending');

-- Verify: Check the auto-created sale
SELECT * FROM sales WHERE customer = 'Test Customer' ORDER BY created_at DESC LIMIT 1;

-- Clean up test data if needed
DELETE FROM order_items WHERE order_id = LAST_INSERT_ID();  -- If items were added
DELETE FROM orders WHERE order_number = 'ORD-001';
DELETE FROM sales WHERE customer = 'Test Customer';

-- ============================================================
-- STOCK AUDIT LOG
-- ============================================================

-- Drop previous triggers/log table if they exist
DROP TRIGGER IF EXISTS trg_stock_after_insert;
DROP TRIGGER IF EXISTS trg_stock_after_update;
DROP TRIGGER IF EXISTS trg_stock_before_delete;
DROP TABLE IF EXISTS stock_log;

-- Create stock_log table
CREATE TABLE stock_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    operation VARCHAR(50) NOT NULL,
    partNumber VARCHAR(50),
    date DATE,
    description VARCHAR(255),
    quantity INT,
    category VARCHAR(100),
    status ENUM('In Stock','Low','Out of Stock'),
    unit VARCHAR(20),
    log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- STOCK TRIGGERS
-- ============================================================

DELIMITER $$

-- AFTER INSERT: Log new stock records
CREATE TRIGGER trg_stock_after_insert
AFTER INSERT ON stock
FOR EACH ROW
BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'INSERT', NEW.partNumber, NEW.date, NEW.description, NEW.quantity, NEW.category, NEW.status, NEW.unit
    );
END$$

-- AFTER UPDATE: Log changes to stock records
CREATE TRIGGER trg_stock_after_update
AFTER UPDATE ON stock
FOR EACH ROW
BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'UPDATE', NEW.partNumber, NEW.date, NEW.description, NEW.quantity, NEW.category, NEW.status, NEW.unit
    );
END$$

-- BEFORE DELETE: Log stock records before deletion
CREATE TRIGGER trg_stock_before_delete
BEFORE DELETE ON stock
FOR EACH ROW
BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'DELETE', OLD.partNumber, OLD.date, OLD.description, OLD.quantity, OLD.category, OLD.status, OLD.unit
    );
END$$

DELIMITER ;
