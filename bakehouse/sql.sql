-- Create Database
CREATE DATABASE IF NOT EXISTS bakehouse;
USE bakehouse;

-- ==============================
-- Bookings Table
-- ==============================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bookingId VARCHAR(50) UNIQUE NOT NULL,
    customerName VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    tableNumber INT NOT NULL,
    status VARCHAR(20) NOT NULL
);

-- ==============================
-- Users Table
-- ==============================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    district VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'manager') DEFAULT 'user' NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active' NOT NULL,
    date_joined DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ==============================
-- Bills + Bill Items (Payments)
-- ==============================
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
CREATE TABLE orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================
-- Sales Table (First Version)
-- ==============================
CREATE TABLE sales_v2 (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  date DATE DEFAULT NULL,
  customer VARCHAR(255) NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('Completed','Pending','Cancelled','Paid') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id),
  KEY idx_sales_date (date),
  KEY idx_sales_customer (customer),
  KEY idx_sales_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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


