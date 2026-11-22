-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 12:52 AM
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
-- Database: `bazaar_pk`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `variant` varchar(150) DEFAULT '',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `address` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'cod',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `total`, `status`, `address`, `payment_method`, `created_at`) VALUES
(1, 3, 6999.00, 'delivered', '132,dds, Lahore, Punjab', 'cod', '2026-05-06 10:19:35'),
(2, 6, 44999.00, 'delivered', '21,djbs,isb, Islamabad, Punjab', 'cod', '2026-05-06 10:56:27'),
(3, 3, 114999.00, 'delivered', '54.jh, Hyderabad, Sindh', 'cod', '2026-05-06 22:44:33'),
(4, 8, 9999.00, 'pending', '27,lahore, Lahore, Punjab', 'cod', '2026-05-07 07:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `vendor_id`, `quantity`, `price_at_purchase`) VALUES
(1, 1, 5, 1, 1, 6999.00),
(2, 2, 7, 2, 1, 44999.00),
(3, 3, 6, 2, 1, 114999.00),
(4, 4, 4, 1, 1, 9999.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `review_count` int(11) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_new` tinyint(1) DEFAULT 0,
  `is_hot` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `specs` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `vendor_id`, `title`, `description`, `price`, `old_price`, `stock`, `category`, `image`, `images`, `rating`, `review_count`, `sales_count`, `is_active`, `is_featured`, `is_new`, `is_hot`, `created_at`, `specs`) VALUES
(4, 1, 'Cricket Bat', 'High quality cricket bat , made up of pure english willow', 9999.00, 15000.00, 16, 'sports', 'prod_69fb0bbb4e644.png', 'prod_69fb0bbb4e644.png', 0.0, 0, 1, 1, 0, 0, 1, '2026-05-06 09:36:59', NULL),
(5, 1, 'Tennis Racket', 'High quality tennis racket made up of pure fiber material', 6999.00, 9999.98, 12, 'sports', 'prod_69fb0e963868f.jpg', 'prod_69fb0e963868f.jpg', 5.0, 1, 1, 1, 1, 1, 1, '2026-05-06 09:38:37', NULL),
(6, 2, 'Iphone 12 pro max', 'Official iphone 12 pro max, USA varient', 114999.00, 149999.99, 26, 'mobiles', 'prod_69fb1c268fc83.jpg', 'prod_69fb1c268fc83.jpg,prod_69fb1c2690164.jpg', 0.0, 0, 1, 1, 1, 0, 0, '2026-05-06 10:47:02', NULL),
(7, 2, 'Samsung s10 5g', 'Official Samsung S10 5g,japan', 44999.00, 52999.99, 15, 'mobiles', 'prod_69fb1ce367f0a.jpg', 'prod_69fb1ce367f0a.jpg', 4.0, 1, 1, 1, 0, 0, 1, '2026-05-06 10:50:11', NULL),
(8, 2, 'Tecno Camon 50i', 'Latest Mobile phone by tecno mobiles', 39999.00, 51999.98, 32, 'mobiles', 'prod_69fb1dc133141.jpg', 'prod_69fb1dc133141.jpg', 0.0, 0, 0, 1, 0, 0, 0, '2026-05-06 10:53:53', NULL),
(9, 3, 'Lenovo Laptop', 'Latest lenovo thinkpad', 69999.00, 94999.99, 34, 'laptops', 'prod_69fb8ea352154.jpg', 'prod_69fb8ea352154.jpg', 0.0, 0, 0, 1, 0, 0, 0, '2026-05-06 18:55:31', NULL),
(10, 3, 'Hp Laptop', 'Latest Edition', 74999.00, 89999.98, 22, 'laptops', 'prod_69fb8fc19119d.jpg', 'prod_69fb8fc19119d.jpg', 0.0, 0, 0, 1, 0, 0, 1, '2026-05-06 19:00:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_type` varchar(50) DEFAULT NULL,
  `variant_value` varchar(100) DEFAULT NULL,
  `price_adjust` decimal(10,2) DEFAULT 0.00,
  `stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `variant_type`, `variant_value`, `price_adjust`, `stock`) VALUES
(3, 6, '128gb', '114999', 0.00, 15),
(4, 6, '2556gb', '123999', 0.00, 8);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `review_text`, `created_at`) VALUES
(1, 5, 3, 5, 'the quality was so good', '2026-05-06 10:35:06'),
(2, 7, 6, 4, 'delivery was so quick', '2026-05-06 10:55:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','vendor','admin') NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cnic` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `created_at`, `cnic`) VALUES
(2, 'admin', 'your@email.com', '$2y$10$PjEvGokXK/rcEkHg4qU2GuFJxCrXjXJIOInEokbUu0.fn3V.Ho/Xi', 'admin', '2026-05-03 14:33:32', NULL),
(3, 'customer', 'customer@gmail.com', '$2y$10$HW6s1td1I/GU9DWEdQSjP.PXkGigRg4BzP1F0oKb5KoxoRStWWqWG', 'customer', '2026-05-03 14:41:52', NULL),
(4, 'vendor', 'vendor@gmail.com', '$2y$10$ZE1FsS5/tn7oDctLU8x.FenMQzIfVs2LdkqgsNUTq5EHLkeHZCEO.', 'vendor', '2026-05-03 14:45:04', NULL),
(5, 'vendor mobile', 'vendormobile@gmail.com', '$2y$10$dKkGEUdlF3udCUublXWimu8SuCgTDLakC//Uq2pcGoMYs.ayzY/X.', 'vendor', '2026-05-06 10:37:42', NULL),
(6, 'customer 2', 'customer2@gmail.com', '$2y$10$w.pm7KunbLsmp1CLBa.dQuiRaqIiITxc4MzgWvIHp.Ne3VclYLRS6', 'customer', '2026-05-06 10:55:06', NULL),
(7, 'vendor laptop', 'vendorlaptop@gmail.com', '$2y$10$HaCeNtGlyi28SqTo92VAheaSvJZkRKGoL2nD35AXZeOoG29S74s0u', 'vendor', '2026-05-06 17:41:54', NULL),
(8, 'nouman', 'nouman@gmail.com', '$2y$10$emd6EMVNXGWICZyLAd3xW.FiPsYdKNI.CamYQNVmqrjvGF6CIRpPG', 'customer', '2026-05-07 07:49:38', NULL),
(9, 'fahad', 'fahad@gmail.com', '$2y$10$0FyYG5qsxxJNjyeS.mEQC.PZvfLZ7obS.3ee4V4b/6bVU4nbWylxq', 'customer', '2026-05-07 09:12:46', '32516-2676781-6'),
(10, 'roshni', 'roshni@gmail.com', '$2y$10$jP81JAFMAkM1fz.SRwiSyeQnrQ7VAjLDzbUWnyAA9SwMO/ku5EOSq', 'vendor', '2026-05-07 20:10:58', '36373-3837383-8');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `status` enum('pending','approved','banned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `user_id`, `store_name`, `logo`, `bio`, `rating`, `status`, `created_at`) VALUES
(1, 4, 'Sports Market', 'vendor_69f75fd347dd1.jpg', 'We sell high quality sports products', 0.0, 'approved', '2026-05-03 14:46:43'),
(2, 5, 'Mobile Store', 'vendor_69fb1a731e29a.jpg', 'We deal with official mobile phones', 0.0, 'approved', '2026-05-06 10:39:47'),
(3, 7, 'Laptop Store', 'vendor_69fb7e8e3657d.png', 'We deal with High quality laptops', 0.0, 'approved', '2026-05-06 17:46:54'),
(4, 10, 'abc', NULL, 'hdncc', 0.0, 'approved', '2026-05-07 20:11:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`,`variant`);

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
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
