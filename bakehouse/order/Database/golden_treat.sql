-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2025 at 11:47 AM
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
(1, 'Kasun Perera', '2025-10-13 08:57:23'),
(2, 'Nimali Silva', '2025-10-13 08:57:23'),
(3, 'Ruwan Jayasinghe', '2025-10-13 08:57:23');

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
  `customer` varchar(100) NOT NULL,
  `product` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `original_quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(60) NOT NULL DEFAULT 'Order Received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mobile` varchar(32) DEFAULT NULL,
  `order_summary` longtext DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `order_date`, `customer`, `product`, `quantity`, `original_quantity`, `price`, `total_amount`, `original_price`, `status`, `created_at`, `updated_at`, `mobile`, `order_summary`, `deleted_at`, `deleted_by`) VALUES
(1, 'ORD-000001', NULL, '2025-09-01', 'Alice Fernando', 'Chocolate Cake', 1, 1, 2500.00, 2500.00, 2500.00, 'Order Received', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, NULL, NULL),
(2, 'ORD-000002', NULL, '2025-09-02', 'Brian Silva', 'Blueberry Muffins (6 pack)', 2, 2, 1800.00, 3600.00, 1800.00, 'Payment Confirmed', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, NULL, NULL),
(3, 'ORD-000003', NULL, '2025-09-02', 'Chathuri Perera', 'Butter Croissant', 9, 12, 2400.00, 21600.00, 2400.00, 'Partially Returned', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, NULL, NULL),
(4, 'ORD-000004', NULL, '2025-09-03', 'Dilshan Jayawardena', 'Vanilla Cupcakes (12 pack)', 1, 1, 2200.00, 2200.00, 2200.00, 'Order Received', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, NULL, NULL),
(6, 'ORD-000006', NULL, '2025-09-03', 'Fathima Rahman', 'Strawberry Tart', 2, 2, 3000.00, 6000.00, 3000.00, 'Ready for Pickup', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, '2025-10-01 16:19:23', NULL),
(7, 'ORD-000007', NULL, '2025-09-04', 'Gihan Abeysekera', 'Fruit Loaf', 1, 1, 1500.00, 1500.00, 1500.00, 'Out for Delivery', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, '2025-10-11 13:10:19', NULL),
(10, 'ORD-000010', NULL, '2025-09-04', 'Janani De Silva', 'Brownies', 8, 8, 1600.00, 12800.00, 1600.00, 'Cancelled', '2025-09-04 05:08:03', '2025-10-13 09:30:17', NULL, NULL, NULL, NULL);

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
(1, 'Chocolate Croissant', 'Flaky croissant filled with rich chocolate', 3.50, 'default.jpg', 'Pastries', 1, 0.00, 1, 50, '2025-10-13 08:57:23'),
(2, 'Blueberry Muffin', 'Freshly baked muffin with juicy blueberries', 2.75, 'default.jpg', 'Muffins', 0, 10.00, 1, 30, '2025-10-13 08:57:23'),
(3, 'Cinnamon Roll', 'Soft roll with cinnamon swirl and cream cheese glaze', 4.25, 'default.jpg', 'Pastries', 0, 0.00, 1, 25, '2025-10-13 08:57:23'),
(4, 'Vanilla Cupcake', 'Moist vanilla cupcake with buttercream frosting', 3.00, 'default.jpg', 'Cupcakes', 0, 0.00, 1, 40, '2025-10-13 08:57:23'),
(5, 'Strawberry Tart', 'Buttery tart shell filled with pastry cream and fresh strawberries', 5.50, 'default.jpg', 'Tarts', 0, 15.00, 1, 20, '2025-10-13 08:57:23');

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
(1, '2025-10-13', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-10-13 08:57:23'),
(2, '2025-10-13', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-10-13 08:57:23'),
(3, '2025-10-13', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-10-13 08:57:23');

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
(1, 1, 'INSERT', '2025-10-13', 'John Doe', 1, 3, 1500.00, 'Paid', 'Admin User', '2025-10-13 08:57:23'),
(2, 2, 'INSERT', '2025-10-13', 'Jane Smith', 2, 2, 900.00, 'Pending', 'Admin User', '2025-10-13 08:57:23'),
(3, 3, 'INSERT', '2025-10-13', 'Walk-in Customer', NULL, 1, 500.00, 'Paid', 'Admin User', '2025-10-13 08:57:23'),
(4, NULL, 'INSERT', '2025-10-13', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-10-13 08:57:23'),
(5, NULL, 'DELETE', '2025-10-13', 'Test Customer', NULL, 1, 2500.00, 'Pending', 'Admin', '2025-10-13 08:57:23');

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
(1, 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-10-13', 'Active', NULL, NULL, '*01A6717B58FF5C7EAFFF6CB7C96F7428EA65FE4C', '2025-10-13 08:57:23', '2025-10-13 08:57:23'),
(2, 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-10-13', 'Active', NULL, NULL, '*1B2333B70420F3DB5F4F164A9B89E21810F06840', '2025-10-13 08:57:23', '2025-10-13 08:57:23'),
(3, 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-10-13', 'Active', NULL, NULL, '*B1952B252B5963C480D1E8C04E89CB950F048185', '2025-10-13 08:57:23', '2025-10-13 08:57:23');

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
(1, 1, 'INSERT', 'Admin User', 'admin@example.com', '0771234567', 'Colombo', 'Colombo', 'admin', '2025-10-13', 'Active', NULL, NULL, '2025-10-13 08:57:23'),
(2, 2, 'INSERT', 'Manager User', 'manager@example.com', '0777654321', 'Kandy', 'Kandy', 'manager', '2025-10-13', 'Active', NULL, NULL, '2025-10-13 08:57:23'),
(3, 3, 'INSERT', 'Customer User', 'customer@example.com', '0751239876', 'Galle', 'Galle', 'customer', '2025-10-13', 'Active', NULL, NULL, '2025-10-13 08:57:23');

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_order_number` (`order_number`),
  ADD KEY `fk_orders_user_id` (`user_id`);

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
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id_new`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id_new` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales_log`
--
ALTER TABLE `sales_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
