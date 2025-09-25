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

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
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
CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `customer` varchar(100) NOT NULL,
  `product` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `original_quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Order Received','Payment Confirmed','Queued for Baking','In Preparation','Decorating','Ready for Pickup','Out for Delivery','Completed','Cancelled','Refunded','Returned','Pending','Partially Returned') NOT NULL DEFAULT 'Order Received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mobile` varchar(32) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--
INSERT INTO `orders` (`id`, `order_date`, `customer`, `product`, `quantity`, `original_quantity`, `price`, `original_price`, `status`, `created_at`, `updated_at`, `mobile`, `deleted_at`) VALUES
(1, '2025-09-01', 'Alice Fernando', 'Chocolate Cake', 1, 1, 2500.00, 2500.00, 'Order Received', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(2, '2025-09-02', 'Brian Silva', 'Blueberry Muffins (6 pack)', 2, 2, 1800.00, 1800.00, 'Payment Confirmed', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(3, '2025-09-02', 'Chathuri Perera', 'Butter Croissant', 12, 12, 2400.00, 2400.00, 'Queued for Baking', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(4, '2025-09-03', 'Dilshan Jayawardena', 'Vanilla Cupcakes (12 pack)', 1, 1, 2200.00, 2200.00, 'In Preparation', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(6, '2025-09-03', 'Fathima Rahman', 'Strawberry Tart', 2, 2, 3000.00, 3000.00, 'Ready for Pickup', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(7, '2025-09-04', 'Gihan Abeysekera', 'Fruit Loaf', 1, 1, 1500.00, 1500.00, 'Out for Delivery', '2025-09-04 16:08:03', '2025-09-13 12:24:07', NULL, NULL),
(10, '2025-09-04', 'Janani De Silva', 'Brownies', 8, 8, 1600.00, 1600.00, 'Cancelled', '2025-09-04 16:08:03', '2025-09-15 12:37:23', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(64) DEFAULT NULL,
  `new_status` varchar(64) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

-- Table structure for table `returns`
CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `return_date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `mobile` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','queued') NOT NULL DEFAULT 'queued',
  `meta` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `mobile` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `next_try` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Create customers (id NOT AUTO_INCREMENT so later ALTER statements in dump can run)
CREATE TABLE IF NOT EXISTS customers (
  id INT(11) NOT NULL,
  user_id INT(11) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  mobile VARCHAR(32) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  district VARCHAR(100) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY ux_customers_user_id (user_id),
  KEY idx_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK (run once; will error if repeated and constraint exists)
ALTER TABLE customers
  ADD CONSTRAINT fk_customers_user_id
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- Idempotent triggers to sync users -> customers
DELIMITER //
DROP TRIGGER IF EXISTS trg_users_after_insert_customer;
//
DROP TRIGGER IF EXISTS trg_users_after_update_customer;
//
DROP TRIGGER IF EXISTS trg_users_after_delete_customer;
//

CREATE TRIGGER trg_users_after_insert_customer
AFTER INSERT ON users
FOR EACH ROW
BEGIN
  INSERT INTO customers (user_id, full_name, email, mobile, address, district, created_at, last_login)
  VALUES (NEW.id, NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NOW(), NEW.last_login)
  ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    mobile = VALUES(mobile),
    address = VALUES(address),
    district = VALUES(district),
    last_login = VALUES(last_login);
END;
//

CREATE TRIGGER trg_users_after_update_customer
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
  UPDATE customers
  SET full_name = NEW.full_name,
      email     = NEW.email,
      mobile    = NEW.mobile,
      address   = NEW.address,
      district  = NEW.district,
      last_login = NEW.last_login
  WHERE user_id = NEW.id;
END;
//

CREATE TRIGGER trg_users_after_delete_customer
AFTER DELETE ON users
FOR EACH ROW
BEGIN
  DELETE FROM customers WHERE user_id = OLD.id;
END;
//
DELIMITER ;
-- === 1) add user_id + total_amount to orders (nullable so legacy rows stay valid) ===
ALTER TABLE `orders`
  ADD COLUMN user_id INT(11) NULL AFTER id,
  ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price;

-- Add FK from orders.user_id -> users.id (user_id nullable so it won't break existing rows)
ALTER TABLE `orders`
  ADD CONSTRAINT fk_orders_user_id
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;


-- === 2) create order_items (one row per product in an order) ===
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `line_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ======================
--  Stock-management + totals triggers
-- ======================
-- Drop any existing triggers to make this idempotent
DROP TRIGGER IF EXISTS before_order_items_insert;
DROP TRIGGER IF EXISTS before_order_items_update;
DROP TRIGGER IF EXISTS before_order_items_delete;
DROP TRIGGER IF EXISTS after_order_items_insert;
DROP TRIGGER IF EXISTS after_order_items_update;
DROP TRIGGER IF EXISTS after_order_items_delete;

DELIMITER //

-- BEFORE INSERT: check product exists and has enough stock; decrement stock; set line_total
CREATE TRIGGER before_order_items_insert
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
  DECLARE avail INT;
  SELECT quantity INTO avail FROM products WHERE id = NEW.product_id FOR UPDATE;
  IF avail IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product not found';
  END IF;
  IF NEW.quantity > avail THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock for product';
  END IF;
  UPDATE products SET quantity = quantity - NEW.quantity WHERE id = NEW.product_id;
  SET NEW.line_total = NEW.unit_price * NEW.quantity;
END;
//

-- BEFORE UPDATE: handle quantity/product changes and adjust stock accordingly; update line_total
CREATE TRIGGER before_order_items_update
BEFORE UPDATE ON order_items
FOR EACH ROW
BEGIN
  DECLARE avail INT;
  -- If product_id unchanged, adjust by difference
  IF NEW.product_id = OLD.product_id THEN
    SET @diff = NEW.quantity - OLD.quantity; -- positive => need more stock; negative => return stock
    IF @diff > 0 THEN
      SELECT quantity INTO avail FROM products WHERE id = NEW.product_id FOR UPDATE;
      IF avail < @diff THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock to increase quantity';
      END IF;
      UPDATE products SET quantity = quantity - @diff WHERE id = NEW.product_id;
    ELSEIF @diff < 0 THEN
      -- restore the returned units
      UPDATE products SET quantity = quantity - @diff WHERE id = NEW.product_id; -- diff negative => subtract negative => add
    END IF;
  ELSE
    -- product changed: give back old.product qty, then reserve NEW.product qty
    UPDATE products SET quantity = quantity + OLD.quantity WHERE id = OLD.product_id;
    SELECT quantity INTO avail FROM products WHERE id = NEW.product_id FOR UPDATE;
    IF avail IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'New product not found';
    END IF;
    IF NEW.quantity > avail THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock for new product';
    END IF;
    UPDATE products SET quantity = quantity - NEW.quantity WHERE id = NEW.product_id;
  END IF;
  SET NEW.line_total = NEW.unit_price * NEW.quantity;
END;
//

-- BEFORE DELETE: restore product stock
CREATE TRIGGER before_order_items_delete
BEFORE DELETE ON order_items
FOR EACH ROW
BEGIN
  UPDATE products SET quantity = quantity + OLD.quantity WHERE id = OLD.product_id;
END;
//

-- AFTER INSERT: add to orders.total_amount
CREATE TRIGGER after_order_items_insert
AFTER INSERT ON order_items
FOR EACH ROW
FOR EACH ROW
BEGIN
  UPDATE `orders` SET total_amount = COALESCE(total_amount,0) + NEW.line_total WHERE id = NEW.order_id;
END;
//

-- AFTER UPDATE: adjust orders.total_amount by diff
CREATE TRIGGER after_order_items_update
AFTER UPDATE ON order_items
FOR EACH ROW
BEGIN
  UPDATE `orders` SET total_amount = COALESCE(total_amount,0) + (NEW.line_total - OLD.line_total) WHERE id = NEW.order_id;
  -- If the order_id changed (moved item between orders), also adjust the old order
  IF OLD.order_id <> NEW.order_id THEN
    UPDATE `orders` SET total_amount = COALESCE(total_amount,0) - OLD.line_total WHERE id = OLD.order_id;
  END IF;
END;
//

-- AFTER DELETE: subtract from orders.total_amount
CREATE TRIGGER after_order_items_delete
AFTER DELETE ON order_items
FOR EACH ROW
BEGIN
  UPDATE `orders` SET total_amount = COALESCE(total_amount,0) - OLD.line_total WHERE id = OLD.order_id;
END;
//

DELIMITER ;


-- === Helpful SELECT to check an order with items ===
-- SELECT o.id, o.order_date, o.customer, o.user_id, o.total_amount,
--        oi.id AS item_id, oi.product_id, oi.product_name, oi.unit_price, oi.quantity, oi.line_total
-- FROM `orders` o
-- JOIN order_items oi ON oi.order_id = o.id
-- WHERE o.id = 1;



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



--user_log--

-- Drop existing triggers if they exist (to allow remaking)
DROP TRIGGER IF EXISTS after_users_insert;
DROP TRIGGER IF EXISTS after_users_update;
DROP TRIGGER IF EXISTS after_users_delete;

-- Drop the users_log table if it exists (to remake cleanly)
DROP TABLE IF EXISTS users_log;

-- Create the users_log table
CREATE TABLE IF NOT EXISTS users_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- Ensure user_id is nullable
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
    VALUES (NULL, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.profile_picture, OLD.last_login);
END //
DELIMITER ;

-- Ensure the users table is using InnoDB (required for triggers and foreign keys)
ALTER TABLE users ENGINE=InnoDB;

-- Clean up orphaned records in users_log
UPDATE users_log SET user_id = NULL WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users);



























--Sales--
CREATE TABLE IF NOT EXISTS sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  customer VARCHAR(100),
  quantity INT,
  total DECIMAL(10,2),
  status VARCHAR(50),
  user VARCHAR(50)
) ENGINE=InnoDB;



-- Drop old triggers / logs so we can recreate cleanly
DROP TRIGGER IF EXISTS trg_sales_after_insert;
DROP TRIGGER IF EXISTS trg_sales_after_update;
DROP TRIGGER IF EXISTS trg_sales_before_delete;


DROP TABLE IF EXISTS sales_log;

-- Create log tables (no foreign-key constraints: keep log data immutable)
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

-- Triggers for sales
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

-- IMPORTANT: use BEFORE DELETE so the parent row still exists while we log it
CREATE TRIGGER trg_sales_before_delete BEFORE DELETE ON sales
FOR EACH ROW
BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, quantity, total, status, user)
  VALUES (OLD.id, 'DELETE', OLD.date, OLD.customer, OLD.quantity, OLD.total, OLD.status, OLD.user);
END$$

DELIMITER ;

