-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2024 at 06:45 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

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
  `e_storeName` text DEFAULT NULL,
  `e_accessToken` text NOT NULL,
  `u_publicKey` text DEFAULT NULL,
  `u_privateKey` text DEFAULT NULL,
  `u_authStatus` varchar(255) DEFAULT NULL,
  `u_captureStatus` varchar(255) DEFAULT NULL,
  `u_chargeStatus` varchar(255) DEFAULT NULL,
  `u_autocapture` varchar(255) DEFAULT NULL,
  `u_webhookId` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ecwid_webhook`
--

CREATE TABLE `ecwid_webhook` (
  `id` int(11) NOT NULL,
  `ecwid_store_id` varchar(255) DEFAULT NULL,
  `ecwid_order_id` varchar(255) DEFAULT NULL,
  `unzer_action` text DEFAULT NULL,
  `unzer_payment_id` text DEFAULT NULL,
  `ecwid_payment_status` text DEFAULT NULL,
  `unzer_api_execute_id` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `unzer_id` varchar(255) DEFAULT NULL,
  `paymentMethodName` text DEFAULT NULL,
  `redirectUrl` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `amountUpdated` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT NULL,
  `returnUrl` text DEFAULT NULL,
  `shopName` text DEFAULT NULL,
  `shopDescription` text DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `paymentId` varchar(255) DEFAULT NULL,
  `chargedId` text DEFAULT NULL,
  `orderId` varchar(255) DEFAULT NULL,
  `invoiceId` varchar(255) DEFAULT NULL,
  `customerId` varchar(255) DEFAULT NULL,
  `basketId` varchar(255) DEFAULT NULL,
  `failureURL` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `unzer_webhook`
--

CREATE TABLE `unzer_webhook` (
  `id` int(11) NOT NULL,
  `ecwid_store_id` varchar(255) DEFAULT NULL,
  `ecwid_store_token` varchar(255) DEFAULT NULL,
  `ecwid_order_id` varchar(255) DEFAULT NULL,
  `unzer_event` text DEFAULT NULL,
  `unzer_public_key` text DEFAULT NULL,
  `unzer_retrieve_url` text DEFAULT NULL,
  `unzer_payment_id` text DEFAULT NULL,
  `unzer_payment_status` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `webhook_urls`
--

CREATE TABLE `webhook_urls` (
  `id` int(11) NOT NULL,
  `store_id` varchar(255) NOT NULL,
  `unzer_webhook_url` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `configurations`
--
ALTER TABLE `configurations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ecwid_webhook`
--
ALTER TABLE `ecwid_webhook`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orderId` (`orderId`);

--
-- Indexes for table `unzer_webhook`
--
ALTER TABLE `unzer_webhook`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `webhook_urls`
--
ALTER TABLE `webhook_urls`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `configurations`
--
ALTER TABLE `configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ecwid_webhook`
--
ALTER TABLE `ecwid_webhook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unzer_webhook`
--
ALTER TABLE `unzer_webhook`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `webhook_urls`
--
ALTER TABLE `webhook_urls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
