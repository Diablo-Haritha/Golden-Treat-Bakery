-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 12:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gt_backup`
--

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `bookingId` varchar(50) NOT NULL,
  `customerName` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `tableNumber` int(11) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(32) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `customer` varchar(100) NOT NULL,
  `product` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `original_quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
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

INSERT INTO `orders` (`id`, `user_id`, `order_date`, `customer`, `product`, `quantity`, `original_quantity`, `price`, `total_amount`, `original_price`, `status`, `created_at`, `updated_at`, `mobile`, `deleted_at`) VALUES
(1, NULL, '2025-09-01', 'Alice Fernando', 'Chocolate Cake', 1, 1, 2500.00, 0.00, 2500.00, 'Order Received', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(2, NULL, '2025-09-02', 'Brian Silva', 'Blueberry Muffins (6 pack)', 2, 2, 1800.00, 0.00, 1800.00, 'Payment Confirmed', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(3, NULL, '2025-09-02', 'Chathuri Perera', 'Butter Croissant', 12, 12, 2400.00, 0.00, 2400.00, 'Queued for Baking', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(4, NULL, '2025-09-03', 'Dilshan Jayawardena', 'Vanilla Cupcakes (12 pack)', 1, 1, 2200.00, 0.00, 2200.00, 'In Preparation', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(6, NULL, '2025-09-03', 'Fathima Rahman', 'Strawberry Tart', 2, 2, 3000.00, 0.00, 3000.00, 'Ready for Pickup', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(7, NULL, '2025-09-04', 'Gihan Abeysekera', 'Fruit Loaf', 1, 1, 1500.00, 0.00, 1500.00, 'Out for Delivery', '2025-09-04 10:38:03', '2025-09-13 06:54:07', NULL, NULL),
(10, NULL, '2025-09-04', 'Janani De Silva', 'Brownies', 8, 8, 1600.00, 0.00, 1600.00, 'Cancelled', '2025-09-04 10:38:03', '2025-09-15 07:07:23', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

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

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `after_order_items_delete` AFTER DELETE ON `order_items` FOR EACH ROW BEGIN
  UPDATE `orders` SET total_amount = COALESCE(total_amount,0) - OLD.line_total WHERE id = OLD.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_order_items_update` AFTER UPDATE ON `order_items` FOR EACH ROW BEGIN
  UPDATE `orders` SET total_amount = COALESCE(total_amount,0) + (NEW.line_total - OLD.line_total) WHERE id = NEW.order_id;
  -- If the order_id changed (moved item between orders), also adjust the old order
  IF OLD.order_id <> NEW.order_id THEN
    UPDATE `orders` SET total_amount = COALESCE(total_amount,0) - OLD.line_total WHERE id = OLD.order_id;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_items_delete` BEFORE DELETE ON `order_items` FOR EACH ROW BEGIN
  UPDATE products SET quantity = quantity + OLD.quantity WHERE id = OLD.product_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_items_insert` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_order_items_update` BEFORE UPDATE ON `order_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

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

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `customer` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `total` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Completed','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `partNumber` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `category` varchar(100) NOT NULL,
  `status` enum('In Stock','Low','Out of Stock') DEFAULT 'In Stock',
  `unit` varchar(20) NOT NULL DEFAULT 'pcs'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `s_products`
--

CREATE TABLE `s_products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `district` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin','manager') NOT NULL DEFAULT 'customer',
  `date_joined` date NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_users_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NULL, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.profile_picture, OLD.last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_users_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'INSERT', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_users_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, profile_picture, last_login)
    VALUES (NEW.id, 'UPDATE', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.profile_picture, NEW.last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_after_delete_customer` AFTER DELETE ON `users` FOR EACH ROW BEGIN
  DELETE FROM customers WHERE user_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_after_insert_customer` AFTER INSERT ON `users` FOR EACH ROW BEGIN
  INSERT INTO customers (user_id, full_name, email, mobile, address, district, created_at, last_login)
  VALUES (NEW.id, NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NOW(), NEW.last_login)
  ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    mobile = VALUES(mobile),
    address = VALUES(address),
    district = VALUES(district),
    last_login = VALUES(last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_after_update_customer` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
  UPDATE customers
  SET full_name = NEW.full_name,
      email     = NEW.email,
      mobile    = NEW.mobile,
      address   = NEW.address,
      district  = NEW.district,
      last_login = NEW.last_login
  WHERE user_id = NEW.id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users_log`
--

CREATE TABLE `users_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `operation` varchar(50) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `role` enum('customer','admin','manager') DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bookingId` (`bookingId`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD UNIQUE KEY `ux_customers_user_id` (`user_id`),
  ADD KEY `idx_customers_email` (`email`);

--
-- Indexes for table `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD KEY `fk_orders_user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `s_products`
--
ALTER TABLE `s_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users_log`
--
ALTER TABLE `users_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_users_log_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `s_products`
--
ALTER TABLE `s_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users_log`
--
ALTER TABLE `users_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users_log`
--
ALTER TABLE `users_log`
  ADD CONSTRAINT `fk_users_log_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
