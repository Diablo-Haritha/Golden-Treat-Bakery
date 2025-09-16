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


USE bakehouse;

-- ==============================
-- Bookings (15 Sample Records)
-- ==============================
INSERT INTO bookings (bookingId, customerName, date, time, tableNumber, status) VALUES
('B001', 'Kasun Perera', '2025-09-01', '18:30:00', 1, 'Confirmed'),
('B002', 'Nimali Fernando', '2025-09-02', '19:00:00', 2, 'Pending'),
('B003', 'Ruwan Jayasuriya', '2025-09-02', '20:00:00', 3, 'Cancelled'),
('B004', 'Amali Fernando', '2025-09-03', '18:00:00', 4, 'Confirmed'),
('B005', 'Tharindu Silva', '2025-09-04', '19:30:00', 5, 'Confirmed'),
('B006', 'Chathuri Perera', '2025-09-05', '20:15:00', 6, 'Pending'),
('B007', 'Iresh Jayasinghe', '2025-09-06', '18:45:00', 7, 'Confirmed'),
('B008', 'Dilani Weerasinghe', '2025-09-07', '19:10:00', 8, 'Cancelled'),
('B009', 'Mahesh Samarasinghe', '2025-09-07', '20:30:00', 9, 'Confirmed'),
('B010', 'Shehan Wickrama', '2025-09-08', '18:00:00', 10, 'Pending'),
('B011', 'Kavindu Ranasinghe', '2025-09-08', '19:00:00', 11, 'Confirmed'),
('B012', 'Rashmi Madushani', '2025-09-09', '20:00:00', 12, 'Confirmed'),
('B013', 'Nadun Karunaratne', '2025-09-10', '18:30:00', 13, 'Cancelled'),
('B014', 'Sajith Rajapaksha', '2025-09-11', '19:45:00', 14, 'Confirmed'),
('B015', 'Pavithra Jayasuriya', '2025-09-12', '20:15:00', 15, 'Pending');

-- ==============================
-- Users (15 Sample Records)
-- ==============================
INSERT INTO users (full_name, email, mobile, address, district, password, role, status, date_joined) VALUES
('User 1', 'user1@example.com', '0711111111', 'Address 1', 'Colombo', 'pw', 'user', 'Active', '2025-01-01'),
('User 2', 'user2@example.com', '0722222222', 'Address 2', 'Gampaha', 'pw', 'admin', 'Active', '2025-01-02'),
('User 3', 'user3@example.com', '0733333333', 'Address 3', 'Kandy', 'pw', 'manager', 'Inactive', '2025-01-03'),
('User 4', 'user4@example.com', '0744444444', 'Address 4', 'Galle', 'pw', 'user', 'Active', '2025-01-04'),
('User 5', 'user5@example.com', '0755555555', 'Address 5', 'Matara', 'pw', 'user', 'Active', '2025-01-05'),
('User 6', 'user6@example.com', '0766666666', 'Address 6', 'Kurunegala', 'pw', 'admin', 'Inactive', '2025-01-06'),
('User 7', 'user7@example.com', '0777777777', 'Address 7', 'Anuradhapura', 'pw', 'manager', 'Active', '2025-01-07'),
('User 8', 'user8@example.com', '0788888888', 'Address 8', 'Jaffna', 'pw', 'user', 'Active', '2025-01-08'),
('User 9', 'user9@example.com', '0799999999', 'Address 9', 'Batticaloa', 'pw', 'user', 'Active', '2025-01-09'),
('User 10', 'user10@example.com', '0700000000', 'Address 10', 'Hambantota', 'pw', 'admin', 'Inactive', '2025-01-10'),
('User 11', 'user11@example.com', '0712345678', 'Address 11', 'Kegalle', 'pw', 'user', 'Active', '2025-01-11'),
('User 12', 'user12@example.com', '0723456789', 'Address 12', 'Puttalam', 'pw', 'manager', 'Active', '2025-01-12'),
('User 13', 'user13@example.com', '0734567890', 'Address 13', 'Monaragala', 'pw', 'user', 'Inactive', '2025-01-13'),
('User 14', 'user14@example.com', '0745678901', 'Address 14', 'Ratnapura', 'pw', 'user', 'Active', '2025-01-14'),
('User 15', 'user15@example.com', '0756789012', 'Address 15', 'Badulla', 'pw', 'admin', 'Active', '2025-01-15');

-- ==============================
-- Bills (15 Sample Records)
-- ==============================
INSERT INTO bills (customer_name, created_at) VALUES
('Kasun Perera', '2025-09-01 10:00:00'),
('Nimali Fernando', '2025-09-01 11:00:00'),
('Ruwan Jayasuriya', '2025-09-02 09:30:00'),
('Amali Fernando', '2025-09-02 12:00:00'),
('Tharindu Silva', '2025-09-03 13:00:00'),
('Chathuri Perera', '2025-09-03 14:30:00'),
('Iresh Jayasinghe', '2025-09-04 15:00:00'),
('Dilani Weerasinghe', '2025-09-04 16:00:00'),
('Mahesh Samarasinghe', '2025-09-05 10:00:00'),
('Shehan Wickrama', '2025-09-05 11:30:00'),
('Kavindu Ranasinghe', '2025-09-06 12:00:00'),
('Rashmi Madushani', '2025-09-06 13:00:00'),
('Nadun Karunaratne', '2025-09-07 14:00:00'),
('Sajith Rajapaksha', '2025-09-07 15:30:00'),
('Pavithra Jayasuriya', '2025-09-08 16:00:00');

-- ==============================
-- Bill Items (15 Sample Records)
-- ==============================
INSERT INTO bill_items (bill_id, item_name, price, qty) VALUES
(1, 'Cake Slice', 500.00, 2),
(2, 'Bread Loaf', 150.00, 3),
(3, 'Pastry', 200.00, 4),
(4, 'Muffin', 250.00, 5),
(5, 'Cupcake', 300.00, 2),
(6, 'Sandwich', 400.00, 1),
(7, 'Bun', 100.00, 6),
(8, 'Croissant', 350.00, 2),
(9, 'Pizza Slice', 600.00, 3),
(10, 'Roll', 120.00, 5),
(11, 'Doughnut', 180.00, 4),
(12, 'Milkshake', 700.00, 1),
(13, 'Ice Cream', 450.00, 2),
(14, 'Samosa', 200.00, 3),
(15, 'Brownie', 550.00, 2);

-- ==============================
-- Orders (15 Sample Records)
-- ==============================
INSERT INTO orders (order_date, customer, product, quantity, status) VALUES
('2025-09-01', 'Kasun Perera', 'Cake', 2, 'Pending'),
('2025-09-01', 'Nimali Fernando', 'Bread', 1, 'Shipped'),
('2025-09-02', 'Ruwan Jayasuriya', 'Pizza', 3, 'Cancelled'),
('2025-09-02', 'Amali Fernando', 'Pastry', 5, 'Returned'),
('2025-09-03', 'Tharindu Silva', 'Cupcake', 4, 'Pending'),
('2025-09-03', 'Chathuri Perera', 'Sandwich', 2, 'Shipped'),
('2025-09-04', 'Iresh Jayasinghe', 'Croissant', 1, 'Pending'),
('2025-09-04', 'Dilani Weerasinghe', 'Muffin', 2, 'Shipped'),
('2025-09-05', 'Mahesh Samarasinghe', 'Doughnut', 6, 'Cancelled'),
('2025-09-05', 'Shehan Wickrama', 'Roll', 3, 'Pending'),
('2025-09-06', 'Kavindu Ranasinghe', 'Pizza', 2, 'Shipped'),
('2025-09-06', 'Rashmi Madushani', 'Cake', 1, 'Pending'),
('2025-09-07', 'Nadun Karunaratne', 'Brownie', 4, 'Returned'),
('2025-09-07', 'Sajith Rajapaksha', 'Ice Cream', 3, 'Shipped'),
('2025-09-08', 'Pavithra Jayasuriya', 'Milkshake', 2, 'Pending');

-- ==============================
-- Sales (First Version, 15 Sample Records)
-- ==============================
INSERT INTO sales_v2 (date, customer, total, status) VALUES
('2025-09-01', 'Kasun Perera', 4500.00, 'Completed'),
('2025-09-02', 'Nimali Fernando', 1500.00, 'Pending'),
('2025-09-03', 'Ruwan Jayasuriya', 9000.00, 'Paid'),
('2025-09-04', 'Amali Fernando', 12500.00, 'Cancelled'),
('2025-09-05', 'Tharindu Silva', 7500.00, 'Completed'),
('2025-09-06', 'Chathuri Perera', 3000.00, 'Pending'),
('2025-09-07', 'Iresh Jayasinghe', 2000.00, 'Completed'),
('2025-09-08', 'Dilani Weerasinghe', 3500.00, 'Paid'),
('2025-09-09', 'Mahesh Samarasinghe', 5000.00, 'Completed'),
('2025-09-10', 'Shehan Wickrama', 2500.00, 'Pending'),
('2025-09-11', 'Kavindu Ranasinghe', 4500.00, 'Completed'),
('2025-09-12', 'Rashmi Madushani', 3000.00, 'Paid'),
('2025-09-13', 'Nadun Karunaratne', 7000.00, 'Cancelled'),
('2025-09-14', 'Sajith Rajapaksha', 8000.00, 'Completed'),
('2025-09-15', 'Pavithra Jayasuriya', 6000.00, 'Pending');

-- ==============================
-- Stock (15 Sample Records)
-- ==============================
INSERT INTO stock (partNumber, date, description, quantity, category, status, unit) VALUES
('P001', '2025-09-01', 'Flour 1kg', 100, 'Ingredients', 'In Stock', 'kg'),
('P002', '2025-09-01', 'Sugar 1kg', 50, 'Ingredients', 'Low', 'kg'),
('P003', '2025-09-02', 'Butter 500g', 0, 'Ingredients', 'Out of Stock', 'g'),
('P004', '2025-09-02', 'Yeast 100g', 30, 'Ingredients', 'In Stock', 'g'),
('P005', '2025-09-03', 'Milk Powder 1kg', 80, 'Ingredients', 'In Stock', 'kg'),
('P006', '2025-09-03', 'Chocolate Chips', 20, 'Ingredients', 'Low', 'g'),
('P007', '2025-09-04', 'Cream', 15, 'Ingredients', 'Low', 'ml'),
('P008', '2025-09-04', 'Baking Powder', 60, 'Ingredients', 'In Stock', 'g'),
('P009', '2025-09-05', 'Paper Boxes', 200, 'Packaging', 'In Stock', 'pcs'),
('P010', '2025-09-05', 'Plastic Wrap', 0, 'Packaging', 'Out of Stock', 'roll'),
('P011', '2025-09-06', 'Cake Boards', 25, 'Packaging', 'Low', 'pcs'),
('P012', '2025-09-06', 'Oven Gloves', 10, 'Equipment', 'Low', 'pcs'),
('P013', '2025-09-07', 'Mixing Bowls', 8, 'Equipment', 'Low', 'pcs'),
('P014', '2025-09-07', 'Serving Trays', 50, 'Equipment', 'In Stock', 'pcs'),
('P015', '2025-09-08', 'Cake Toppers', 40, 'Decoration', 'In Stock', 'pcs');

-- ==============================
-- Sales v2 (15 Sample Records)
-- ==============================
INSERT INTO sales (date, customer, quantity, total, status) VALUES
('2025-09-01', 'Kasun Perera', 2, 4500.00, 'Completed'),
('2025-09-02', 'Nimali Fernando', 1, 1500.00, 'Pending'),
('2025-09-03', 'Ruwan Jayasuriya', 3, 9000.00, 'Paid'),
('2025-09-04', 'Amali Fernando', 5, 12500.00, 'Cancelled'),
('2025-09-05', 'Tharindu Silva', 4, 7500.00, 'Completed'),
('2025-09-06', 'Chathuri Perera', 2, 3000.00, 'Pending'),
('2025-09-07', 'Iresh Jayasinghe', 1, 2000.00, 'Completed'),
('2025-09-08', 'Dilani Weerasinghe', 2, 3500.00, 'Paid'),
('2025-09-09', 'Mahesh Samarasinghe', 3, 5000.00, 'Completed'),
('2025-09-10', 'Shehan Wickrama', 2, 2500.00, 'Pending'),
('2025-09-11', 'Kavindu Ranasinghe', 2, 4500.00, 'Completed'),
('2025-09-12', 'Rashmi Madushani', 1, 3000.00, 'Paid'),
('2025-09-13', 'Nadun Karunaratne', 4, 7000.00, 'Cancelled'),
('2025-09-14', 'Sajith Rajapaksha', 3, 8000.00, 'Completed'),
('2025-09-15', 'Pavithra Jayasuriya', 2, 6000.00, 'Pending');
