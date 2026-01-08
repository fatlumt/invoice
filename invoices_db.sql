-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 04:47 PM
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
-- Database: `invoices_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `company_profile`
--

CREATE TABLE `company_profile` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `street` varchar(160) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `city` varchar(120) NOT NULL,
  `phone` varchar(80) NOT NULL,
  `email` varchar(160) NOT NULL,
  `iban` varchar(50) NOT NULL,
  `company_id` varchar(80) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 8.10,
  `logo_path` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_name` varchar(255) NOT NULL DEFAULT '',
  `addr_line1` varchar(255) DEFAULT NULL,
  `postal_code` varchar(32) DEFAULT NULL,
  `tax_id` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_profile`
--

INSERT INTO `company_profile` (`id`, `name`, `street`, `zip`, `city`, `phone`, `email`, `iban`, `company_id`, `vat_rate`, `logo_path`, `updated_at`, `company_name`, `addr_line1`, `postal_code`, `tax_id`) VALUES
(1, 'Krasniqi Plattenleger', 'Feldstrasse 110', '4123', 'Allschwil', '076 50 30 788', 'info@krasniqi-plattenleger.ch', 'CH26 0023 3233 3300 2501 L', 'CHE-255-255-255', 8.10, 'uploads/logo_20251018_112943_80984964.png', '2025-10-18 09:38:00', 'Krasniqi Plattenleger', 'Feldstrasse', '4123', 'CH-255-255-255');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(190) NOT NULL,
  `customer_address` text NOT NULL,
  `contract_number` varchar(60) DEFAULT NULL,
  `contract_date` date DEFAULT NULL,
  `title` varchar(255) DEFAULT 'VERTRAG',
  `body` longtext DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `payment_terms` text DEFAULT NULL,
  `signer` varchar(120) DEFAULT NULL,
  `signer_title` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `customer_name`, `customer_address`, `contract_number`, `contract_date`, `title`, `body`, `total_amount`, `payment_terms`, `signer`, `signer_title`, `created_at`) VALUES
(1, 'Name Surname', 'Name Surname\r\nAdresse', 'V-00001', '2025-10-20', 'VERTRAG', '\r\n          <div class=\"box\">\r\n            <div style=\"text-align:center;margin-bottom:8px\">\r\n              <div style=\"font-size:20px;font-weight:800;text-decoration:underline\" id=\"titleVis\" contenteditable=\"true\">VERTRAG</div>\r\n              <div class=\"small\">Datum: <span id=\"dateVis\" contenteditable=\"true\">20.10.2025</span></div>\r\n            </div>\r\n\r\n            <p>Zwischen <strong id=\"partyCustomer\" contenteditable=\"true\">Kunde • Adresse</strong> (im Folgenden als „Kunde“ bezeichnet) und\r\n            <strong>Krasniqi Plattenleger</strong> (im Folgenden als „Unternehmer“ bezeichnet).</p>\r\n\r\n            <h2>1. Gegenstand des Vertrags</h2>\r\n            <div contenteditable=\"true\" id=\"sec1\">\r\n              Der Unternehmer verpflichtet sich, die folgenden Dienstleistungen für den Kunden zu erbringen:\r\n              <ul>\r\n                <li>Fenster</li>\r\n                <li>Rollladen</li>\r\n              </ul>\r\n            </div>\r\n\r\n            <h2>2. Lieferzeit</h2>\r\n            <p contenteditable=\"true\" id=\"sec2\">Lieferzeit ca. [hier einfügen].</p>\r\n\r\n            <h2>3. Zahlungsbedingungen</h2>\r\n            <div contenteditable=\"true\" id=\"sec3\">\r\n              a. Der Gesamtpreis für die Dienstleistungen beträgt CHF 25’000.<br>\r\n              b. Eine Anzahlung in Höhe von CHF 10’000 ist vor Beginn der Arbeiten zu leisten.<br>\r\n              c. Der Restbetrag wird in monatlichen Raten beglichen.<br>\r\n              d. Die Ratenzahlungen beginnen einen Monat nach der Anzahlung.\r\n            </div>\r\n\r\n            <h2>4. Unterschriften</h2>\r\n            <div class=\"signature\">\r\n              <div>\r\n                <div class=\"line\"><small>Unterschrift Kunde</small></div>\r\n              </div>\r\n              <div>\r\n                <div class=\"line\"><small>Unternehmer</small></div>\r\n                <div class=\"small\" id=\"signBlock\" contenteditable=\"true\">SHKELQIM KRASNIQI • Geschäftsführer</div>\r\n              </div>\r\n            </div>\r\n          </div>\r\n        ', 2500000.00, 'Anzahlung 10000 CHF; Rest in Monatsraten.', 'SHKELQIM KRASNIQI', 'Geschäftsführer', '2025-10-20 09:46:47'),
(2, 'Fatlum Thaqi', 'Gartenstrasse 20, Basel 4123', '', '2025-10-21', 'VERTRAG', '<section class=\"section header\">\n          <div class=\"brand\" style=\"align-items:flex-start\">\n            <div>\n                              <img src=\"uploads/logo_20251018_112943_80984964.png\" alt=\"Logo\" style=\"width:140px; height:auto\">\n                          </div>\n          </div>\n          <div class=\"company-block\">\n            <div>Feldstrasse 110</div>\n            <div>4123 Allschwil</div>\n            <div>+0765030788</div>\n            <div>info@krasniqi-plattenleger.ch</div>\n          </div>\n        </section>', 35000.00, '250', 'SHKELQIM KRASNIQI', 'Geschäftsführer', '2025-10-20 11:20:23');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `bill_to` text DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `ref_text` varchar(255) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `amount_due` decimal(10,2) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `items_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lump_sum` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `customer_name`, `bill_to`, `invoice_number`, `invoice_date`, `ref_text`, `vat_rate`, `discount`, `subtotal`, `vat_amount`, `total`, `amount_due`, `remark`, `items_json`, `created_at`, `lump_sum`) VALUES
(10, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '0000-00-00', '', 8.10, NULL, 5000.00, 405.00, 5405.00, 5405.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"5000\",\"line\":\"5’000.00 CHF\"}]', '2025-10-17 14:27:04', 0),
(11, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '0000-00-00', '', 8.10, NULL, 500.00, 40.50, 540.50, 540.50, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"item\",\"pos\":\"2\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"item\",\"pos\":\"3\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0500\",\"line\":\"500.00 CHF\"},{\"type\":\"item\",\"pos\":\"4\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"group\",\"desc\":\"Abschnitt / Raum\"},{\"type\":\"item\",\"pos\":\"5\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"item\",\"pos\":\"6\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', '2025-10-17 16:49:42', 0),
(12, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '2025-10-18', '', 8.10, NULL, 0.00, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', '2025-10-18 07:59:08', 0),
(13, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '2025-10-18', '', 8.10, NULL, 0.00, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"item\",\"pos\":\"2\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"},{\"type\":\"group\",\"desc\":\"Abschnitt / Raum\"}]', '2025-10-18 07:59:54', 0),
(14, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '2025-10-18', '', 8.10, NULL, 0.00, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', '2025-10-18 08:02:45', 0),
(15, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '2025-10-18', '', 8.10, NULL, 0.00, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', '2025-10-18 08:50:50', 0),
(16, 'Name Surname', 'Name Surname\r\nAdresse', '00000', '2025-10-18', '', 8.10, NULL, 0.00, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', '2025-10-18 10:45:01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `offerten`
--

CREATE TABLE `offerten` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `bill_to` text DEFAULT NULL,
  `offer_number` varchar(50) DEFAULT NULL,
  `offer_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `ref_text` varchar(255) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items_json`)),
  `lump_sum` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offerten`
--

INSERT INTO `offerten` (`id`, `customer_name`, `bill_to`, `offer_number`, `offer_date`, `valid_until`, `ref_text`, `vat_rate`, `subtotal`, `vat_amount`, `total`, `remark`, `items_json`, `lump_sum`, `created_at`) VALUES
(1, 'Name Surname', 'Name Surname\r\nAdresse', 'Offerte', '2025-10-20', '2025-11-19', '', 8.10, 0.00, 0.00, 0.00, '', '[{\"type\":\"group\",\"desc\":\"Erdgeschoss\"},{\"type\":\"item\",\"pos\":\"1\",\"desc\":\"Position\",\"qty\":\"1\",\"unit\":\"m\",\"price\":\"0.00\",\"line\":\"0.00 CHF\"}]', 0, '2025-10-20 13:24:07');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `name`, `password_hash`, `role`, `created_at`) VALUES
(6, 'f@f.com', 'a', '$2y$10$JzxEPBPiQu6XeQ1zCfimUu.koYKy9nj8unnSLo8TcO211pyhlpk1C', 'admin', '2025-10-17 21:36:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `company_profile`
--
ALTER TABLE `company_profile`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offerten`
--
ALTER TABLE `offerten`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `company_profile`
--
ALTER TABLE `company_profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `offerten`
--
ALTER TABLE `offerten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
