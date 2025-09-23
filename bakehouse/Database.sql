CREATE DATABASE golden_treat;

USE golden_treat;

-- Users table (as provided, no changes needed)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'manager') DEFAULT 'customer' NOT NULL,
    date_joined DATE NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table (new, to match profile_api.php assumptions)
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    order_date DATE NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending' NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    quantity INT DEFAULT 0
);

CREATE TABLE s_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image VARCHAR(255)
);

CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookingId VARCHAR(50) UNIQUE NOT NULL,
    customerName VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    tableNumber INT NOT NULL,
    status VARCHAR(20) NOT NULL
);

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
);

-- ==============================
-- Orders Table
-- ==============================
CREATE TABLE orders(
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  order_date DATE DEFAULT NULL,
  customer VARCHAR(255) NOT NULL,
  product VARCHAR(255) NOT NULL,
  quantity INT(10) UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('Pending','Shipped','Cancelled','Returned') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  KEY idx_orders_order_date (order_date),
  KEY idx_orders_customer (customer),
  KEY idx_orders_status (status)
)


-- ==============================
-- Stock Table
-- ==============================
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

-- ==============================
-- Sales Table (Second Version with Quantity)
-- ==============================
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    customer VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Pending','Paid','Completed','Cancelled') DEFAULT 'Pending'
);

-- Example Data Insert


-- Create otp table matching the provided structure
CREATE TABLE otp (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    otp VARCHAR(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--sales--
-- ===========================================
-- 1. SALES TABLE (your main sales records)
-- ===========================================
CREATE TABLE IF NOT EXISTS sales (
id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    customer VARCHAR(100),
    quantity INT,
    total DECIMAL(10,2),
    status VARCHAR(50),
    user VARCHAR(50)
);
-- Drop existing triggers if they exist (to allow remaking)
DROP TRIGGER IF EXISTS after_sales_insert;
DROP TRIGGER IF EXISTS after_sales_update;
DROP TRIGGER IF EXISTS after_sales_delete;

-- Drop the sales_log table if it exists (to remake cleanly)
DROP TABLE IF EXISTS sales_log;

-- Creating the sales_log table
CREATE TABLE IF NOT EXISTS sales_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    operation VARCHAR(50) NOT NULL,
    date DATE,
    customer VARCHAR(100),
    quantity INT,
    total DECIMAL(10,2),
    status VARCHAR(50),
    user VARCHAR(50),
    log_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add foreign key constraint for referential integrity
ALTER TABLE sales_log
ADD CONSTRAINT fk_sales_log_sale_id
FOREIGN KEY (sale_id) REFERENCES sales(id)
ON DELETE SET NULL;

-- Trigger for INSERT operations
DELIMITER //
CREATE TRIGGER after_sales_insert
AFTER INSERT ON sales
FOR EACH ROW
BEGIN
    INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
    VALUES (NEW.id, 'INSERT', NEW.date, NEW.customer, NEW.quantity, NEW.total, NEW.status, NEW.user);
END //
DELIMITER ;

-- Trigger for UPDATE operations
DELIMITER //
CREATE TRIGGER after_sales_update
AFTER UPDATE ON sales
FOR EACH ROW
BEGIN
    INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
    VALUES (NEW.id, 'UPDATE', NEW.date, NEW.customer, NEW.quantity, NEW.total, NEW.status, NEW.user);
END //
DELIMITER ;

-- Trigger for DELETE operations
DELIMITER //
CREATE TRIGGER after_sales_delete
AFTER DELETE ON sales
FOR EACH ROW
BEGIN
    INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
    VALUES (OLD.id, 'DELETE', OLD.date, OLD.customer, OLD.quantity, OLD.total, OLD.status, OLD.user);
END //
DELIMITER ;

--suer--

-- Drop existing triggers if they exist (to allow remaking)
DROP TRIGGER IF EXISTS after_users_insert;
DROP TRIGGER IF EXISTS after_users_update;
DROP TRIGGER IF EXISTS after_users_delete;

-- Drop the users_log table if it exists (to remake cleanly)
DROP TABLE IF EXISTS users_log;

-- Creating the users_log table
CREATE TABLE IF NOT EXISTS users_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
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
);

-- Add foreign key constraint for referential integrity
ALTER TABLE users_log
ADD CONSTRAINT fk_users_log_user_id
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL;

-- Trigger for INSERT operations
DELIMITER //
CREATE TRIGGER after_users_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'INSERT', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END //
DELIMITER ;

-- Trigger for UPDATE operations
DELIMITER //
CREATE TRIGGER after_users_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'UPDATE', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END //
DELIMITER ;

-- Trigger for DELETE operations
DELIMITER //
CREATE TRIGGER after_users_delete
AFTER DELETE ON users
FOR EACH ROW
BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (OLD.id, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.profile_picture, OLD.last_login);
END //
DELIMITER ;