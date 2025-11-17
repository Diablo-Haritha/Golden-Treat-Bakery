-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 06:01 PM
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
  `payment_method` varchar(50) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 8.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `customer_name`, `payment_method`, `discount`, `vat_percent`, `grand_total`, `user_id`, `created_at`) VALUES
(1, 'Kasun Perera', 'Cash', 0.00, 8.00, 1904.00, 1, '2025-11-17 13:15:08'),
(2, 'Nimali Silva', 'Card', 50.00, 8.00, 252.00, 2, '2025-11-17 13:15:08'),
(3, 'Ruwan Jayasinghe', 'Online', 0.00, 8.00, 2700.00, 1, '2025-11-17 13:15:08');

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `product_id`, `item_name`, `price`, `qty`) VALUES
(1, 1, 1, 'Chocolate Croissant', 3.50, 1),
(2, 1, NULL, 'Custom Soft Drink', 200.00, 2),
(3, 2, 2, 'Blueberry Muffin', 2.75, 3),
(4, 2, NULL, 'Egg Puff', 80.00, 5),
(5, 3, NULL, 'Pizza Large', 2500.00, 1),
(6, 3, 5, 'Strawberry Tart', 5.50, 2);

--
-- Triggers `bill_items`
--
DELIMITER $$
CREATE TRIGGER `trg_bill_items_after_insert` AFTER INSERT ON `bill_items` FOR EACH ROW BEGIN
    IF NEW.product_id IS NOT NULL THEN
        UPDATE products 
        SET stock_quantity = stock_quantity - NEW.qty 
        WHERE id = NEW.product_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `bookingId` varchar(50) NOT NULL,
  `customerName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `tableNumber` int(11) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings_log`
--

CREATE TABLE `bookings_log` (
  `log_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `operation` varchar(50) NOT NULL,
  `booking_ref` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(10) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `guests` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'ORD-000001', NULL, '2025-09-01', NULL, '', 'unknown1@example.com', 'Chocolate Cake', 1, 1, 2500.00, 2500.00, 2500.00, 'Cancelled', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0001', NULL, NULL, NULL),
(2, 'ORD-000002', NULL, '2025-09-02', NULL, '', 'unknown2@example.com', 'Blueberry Muffins (6 pack)', 1, 2, 1800.00, 0.00, 1800.00, 'Completed', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0002', NULL, NULL, NULL),
(3, 'ORD-000003', NULL, '2025-09-02', NULL, '', 'unknown3@example.com', 'Butter Croissant', 9, 12, 2400.00, 21600.00, 2400.00, 'Cancelled', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0003', NULL, NULL, NULL),
(4, 'ORD-000004', NULL, '2025-09-03', NULL, 'Dilshan Jayawardena', 'dilshan.jayawardena@example.com', 'Vanilla Cupcakes (12 pack)', 0, 1, 2200.00, 1000.00, 2200.00, 'Completed', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0004', NULL, NULL, NULL),
(6, 'ORD-000006', NULL, '2025-09-03', NULL, 'Fathima Rahman', 'fathima.rahman@example.com', 'Strawberry Tart', 2, 2, 3000.00, 6000.00, 3000.00, 'Ready for Pickup', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0006', NULL, '2025-10-01 16:19:23', NULL),
(7, 'ORD-000007', NULL, '2025-09-04', NULL, 'Gihan Abeysekera', 'gihan.abeysekera@example.com', 'Fruit Loaf', 1, 1, 1500.00, 1500.00, 1500.00, 'Out for Delivery', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0007', NULL, '2025-10-11 13:10:19', NULL),
(10, 'ORD-000010', NULL, '2025-09-04', NULL, 'Janani De', 'janani.de@example.com', 'Brownies', 7, 8, 1600.00, 11200.00, 1600.00, 'Partially Returned', '2025-09-03 23:08:03', '2025-11-17 16:57:58', '555-0010', NULL, NULL, NULL),
(21, 'ORD-000001', NULL, '2025-11-16', NULL, 'Customer 1', 'customer1@example.com', 'Product 1', 1, 1, 100.00, 100.00, 100.00, 'pending', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0021', NULL, NULL, NULL),
(22, 'ORD-000002', NULL, '2025-11-16', NULL, 'Customer 2', 'customer2@example.com', 'Product 2', 2, 2, 100.00, 200.00, 100.00, 'confirmed', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0022', NULL, NULL, NULL),
(23, 'ORD-000003', NULL, '2025-11-16', NULL, 'Customer 3', 'customer3@example.com', 'Product 3', 3, 3, 100.00, 300.00, 100.00, 'shipped', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0023', NULL, NULL, NULL),
(24, 'ORD-000004', NULL, '2025-11-16', NULL, 'Customer 4', 'customer4@example.com', 'Product 4', 4, 4, 100.00, 400.00, 100.00, 'delivered', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0024', NULL, NULL, NULL),
(25, 'ORD-000005', NULL, '2025-11-16', NULL, 'Customer 5', 'customer5@example.com', 'Product 5', 5, 5, 100.00, 500.00, 100.00, 'pending', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0025', NULL, NULL, NULL),
(26, 'ORD-000006', NULL, '2025-11-16', NULL, 'Customer 6', 'customer6@example.com', 'Product 6', 6, 6, 100.00, 600.00, 100.00, 'confirmed', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0026', NULL, NULL, NULL),
(27, 'ORD-000007', NULL, '2025-11-16', NULL, 'Customer 7', 'customer7@example.com', 'Product 7', 7, 7, 100.00, 700.00, 100.00, 'shipped', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0027', NULL, NULL, NULL),
(28, 'ORD-000008', NULL, '2025-11-16', NULL, 'Customer 8', 'customer8@example.com', 'Product 8', 8, 8, 100.00, 800.00, 100.00, 'delivered', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0028', NULL, NULL, NULL),
(29, 'ORD-000009', NULL, '2025-11-16', NULL, 'Customer 9', 'customer9@example.com', 'Product 9', 9, 9, 100.00, 900.00, 100.00, 'pending', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0029', NULL, NULL, NULL),
(30, 'ORD-000010', NULL, '2025-11-16', NULL, 'Customer 10', 'customer10@example.com', 'Product 10', 10, 10, 100.00, 1000.00, 100.00, 'confirmed', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0030', NULL, NULL, NULL),
(31, 'ORD-000011', NULL, '2025-11-16', NULL, 'Customer 11', 'customer11@example.com', 'Product 11', 1, 1, 1100.00, 1100.00, 1100.00, 'shipped', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0031', NULL, NULL, NULL),
(32, 'ORD-000012', NULL, '2025-11-16', NULL, 'Customer 12', 'customer12@example.com', 'Product 12', 2, 2, 600.00, 1200.00, 600.00, 'delivered', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0032', NULL, NULL, NULL),
(33, 'ORD-000013', NULL, '2025-11-16', NULL, 'Customer 13', 'customer13@example.com', 'Product 13', 0, 3, 433.33, 0.00, 433.33, 'Returned', '2025-11-17 16:30:21', '2025-11-17 16:59:25', '555-0033', NULL, NULL, NULL),
(34, 'ORD-000014', NULL, '2025-11-16', NULL, 'Customer 14', 'customer14@example.com', 'Product 14', 4, 4, 350.00, 1400.00, 350.00, 'confirmed', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0034', NULL, NULL, NULL),
(35, 'ORD-000015', NULL, '2025-11-16', NULL, 'Customer 15', 'customer15@example.com', 'Product 15', 5, 5, 300.00, 1500.00, 300.00, 'shipped', '2025-11-17 16:30:21', '2025-11-17 16:57:58', '555-0035', NULL, NULL, NULL);

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
(1, 4, NULL, 'butter', 1, 1000.00, NULL, '2025-11-17 13:16:57'),
(2, 10, NULL, 'butter', 1, 0.00, NULL, '2025-11-17 16:18:25'),
(3, 1, NULL, 'Product 1', 1, 100.00, NULL, '2025-11-17 16:30:21'),
(4, 2, NULL, 'Product 2', 2, 100.00, NULL, '2025-11-17 16:30:21'),
(5, 3, NULL, 'Product 3', 3, 100.00, NULL, '2025-11-17 16:30:21'),
(6, 4, NULL, 'Product 4', 4, 100.00, NULL, '2025-11-17 16:30:21'),
(8, 6, NULL, 'Product 6', 6, 100.00, NULL, '2025-11-17 16:30:21'),
(9, 7, NULL, 'Product 7', 7, 100.00, NULL, '2025-11-17 16:30:21'),
(12, 10, NULL, 'Product 10', 10, 100.00, NULL, '2025-11-17 16:30:21'),
(18, 1, NULL, 'Chocolate Cake', 1, 2500.00, NULL, '2025-09-03 23:08:03'),
(19, 2, NULL, 'Blueberry Muffins (6 pack)', 1, 1800.00, NULL, '2025-09-03 23:08:03'),
(20, 3, NULL, 'Butter Croissant', 9, 2400.00, NULL, '2025-09-03 23:08:03'),
(21, 4, NULL, 'Vanilla Cupcakes (12 pack)', 0, 2200.00, NULL, '2025-09-03 23:08:03'),
(22, 10, NULL, 'Brownies', 7, 1600.00, NULL, '2025-09-03 23:08:03');

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
(6, 0, 4, 'Returned', 'Completed', NULL, 'Updated through admin UI', '2025-11-17 08:16:57'),
(0, 0, 10, 'Cancelled', 'Partially Returned', NULL, 'Return processed (qty: 1, refund: 1000.00)', '2025-11-17 11:18:52'),
(0, 0, 33, 'pending', 'Returned', NULL, 'Return processed (qty: 3, refund: 433.33)', '2025-11-17 11:59:25');

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
(1, 'Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'default.jpg', 'Pastries', 1, 0.00, 1, 49, '2025-11-17 13:15:08'),
(2, 'Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'default.jpg', 'Muffins', 0, 10.00, 1, 27, '2025-11-17 13:15:08'),
(3, 'Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'default.jpg', 'Pastries', 0, 0.00, 1, 25, '2025-11-17 13:15:08'),
(4, 'Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'default.jpg', 'Cupcakes', 0, 0.00, 1, 40, '2025-11-17 13:15:08'),
(5, 'Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'default.jpg', 'Tarts', 0, 15.00, 1, 18, '2025-11-17 13:15:08');

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
(1, 0, 10, '2025-11-17', 1, 'test', 1000.00, NULL, '2025-11-17 11:18:52'),
(2, 0, 33, '2025-11-17', 3, 'test', 433.33, NULL, '2025-11-17 11:59:25');

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
(1, '2025-11-17', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-11-17 13:15:08'),
(2, '2025-11-17', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-11-17 13:15:08'),
(3, '2025-11-17', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-11-17 13:15:08'),
(5, '2025-11-17', 'Customer 1', NULL, 1, 100.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(6, '2025-11-17', 'Customer 2', NULL, 1, 200.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(7, '2025-11-17', 'Customer 3', NULL, 1, 300.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(8, '2025-11-17', 'Customer 4', NULL, 1, 400.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(9, '2025-11-17', 'Customer 5', NULL, 1, 500.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(10, '2025-11-17', 'Customer 6', NULL, 1, 600.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(11, '2025-11-17', 'Customer 7', NULL, 1, 700.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(12, '2025-11-17', 'Customer 8', NULL, 1, 800.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(13, '2025-11-17', 'Customer 9', NULL, 1, 900.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(14, '2025-11-17', 'Customer 10', NULL, 1, 1000.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(15, '2025-11-17', 'Customer 11', NULL, 1, 1100.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(16, '2025-11-17', 'Customer 12', NULL, 1, 1200.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(17, '2025-11-17', 'Customer 13', NULL, 1, 1300.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(18, '2025-11-17', 'Customer 14', NULL, 1, 1400.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(19, '2025-11-17', 'Customer 15', NULL, 1, 1500.00, 'Pending', 'Admin', '2025-11-17 16:30:21');

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
(1, 1, 'INSERT', '2025-11-17', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-11-17 13:15:08'),
(2, 2, 'INSERT', '2025-11-17', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-11-17 13:15:08'),
(3, 3, 'INSERT', '2025-11-17', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-11-17 13:15:08'),
(4, NULL, 'INSERT', '2025-11-17', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-11-17 13:15:08'),
(5, NULL, 'DELETE', '2025-11-17', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-11-17 13:15:08'),
(6, 5, 'INSERT', '2025-11-17', 'Customer 1', NULL, 1, 100.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(7, 6, 'INSERT', '2025-11-17', 'Customer 2', NULL, 1, 200.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(8, 7, 'INSERT', '2025-11-17', 'Customer 3', NULL, 1, 300.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(9, 8, 'INSERT', '2025-11-17', 'Customer 4', NULL, 1, 400.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(10, 9, 'INSERT', '2025-11-17', 'Customer 5', NULL, 1, 500.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(11, 10, 'INSERT', '2025-11-17', 'Customer 6', NULL, 1, 600.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(12, 11, 'INSERT', '2025-11-17', 'Customer 7', NULL, 1, 700.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(13, 12, 'INSERT', '2025-11-17', 'Customer 8', NULL, 1, 800.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(14, 13, 'INSERT', '2025-11-17', 'Customer 9', NULL, 1, 900.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(15, 14, 'INSERT', '2025-11-17', 'Customer 10', NULL, 1, 1000.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(16, 15, 'INSERT', '2025-11-17', 'Customer 11', NULL, 1, 1100.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(17, 16, 'INSERT', '2025-11-17', 'Customer 12', NULL, 1, 1200.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(18, 17, 'INSERT', '2025-11-17', 'Customer 13', NULL, 1, 1300.00, 'Pending', 'Admin', '2025-11-17 16:30:21'),
(19, 18, 'INSERT', '2025-11-17', 'Customer 14', NULL, 1, 1400.00, 'Paid', 'Admin', '2025-11-17 16:30:21'),
(20, 19, 'INSERT', '2025-11-17', 'Customer 15', NULL, 1, 1500.00, 'Pending', 'Admin', '2025-11-17 16:30:21');

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
(1, 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-11-17', 'Active', NULL, NULL, '*01A6717B58FF5C7EAFFF6CB7C96F7428EA65FE4C', '2025-11-17 13:15:08', '2025-11-17 13:15:08'),
(2, 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-11-17', 'Active', NULL, NULL, '*1B2333B70420F3DB5F4F164A9B89E21810F06840', '2025-11-17 13:15:08', '2025-11-17 13:15:08'),
(3, 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-11-17', 'Active', NULL, NULL, '*B1952B252B5963C480D1E8C04E89CB950F048185', '2025-11-17 13:15:08', '2025-11-17 13:15:08');

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
(1, 1, 'INSERT', 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-11-17', 'Active', NULL, NULL, '2025-11-17 13:15:08'),
(2, 2, 'INSERT', 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-11-17', 'Active', NULL, NULL, '2025-11-17 13:15:08'),
(3, 3, 'INSERT', 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-11-17', 'Active', NULL, NULL, '2025-11-17 13:15:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `product_id` (`product_id`);

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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings_log`
--
ALTER TABLE `bookings_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customizations`
--
ALTER TABLE `customizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `sales_log`
--
ALTER TABLE `sales_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users_log`
--
ALTER TABLE `users_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bill_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

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
