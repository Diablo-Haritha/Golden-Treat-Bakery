-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 06:14 PM
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
-- Database: `golden_treat`
--

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `customer_name`, `created_at`) VALUES
(1, 'Kasun Perera', '2025-10-15 13:28:47'),
(2, 'Nimali Silva', '2025-10-15 13:28:47'),
(3, 'Ruwan Jayasinghe', '2025-10-15 13:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `item_name`, `price`, `qty`) VALUES
(1, 1, 'Chocolate Cake', 1500.00, 1),
(2, 1, 'Soft Drink', 200.00, 2),
(3, 2, 'Butter Bread', 120.00, 3),
(4, 2, 'Egg Puff', 80.00, 5),
(5, 3, 'Pizza Large', 2500.00, 1),
(6, 3, 'Iced Coffee', 450.00, 2);

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

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `bookingId`, `customerName`, `date`, `time`, `tableNumber`, `status`) VALUES
(1, 'BID1001', 'hritha', '2025-10-16', '23:04:00', 13, 'Pending');

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `trg_bookings_after_insert` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    INSERT INTO bookings_log (
        booking_id, operation, booking_ref, customerName, date, time, tableNumber, status
    ) VALUES (
        NEW.id, 'INSERT', NEW.bookingId, NEW.customerName, NEW.date, NEW.time, NEW.tableNumber, NEW.status
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bookings_after_update` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    INSERT INTO bookings_log (
        booking_id, operation, booking_ref, customerName, date, time, tableNumber, status
    ) VALUES (
        NEW.id, 'UPDATE', NEW.bookingId, NEW.customerName, NEW.date, NEW.time, NEW.tableNumber, NEW.status
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bookings_before_delete` BEFORE DELETE ON `bookings` FOR EACH ROW BEGIN
    INSERT INTO bookings_log (
        booking_id, operation, booking_ref, customerName, date, time, tableNumber, status
    ) VALUES (
        OLD.id, 'DELETE', OLD.bookingId, OLD.customerName, OLD.date, OLD.time, OLD.tableNumber, OLD.status
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bookings_log`
--

CREATE TABLE `bookings_log` (
  `log_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `operation` varchar(50) NOT NULL,
  `booking_ref` varchar(50) DEFAULT NULL,
  `customerName` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `tableNumber` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings_log`
--

INSERT INTO `bookings_log` (`log_id`, `booking_id`, `operation`, `booking_ref`, `customerName`, `date`, `time`, `tableNumber`, `status`, `log_timestamp`) VALUES
(1, 1, 'INSERT', 'BID1001', 'hritha', '2025-10-16', '23:04:00', 13, 'Pending', '2025-10-15 13:29:37');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `selected_customizations` text DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customizations`
--

CREATE TABLE `customizations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price_adjustment` decimal(10,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT 'General',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customizations`
--

INSERT INTO `customizations` (`id`, `name`, `price_adjustment`, `category`, `is_active`) VALUES
(1, 'Extra Chocolate', 0.75, 'Toppings', 1),
(2, 'Almond Topping', 0.50, 'Toppings', 1),
(3, 'Walnut Topping', 0.75, 'Toppings', 1),
(4, 'Sprinkles', 0.25, 'Toppings', 1),
(5, 'Chocolate Drizzle', 0.50, 'Toppings', 1),
(6, 'Gluten-Free', 1.00, 'Dietary', 1),
(7, 'Vegan', 1.50, 'Dietary', 1),
(8, 'Extra Large', 2.00, 'Size', 1),
(9, 'Birthday Message', 1.00, 'Special', 1),
(10, 'Wedding Decoration', 3.00, 'Special', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `product` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `original_quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(60) NOT NULL DEFAULT 'Order Received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `customer_phone` varchar(32) DEFAULT NULL,
  `order_summary` longtext DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `order_date`, `session_id`, `customer_name`, `customer_email`, `product`, `quantity`, `original_quantity`, `price`, `total_amount`, `original_price`, `status`, `created_at`, `updated_at`, `customer_phone`, `order_summary`, `deleted_at`, `deleted_by`) VALUES
(1, 'ORD-000001', NULL, '2025-09-01', NULL, '', '', 'Chocolate Cake', 0, 1, 2500.00, 0.00, 2500.00, 'Returned', '2025-09-03 12:38:03', '2025-10-15 13:36:13', NULL, NULL, NULL, NULL),
(2, 'ORD-000002', NULL, '2025-09-02', NULL, '', '', 'Blueberry Muffins (6 pack)', 0, 2, 1800.00, 0.00, 1800.00, 'Returned', '2025-09-03 12:38:03', '2025-10-15 13:36:38', NULL, NULL, NULL, NULL),
(3, 'ORD-000003', NULL, '2025-09-02', NULL, '', '', 'Butter Croissant', 9, 12, 2400.00, 21600.00, 2400.00, 'Cancelled', '2025-09-03 12:38:03', '2025-10-13 17:56:28', NULL, NULL, NULL, NULL),
(4, 'ORD-000004', NULL, '2025-09-03', NULL, 'Dilshan Jayawardena', '', 'Vanilla Cupcakes (12 pack)', 0, 1, 2200.00, 0.00, 2200.00, 'Returned', '2025-09-03 12:38:03', '2025-10-13 17:30:20', NULL, NULL, NULL, NULL),
(6, 'ORD-000006', NULL, '2025-09-03', NULL, 'Fathima Rahman', '', 'Strawberry Tart', 2, 2, 3000.00, 6000.00, 3000.00, 'Ready for Pickup', '2025-09-03 12:38:03', '2025-10-12 17:00:17', NULL, NULL, '2025-10-01 16:19:23', NULL),
(7, 'ORD-000007', NULL, '2025-09-04', NULL, 'Gihan Abeysekera', '', 'Fruit Loaf', 1, 1, 1500.00, 1500.00, 1500.00, 'Out for Delivery', '2025-09-03 12:38:03', '2025-10-12 17:00:17', NULL, NULL, '2025-10-11 13:10:19', NULL),
(10, 'ORD-000010', NULL, '2025-09-04', NULL, 'Janani De Silva', '', 'Brownies', 8, 8, 1600.00, 12800.00, 1600.00, 'Cancelled', '2025-09-03 12:38:03', '2025-10-12 17:00:17', NULL, NULL, NULL, NULL),
(21, 'GT20251015560', 4, '0000-00-00', NULL, 'Broken', 'broken@gmail.com', 'Vanilla Cupcake x1', 1, 0, 3.00, 3.00, 0.00, 'Order Received', '2025-10-15 15:29:21', '2025-10-15 15:41:02', '0762907982', NULL, NULL, NULL),
(22, 'GT20251015396', 4, '0000-00-00', NULL, 'hi', 'broken@gmail.com', '', 1, 0, 0.00, 5.00, 0.00, 'Order Received', '2025-10-15 16:09:47', '2025-10-15 16:09:47', '0762907982', NULL, NULL, NULL);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_orders_after_insert_auto_sale` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customizations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `customizations`, `created_at`) VALUES
(0, 21, NULL, 'Vanilla Cupcake', 1, 3.00, '[]', '2025-10-15 15:29:21'),
(0, 22, NULL, 'Cinnamon Roll', 1, 5.00, '[1]', '2025-10-15 16:09:47');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id_new` int(11) NOT NULL,
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

INSERT INTO `order_status_history` (`id_new`, `id`, `order_id`, `old_status`, `new_status`, `changed_by`, `note`, `created_at`) VALUES
(1, 0, 3, 'Queued for Baking', 'Completed', NULL, 'Updated through admin UI', '2025-10-01 15:52:03'),
(2, 0, 3, 'Returned', 'Partially Returned', NULL, 'Return processed (qty: 1)', '2025-10-01 16:04:17'),
(3, 0, 4, 'In Preparation', 'Order Received', NULL, 'Updated through admin UI', '2025-10-01 16:07:01'),
(4, 0, 6, 'Ready for Pickup', 'Deleted', NULL, 'Order soft-deleted via admin UI', '2025-10-01 16:19:23'),
(5, 0, 7, 'Out for Delivery', 'Deleted', NULL, 'Order soft-deleted via admin UI', '2025-10-11 13:10:19'),
(0, 0, 1, 'Cancelled', 'Returned', NULL, 'Return processed (qty: 1, refund: 2500.00)', '2025-10-15 19:06:13'),
(0, 0, 2, 'Completed', 'Returned', NULL, 'Return processed (qty: 1, refund: 1800.00)', '2025-10-15 19:06:38');

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'default.jpg',
  `category` varchar(100) DEFAULT NULL,
  `is_daily_special` tinyint(1) DEFAULT 0,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `visibility` tinyint(1) DEFAULT 1,
  `stock_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image`, `category`, `is_daily_special`, `discount_percentage`, `visibility`, `stock_quantity`, `created_at`) VALUES
(1, 'Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'default.jpg', 'Pastries', 1, 0.00, 1, 50, '2025-10-15 13:28:47'),
(2, 'Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'default.jpg', 'Muffins', 0, 10.00, 1, 30, '2025-10-15 13:28:47'),
(3, 'Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'default.jpg', 'Pastries', 0, 0.00, 1, 24, '2025-10-15 13:28:47'),
(4, 'Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'default.jpg', 'Cupcakes', 0, 0.00, 1, 39, '2025-10-15 13:28:47'),
(5, 'Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'default.jpg', 'Tarts', 0, 15.00, 1, 20, '2025-10-15 13:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `product_customizations`
--

CREATE TABLE `product_customizations` (
  `product_id` int(11) NOT NULL,
  `customization_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_customizations`
--

INSERT INTO `product_customizations` (`product_id`, `customization_id`) VALUES
(1, 1),
(1, 2),
(1, 6),
(1, 7),
(1, 8),
(2, 3),
(2, 4),
(2, 6),
(2, 7),
(2, 8),
(3, 1),
(3, 3),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(4, 4),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(5, 4),
(5, 5),
(5, 6),
(5, 10);

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id_new` int(11) NOT NULL,
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
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`id_new`, `id`, `order_id`, `return_date`, `quantity`, `reason`, `refund_amount`, `processed_by`, `created_at`) VALUES
(1, 0, 1, '2025-10-15', 1, '', 2500.00, NULL, '2025-10-15 19:06:13'),
(2, 0, 2, '2025-10-15', 1, '', 1800.00, NULL, '2025-10-15 19:06:38');

--
-- Triggers `returns`
--
DELIMITER $$
CREATE TRIGGER `trg_returns_after_delete` AFTER DELETE ON `returns` FOR EACH ROW BEGIN
  DECLARE v_curr_qty INT DEFAULT 0;
  DECLARE v_price_per_unit DECIMAL(12,4) DEFAULT 0.00;
  DECLARE v_old_status VARCHAR(64) DEFAULT '';
  DECLARE v_new_qty INT DEFAULT 0;
  DECLARE v_new_total DECIMAL(12,2) DEFAULT 0.00;
  DECLARE v_new_status VARCHAR(64) DEFAULT '';
  DECLARE v_changed_by INT;

  SELECT `quantity`, `price`, `status`
    INTO v_curr_qty, v_price_per_unit, v_old_status
    FROM `orders`
    WHERE `id` = OLD.order_id
    LIMIT 1;

  IF v_curr_qty IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order missing during AFTER DELETE';
  END IF;

  SET v_new_qty = v_curr_qty + OLD.quantity;
  SET v_new_total = ROUND(v_price_per_unit * v_new_qty, 2);
  SET v_new_status = 'Restored';

  UPDATE `orders`
    SET `quantity` = v_new_qty,
        `total_amount` = v_new_total,
        `status` = v_new_status
    WHERE `id` = OLD.order_id;

  SET v_changed_by = IFNULL(OLD.processed_by, NULL);

  INSERT INTO `order_status_history`
    (`order_id`, `old_status`, `new_status`, `changed_by`, `note`, `created_at`)
  VALUES
    (OLD.order_id, v_old_status, v_new_status, v_changed_by,
     CONCAT('Return restored (qty: ', OLD.quantity, ')'),
     CURRENT_TIMESTAMP());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_returns_after_insert` AFTER INSERT ON `returns` FOR EACH ROW BEGIN
  DECLARE v_old_qty INT DEFAULT 0;
  DECLARE v_price_per_unit DECIMAL(12,4) DEFAULT 0.00;
  DECLARE v_old_status VARCHAR(64) DEFAULT '';
  DECLARE v_new_qty INT DEFAULT 0;
  DECLARE v_new_total DECIMAL(12,2) DEFAULT 0.00;
  DECLARE v_new_status VARCHAR(64) DEFAULT '';
  DECLARE v_changed_by INT;

  -- read current order details
  SELECT `quantity`, `price`, `status`
    INTO v_old_qty, v_price_per_unit, v_old_status
    FROM `orders`
    WHERE `id` = NEW.order_id
    LIMIT 1;

  IF v_old_qty IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order missing during AFTER INSERT';
  END IF;

  SET v_new_qty = GREATEST(v_old_qty - NEW.quantity, 0);
  SET v_new_total = ROUND(v_price_per_unit * v_new_qty, 2);

  IF v_new_qty = 0 THEN
    SET v_new_status = 'Returned';
  ELSE
    SET v_new_status = 'Partially Returned';
  END IF;

  UPDATE `orders`
    SET `quantity` = v_new_qty,
        `total_amount` = v_new_total,
        `status` = v_new_status
    WHERE `id` = NEW.order_id;

  SET v_changed_by = IFNULL(NEW.processed_by, NULL);

  INSERT INTO `order_status_history`
    (`order_id`, `old_status`, `new_status`, `changed_by`, `note`, `created_at`)
  VALUES
    (NEW.order_id, v_old_status, v_new_status, v_changed_by,
     CONCAT('Return processed (qty: ', NEW.quantity, ', refund: ', IFNULL(NEW.refund_amount,0), ')'),
     CURRENT_TIMESTAMP());
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_returns_before_insert` BEFORE INSERT ON `returns` FOR EACH ROW BEGIN
  DECLARE v_order_qty INT;

  SELECT `quantity` INTO v_order_qty
    FROM `orders`
    WHERE `id` = NEW.order_id
    LIMIT 1;

  IF v_order_qty IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Referenced order not found';
  END IF;

  IF NEW.quantity IS NULL OR NEW.quantity <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Return quantity must be at least 1';
  END IF;

  IF NEW.quantity > v_order_qty THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Return quantity exceeds available order quantity';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `customer` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Cancelled','Returned') DEFAULT 'Pending',
  `staff` varchar(100) DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `date`, `customer`, `user_id`, `quantity`, `total`, `status`, `staff`, `created_at`) VALUES
(1, '2025-10-15', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-10-15 13:28:47'),
(2, '2025-10-15', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-10-15 13:28:47'),
(3, '2025-10-15', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-10-15 13:28:47'),
(5, '2025-10-15', 'Broken', NULL, 1, 3.00, 'Pending', 'Admin', '2025-10-15 15:29:21'),
(6, '2025-10-15', 'hi', NULL, 1, 5.00, 'Pending', 'Admin', '2025-10-15 16:09:47');

--
-- Triggers `sales`
--
DELIMITER $$
CREATE TRIGGER `trg_sales_after_insert` AFTER INSERT ON `sales` FOR EACH ROW BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (NEW.id, 'INSERT', NEW.date, NEW.customer, NEW.user_id, NEW.quantity, NEW.total, NEW.status, NEW.staff);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sales_after_update` AFTER UPDATE ON `sales` FOR EACH ROW BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (NEW.id, 'UPDATE', NEW.date, NEW.customer, NEW.user_id, NEW.quantity, NEW.total, NEW.status, NEW.staff);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sales_before_delete` BEFORE DELETE ON `sales` FOR EACH ROW BEGIN
  INSERT INTO sales_log (sale_id, operation, date, customer, user_id, quantity, total, status, staff)
  VALUES (OLD.id, 'DELETE', OLD.date, OLD.customer, OLD.user_id, OLD.quantity, OLD.total, OLD.status, OLD.staff);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales_log`
--

CREATE TABLE `sales_log` (
  `log_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `operation` varchar(50) NOT NULL,
  `date` date DEFAULT NULL,
  `customer` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Paid','Cancelled','Returned') DEFAULT NULL,
  `staff` varchar(100) DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_log`
--

INSERT INTO `sales_log` (`log_id`, `sale_id`, `operation`, `date`, `customer`, `user_id`, `quantity`, `total`, `status`, `staff`, `log_timestamp`) VALUES
(1, 1, 'INSERT', '2025-10-15', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-10-15 13:28:47'),
(2, 2, 'INSERT', '2025-10-15', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-10-15 13:28:47'),
(3, 3, 'INSERT', '2025-10-15', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-10-15 13:28:47'),
(4, NULL, 'INSERT', '2025-10-15', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-10-15 13:28:47'),
(5, NULL, 'DELETE', '2025-10-15', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-10-15 13:28:47'),
(6, 5, 'INSERT', '2025-10-15', 'Broken', NULL, 1, 3.00, 'Pending', 'Admin', '2025-10-15 15:29:21'),
(7, 6, 'INSERT', '2025-10-15', 'hi', NULL, 1, 5.00, 'Pending', 'Admin', '2025-10-15 16:09:47');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(255) NOT NULL,
  `shop_slogan` varchar(255) DEFAULT NULL,
  `shop_tel` varchar(50) DEFAULT NULL,
  `shop_email` varchar(255) DEFAULT NULL,
  `shop_address` text DEFAULT NULL,
  `thank_note` text DEFAULT NULL,
  `vat_percent` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `shop_name`, `shop_slogan`, `shop_tel`, `shop_email`, `shop_address`, `thank_note`, `vat_percent`) VALUES
(1, 'Golden Treat Bakery', 'Fresh & Tasty Every Day', '011-2345678', 'golden@example.com', '123 Main Street, Colombo', 'Thank you for visiting Golden Treat!', 8.00);

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

--
-- Triggers `stock`
--
DELIMITER $$
CREATE TRIGGER `trg_stock_after_insert` AFTER INSERT ON `stock` FOR EACH ROW BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'INSERT', NEW.partNumber, NEW.date, NEW.description, NEW.quantity, NEW.category, NEW.status, NEW.unit
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_stock_after_update` AFTER UPDATE ON `stock` FOR EACH ROW BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'UPDATE', NEW.partNumber, NEW.date, NEW.description, NEW.quantity, NEW.category, NEW.status, NEW.unit
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_stock_before_delete` BEFORE DELETE ON `stock` FOR EACH ROW BEGIN
    INSERT INTO stock_log (
        operation, partNumber, date, description, quantity, category, status, unit
    )
    VALUES (
        'DELETE', OLD.partNumber, OLD.date, OLD.description, OLD.quantity, OLD.category, OLD.status, OLD.unit
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_log`
--

CREATE TABLE `stock_log` (
  `log_id` int(11) NOT NULL,
  `operation` varchar(50) NOT NULL,
  `partNumber` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('In Stock','Low','Out of Stock') DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `role` enum('admin','manager','customer') NOT NULL DEFAULT 'customer',
  `date_joined` date DEFAULT curdate(),
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `mobile`, `address`, `district`, `role`, `date_joined`, `status`, `profile_picture`, `last_login`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-10-15', 'Active', NULL, NULL, '*01A6717B58FF5C7EAFFF6CB7C96F7428EA65FE4C', '2025-10-15 13:28:47', '2025-10-15 13:28:47'),
(2, 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-10-15', 'Active', NULL, NULL, '*1B2333B70420F3DB5F4F164A9B89E21810F06840', '2025-10-15 13:28:47', '2025-10-15 13:28:47'),
(3, 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-10-15', 'Active', NULL, NULL, '*B1952B252B5963C480D1E8C04E89CB950F048185', '2025-10-15 13:28:47', '2025-10-15 13:28:47'),
(4, 'Broken', 'broken@gmail.com', '0762907982', 'dfds', 'djbkfs', 'customer', '2025-10-15', 'Active', NULL, NULL, '$2y$10$8A1xXcvVPpnYN.B0mYa/2u1nkRp8MqjKjGtMy.KaXicWon0oq3slu', '2025-10-15 15:28:24', '2025-10-15 15:28:24');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `trg_users_after_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (NEW.id, 'INSERT', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.status, NEW.profile_picture, NEW.last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_after_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (NEW.id, 'UPDATE', NEW.full_name, NEW.email, NEW.mobile, NEW.address, NEW.district, NEW.role, NEW.date_joined, NEW.status, NEW.profile_picture, NEW.last_login);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_users_before_delete` BEFORE DELETE ON `users` FOR EACH ROW BEGIN
    INSERT INTO users_log (user_id, operation, full_name, email, mobile, address, district, role, date_joined, status, profile_picture, last_login)
    VALUES (OLD.id, 'DELETE', OLD.full_name, OLD.email, OLD.mobile, OLD.address, OLD.district, OLD.role, OLD.date_joined, OLD.status, OLD.profile_picture, OLD.last_login);
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
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `role` enum('admin','manager','customer') DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_log`
--

INSERT INTO `users_log` (`log_id`, `user_id`, `operation`, `full_name`, `email`, `mobile`, `address`, `district`, `role`, `date_joined`, `status`, `profile_picture`, `last_login`, `log_timestamp`) VALUES
(1, 1, 'INSERT', 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-10-15', 'Active', NULL, NULL, '2025-10-15 13:28:47'),
(2, 2, 'INSERT', 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-10-15', 'Active', NULL, NULL, '2025-10-15 13:28:47'),
(3, 3, 'INSERT', 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-10-15', 'Active', NULL, NULL, '2025-10-15 13:28:47'),
(4, 4, 'INSERT', 'Broken', 'broken@gmail.com', '0762907982', 'dfds', 'djbkfs', 'customer', '2025-10-15', 'Active', NULL, NULL, '2025-10-15 15:28:24');

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
-- Indexes for table `bookings_log`
--
ALTER TABLE `bookings_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `customizations`
--
ALTER TABLE `customizations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_customizations`
--
ALTER TABLE `product_customizations`
  ADD PRIMARY KEY (`product_id`,`customization_id`),
  ADD KEY `customization_id` (`customization_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sales_log`
--
ALTER TABLE `sales_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_log`
--
ALTER TABLE `stock_log`
  ADD PRIMARY KEY (`log_id`);

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
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings_log`
--
ALTER TABLE `bookings_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customizations`
--
ALTER TABLE `customizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales_log`
--
ALTER TABLE `sales_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_log`
--
ALTER TABLE `stock_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users_log`
--
ALTER TABLE `users_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings_log`
--
ALTER TABLE `bookings_log`
  ADD CONSTRAINT `bookings_log_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otps`
--
ALTER TABLE `otps`
  ADD CONSTRAINT `otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_customizations`
--
ALTER TABLE `product_customizations`
  ADD CONSTRAINT `product_customizations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_customizations_ibfk_2` FOREIGN KEY (`customization_id`) REFERENCES `customizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_log`
--
ALTER TABLE `sales_log`
  ADD CONSTRAINT `sales_log_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users_log`
--
ALTER TABLE `users_log`
  ADD CONSTRAINT `users_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
