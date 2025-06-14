-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2025 at 08:20 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bookstore`
--

-- --------------------------------------------------------

--
-- Table structure for table `book_images`
--

CREATE TABLE `book_images` (
  `image_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_type` enum('cover','gallery','thumbnail') DEFAULT 'gallery',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_password` varchar(100) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_address` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_email`, `customer_password`, `customer_name`, `customer_phone`, `customer_address`) VALUES
(1, 'cf23001@adab.umpsa.edu.my', '123password', 'Rami Benouahmane', '', ''),
(2, 'saeed@gmail.com', '123password', 'Mohammed Saeed', '', ''),
(3, 'abdullah@gmail.com', '123password', 'Badullah Alwahedi', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `delivery_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `delivery_status` enum('pending','in_progress','completed','cancelled','failed') DEFAULT 'pending',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `estimated_delivery` timestamp NULL DEFAULT NULL,
  `actual_delivery_time` timestamp NULL DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `delivery_number`, `order_id`, `courier_id`, `delivery_status`, `assigned_at`, `started_at`, `completed_at`, `estimated_delivery`, `actual_delivery_time`, `delivery_notes`, `cancel_reason`, `created_at`, `updated_at`) VALUES
(1, 'DEL-2025-001', 1, 1, 'pending', '2025-06-14 06:50:00', NULL, NULL, '2025-06-14 08:50:00', NULL, 'First delivery of the day', NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52'),
(2, 'DEL-2025-002', 2, 1, 'in_progress', '2025-06-14 06:50:00', NULL, NULL, '2025-06-14 07:50:00', NULL, 'Customer contacted, on the way', NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52'),
(3, 'DEL-2025-003', 3, 1, 'pending', '2025-06-14 06:50:00', NULL, NULL, '2025-06-14 09:50:00', NULL, 'Heavy package delivery', NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52'),
(4, 'DEL-2025-004', 4, 1, 'completed', '2025-06-14 06:50:00', NULL, NULL, '2025-06-14 05:50:00', NULL, 'Completed successfully', NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52'),
(5, 'DEL-2025-005', 5, 1, 'completed', '2025-06-14 06:50:00', NULL, NULL, '2025-06-14 04:50:00', NULL, 'Excellent delivery experience', NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `feedback_type` enum('smooth_delivery','customer_issue','address_problem','package_issue','traffic_delay','other') NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `customer_satisfaction` int(11) DEFAULT NULL CHECK (`customer_satisfaction` >= 1 and `customer_satisfaction` <= 5),
  `delivery_duration_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `delivery_id`, `courier_id`, `feedback_type`, `rating`, `comments`, `customer_satisfaction`, `delivery_duration_minutes`, `created_at`) VALUES
(1, 4, 1, 'smooth_delivery', 5, 'Customer was very satisfied. Delivery completed on time.', 5, 120, '2025-06-14 18:19:52'),
(2, 5, 1, 'smooth_delivery', 4, 'Good delivery, minor traffic delay but customer understanding.', 4, 150, '2025-06-14 18:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `order_contents` text NOT NULL,
  `order_total` decimal(10,2) NOT NULL,
  `order_status` enum('pending','confirmed','assigned','delivered','cancelled') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_number`, `customer_id`, `seller_id`, `customer_name`, `customer_email`, `customer_phone`, `customer_address`, `order_contents`, `order_total`, `order_status`, `priority`, `special_instructions`, `created_at`, `updated_at`) VALUES
(1, 'ORD-2025-001', 4, NULL, NULL, NULL, NULL, NULL, 'Programming Books (2x), Technical Manual (1x)', 75.99, 'assigned', 'medium', 'Customer requested delivery before 5 PM', '2025-06-14 06:50:00', '2025-06-14 18:19:52'),
(2, 'ORD-2025-002', 5, NULL, NULL, NULL, NULL, NULL, 'Fiction Novels (3x), Cookbook (1x)', 45.50, 'assigned', 'medium', 'Call customer 15 minutes before arrival', '2025-06-14 06:50:00', '2025-06-14 18:19:52'),
(3, 'ORD-2025-003', 6, NULL, NULL, NULL, NULL, NULL, 'Educational Textbooks (5x)', 120.75, 'assigned', 'medium', 'Heavy package - use service elevator', '2025-06-14 06:50:00', '2025-06-14 18:19:52'),
(4, 'ORD-2025-004', 7, NULL, NULL, NULL, NULL, NULL, 'Art & Design Books (4x)', 89.25, 'delivered', 'medium', 'Delivered successfully - left with receptionist', '2025-06-14 06:50:00', '2025-06-14 18:19:52'),
(5, 'ORD-2025-005', 8, NULL, NULL, NULL, NULL, NULL, 'Business & Finance Books (3x)', 65.00, 'delivered', 'medium', 'Customer very satisfied with service', '2025-06-14 06:50:00', '2025-06-14 18:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `password_reset_date` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `rating` int(1) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_activity_log`
--

CREATE TABLE `seller_activity_log` (
  `log_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_books`
--

CREATE TABLE `seller_books` (
  `book_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_public` tinyint(1) DEFAULT 1,
  `is_visible` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `cover_image` varchar(255) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `condition` varchar(50) DEFAULT NULL,
  `publication_year` int(4) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `language` varchar(50) DEFAULT 'English',
  `status` enum('available','out_of_stock','discontinued') DEFAULT 'available',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost_price` decimal(10,2) DEFAULT NULL,
  `book_condition` varchar(50) DEFAULT NULL,
  `condition_type` enum('new','good','fair','poor') DEFAULT 'new',
  `description` text DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `publisher` varchar(255) DEFAULT NULL,
  `pages` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `dimensions` varchar(50) DEFAULT NULL,
  `tags` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_books`
--

INSERT INTO `seller_books` (`book_id`, `seller_id`, `title`, `author`, `price`, `stock_quantity`, `created_at`, `is_public`, `is_visible`, `is_featured`, `cover_image`, `isbn`, `category`, `condition`, `publication_year`, `publication_date`, `language`, `status`, `updated_at`, `cost_price`, `book_condition`, `condition_type`, `description`, `view_count`, `sales_count`, `rating`, `date_added`, `publisher`, `pages`, `weight`, `dimensions`, `tags`) VALUES
(1, 1, 'Programming Fundamentals', 'John Programmer', 49.99, 10, '2025-06-14 18:19:52', 1, 1, 0, NULL, '1234567890123', 'Programming', NULL, NULL, NULL, 'English', 'available', '2025-06-14 18:19:52', NULL, NULL, 'new', 'Learn the basics of programming', 0, 0, 0.00, '2025-06-14 18:19:52', 'Tech Publisher', 350, NULL, NULL, NULL),
(2, 1, 'Web Development Guide', 'Jane Developer', 59.99, 8, '2025-06-14 18:19:52', 1, 1, 0, NULL, '1234567890124', 'Programming', NULL, NULL, NULL, 'English', 'available', '2025-06-14 18:19:52', NULL, NULL, 'new', 'Complete guide to web development', 0, 0, 0.00, '2025-06-14 18:19:52', 'Web Publisher', 450, NULL, NULL, NULL),
(3, 2, 'Database Design', 'Mike Database', 45.99, 12, '2025-06-14 18:19:52', 1, 1, 0, NULL, '1234567890125', 'Technical', NULL, NULL, NULL, 'English', 'available', '2025-06-14 18:19:52', NULL, NULL, 'new', 'Learn database design principles', 0, 0, 0.00, '2025-06-14 18:19:52', 'DB Publisher', 300, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `seller_notifications`
--

CREATE TABLE `seller_notifications` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_orders`
--

CREATE TABLE `seller_orders` (
  `order_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `order_status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_reviews`
--

CREATE TABLE `seller_reviews` (
  `review_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `rating` int(1) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_sessions`
--

CREATE TABLE `seller_sessions` (
  `session_id` varchar(128) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `session_data` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_users`
--

CREATE TABLE `seller_users` (
  `seller_id` int(11) NOT NULL,
  `seller_email` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `seller_password` varchar(255) NOT NULL,
  `seller_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `business_email` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `compact_view` tinyint(1) DEFAULT 0,
  `email_notifications` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `currency` varchar(10) DEFAULT 'USD',
  `notify_orders` tinyint(1) DEFAULT 0,
  `notify_messages` tinyint(1) DEFAULT 0,
  `notify_reviews` tinyint(1) DEFAULT 0,
  `notify_system` tinyint(1) DEFAULT 0,
  `notify_marketing` tinyint(1) DEFAULT 0,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL,
  `password_reset_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_users`
--

INSERT INTO `seller_users` (`seller_id`, `seller_email`, `email`, `seller_password`, `seller_name`, `created_at`, `phone`, `bio`, `website`, `location`, `business_name`, `business_type`, `business_address`, `business_phone`, `business_email`, `tax_id`, `profile_photo`, `dark_mode`, `compact_view`, `email_notifications`, `language`, `timezone`, `currency`, `notify_orders`, `notify_messages`, `notify_reviews`, `notify_system`, `notify_marketing`, `sms_notifications`, `two_factor_enabled`, `two_factor_secret`, `password_changed_at`, `registration_date`, `updated_at`, `remember_token`, `password_reset_date`) VALUES
(1, 'seller1@bookstore.com', NULL, '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', 'Seller One', '2025-06-15 06:50:00', '+1111222333', NULL, NULL, NULL, 'BookStore Seller 1', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 'en', 'UTC', 'USD', 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52', NULL, NULL),
(2, 'seller2@bookstore.com', NULL, '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', 'Seller Two', '2025-06-15 06:50:00', '+1222333444', NULL, NULL, NULL, 'BookStore Seller 2', NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 'en', 'UTC', 'USD', 0, 0, 0, 0, 0, 0, 0, NULL, NULL, '2025-06-14 18:19:52', '2025-06-14 18:19:52', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('Customer','Courier','Admin','Seller') NOT NULL DEFAULT 'Customer',
  `courier_id` varchar(10) DEFAULT NULL,
  `seller_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `phone_number`, `address`, `role`, `courier_id`, `seller_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Test Courier', 'test.courier@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1234567890', '123 Courier St, City', 'Courier', 'COR001', NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 08:47:39'),
(2, 'John Delivery', 'john.delivery@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1987654321', '456 Delivery Ave, City', 'Courier', 'COR002', NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(3, 'Sarah Fast', 'sarah.fast@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1555123456', '789 Express Rd, City', 'Courier', 'COR003', NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(4, 'John Smith', 'john.smith@email.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1234567890', '123 Main St, Downtown, City', 'Customer', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(5, 'Jane Doe', 'jane.doe@email.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1987654321', '456 Oak Ave, Suburb, City', 'Customer', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(6, 'Mike Johnson', 'mike.johnson@email.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1555123456', '789 Pine Rd, Eastside, City', 'Customer', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(7, 'Sarah Wilson', 'sarah.wilson@email.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1777888999', '321 Elm St, Northside, City', 'Customer', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(8, 'Robert Brown', 'robert.brown@email.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1666555444', '654 Cedar Ave, Westside, City', 'Customer', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(9, 'Admin User', 'admin@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1999888777', '100 Admin Plaza, City', 'Admin', NULL, NULL, 'active', '2025-06-14 06:50:00', '2025-06-14 06:50:00'),
(10, 'Seller One', 'seller1@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1111222333', '200 Seller Plaza, City', 'Seller', NULL, 1, 'active', '2025-06-15 06:50:00', '2025-06-15 06:50:00'),
(11, 'Seller Two', 'seller2@bookstore.com', '$2y$10$vFbmMr4XplcSSF5zuhln6.QQAv7c.51gZCwquysrI4fmToADq9.T2', '+1222333444', '300 Seller Ave, City', 'Seller', NULL, 2, 'active', '2025-06-15 06:50:00', '2025-06-15 06:50:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `book_images`
--
ALTER TABLE `book_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `delivery_number` (`delivery_number`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `courier_id` (`courier_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `courier_id` (`courier_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_number` (`order_number`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `f` (`book_id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seller_activity_log`
--
ALTER TABLE `seller_activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `ff` (`seller_id`);

--
-- Indexes for table `seller_books`
--
ALTER TABLE `seller_books`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `idx_title_search` (`title`(50));

--
-- Indexes for table `seller_notifications`
--
ALTER TABLE `seller_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seller_orders`
--
ALTER TABLE `seller_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `seller_reviews`
--
ALTER TABLE `seller_reviews`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `seller_sessions`
--
ALTER TABLE `seller_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `seller_users`
--
ALTER TABLE `seller_users`
  ADD PRIMARY KEY (`seller_id`),
  ADD UNIQUE KEY `seller_email` (`seller_email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `courier_id` (`courier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `book_images`
--
ALTER TABLE `book_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_activity_log`
--
ALTER TABLE `seller_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_books`
--
ALTER TABLE `seller_books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seller_notifications`
--
ALTER TABLE `seller_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_orders`
--
ALTER TABLE `seller_orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_reviews`
--
ALTER TABLE `seller_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_users`
--
ALTER TABLE `seller_users`
  MODIFY `seller_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`courier_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`delivery_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`courier_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
