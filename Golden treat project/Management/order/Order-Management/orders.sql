-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2025 at 07:16 PM
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
-- Database: `gt`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `customer` varchar(100) NOT NULL,
  `product` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Order Received','Payment Confirmed','Queued for Baking','In Preparation','Decorating','Ready for Pickup','Out for Delivery','Completed','Cancelled','Refunded') NOT NULL DEFAULT 'Order Received',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_date`, `customer`, `product`, `quantity`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-09-01', 'Alice Fernando', 'Chocolate Cake', 1, 2500.00, 'Order Received', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(2, '2025-09-02', 'Brian Silva', 'Blueberry Muffins (6 pack)', 2, 1800.00, 'Payment Confirmed', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(3, '2025-09-02', 'Chathuri Perera', 'Butter Croissant', 12, 2400.00, 'Queued for Baking', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(4, '2025-09-03', 'Dilshan Jayawardena', 'Vanilla Cupcakes (12 pack)', 1, 2200.00, 'In Preparation', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(5, '2025-09-03', 'Erandi Rathnayake', 'Wedding Cake', 1, 12000.00, 'Decorating', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(6, '2025-09-03', 'Fathima Rahman', 'Strawberry Tart', 2, 3000.00, 'Ready for Pickup', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(7, '2025-09-04', 'Gihan Abeysekera', 'Fruit Loaf', 1, 1500.00, 'Out for Delivery', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(8, '2025-09-04', 'Hansika Karunaratne', 'Cinnamon Rolls', 6, 1200.00, 'Completed', '2025-09-04 16:08:03', '2025-09-04 16:18:52'),
(9, '2025-09-04', 'Ishan Bandara', 'Cheese Cake', 1, 3200.00, 'Cancelled', '2025-09-04 16:08:03', '2025-09-04 16:08:03'),
(10, '2025-09-04', 'Janani De Silva', 'Brownies', 8, 1600.00, 'Refunded', '2025-09-04 16:08:03', '2025-09-04 16:18:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
