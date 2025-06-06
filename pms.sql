-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 06, 2025 at 07:42 AM
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
-- Database: `pms`
--

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `amenity_name` varchar(100) NOT NULL,
  `display_name_ar` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `description`, `last_updated`) VALUES
('APP_NAME', 'نظام إدارة العقارات', 'اسم التطبيق', '2025-05-29 16:27:11'),
('ITEMS_PER_PAGE', '10', 'عدد العناصر المعروضة في كل صفحة للتصفح', '2025-05-29 16:27:11'),
('VAT_PERCENTAGE', '15.00', 'النسبة المئوية لضريبة القيمة المضافة الافتراضية (مثال: 15.00 لـ 15%)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_PRODUCTION_CLEARANCE', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/invoices/clearance/single', 'رابط ZATCA API (بيئة إنتاج للتصريح المسبق للفواتير)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_PRODUCTION_COMPLIANCE_INVOICE', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/compliance/invoices', 'رابط ZATCA API (بيئة إنتاج لفحص امتثال الفواتير للـ CSID)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_PRODUCTION_REPORTING', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/invoices/reporting/single', 'رابط ZATCA API (بيئة إنتاج للإبلاغ عن الفواتير)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_SANDBOX_PORTAL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal', 'رابط بوابة مطوري الفاتورة الإلكترونية (بيئة تجريبية)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_SIMULATION_CLEARANCE', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/clearance/single', 'رابط ZATCA API (بيئة محاكاة للتصريح المسبق للفواتير)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_SIMULATION_COMPLIANCE_INVOICE', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/compliance/invoices', 'رابط ZATCA API (بيئة محاكاة لفحص امتثال الفواتير للـ CSID)', '2025-05-29 16:27:11'),
('ZATCA_API_URL_SIMULATION_REPORTING', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/invoices/reporting/single', 'رابط ZATCA API (بيئة محاكاة للإبلاغ عن الفواتير)', '2025-05-29 16:27:11'),
('ZATCA_CERTIFICATE_PATH', '/opt/zatca_sdk/cert/certificate.pem', 'المسار الكامل لملف شهادة التشفير (للتوقيع أو SDK)', '2025-05-29 16:27:11'),
('ZATCA_CLIENT_ID', 'YOUR_ZATCA_CLIENT_ID_HERE', 'معرف عميل ZATCA (يُحصل عليه من بوابة هيئة الزكاة)', '2025-05-29 16:27:11'),
('ZATCA_CLIENT_SECRET', 'YOUR_ZATCA_CLIENT_SECRET_HERE', 'كلمة سر عميل ZATCA (يُحصل عليه من بوابة هيئة الزكاة)', '2025-05-29 16:27:11'),
('ZATCA_COMPLIANCE_OTP', '123345', 'OTP لعملية فحص الامتثال للـ CSID (لبيئة المحاكاة، يُحصل عليه من بوابة هيئة الزكاة)', '2025-05-29 16:27:11'),
('ZATCA_INVOICE_TYPE_CODE_SIMPLIFIED', '388', 'رمز نوع الفاتورة للفواتير المبسطة (عادة 388)', '2025-05-29 16:27:11'),
('ZATCA_INVOICE_TYPE_CODE_STANDARD', '388', 'رمز نوع الفاتورة للفواتير القياسية (عادة 388, يمكن أن يختلف)', '2025-05-29 16:27:11'),
('ZATCA_PAYMENT_MEANS_CODE_BANK', '42', 'رمز وسيلة الدفع (تحويل بنكي) حسب متطلبات ZATCA', '2025-05-29 16:27:11'),
('ZATCA_PAYMENT_MEANS_CODE_CARD', '48', 'رمز وسيلة الدفع (بطاقة) حسب متطلبات ZATCA', '2025-05-29 16:27:11'),
('ZATCA_PAYMENT_MEANS_CODE_CASH', '10', 'رمز وسيلة الدفع (نقدًا) حسب متطلبات ZATCA', '2025-05-29 16:27:11'),
('ZATCA_PRIVATE_KEY_PASSWORD', '', 'كلمة مرور المفتاح الخاص (إذا كان محميًا)', '2025-05-29 16:27:11'),
('ZATCA_PRIVATE_KEY_PATH', '/opt/zatca_sdk/cert/privatekey.pem', 'المسار الكامل لملف المفتاح الخاص (للتوقيع أو SDK)', '2025-05-29 16:27:11'),
('ZATCA_SELLER_ADDITIONAL_NO', '5678', 'الرقم الإضافي لعنوان البائع (إذا وجد)', '2025-05-29 16:27:11'),
('ZATCA_SELLER_BUILDING_NO', '1234', 'رقم مبنى البائع', '2025-05-29 16:27:11'),
('ZATCA_SELLER_CITY_NAME', 'اسم المدينة', 'اسم مدينة البائع', '2025-05-29 16:27:11'),
('ZATCA_SELLER_COUNTRY_CODE', 'SA', 'رمز البلد للبائع (مثل SA للمملكة العربية السعودية)', '2025-05-29 16:27:11'),
('ZATCA_SELLER_DISTRICT_NAME', 'اسم الحي', 'اسم حي البائع', '2025-05-29 16:27:11'),
('ZATCA_SELLER_NAME', 'اسم شركتك المسجل', 'اسم البائع (شركتك) كما هو مسجل لدى هيئة الزكاة', '2025-05-29 16:27:11'),
('ZATCA_SELLER_POSTAL_CODE', '12345', 'الرمز البريدي لعنوان البائع', '2025-05-29 16:27:11'),
('ZATCA_SELLER_STREET_NAME', 'اسم الشارع', 'اسم شارع البائع', '2025-05-29 16:27:11'),
('ZATCA_SELLER_VAT_NUMBER', '300000000000003', 'رقم تسجيل ضريبة القيمة المضافة للبائع', '2025-05-29 16:27:11'),
('ZATCA_SOLUTION_NAME', 'YourCustomSolutionName', 'اسم الحل التقني المسجل لدى هيئة الزكاة والضريبة والجمارك', '2025-05-29 16:27:11');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL COMMENT 'e.g., CREATE_OWNER, LOGIN_SUCCESS, UPDATE_INVOICE_STATUS',
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action_type`, `target_table`, `target_id`, `details`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, NULL, 'LOGIN_ATTEMPT_FAILED', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-03 04:16:00'),
(2, NULL, 'LOGIN_ATTEMPT_FAILED', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 04:21:11'),
(3, NULL, 'LOGIN_ATTEMPT_FAILED', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 04:24:06'),
(4, 1, 'LOGIN_SUCCESS', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 04:24:14'),
(5, 1, 'LOGOUT', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 04:25:35'),
(6, 1, 'LOGIN_SUCCESS', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 04:27:02'),
(7, 1, 'LOGIN_SUCCESS', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 05:55:33'),
(8, 1, 'CREATE_OWNER', 'owners', 2, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 05:56:47'),
(9, 1, 'EDIT_OWNER', 'owners', 2, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 05:57:00'),
(10, 1, 'LOGIN_SUCCESS', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 06:51:35'),
(11, 1, 'CREATE_PROPERTY', 'properties', 2, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-04 07:02:58'),
(12, 1, 'LOGIN_SUCCESS', 'users', 1, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-06 04:23:55'),
(13, 1, 'EDIT_OWNER', 'owners', 2, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-06 04:30:25'),
(14, 1, 'CREATE_PROPERTY', 'properties', 3, '0', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-06-06 05:23:19');

-- --------------------------------------------------------

--
-- Table structure for table `billing_periods`
--

CREATE TABLE `billing_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL COMMENT 'e.g., January 2025, Q1 2025',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL COMMENT 'قد تكون الفاتورة مباشرة للمستأجر دون عقد إيجار محدد',
  `invoice_number` varchar(50) NOT NULL COMMENT 'رقم الفاتورة الداخلي للنظام',
  `invoice_sequence_number` int(11) NOT NULL COMMENT 'ICV - Invoice Counter Value (متسلسل لكل مصدر فاتورة/جهاز)',
  `previous_invoice_hash` varchar(255) DEFAULT NULL COMMENT 'تجزئة الفاتورة المبسطة السابقة (PIH)',
  `invoice_date` date NOT NULL,
  `invoice_time` time NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `invoice_type_zatca` enum('Invoice','SimplifiedInvoice','DebitNote','CreditNote') DEFAULT 'SimplifiedInvoice' COMMENT 'نوع الفاتورة حسب ZATCA (فاتورة ضريبية، فاتورة مبسطة، إشعار مدين، إشعار دائن)',
  `transaction_type_code` varchar(3) DEFAULT '388' COMMENT 'ZATCA BT-3: رمز نوع الفاتورة (e.g. 388 for Tax Invoice, 381 for Credit Note)',
  `notes_zatca` text DEFAULT NULL COMMENT 'ملاحظات ZATCA (مثل سبب الإشعار المدين/الدائن)',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT 'ZATCA BT-13: رقم أمر الشراء',
  `contract_id` varchar(50) DEFAULT NULL COMMENT 'ZATCA BT-12: رقم العقد',
  `sub_total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المجموع الفرعي (بدون ضريبة القيمة المضافة أو الخصومات)',
  `discount_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي مبلغ الخصم على مستوى الفاتورة',
  `vat_percentage` decimal(5,2) DEFAULT 15.00,
  `vat_amount` decimal(12,2) GENERATED ALWAYS AS (round((`sub_total_amount` - ifnull(`discount_amount`,0)) * `vat_percentage` / 100,2)) STORED,
  `total_amount` decimal(12,2) GENERATED ALWAYS AS (`sub_total_amount` - ifnull(`discount_amount`,0) + round((`sub_total_amount` - ifnull(`discount_amount`,0)) * `vat_percentage` / 100,2)) STORED,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('Draft','Unpaid','Partially Paid','Paid','Overdue','Cancelled','Void') DEFAULT 'Unpaid',
  `zatca_status` enum('Not Sent','Sent','Generating','Compliance Check Pending','Compliance Check Failed','Compliance Check Passed','Clearance Pending','Cleared','Reporting Pending','Reported','Rejected','Error') DEFAULT 'Not Sent',
  `zatca_uuid` varchar(100) DEFAULT NULL COMMENT 'المعرف الفريد للفاتورة من ZATCA',
  `zatca_qr_code_data` text DEFAULT NULL,
  `zatca_invoice_hash` varchar(255) DEFAULT NULL,
  `zatca_signed_xml` mediumtext DEFAULT NULL COMMENT 'ملف XML الموقّع والمرسل إلى ZATCA',
  `zatca_submission_response` text DEFAULT NULL,
  `zatca_compliance_csid` text DEFAULT NULL COMMENT 'معلومات CSID المستخدمة (إذا تم الحصول عليها بنجاح)',
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'اسم أو وصف البند',
  `item_description` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price_before_vat` decimal(10,2) NOT NULL COMMENT 'سعر الوحدة قبل الضريبة والخصم',
  `item_discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'مبلغ الخصم على هذا البند (لكل وحدة أو إجمالي للبند)',
  `item_taxable_amount` decimal(12,2) GENERATED ALWAYS AS (round(`quantity` * `unit_price_before_vat` - ifnull(`item_discount_amount`,0),2)) STORED COMMENT 'المبلغ الخاضع للضريبة للبند',
  `item_vat_category_code` varchar(5) DEFAULT 'S' COMMENT 'رمز فئة ضريبة القيمة المضافة (S=Standard, Z=Zero-rated, E=Exempt, O=Out of scope)',
  `item_vat_percentage` decimal(5,2) DEFAULT 15.00,
  `item_vat_amount` decimal(12,2) GENERATED ALWAYS AS (round(round(`quantity` * `unit_price_before_vat` - ifnull(`item_discount_amount`,0),2) * `item_vat_percentage` / 100,2)) STORED,
  `item_sub_total_with_vat` decimal(12,2) GENERATED ALWAYS AS (round(`quantity` * `unit_price_before_vat` - ifnull(`item_discount_amount`,0),2) + round(round(`quantity` * `unit_price_before_vat` - ifnull(`item_discount_amount`,0),2) * `item_vat_percentage` / 100,2)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leases`
--

CREATE TABLE `leases` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lease_type_id` int(11) DEFAULT NULL,
  `lease_contract_number` varchar(50) NOT NULL,
  `lease_start_date` date NOT NULL,
  `lease_end_date` date NOT NULL,
  `rent_amount` decimal(10,2) NOT NULL,
  `payment_frequency` enum('Monthly','Quarterly','Semi-Annually','Annually','Custom') DEFAULT 'Monthly',
  `payment_due_day` tinyint(4) DEFAULT 1,
  `deposit_amount` decimal(10,2) DEFAULT 0.00,
  `grace_period_days` int(11) DEFAULT 0,
  `status` enum('Active','Expired','Terminated','Pending','Draft') DEFAULT 'Pending',
  `next_billing_date` date DEFAULT NULL,
  `last_billed_on` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `contract_document_path` varchar(255) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_types`
--

CREATE TABLE `lease_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `display_name_ar` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lease_types`
--

INSERT INTO `lease_types` (`id`, `type_name`, `display_name_ar`) VALUES
(1, 'residential', 'سكني'),
(2, 'commercial', 'تجاري');

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `national_id_iqama` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `registration_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `owners`
--

INSERT INTO `owners` (`id`, `name`, `email`, `phone`, `national_id_iqama`, `address`, `registration_date`, `notes`, `created_by_id`, `created_at`, `updated_at`) VALUES
(1, 'eng ahmed', 'test@test.com', '0505050505050', '7896523589', 'test address', NULL, NULL, 1, '2025-05-29 18:24:02', '2025-05-29 18:24:02'),
(2, 'مثنى للخدمات العقارية إدارة الأملاك', 'teste@test.cim', '855555', 'hhhhhh', 'شارع الملك عبدالعزيز', '2025-06-04', 'vggg', 1, '2025-06-04 05:56:47', '2025-06-06 04:30:25');

-- --------------------------------------------------------

--
-- Table structure for table `owner_documents`
--

CREATE TABLE `owner_documents` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) NOT NULL,
  `status` enum('Pending','Completed','Failed','Cancelled','Refunded') DEFAULT 'Pending',
  `payment_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL COMMENT 'e.g., Cash, BankTransfer, Card',
  `display_name_ar` varchar(100) NOT NULL,
  `zatca_code` varchar(2) DEFAULT NULL COMMENT 'ZATCA payment means code (e.g., 10, 42, 48)',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `display_name_ar`, `zatca_code`, `is_active`) VALUES
(1, 'Cash', 'نقداً', '10', 1),
(2, 'BankTransfer', 'تحويل بنكي', '42', 1),
(3, 'Card', 'بطاقة', '48', 1),
(4, 'Cheque', 'شيك', '30', 1),
(5, 'OnlinePayment', 'دفع إلكتروني', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL COMMENT 'e.g., view_invoices, edit_settings',
  `display_name_ar` varchar(150) NOT NULL,
  `module` varchar(50) DEFAULT NULL COMMENT 'e.g., invoices, settings, users'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permission_role`
--

CREATE TABLE `permission_role` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `property_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `property_type_id` int(11) DEFAULT NULL,
  `number_of_units` int(11) DEFAULT 0,
  `construction_year` year(4) DEFAULT NULL,
  `land_area_sqm` decimal(10,2) DEFAULT NULL,
  `google_maps_link` text DEFAULT NULL COMMENT 'Link to the property location on Google Maps',
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `owner_id`, `property_code`, `name`, `address`, `city`, `property_type_id`, `number_of_units`, `construction_year`, `land_area_sqm`, `google_maps_link`, `notes`, `created_by_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'bld001', 'borg el kawsar', 'el rayida test address', 'test4545', 1, 1, NULL, 150.00, NULL, 'new notes', 1, '2025-05-29 18:27:07', '2025-05-29 18:27:07'),
(2, 2, 'bld001ww', 'borg el kawsar', 'efrfrf', NULL, 2, 0, NULL, NULL, NULL, NULL, 1, '2025-06-04 07:02:58', '2025-06-04 07:02:58'),
(3, 1, 'PROP-00003', 'borg el kawsarcv', 'cvbcvbcvb', 'cvbcvbc', 4, 2, '2022', 2000.00, NULL, '', 1, '2025-06-06 05:23:19', '2025-06-06 05:23:19');

-- --------------------------------------------------------

--
-- Table structure for table `property_types`
--

CREATE TABLE `property_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `display_name_ar` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_types`
--

INSERT INTO `property_types` (`id`, `type_name`, `display_name_ar`) VALUES
(1, 'residential_building', 'مبنى سكني'),
(2, 'commercial_building', 'مبنى تجاري'),
(3, 'villa_complex', 'مجمع فلل'),
(4, 'land', 'أرض');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL COMMENT 'Internal key for the role, e.g., admin, staff',
  `display_name_ar` varchar(100) NOT NULL COMMENT 'Arabic display name for the role',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `display_name_ar`, `description`) VALUES
(1, 'admin', 'مسؤول نظام', 'يمتلك كافة الصلاحيات على النظام'),
(2, 'staff', 'موظف', 'يمتلك صلاحيات محددة لإدارة البيانات');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `tenant_type_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `national_id_iqama` varchar(20) NOT NULL,
  `phone_primary` varchar(20) NOT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `buyer_vat_number` varchar(20) DEFAULT NULL COMMENT 'رقم تسجيل ضريبة القيمة المضافة للمشتري (إذا كان مسجلاً)',
  `buyer_street_name` varchar(100) DEFAULT NULL,
  `buyer_building_no` varchar(10) DEFAULT NULL,
  `buyer_additional_no` varchar(10) DEFAULT NULL,
  `buyer_district_name` varchar(100) DEFAULT NULL,
  `buyer_city_name` varchar(100) DEFAULT NULL,
  `buyer_postal_code` varchar(10) DEFAULT NULL,
  `buyer_country_code` varchar(2) DEFAULT 'SA',
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `tenant_type_id`, `full_name`, `national_id_iqama`, `phone_primary`, `phone_secondary`, `email`, `current_address`, `occupation`, `nationality`, `gender`, `date_of_birth`, `buyer_vat_number`, `buyer_street_name`, `buyer_building_no`, `buyer_additional_no`, `buyer_district_name`, `buyer_city_name`, `buyer_postal_code`, `buyer_country_code`, `emergency_contact_name`, `emergency_contact_phone`, `notes`, `created_by_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'test tents', '20202336669', '025896321', '9595962000', 'test2@test.com', 'test addresxs tent', 'function getAttribute() { [native code] }', NULL, NULL, NULL, '300000000000123', 'شارع الملك عبدالعزيز', '125', '859696', 'hfgfhghfgh', 'الرياض', '12345', 'EG', NULL, NULL, NULL, 1, '2025-05-29 18:41:24', '2025-05-29 18:48:43');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_documents`
--

CREATE TABLE `tenant_documents` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenant_types`
--

CREATE TABLE `tenant_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `display_name_ar` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_types`
--

INSERT INTO `tenant_types` (`id`, `type_name`, `display_name_ar`) VALUES
(1, 'individual', 'فرد'),
(2, 'company', 'شركة/مؤسسة');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `unit_number` varchar(50) NOT NULL,
  `unit_type_id` int(11) DEFAULT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `size_sqm` decimal(8,2) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `status` enum('Vacant','Occupied','Under Maintenance','Reserved') DEFAULT 'Vacant',
  `base_rent_price` decimal(10,2) DEFAULT NULL,
  `electricity_meter_number` varchar(100) DEFAULT NULL,
  `water_meter_number` varchar(100) DEFAULT NULL,
  `is_furnished` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = No, 1 = Yes',
  `has_parking` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = No, 1 = Yes',
  `view_description` varchar(255) DEFAULT NULL COMMENT 'e.g., Sea View, Garden View',
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `property_id`, `unit_number`, `unit_type_id`, `floor_number`, `size_sqm`, `bedrooms`, `bathrooms`, `features`, `status`, `base_rent_price`, `electricity_meter_number`, `water_meter_number`, `is_furnished`, `has_parking`, `view_description`, `notes`, `created_by_id`, `created_at`, `updated_at`) VALUES
(1, 1, '101', 1, 2, 100.00, 2, 1, '0', 'Vacant', 5000.00, NULL, NULL, 0, 0, NULL, 'test', 1, '2025-05-29 18:40:18', '2025-05-29 18:40:18'),
(2, 3, '1', NULL, NULL, NULL, NULL, NULL, NULL, 'Vacant', NULL, NULL, NULL, 0, 0, NULL, NULL, 1, '2025-06-06 05:23:19', '2025-06-06 05:23:19'),
(3, 3, '2', NULL, NULL, NULL, NULL, NULL, NULL, 'Vacant', NULL, NULL, NULL, 0, 0, NULL, NULL, 1, '2025-06-06 05:23:19', '2025-06-06 05:23:19');

-- --------------------------------------------------------

--
-- Table structure for table `unit_amenities`
--

CREATE TABLE `unit_amenities` (
  `unit_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unit_types`
--

CREATE TABLE `unit_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `display_name_ar` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `unit_types`
--

INSERT INTO `unit_types` (`id`, `type_name`, `display_name_ar`) VALUES
(1, 'apartment_2br', 'شقة غرفتين نوم'),
(2, 'apartment_3br', 'شقة ثلاث غرف نوم'),
(3, 'shop', 'محل تجاري'),
(4, 'office', 'مكتب');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password_hash`, `role_id`, `created_by_id`, `created_at`, `is_active`) VALUES
(1, 'Admin User', 'admin', 'admin@example.com', '$2y$10$wGWTYbQmvd8Gn.iY9IzV.u4xFp6I9dC9fa3iyExVPh3ULsPOJizFq', 1, NULL, '2025-05-29 16:27:11', 1);

-- --------------------------------------------------------

--
-- Table structure for table `utility_readings`
--

CREATE TABLE `utility_readings` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `utility_type_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading_value` decimal(10,2) DEFAULT 0.00,
  `current_reading_value` decimal(10,2) NOT NULL,
  `consumption` decimal(10,2) GENERATED ALWAYS AS (`current_reading_value` - `previous_reading_value`) STORED,
  `rate_per_unit` decimal(8,4) DEFAULT NULL,
  `amount_due` decimal(10,2) GENERATED ALWAYS AS (round((`current_reading_value` - `previous_reading_value`) * `rate_per_unit`,2)) STORED,
  `billed_status` enum('Pending','Billed','Paid') DEFAULT 'Pending',
  `invoice_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utility_types`
--

CREATE TABLE `utility_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit_of_measure` varchar(20) DEFAULT 'kWh'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utility_types`
--

INSERT INTO `utility_types` (`id`, `name`, `unit_of_measure`) VALUES
(1, 'كهرباء', 'kWh'),
(2, 'ماء', 'm³'),
(3, 'غاز', 'm³');

-- --------------------------------------------------------

--
-- Table structure for table `vacation_notices`
--

CREATE TABLE `vacation_notices` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `notice_date` date NOT NULL COMMENT 'تاريخ استلام الإشعار',
  `vacating_date` date NOT NULL COMMENT 'التاريخ المتوقع للإخلاء',
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Cancelled') DEFAULT 'Pending',
  `created_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `amenity_name_unique` (`amenity_name`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_auditlog_user` (`user_id`);

--
-- Indexes for table `billing_periods`
--
ALTER TABLE `billing_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_name_unique` (`period_name`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number_internal` (`invoice_number`),
  ADD UNIQUE KEY `idx_invoice_sequence_number_unique` (`invoice_sequence_number`),
  ADD KEY `idx_invoices_lease_id` (`lease_id`),
  ADD KEY `idx_invoices_tenant_id` (`tenant_id`),
  ADD KEY `fk_invoices_created_by_idx` (`created_by_id`),
  ADD KEY `idx_invoice_status` (`status`),
  ADD KEY `idx_invoice_due_date` (`due_date`),
  ADD KEY `idx_invoice_type_zatca` (`invoice_type_zatca`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_items_invoice_id` (`invoice_id`);

--
-- Indexes for table `leases`
--
ALTER TABLE `leases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lease_contract_number` (`lease_contract_number`),
  ADD UNIQUE KEY `lease_contract_number_unique` (`lease_contract_number`),
  ADD KEY `fk_leases_lease_type` (`lease_type_id`),
  ADD KEY `idx_leases_unit_id` (`unit_id`),
  ADD KEY `idx_leases_tenant_id` (`tenant_id`),
  ADD KEY `fk_leases_created_by_idx` (`created_by_id`),
  ADD KEY `idx_lease_status` (`status`),
  ADD KEY `idx_lease_end_date` (`lease_end_date`);

--
-- Indexes for table `lease_types`
--
ALTER TABLE `lease_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name_lease_unique` (`type_name`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_owner_email_unique` (`email`),
  ADD UNIQUE KEY `idx_owner_phone_unique` (`phone`),
  ADD UNIQUE KEY `idx_owner_national_id_unique` (`national_id_iqama`),
  ADD UNIQUE KEY `idx_owner_email_unique_new` (`email`),
  ADD UNIQUE KEY `idx_owner_phone_unique_new` (`phone`),
  ADD UNIQUE KEY `idx_owner_national_id_unique_new` (`national_id_iqama`),
  ADD KEY `fk_owners_created_by_idx` (`created_by_id`),
  ADD KEY `idx_owner_name` (`name`);

--
-- Indexes for table `owner_documents`
--
ALTER TABLE `owner_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ownerdoc_owner` (`owner_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_payment_method_id` (`payment_method_id`),
  ADD KEY `idx_payments_invoice_id` (`invoice_id`),
  ADD KEY `idx_payments_tenant_id` (`tenant_id`),
  ADD KEY `fk_payments_received_by_idx` (`received_by_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_name_unique` (`method_name`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key_unique` (`permission_key`);

--
-- Indexes for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_permissionrole_role` (`role_id`),
  ADD KEY `fk_permissionrole_permission` (`permission_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `property_code` (`property_code`),
  ADD UNIQUE KEY `property_code_unique` (`property_code`),
  ADD KEY `fk_properties_property_type` (`property_type_id`),
  ADD KEY `idx_properties_owner_id` (`owner_id`),
  ADD KEY `fk_properties_created_by_idx` (`created_by_id`),
  ADD KEY `idx_property_name` (`name`),
  ADD KEY `idx_property_type_filter` (`property_type_id`);

--
-- Indexes for table `property_types`
--
ALTER TABLE `property_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name_unique` (`type_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name_unique` (`role_name`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_tenant_national_id_unique` (`national_id_iqama`),
  ADD UNIQUE KEY `idx_tenant_phone_unique` (`phone_primary`),
  ADD UNIQUE KEY `idx_tenant_national_id_unique_new` (`national_id_iqama`),
  ADD UNIQUE KEY `idx_tenant_phone_unique_new` (`phone_primary`),
  ADD UNIQUE KEY `idx_tenant_email_unique` (`email`),
  ADD UNIQUE KEY `idx_tenant_email_unique_new` (`email`),
  ADD KEY `fk_tenants_tenant_type` (`tenant_type_id`),
  ADD KEY `fk_tenants_created_by_idx` (`created_by_id`),
  ADD KEY `idx_tenant_name` (`full_name`);

--
-- Indexes for table `tenant_documents`
--
ALTER TABLE `tenant_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tenantdoc_tenant` (`tenant_id`);

--
-- Indexes for table `tenant_types`
--
ALTER TABLE `tenant_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name_tenant_unique` (`type_name`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `property_id_unit_number` (`property_id`,`unit_number`),
  ADD UNIQUE KEY `property_id_unit_number_unique` (`property_id`,`unit_number`),
  ADD KEY `fk_units_unit_type` (`unit_type_id`),
  ADD KEY `fk_units_created_by_idx` (`created_by_id`),
  ADD KEY `idx_unit_status` (`status`),
  ADD KEY `idx_unit_type_filter` (`unit_type_id`);

--
-- Indexes for table `unit_amenities`
--
ALTER TABLE `unit_amenities`
  ADD PRIMARY KEY (`unit_id`,`amenity_id`),
  ADD KEY `fk_unitamenities_unit` (`unit_id`),
  ADD KEY `fk_unitamenities_amenity` (`amenity_id`);

--
-- Indexes for table `unit_types`
--
ALTER TABLE `unit_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name_unique_unit` (`type_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username_unique` (`username`),
  ADD UNIQUE KEY `email_unique` (`email`),
  ADD KEY `fk_users_role_id` (`role_id`),
  ADD KEY `fk_users_created_by` (`created_by_id`),
  ADD KEY `idx_user_role_filter` (`role_id`);

--
-- Indexes for table `utility_readings`
--
ALTER TABLE `utility_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_utility_readings_unit_id` (`unit_id`),
  ADD KEY `idx_utility_readings_utility_type_id` (`utility_type_id`),
  ADD KEY `idx_utility_readings_invoice_id` (`invoice_id`),
  ADD KEY `fk_utility_readings_created_by_idx` (`created_by_id`),
  ADD KEY `idx_util_reading_date` (`reading_date`),
  ADD KEY `idx_util_billed_status` (`billed_status`);

--
-- Indexes for table `utility_types`
--
ALTER TABLE `utility_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `name_unique_utiltype` (`name`);

--
-- Indexes for table `vacation_notices`
--
ALTER TABLE `vacation_notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vacation_lease` (`lease_id`),
  ADD KEY `fk_vacation_tenant` (`tenant_id`),
  ADD KEY `fk_vacation_created_by` (`created_by_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `billing_periods`
--
ALTER TABLE `billing_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leases`
--
ALTER TABLE `leases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_types`
--
ALTER TABLE `lease_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `owner_documents`
--
ALTER TABLE `owner_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `property_types`
--
ALTER TABLE `property_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tenant_documents`
--
ALTER TABLE `tenant_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_types`
--
ALTER TABLE `tenant_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `unit_types`
--
ALTER TABLE `unit_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `utility_readings`
--
ALTER TABLE `utility_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utility_types`
--
ALTER TABLE `utility_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vacation_notices`
--
ALTER TABLE `vacation_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_auditlog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `leases`
--
ALTER TABLE `leases`
  ADD CONSTRAINT `fk_leases_lease_type` FOREIGN KEY (`lease_type_id`) REFERENCES `lease_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `owner_documents`
--
ALTER TABLE `owner_documents`
  ADD CONSTRAINT `fk_ownerdoc_owner` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `permission_role`
--
ALTER TABLE `permission_role`
  ADD CONSTRAINT `fk_permissionrole_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_permissionrole_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_properties_property_type` FOREIGN KEY (`property_type_id`) REFERENCES `property_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenants_tenant_type` FOREIGN KEY (`tenant_type_id`) REFERENCES `tenant_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tenant_documents`
--
ALTER TABLE `tenant_documents`
  ADD CONSTRAINT `fk_tenantdoc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_units_unit_type` FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `unit_amenities`
--
ALTER TABLE `unit_amenities`
  ADD CONSTRAINT `fk_unitamenities_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_unitamenities_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `vacation_notices`
--
ALTER TABLE `vacation_notices`
  ADD CONSTRAINT `fk_vacation_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vacation_lease` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_vacation_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
