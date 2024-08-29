-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 29, 2024 at 05:37 AM
-- Server version: 10.3.39-MariaDB
-- PHP Version: 8.1.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mavenhostingserv_unzerecwid`
--

-- --------------------------------------------------------

--
-- Table structure for table `configurations`
--

CREATE TABLE `configurations` (
  `id` int(11) NOT NULL,
  `e_storeId` varchar(255) NOT NULL,
  `e_accessToken` text NOT NULL,
  `u_publicKey` text DEFAULT NULL,
  `u_privateKey` text DEFAULT NULL,
  `u_authStatus` varchar(255) DEFAULT NULL,
  `u_captureStatus` varchar(255) DEFAULT NULL,
  `u_chargeStatus` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `configurations`
--

INSERT INTO `configurations` (`id`, `e_storeId`, `e_accessToken`, `u_publicKey`, `u_privateKey`, `u_authStatus`, `u_captureStatus`, `u_chargeStatus`, `createdAt`, `updatedAt`) VALUES
(1, '15083087', 'secret_pkNidaczdQUqrzpWmrvGTSh5wTLmwwBC', 's-pub-2a10alqCpg0yVmqWiOXEgUKJOsZe9u5a', 's-priv-2a10LBWM8kCXJzxOp5yvlaT8fDpPD6ZZ', 'AWAITING_PAYMENT', 'PAID', 'REFUNDED', '2024-06-14 10:10:10', '2024-08-27 11:41:09');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `unzer_id` varchar(255) DEFAULT NULL,
  `redirectUrl` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `returnUrl` text DEFAULT NULL,
  `shopName` text DEFAULT NULL,
  `shopDescription` text DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `paymentId` varchar(255) DEFAULT NULL,
  `orderId` varchar(255) DEFAULT NULL,
  `invoiceId` varchar(255) DEFAULT NULL,
  `customerId` varchar(255) DEFAULT NULL,
  `basketId` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `unzer_id`, `redirectUrl`, `amount`, `currency`, `returnUrl`, `shopName`, `shopDescription`, `tagline`, `action`, `paymentId`, `orderId`, `invoiceId`, `customerId`, `basketId`, `created_at`, `updated_at`) VALUES
(1, 's-ppg-18e5adf8949d490b0f7601ff5de33c5099bba98dca0612993693919fc5ee6d87', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-18e5adf8949d490b0f7601ff5de33c5099bba98dca0612993693919fc5ee6d87', 120.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-513992695', '15083087', '', '', 'authorize', 's-pay-10765', '513992695', '513992695', 's-cst-7d988e9ae999', 's-bsk-a3d66b41a60e', '2024-08-21 11:09:41', '2024-08-21 11:45:24'),
(2, 's-ppg-bf9139a9e4e4baa33be2312141531a5eb474af31d1059d88b604c02ff2b3623d', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-bf9139a9e4e4baa33be2312141531a5eb474af31d1059d88b604c02ff2b3623d', 120.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-514014320', '15083087', '', '', 'authorize', 's-pay-10766', '514014320', '514014320', 's-cst-683b57ac4ee9', 's-bsk-ba78fe321540', '2024-08-21 12:00:22', '2024-08-21 12:00:22'),
(3, 's-ppg-e31ad825a1027d4e278f322f3d2a07a10f89267ee2124a379a345e50d844db8f', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-e31ad825a1027d4e278f322f3d2a07a10f89267ee2124a379a345e50d844db8f', 120.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-514167318', '15083087', '', '', 'authorize', 's-pay-10768', '514167318', '514167318', 's-cst-0c0d60243618', 's-bsk-598603a0a397', '2024-08-22 07:42:08', '2024-08-22 07:43:11'),
(4, 's-ppg-c820cb745b482b8efa5be539060ff41dd3ad3751c7ad0e5d69d4bfc22be79483', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-c820cb745b482b8efa5be539060ff41dd3ad3751c7ad0e5d69d4bfc22be79483', 120.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-514228104', '15083087', '', '', 'authorize', 's-pay-10769', '514228104', '514228104', 's-cst-b5d2c5349455', 's-bsk-2bfa1b66e78a', '2024-08-22 10:04:21', '2024-08-22 10:04:21'),
(5, 's-ppg-440330b4c9281f089655cd82c8331c9c07bc19bb0efcdc321dc29e7aaea35cfd', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-440330b4c9281f089655cd82c8331c9c07bc19bb0efcdc321dc29e7aaea35cfd', 246.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-514226000', '15083087', '', '', 'authorize', 's-pay-10770', '514226000', '514226000', 's-cst-a6a1ae6288e6', 's-bsk-bf3bd37acd01', '2024-08-22 10:07:59', '2024-08-22 10:07:59'),
(6, 's-ppg-495182c8a9bffa15389ab0d3d0922c8986690ee8be7e36b9c4e6f3e56850392d', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-495182c8a9bffa15389ab0d3d0922c8986690ee8be7e36b9c4e6f3e56850392d', 1.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-514366624', '15083087', '', '', 'authorize', 's-pay-10781', '514366624', '514366624', 's-cst-2e4a7e425218', 's-bsk-ed4d1458da38', '2024-08-23 06:27:31', '2024-08-23 06:27:31'),
(7, 's-ppg-65033e304cd63aebfd11a8a2a3848c6b2bf47f555c407daedd3c279cc671ac84', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-65033e304cd63aebfd11a8a2a3848c6b2bf47f555c407daedd3c279cc671ac84', 76.00, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-515012097', '15083087', '', '', 'authorize', 's-pay-10788', '515012097', '515012097', 's-cst-f60be3d5f0ba', 's-bsk-274f0e0fc412', '2024-08-27 07:42:48', '2024-08-27 08:15:45'),
(8, 's-ppg-3070655c8b1a6f25dd7bb09c429cdce670a7ff7cf51ced0deaf8de69802d2db0', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-3070655c8b1a6f25dd7bb09c429cdce670a7ff7cf51ced0deaf8de69802d2db0', 76.10, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-515020010', '15083087', '', '', 'authorize', 's-pay-10789', '515020010', '515020010', 's-cst-419b854708f0', 's-bsk-1c3f3b22cd21', '2024-08-27 08:34:30', '2024-08-27 08:34:30'),
(9, 's-ppg-35995e2d9fcd10be590a1e7e1cab3ba1e689c4afac881a893751d54e60a77408', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-35995e2d9fcd10be590a1e7e1cab3ba1e689c4afac881a893751d54e60a77408', 76.10, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-515060178', '15083087', '', '', 'authorize', 's-pay-10791', '515060178', '515060178', 's-cst-7d5dd2b09896', 's-bsk-0517897e9baa', '2024-08-27 11:42:45', '2024-08-27 11:42:45'),
(10, 's-ppg-7ec3c5e79f2151bba3b88e2243ae41cb988a13993565ecdb20da9016524c1243', 'https://sbx-payment.unzer.com/v1/paypage/s-ppg-7ec3c5e79f2151bba3b88e2243ae41cb988a13993565ecdb20da9016524c1243', 21.15, 'EUR', 'https://unzerecwid.mavenhostingservice.com/returnurl.php?order=15083087-515215371', '15083087', '', '', 'authorize', 's-pay-10792', '515215371', '515215371', 's-cst-ea366cd9efa6', 's-bsk-68dbe59d81aa', '2024-08-28 07:04:57', '2024-08-28 07:04:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `configurations`
--
ALTER TABLE `configurations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orderId` (`orderId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `configurations`
--
ALTER TABLE `configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
