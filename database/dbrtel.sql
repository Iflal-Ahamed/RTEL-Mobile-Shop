-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 08:53 AM
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
-- Database: `dbrtel`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbladdress`
--

CREATE TABLE `tbladdress` (
  `address_id` varchar(10) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `address` varchar(250) NOT NULL,
  `province` varchar(250) NOT NULL,
  `district` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladdress`
--

INSERT INTO `tbladdress` (`address_id`, `cus_id`, `address`, `province`, `district`) VALUES
('A001', 1, 'School Road Batticaloa', 'eastern', 'Batticaloa'),
('A002', 2, '67 under 8b Kandy Road, Mawanella, Sri Lanka', 'central', 'Kandy'),
('A003', 3, 'No.191 under 2 A, Moratuhena Road, Athurugiriya', 'uva', 'Badulla');

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin`
--

CREATE TABLE `tbladmin` (
  `admin_id` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `email` varchar(250) NOT NULL,
  `password` varchar(250) NOT NULL,
  `type` varchar(50) NOT NULL,
  `role_label` varchar(50) NOT NULL DEFAULT 'Admin',
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin`
--

INSERT INTO `tbladmin` (`admin_id`, `name`, `phone`, `email`, `password`, `type`, `role_label`, `status`) VALUES
('ad2601', 'Super Admin', '0170000000', 'Admin@gmail.com', '$2y$10$SzGRzols7QFzmUxvS3.1y.BoXMaIHw9aeXNi79fTm5Numa30GIUpK', 'superadmin', 'Admin', 1),
('M260429578', 'MIM FAHMI', '', 'fahmi@gmail.com', '$2y$10$rOiobeVQKYSqxhVihBPpGOuqTF2nKUllGeTSl3uPK2RyGGz7f1sc2', 'manager', 'Admin', 1),
('M260501574', 'Mohammed Irshad Muhammed', '', 'irshad@gamil.com', '$2y$10$5VzaVE9miy8pLdrSDyThsOhGYL7vACQfP7p8W58qKJPCNWqXS1kiu', 'manager', 'Designer', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin_alert_read`
--

CREATE TABLE `tbladmin_alert_read` (
  `admin_id` varchar(50) NOT NULL,
  `order_seen_at` datetime DEFAULT NULL,
  `feedback_seen_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_seen_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin_alert_read`
--

INSERT INTO `tbladmin_alert_read` (`admin_id`, `order_seen_at`, `feedback_seen_id`, `rating_seen_id`, `updated_at`) VALUES
('ad2601', '2026-05-03 22:02:31', 24, 6, '2026-05-03 22:02:31'),
('M260429578', NULL, 0, 0, '2026-04-30 21:18:03');

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin_log`
--

CREATE TABLE `tbladmin_log` (
  `adminlog_id` varchar(10) NOT NULL,
  `admin_id` varchar(10) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(10) NOT NULL,
  `activity_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin_log`
--

INSERT INTO `tbladmin_log` (`adminlog_id`, `admin_id`, `action_type`, `entity_type`, `entity_id`, `activity_date`) VALUES
('L021472577', 'ad2601', 'product_delete:success [Admin] Deleted product #8', 'system', '-', '2026-05-01'),
('L026541279', 'ad2601', 'order_status:success [Admin] Order O69F36DD40: Del', 'system', '-', '2026-05-01'),
('L027213620', 'ad2601', 'order_status:success [Admin] Order O69F40C805: Pen', 'system', '-', '2026-05-01'),
('L027318872', 'ad2601', 'order_status:success [Admin] Order O69F40C805: Acc', 'system', '-', '2026-05-01'),
('L027442262', 'ad2601', 'order_status:success [Admin] Order O69F40C805: On ', 'system', '-', '2026-05-01'),
('L028601039', 'ad2601', 'order_status:success [Admin] Order O69F4110A8: Pen', 'system', '-', '2026-05-01'),
('L028779664', 'ad2601', 'order_status:success [Admin] Order O69F4110A8: Acc', 'system', '-', '2026-05-01'),
('L029598386', 'ad2601', 'order_status:success [Admin] Order O69F4116F7: Pen', 'system', '-', '2026-05-01'),
('L029706235', 'ad2601', 'order_status:success [Admin] Order O69F4116F7: Acc', 'system', '-', '2026-05-01'),
('L029981135', 'ad2601', 'order_status:success [Admin] Order O69F4116F7: On ', 'system', '-', '2026-05-01'),
('L046922549', 'iflalahame', 'login:failed Password mismatch', 'auth', '-', '2026-05-01'),
('L046997165', 'iflalahame', 'login:failed Password mismatch', 'auth', '-', '2026-05-01'),
('L047059259', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L082247938', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L084191285', 'ad2601', 'brand_add:success [Admin] Added brand: Poco', 'system', '-', '2026-05-01'),
('L084254490', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-05-01'),
('L084267536', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-05-01'),
('L085738654', 'ad2601', 'product_add:success [Admin] Added product #38: Poc', 'system', '-', '2026-05-01'),
('L090022831', 'ad2601', 'product_edit:success [Admin] Updated product #38: ', 'system', '-', '2026-05-01'),
('L090212605', 'ad2601', 'product_edit:success [Admin] Updated product #38: ', 'system', '-', '2026-05-01'),
('L092973830', 'ad2601', 'bundle_add:success [Admin] Added bundle #5: May_Bu', 'system', '-', '2026-05-01'),
('L094025514', 'ad2601', 'order_status:success [Admin] Order O69F42A97B: Pen', 'system', '-', '2026-05-01'),
('L094171816', 'ad2601', 'order_status:success [Admin] Order O69F42A97B: Acc', 'system', '-', '2026-05-01'),
('L094494869', 'ad2601', 'order_status:success [Admin] Order O69F42A97B: On ', 'system', '-', '2026-05-01'),
('L094683852', 'ad2601', 'order_status:success [Admin] Order O69F42A97B: Del', 'system', '-', '2026-05-01'),
('L098859973', 'ad2601', 'shipping_rate_save:success [Admin] Saved shipping ', 'system', '-', '2026-05-01'),
('L099297950', 'ad2601', 'discount_save:success [Admin] Updated coupon M2001', 'system', '-', '2026-05-01'),
('L100161375', 'ad2601', 'promotion_save:success [Admin] Created home promot', 'system', '-', '2026-05-01'),
('L102235139', 'ad2601', 'settings_update:success [Admin] AI training settin', 'system', '-', '2026-05-01'),
('L102785165', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-05-01'),
('L104359947', 'ad2601', 'manager_create:success [Admin] Created manager M26', 'system', '-', '2026-05-01'),
('L104958470', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L105068461', 'ad2601', 'manager_permission:success [Admin] Updated manager', 'system', '-', '2026-05-01'),
('L152949270', 'ghhg', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L153054681', 'iflaliflal', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L153197857', 'iflalahame', 'login:failed Password mismatch', 'auth', '-', '2026-05-01'),
('L190302814', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L202212087', 'ad2601', 'promotion_save:success [Admin] Updated home promot', 'system', '-', '2026-05-01'),
('L234261730', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L236628811', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L239726003', 'ad2601', 'order_status:success [Admin] Order O69F2DB890: Pen', 'system', '-', '2026-04-30'),
('L244818501', 'ad2601', 'order_status:success [Admin] Order O69F2DB890: Acc', 'system', '-', '2026-04-30'),
('L245347661', 'ad2601', 'order_status:success [Admin] Order O69F2DB890: On ', 'system', '-', '2026-04-30'),
('L247107464', 'ad2601', 'order_status:success [Admin] Order O69F2DB890: Del', 'system', '-', '2026-04-30'),
('L248303549', 'fahmi@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L255299736', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-03'),
('L269637879', 'M260429578', 'brand_status:success [manager] Toggled brand statu', 'system', '-', '2026-04-29'),
('L269644111', 'M260429578', 'brand_status:success [manager] Toggled brand statu', 'system', '-', '2026-04-29'),
('L269656554', 'M260429578', 'brand_status:success [manager] Toggled brand statu', 'system', '-', '2026-04-29'),
('L269715533', 'M260429578', 'brand_edit:success [manager] Updated brand #3: Red', 'system', '-', '2026-04-29'),
('L269818403', 'M260429578', 'category_status:success [manager] Toggled category', 'system', '-', '2026-04-29'),
('L269821974', 'M260429578', 'category_status:success [manager] Toggled category', 'system', '-', '2026-04-29'),
('L278928106', 'ad2601', 'product_edit:success [Admin] Updated product #3: i', 'system', '-', '2026-04-30'),
('L280309501', 'ad2601', 'product_edit:success [Admin] Updated product #3: i', 'system', '-', '2026-04-30'),
('L284163922', 'ad2601', 'manager_permission:success [admin] Deactivated man', 'system', '-', '2026-04-29'),
('L284334003', 'M260429578', 'brand_status:success [manager] Toggled brand statu', 'system', '-', '2026-04-29'),
('L284347456', 'M260429578', 'brand_status:success [manager] Toggled brand statu', 'system', '-', '2026-04-29'),
('L284792928', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L285799638', 'fahmi@gmai', 'login:failed Account deactivated', 'auth', '-', '2026-04-29'),
('L286339600', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-04-30'),
('L286344422', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-04-30'),
('L286851496', 'fahmi@gmai', 'login:failed Account deactivated', 'auth', '-', '2026-04-29'),
('L290014096', 'ad2601', 'product_add:success [Admin] Added product #37: Red', 'system', '-', '2026-04-30'),
('L293215292', 'ad2601', 'product_edit:success [Admin] Updated product #37: ', 'system', '-', '2026-04-30'),
('L294608219', 'ad2601', 'manager_permission:success [admin] Deactivated man', 'system', '-', '2026-04-29'),
('L294927993', 'ad2601', 'bundle_add:success [Admin] Added bundle #4: Samsun', 'system', '-', '2026-04-30'),
('L295492310', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #4: Sam', 'system', '-', '2026-04-30'),
('L300913449', 'ad2601', 'order_status:success [Admin] Order O69F0D9247: Acc', 'system', '-', '2026-04-30'),
('L302708214', 'ad2601', 'shipping_rate_save:success [Admin] Saved shipping ', 'system', '-', '2026-04-30'),
('L303194251', 'ad2601', 'shipping_rate_save:success [Admin] Saved shipping ', 'system', '-', '2026-04-30'),
('L303389224', 'ad2601', 'shipping_rate_save:success [Admin] Saved shipping ', 'system', '-', '2026-04-30'),
('L303471812', 'ad2601', 'shipping_rule_save:success [Admin] Free delivery r', 'system', '-', '2026-04-30'),
('L304656517', 'ad2601', 'shipping_rule_save:success [Admin] Free delivery r', 'system', '-', '2026-04-30'),
('L305583600', 'ad2601', 'discount_save:success [Admin] Created coupon AS903', 'system', '-', '2026-04-30'),
('L306076862', 'ad2601', 'discount_save:success [Admin] Created coupon IFL10', 'system', '-', '2026-04-30'),
('L306648376', 'ad2601', 'discount_save:success [Admin] Updated coupon IFL10', 'system', '-', '2026-04-30'),
('L308256058', 'ad2601', 'discount_save:success [Admin] Created special disc', 'system', '-', '2026-04-30'),
('L308544840', 'ad2601', 'discount_save:success [Admin] Updated special disc', 'system', '-', '2026-04-30'),
('L310116043', 'ad2601', 'settings_update:success [Admin] Banner updated #2', 'system', '-', '2026-04-30'),
('L310277710', 'ad2601', 'settings_update:success [Admin] Banner updated #2', 'system', '-', '2026-04-30'),
('L310353119', 'ad2601', 'settings_update:success [Admin] Banner updated #2', 'system', '-', '2026-04-30'),
('L310512307', 'ad2601', 'settings_update:success [Admin] Banner updated #2', 'system', '-', '2026-04-30'),
('L310896499', 'ad2601', 'settings_update:success [Admin] Updated contact in', 'system', '-', '2026-04-30'),
('L311034124', 'ad2601', 'settings_update:success [Admin] Updated contact in', 'system', '-', '2026-04-30'),
('L311813801', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-30'),
('L313676332', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L314296972', 'ad2601', 'order_status:success [Admin] Order O69F2F75D3: Pen', 'system', '-', '2026-04-30'),
('L400364130', 'ad2601', 'brand_add:success [admin] Added brand: Huawei', 'system', '-', '2026-04-29'),
('L401045564', 'ad2601', 'brand_add:success [admin] Added brand: OnePlus', 'system', '-', '2026-04-29'),
('L401405244', 'ad2601', 'brand_add:success [admin] Added brand: Google', 'system', '-', '2026-04-29'),
('L401681599', 'ad2601', 'brand_add:success [admin] Added brand: Oppo', 'system', '-', '2026-04-29'),
('L402286458', 'ad2601', 'brand_add:success [admin] Added brand: Nokia', 'system', '-', '2026-04-29'),
('L402761358', 'ad2601', 'brand_add:success [admin] Added brand: Realme', 'system', '-', '2026-04-29'),
('L403498985', 'ad2601', 'brand_add:success [admin] Added brand: Vivo', 'system', '-', '2026-04-29'),
('L404272669', 'ad2601', 'category_add:success [admin] Added category: Bluet', 'system', '-', '2026-04-29'),
('L404972786', 'ad2601', 'category_add:success [admin] Added category: Memor', 'system', '-', '2026-04-29'),
('L405437991', 'ad2601', 'category_add:success [admin] Added category: Phone', 'system', '-', '2026-04-29'),
('L406791114', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L409766771', 'ad2601', 'product_add:success [Admin] Added product #8: Gala', 'system', '-', '2026-04-29'),
('L414192876', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L417668725', 'ad2601', 'product_edit:success [Admin] Updated product #18: ', 'system', '-', '2026-04-29'),
('L417867637', 'ad2601', 'product_edit:success [Admin] Updated product #17: ', 'system', '-', '2026-04-29'),
('L420753504', 'ad2601', 'product_edit:success [Admin] Updated product #9: S', 'system', '-', '2026-04-29'),
('L422886287', 'ad2601', 'product_add:success [Admin] Added product #19: Sof', 'system', '-', '2026-04-29'),
('L425815318', 'ad2601', 'product_edit:success [Admin] Updated product #9: S', 'system', '-', '2026-04-29'),
('L427436517', 'ad2601', 'product_edit:success [Admin] Updated product #9: S', 'system', '-', '2026-04-29'),
('L428885280', 'ad2601', 'product_add:success [Admin] Added product #20: Mag', 'system', '-', '2026-04-29'),
('L430951021', 'ad2601', 'product_add:success [Admin] Added product #21: Sam', 'system', '-', '2026-04-29'),
('L434061850', 'ad2601', 'product_edit:success [Admin] Updated product #18: ', 'system', '-', '2026-04-29'),
('L434661148', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L434987977', 'ad2601', 'product_edit:success [Admin] Updated product #17: ', 'system', '-', '2026-04-29'),
('L435734273', 'ad2601', 'product_edit:success [Admin] Updated product #16: ', 'system', '-', '2026-04-29'),
('L437778063', 'ad2601', 'product_edit:success [Admin] Updated product #12: ', 'system', '-', '2026-04-29'),
('L438487409', 'ad2601', 'product_edit:success [Admin] Updated product #36: ', 'system', '-', '2026-04-29'),
('L438945668', 'ad2601', 'product_edit:success [Admin] Updated product #35: ', 'system', '-', '2026-04-29'),
('L439485643', 'ad2601', 'product_edit:success [Admin] Updated product #34: ', 'system', '-', '2026-04-29'),
('L440512531', 'ad2601', 'product_edit:success [Admin] Updated product #29: ', 'system', '-', '2026-04-29'),
('L442554924', 'ad2601', 'product_edit:success [Admin] Updated product #36: ', 'system', '-', '2026-04-29'),
('L442819991', 'ad2601', 'product_edit:success [Admin] Updated product #35: ', 'system', '-', '2026-04-29'),
('L443971088', 'ad2601', 'product_edit:success [Admin] Updated product #31: ', 'system', '-', '2026-04-29'),
('L445539363', 'ad2601', 'product_edit:success [Admin] Updated product #30: ', 'system', '-', '2026-04-29'),
('L445758079', 'ad2601', 'product_edit:success [Admin] Updated product #29: ', 'system', '-', '2026-04-29'),
('L446167219', 'ad2601', 'product_edit:success [Admin] Updated product #36: ', 'system', '-', '2026-04-29'),
('L446258035', 'ad2601', 'product_edit:success [Admin] Updated product #35: ', 'system', '-', '2026-04-29'),
('L446946919', 'ad2601', 'product_edit:success [Admin] Updated product #35: ', 'system', '-', '2026-04-29'),
('L447206859', 'ad2601', 'product_edit:success [Admin] Updated product #30: ', 'system', '-', '2026-04-29'),
('L447445221', 'ad2601', 'product_edit:success [Admin] Updated product #29: ', 'system', '-', '2026-04-29'),
('L448184448', 'ad2601', 'product_edit:success [Admin] Updated product #28: ', 'system', '-', '2026-04-29'),
('L449547544', 'ad2601', 'product_edit:success [Admin] Updated product #27: ', 'system', '-', '2026-04-29'),
('L450357173', 'ad2601', 'product_edit:success [Admin] Updated product #26: ', 'system', '-', '2026-04-29'),
('L451237355', 'ad2601', 'product_edit:success [Admin] Updated product #11: ', 'system', '-', '2026-04-29'),
('L451389671', 'ad2601', 'product_delete:success [Admin] Deleted product #15', 'system', '-', '2026-04-29'),
('L451457003', 'ad2601', 'product_delete:success [Admin] Deleted product #13', 'system', '-', '2026-04-29'),
('L451514485', 'ad2601', 'product_delete:success [Admin] Deleted product #14', 'system', '-', '2026-04-29'),
('L452474573', 'ad2601', 'product_edit:success [Admin] Updated product #25: ', 'system', '-', '2026-04-29'),
('L452581558', 'ad2601', 'product_delete:success [Admin] Deleted product #24', 'system', '-', '2026-04-29'),
('L453339145', 'ad2601', 'product_edit:success [Admin] Updated product #23: ', 'system', '-', '2026-04-29'),
('L453418597', 'ad2601', 'product_delete:success [Admin] Deleted product #22', 'system', '-', '2026-04-29'),
('L453511128', 'ad2601', 'product_delete:success [Admin] Deleted product #10', 'system', '-', '2026-04-29'),
('L453632621', 'ad2601', 'product_delete:success [Admin] Deleted product #33', 'system', '-', '2026-04-29'),
('L453695724', 'ad2601', 'product_delete:success [Admin] Deleted product #32', 'system', '-', '2026-04-29'),
('L453831800', 'ad2601', 'brand_delete:success [Admin] Deleted brand #19', 'system', '-', '2026-04-29'),
('L453939563', 'ad2601', 'brand_delete:success [Admin] Deleted brand #18', 'system', '-', '2026-04-29'),
('L454298706', 'ad2601', 'brand_delete:success [Admin] Deleted brand #11', 'system', '-', '2026-04-29'),
('L454537949', 'ad2601', 'brand_delete:success [Admin] Deleted brand #13', 'system', '-', '2026-04-29'),
('L454595680', 'ad2601', 'brand_delete:success [Admin] Deleted brand #17', 'system', '-', '2026-04-29'),
('L454642839', 'ad2601', 'brand_delete:success [Admin] Deleted brand #16', 'system', '-', '2026-04-29'),
('L454693015', 'ad2601', 'brand_delete:success [Admin] Deleted brand #15', 'system', '-', '2026-04-29'),
('L454759960', 'ad2601', 'brand_delete:success [Admin] Deleted brand #14', 'system', '-', '2026-04-29'),
('L454962022', 'ad2601', 'category_delete:success [Admin] Deleted category #', 'system', '-', '2026-04-29'),
('L455534817', 'ad2601', 'product_edit:success [Admin] Updated product #34: ', 'system', '-', '2026-04-29'),
('L455769733', 'ad2601', 'category_delete:success [Admin] Deleted category #', 'system', '-', '2026-04-29'),
('L455918958', 'ad2601', 'category_edit:success [Admin] Updated category #3:', 'system', '-', '2026-04-29'),
('L456217148', 'ad2601', 'category_edit:success [Admin] Updated category #14', 'system', '-', '2026-04-29'),
('L456462836', 'ad2601', 'category_edit:success [Admin] Updated category #11', 'system', '-', '2026-04-29'),
('L456599756', 'ad2601', 'category_edit:success [Admin] Updated category #10', 'system', '-', '2026-04-29'),
('L456731780', 'ad2601', 'category_edit:success [Admin] Updated category #9:', 'system', '-', '2026-04-29'),
('L457557110', 'ad2601', 'manager_permission:success [Admin] Activated manag', 'system', '-', '2026-04-29'),
('L460635488', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L460764749', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L470324289', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L479568219', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-05-01'),
('L483998691', 'ad2601', 'product_edit:success [Admin] Updated product #30: ', 'system', '-', '2026-04-29'),
('L483999853', 'ad2601', 'product_edit:success [Admin] Updated product #30: ', 'system', '-', '2026-04-29'),
('L514851922', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L515177307', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-29'),
('L515279984', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-29'),
('L536384335', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-05-01'),
('L543952883', 'ad2601', 'order_status:success [Admin] Order O69F4DA372: Pen', 'system', '-', '2026-05-01'),
('L544205204', 'ad2601', 'order_status:success [Admin] Order O69F4DA372: Acc', 'system', '-', '2026-05-01'),
('L544637086', 'ad2601', 'order_status:success [Admin] Order O69F4DA372: On ', 'system', '-', '2026-05-01'),
('L546484543', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L550397445', 'ad2601', 'product_edit:success [Admin] Updated product #12: ', 'system', '-', '2026-05-01'),
('L551183063', 'ad2601', 'order_status:success [Admin] Order O69F4DA372: Del', 'system', '-', '2026-05-01'),
('L555787200', 'ad2601', 'manager_permission:success [Admin] Deactivated man', 'system', '-', '2026-05-01'),
('L556224382', 'ismi@gmail', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L556673060', 'irshad@gma', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L556848063', 'irshad@gma', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L557114533', 'irshad@gma', 'login:failed Unknown username', 'auth', '-', '2026-05-01'),
('L557379912', 'Fahmi@gmai', 'login:failed Account deactivated', 'auth', '-', '2026-05-01'),
('L557543445', 'ad2601', 'manager_permission:success [Admin] Activated manag', 'system', '-', '2026-05-01'),
('L557659401', 'Fahmi@gmai', 'login:success Admin login successful', 'auth', '-', '2026-05-01'),
('L560768242', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L561186038', 'ad2601', 'product_status:success [Admin] Toggled product sta', 'system', '-', '2026-04-29'),
('L561508179', 'ad2601', 'product_status:success [Admin] Toggled product sta', 'system', '-', '2026-04-29'),
('L561773905', 'ad2601', 'order_status:success [Admin] Order O69F0D9247: Pen', 'system', '-', '2026-04-29'),
('L563327114', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-29'),
('L575585823', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L575692296', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-30'),
('L599787364', 'ad2601', 'order_status:success [Admin] Order O69F0D9247: On ', 'system', '-', '2026-04-30'),
('L599896658', 'ad2601', 'order_status:success [Admin] Order O69F0D9247: Del', 'system', '-', '2026-04-30'),
('L610719051', 'ad2601', 'order_status:success [Admin] Order O69F36DD40: Pen', 'system', '-', '2026-04-30'),
('L610818929', 'ad2601', 'order_status:success [Admin] Order O69F36DD40: Acc', 'system', '-', '2026-04-30'),
('L611001822', 'ad2601', 'order_status:success [Admin] Order O69F36DD40: On ', 'system', '-', '2026-04-30'),
('L614035571', 'iflaliflal', 'login:failed Unknown username', 'auth', '-', '2026-04-30'),
('L620254868', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L621875708', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-27'),
('L622003526', 'ad2601', 'brand_add:success [Admin] Added brand: HTC', 'system', '-', '2026-04-30'),
('L622104033', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-04-30'),
('L622134355', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-04-30'),
('L622296234', 'ad2601', 'brand_edit:success [Admin] Updated brand #20: HTC', 'system', '-', '2026-04-30'),
('L622382199', 'ad2601', 'brand_delete:success [Admin] Deleted brand #20', 'system', '-', '2026-04-30'),
('L629999306', 'ad2601', 'order_status:success [Admin] Order O69F3684B9: Pen', 'system', '-', '2026-04-30'),
('L630128784', 'ad2601', 'order_status:success [Admin] Order O69F3684B9: Acc', 'system', '-', '2026-04-30'),
('L630284539', 'ad2601', 'order_status:success [Admin] Order O69F3684B9: On ', 'system', '-', '2026-04-30'),
('L630506421', 'ad2601', 'order_status:success [Admin] Order O69F3684B9: Del', 'system', '-', '2026-04-30'),
('L630635640', 'ad2601', 'order_status:success [Admin] Order O69F0B91B8: Pen', 'system', '-', '2026-04-30'),
('L632961563', 'ad2601', 'customer_block:success [Admin] Blocked customer 2', 'system', '-', '2026-04-30'),
('L637229677', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L640545556', 'ad2601', 'product_edit:success [Admin] Updated product #27: ', 'system', '-', '2026-04-29'),
('L640837168', 'fahmi@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L641286756', 'ad2601', 'manager_permission:success [Admin] Deactivated man', 'system', '-', '2026-04-30'),
('L641469825', 'fahmi@gmai', 'login:failed Account deactivated', 'auth', '-', '2026-04-30'),
('L642011190', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-30'),
('L654034850', 'ad2601', 'product_edit:success [Admin] Updated product #16: ', 'system', '-', '2026-04-29'),
('L654177154', 'ad2601', 'product_edit:success [Admin] Updated product #12: ', 'system', '-', '2026-04-29'),
('L654331237', 'ad2601', 'product_edit:success [Admin] Updated product #18: ', 'system', '-', '2026-04-29'),
('L654448507', 'ad2601', 'product_edit:success [Admin] Updated product #8: G', 'system', '-', '2026-04-29'),
('L655208191', 'ad2601', 'product_edit:success [Admin] Updated product #7: R', 'system', '-', '2026-04-29'),
('L655327457', 'ad2601', 'product_edit:success [Admin] Updated product #5: S', 'system', '-', '2026-04-29'),
('L655444296', 'ad2601', 'product_edit:success [Admin] Updated product #3: i', 'system', '-', '2026-04-29'),
('L655588409', 'ad2601', 'category_delete:success [Admin] Deleted category #', 'system', '-', '2026-04-29'),
('L656958582', 'ad2601', 'category_delete:success [Admin] Deleted category #', 'system', '-', '2026-04-29'),
('L662074294', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-30'),
('L677886720', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-27'),
('L681185580', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L684614295', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L684974874', 'iflalahame', 'login:failed Password mismatch', 'auth', '-', '2026-04-30'),
('L685372043', 'iflalahame', 'login:failed Password mismatch', 'auth', '-', '2026-04-30'),
('L685474608', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L685661898', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L688209993', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L689089551', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L689106615', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-04-30'),
('L691385695', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L692707675', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L695078672', 'ad2601', 'customer_block:success [Admin] Unblocked customer ', 'system', '-', '2026-04-30'),
('L695237525', 'ad2601', 'customer_block:success [Admin] Blocked customer 2', 'system', '-', '2026-04-30'),
('L696322048', 'ad2601', 'shipping_rate_save:success [Admin] Saved shipping ', 'system', '-', '2026-04-30'),
('L697094341', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L698342524', 'ad2601', 'discount_save:success [Admin] Created coupon TEST1', 'system', '-', '2026-04-30'),
('L698466899', 'ad2601', 'product_edit:success [Admin] Updated product #31: ', 'system', '-', '2026-04-29'),
('L706286130', 'ad2601', 'bundle_status:success [Admin] Toggled bundle statu', 'system', '-', '2026-04-29'),
('L709511842', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L735111802', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L749403466', 'ad2601', 'bundle_edit:success [Admin] Updated bundle #1: iBu', 'system', '-', '2026-04-29'),
('L754566735', 'ad2601', 'bundle_add:success [Admin] Added bundle #2: iBundl', 'system', '-', '2026-04-29'),
('L775086947', 'ad2601', 'bundle_add:success [Admin] Added bundle #3: iBundl', 'system', '-', '2026-04-29'),
('L826285298', 'admin@gmai', 'login:success Admin login successful', 'auth', '-', '2026-04-29'),
('L826393366', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-04-29'),
('L851373352', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-27'),
('L859156261', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-04-28'),
('L859678392', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-04-28'),
('L859771949', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-04-28'),
('L860067780', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-04-28'),
('L860108021', 'admin@gmai', 'login:failed Unknown username', 'auth', '-', '2026-04-28'),
('L860928517', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L860963076', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L861267398', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L862052099', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L862185434', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L863174582', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L914125452', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-02'),
('L914128910', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-27'),
('L915401118', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-05-02'),
('L915626139', 'ad2601', 'brand_status:success [Admin] Toggled brand status ', 'system', '-', '2026-05-02'),
('L916006643', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L916108159', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L922665068', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L922727825', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L925619278', 'ad2601', 'settings_update:success [Admin] Banner updated #3', 'system', '-', '2026-05-02'),
('L925816273', 'ad2601', 'settings_update:success [Admin] Banner updated #3', 'system', '-', '2026-05-02'),
('L926466815', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-05-02'),
('L926664252', 'ad2601', 'settings_update:success [Admin] Seasonal settings ', 'system', '-', '2026-05-02'),
('L935723607', 'shahanifar', 'login:failed Password mismatch', 'auth', '-', '2026-04-28'),
('L935831306', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L955423025', 'shahanifar', 'login:success Admin login successful', 'auth', '-', '2026-04-28'),
('L995446706', 'Fahmi@gmai', 'login:failed Account deactivated', 'auth', '-', '2026-05-01'),
('L995832985', 'iflalahame', 'login:success Admin login successful', 'auth', '-', '2026-05-01');

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin_page_permission`
--

CREATE TABLE `tbladmin_page_permission` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `page_key` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin_page_permission`
--

INSERT INTO `tbladmin_page_permission` (`id`, `admin_id`, `page_key`, `created_at`) VALUES
(1, 'M260429578', 'index.php', '2026-04-29 03:06:40'),
(2, 'M260429578', 'brand.php', '2026-04-29 03:06:40'),
(3, 'M260429578', 'category.php', '2026-04-29 03:06:40'),
(4, 'M260429578', 'product.php', '2026-04-29 03:06:40'),
(5, 'M260429578', 'allproducts.php', '2026-04-29 03:06:40'),
(9, 'M260501574', 'banner.php', '2026-05-01 06:41:46'),
(10, 'M260501574', 'logo.php', '2026-05-01 06:41:46'),
(11, 'M260501574', 'seasonal.php', '2026-05-01 06:41:46');

-- --------------------------------------------------------

--
-- Table structure for table `tblai_setting`
--

CREATE TABLE `tblai_setting` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblai_setting`
--

INSERT INTO `tblai_setting` (`setting_key`, `setting_value`, `updated_at`) VALUES
('product_media_focus_keywords', 'camera sample,night mode,gaming performance,fps test,benchmark,battery test,display test,review', '2026-04-27 10:52:46'),
('product_media_system_prompt', 'Prioritize product-relevant media searches. Avoid ambiguous non-tech meaning. Focus on camera sample photos.', '2026-05-01 04:37:03');

-- --------------------------------------------------------

--
-- Table structure for table `tblai_user_behavior`
--

CREATE TABLE `tblai_user_behavior` (
  `behavior_id` bigint(20) UNSIGNED NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `event_type` varchar(30) NOT NULL,
  `event_weight` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `event_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblai_user_behavior`
--

INSERT INTO `tblai_user_behavior` (`behavior_id`, `cus_id`, `product_id`, `event_type`, `event_weight`, `event_date`) VALUES
(1, '1', '1', 'view', 1.0000, '2026-04-27 07:08:54'),
(2, '1', '1', 'view', 1.0000, '2026-04-27 07:10:39'),
(3, '1', '1', 'view', 1.0000, '2026-04-27 07:12:36'),
(4, '1', '1', 'view', 1.0000, '2026-04-27 07:13:31'),
(5, '1', '1', 'add_cart', 5.0000, '2026-04-27 07:16:54'),
(9, '1', '1', 'view', 1.0000, '2026-04-27 07:50:20'),
(11, '1', '3', 'add_cart', 5.0000, '2026-04-27 07:59:44'),
(16, '1', '1', 'view', 1.0000, '2026-04-27 11:43:28'),
(17, '1', '1', 'view', 1.0000, '2026-04-27 12:52:18'),
(21, '1', '2', 'view', 1.0000, '2026-04-27 13:12:31'),
(22, '1', '1', 'view', 1.0000, '2026-04-27 13:12:53'),
(34, '1', '1', 'view', 1.0000, '2026-04-27 13:30:56'),
(36, '1', '1', 'view', 1.0000, '2026-04-27 13:35:29'),
(40, '1', '2', 'view', 1.0000, '2026-04-27 13:38:25'),
(41, '1', '2', 'view', 1.0000, '2026-04-27 13:40:50'),
(42, '1', '1', 'view', 1.0000, '2026-04-27 13:41:59'),
(43, '1', '1', 'view', 1.0000, '2026-04-27 13:46:02'),
(44, '1', '1', 'view', 1.0000, '2026-04-27 13:46:10'),
(46, '1', '1', 'view', 1.0000, '2026-04-27 13:58:23'),
(47, '1', '3', 'view', 1.0000, '2026-04-27 15:54:40'),
(48, '1', '3', 'wishlist', 3.0000, '2026-04-27 15:54:43'),
(49, '1', '3', 'view', 1.0000, '2026-04-27 15:54:54'),
(50, '1', '3', 'view', 1.0000, '2026-04-27 15:55:19'),
(51, '1', '3', 'view', 1.0000, '2026-04-27 15:57:07'),
(52, '1', '4', 'view', 1.0000, '2026-04-27 16:03:37'),
(53, '1', '3', 'view', 1.0000, '2026-04-27 16:04:54'),
(54, '1', '3', 'view', 1.0000, '2026-04-27 16:08:33'),
(55, '1', '4', 'view', 1.0000, '2026-04-27 16:09:46'),
(56, '1', '4', 'view', 1.0000, '2026-04-27 16:11:26'),
(57, '1', '4', 'view', 1.0000, '2026-04-27 16:11:33'),
(58, '1', '3', 'view', 1.0000, '2026-04-27 16:11:55'),
(59, '1', '3', 'view', 1.0000, '2026-04-27 16:14:35'),
(60, '1', '3', 'view', 1.0000, '2026-04-27 16:14:41'),
(61, '1', '4', 'view', 1.0000, '2026-04-27 16:15:26'),
(62, '1', '4', 'view', 1.0000, '2026-04-27 16:22:52'),
(63, '1', '4', 'view', 1.0000, '2026-04-27 16:28:52'),
(64, '1', '3', 'view', 1.0000, '2026-04-27 16:28:57'),
(65, '1', '3', 'view', 1.0000, '2026-04-27 16:33:35'),
(66, '1', '3', 'view', 1.0000, '2026-04-27 16:38:25'),
(67, '1', '3', 'view', 1.0000, '2026-04-27 16:38:36'),
(68, '1', '3', 'view', 1.0000, '2026-04-27 16:42:55'),
(69, '1', '3', 'view', 1.0000, '2026-04-27 16:43:05'),
(70, '1', '3', 'view', 1.0000, '2026-04-27 16:46:36'),
(71, '1', '3', 'view', 1.0000, '2026-04-27 16:55:01'),
(72, '1', '3', 'view', 1.0000, '2026-04-27 16:55:04'),
(73, '1', '3', 'view', 1.0000, '2026-04-27 17:01:30'),
(74, '1', '3', 'view', 1.0000, '2026-04-27 17:08:32'),
(75, '1', '3', 'view', 1.0000, '2026-04-27 17:16:51'),
(76, '1', '2', 'view', 1.0000, '2026-04-27 17:21:19'),
(77, '1', '2', 'view', 1.0000, '2026-04-27 17:28:10'),
(78, '1', '3', 'view', 1.0000, '2026-04-27 18:54:40'),
(79, '1', '3', 'view', 1.0000, '2026-04-27 18:56:20'),
(80, '1', '3', 'view', 1.0000, '2026-04-27 19:08:54'),
(81, '1', '3', 'view', 1.0000, '2026-04-27 19:21:17'),
(82, '1', '3', 'view', 1.0000, '2026-04-27 19:21:22'),
(83, '1', '3', 'view', 1.0000, '2026-04-27 19:21:36'),
(84, '1', '3', 'view', 1.0000, '2026-04-27 19:23:17'),
(85, '1', '5', 'view', 1.0000, '2026-04-27 19:25:33'),
(86, '1', '5', 'view', 1.0000, '2026-04-27 19:27:40'),
(87, '1', '5', 'view', 1.0000, '2026-04-27 19:30:04'),
(88, '1', '5', 'view', 1.0000, '2026-04-27 19:32:11'),
(89, '1', '5', 'view', 1.0000, '2026-04-27 19:32:47'),
(90, '1', '5', 'view', 1.0000, '2026-04-27 19:37:07'),
(91, '1', '5', 'view', 1.0000, '2026-04-27 19:49:50'),
(92, '1', '3', 'view', 1.0000, '2026-04-27 19:50:53'),
(93, '1', '3', 'view', 1.0000, '2026-04-27 19:54:34'),
(94, '1', '3', 'view', 1.0000, '2026-04-27 19:56:25'),
(95, '1', '3', 'add_cart', 5.0000, '2026-04-27 19:57:14'),
(96, '1', '4', 'add_cart', 5.0000, '2026-04-27 19:57:15'),
(97, '1', '3', 'view', 1.0000, '2026-04-27 19:57:25'),
(98, '1', '5', 'view', 1.0000, '2026-04-27 19:57:47'),
(99, '1', '4', 'view', 1.0000, '2026-04-27 19:58:16'),
(100, '1', '4', 'view', 1.0000, '2026-04-27 20:01:40'),
(101, '1', '4', 'view', 1.0000, '2026-04-27 20:06:58'),
(102, '1', '3', 'view', 1.0000, '2026-04-27 20:07:14'),
(103, '1', '3', 'view', 1.0000, '2026-04-27 20:12:00'),
(104, '1', '3', 'view', 1.0000, '2026-04-27 20:15:28'),
(105, '1', '3', 'view', 1.0000, '2026-04-27 20:20:08'),
(106, '1', '6', 'view', 1.0000, '2026-04-27 20:20:33'),
(107, '1', '6', 'view', 1.0000, '2026-04-27 20:24:39'),
(108, '1', '4', 'view', 1.0000, '2026-04-27 20:25:02'),
(109, '1', '4', 'add_cart', 5.0000, '2026-04-27 20:25:19'),
(110, '1', '6', 'add_cart', 5.0000, '2026-04-27 20:25:19'),
(111, '1', '4', 'view', 1.0000, '2026-04-28 07:41:26'),
(112, '1', '3', 'view', 1.0000, '2026-04-28 07:42:01'),
(113, '1', '4', 'view', 1.0000, '2026-04-28 07:53:07'),
(114, '1', '4', 'add_cart', 5.0000, '2026-04-28 07:53:19'),
(115, '1', '4', 'add_cart', 5.0000, '2026-04-28 07:53:19'),
(116, '1', '4', 'view', 1.0000, '2026-04-28 07:56:37'),
(117, '1', '4', 'add_cart', 5.0000, '2026-04-28 07:56:46'),
(118, '1', '4', 'add_cart', 5.0000, '2026-04-28 07:56:46'),
(119, '1', '7', 'view', 1.0000, '2026-04-28 19:06:06'),
(120, '1', '7', 'add_cart', 5.0000, '2026-04-28 19:09:43'),
(121, '1', '4', 'view', 1.0000, '2026-04-28 20:06:11'),
(122, '1', '5', 'view', 1.0000, '2026-04-28 20:06:24'),
(123, '1', '3', 'view', 1.0000, '2026-04-28 20:13:38'),
(124, '1', '3', 'add_cart', 5.0000, '2026-04-28 20:16:17'),
(125, '1', '6', 'add_cart', 5.0000, '2026-04-28 20:16:18'),
(126, '1', '4', 'add_cart', 5.0000, '2026-04-28 20:16:18'),
(127, '1', '3', 'view', 1.0000, '2026-04-28 20:16:42'),
(128, '1', '3', 'view', 1.0000, '2026-04-28 20:20:42'),
(129, '1', '5', 'view', 1.0000, '2026-04-28 20:24:50'),
(130, '1', '4', 'view', 1.0000, '2026-04-28 20:25:16'),
(131, '1', '4', 'view', 1.0000, '2026-04-28 20:25:19'),
(132, '1', '7', 'view', 1.0000, '2026-04-28 22:17:33'),
(133, '1', '7', 'view', 1.0000, '2026-04-28 22:18:58'),
(134, '1', '7', 'view', 1.0000, '2026-04-28 22:20:19'),
(135, '1', '7', 'view', 1.0000, '2026-04-28 22:21:14'),
(136, '1', '7', 'view', 1.0000, '2026-04-28 22:27:25'),
(137, '1', '7', 'view', 1.0000, '2026-04-28 22:27:45'),
(138, '1', '5', 'view', 1.0000, '2026-04-28 22:29:42'),
(139, '1', '9', 'view', 1.0000, '2026-04-29 11:30:34'),
(140, '1', '9', 'view', 1.0000, '2026-04-29 11:33:05'),
(141, '1', '9', 'view', 1.0000, '2026-04-29 11:35:48'),
(142, '1', '9', 'view', 1.0000, '2026-04-29 11:38:13'),
(143, '1', '36', 'view', 1.0000, '2026-04-29 11:49:54'),
(144, '1', '36', 'view', 1.0000, '2026-04-29 11:56:01'),
(145, '1', '4', 'view', 1.0000, '2026-04-29 11:59:04'),
(146, '1', '36', 'view', 1.0000, '2026-04-29 12:24:41'),
(147, '1', '28', 'view', 1.0000, '2026-04-29 12:27:48'),
(148, '1', '7', 'view', 1.0000, '2026-04-29 12:28:03'),
(149, '1', '12', 'view', 1.0000, '2026-04-29 12:30:35'),
(150, '1', '11', 'view', 1.0000, '2026-04-29 12:50:44'),
(151, '1', '11', 'view', 1.0000, '2026-04-29 13:01:31'),
(152, '1', '30', 'view', 1.0000, '2026-04-29 13:10:11'),
(153, '1', '11', 'view', 1.0000, '2026-04-29 13:10:49'),
(154, '1', '9', 'view', 1.0000, '2026-04-29 15:14:08'),
(155, '1', '9', 'add_cart', 5.0000, '2026-04-29 15:14:40'),
(156, '1', '21', 'add_cart', 5.0000, '2026-04-29 15:14:40'),
(157, '1', '20', 'add_cart', 5.0000, '2026-04-29 15:14:40'),
(158, '1', '9', 'add_cart', 5.0000, '2026-04-29 15:14:47'),
(159, '1', '9', 'add_cart', 5.0000, '2026-04-29 15:14:47'),
(160, '1', '9', 'add_cart', 5.0000, '2026-04-29 15:14:52'),
(161, '1', '21', 'add_cart', 5.0000, '2026-04-29 15:14:52'),
(162, '1', '20', 'add_cart', 5.0000, '2026-04-29 15:14:52'),
(163, '1', '35', 'add_cart', 5.0000, '2026-04-29 15:14:52'),
(164, '1', '11', 'view', 1.0000, '2026-04-29 19:05:57'),
(165, '1', '4', 'view', 1.0000, '2026-04-29 19:06:28'),
(166, '1', '27', 'view', 1.0000, '2026-04-29 19:06:36'),
(167, '1', '31', 'view', 1.0000, '2026-04-29 19:06:55'),
(168, '1', '31', 'view', 1.0000, '2026-04-29 19:07:30'),
(169, '1', '31', 'view', 1.0000, '2026-04-29 19:10:05'),
(170, '1', '11', 'view', 1.0000, '2026-04-29 19:10:20'),
(171, '1', '17', 'view', 1.0000, '2026-04-29 19:10:44'),
(172, '1', '11', 'view', 1.0000, '2026-04-29 19:19:15'),
(173, '1', '11', 'view', 1.0000, '2026-04-29 19:20:31'),
(174, '1', '11', 'view', 1.0000, '2026-04-29 19:20:49'),
(175, '1', '11', 'view', 1.0000, '2026-04-29 19:21:50'),
(176, '1', '11', 'view', 1.0000, '2026-04-29 19:23:07'),
(177, '1', '11', 'view', 1.0000, '2026-04-29 19:23:34'),
(178, '1', '11', 'view', 1.0000, '2026-04-29 19:25:07'),
(179, '1', '11', 'view', 1.0000, '2026-04-29 19:25:16'),
(180, '1', '11', 'view', 1.0000, '2026-04-29 19:25:58'),
(181, '1', '11', 'view', 1.0000, '2026-04-29 19:34:26'),
(182, '1', '11', 'view', 1.0000, '2026-04-29 19:40:46'),
(183, '1', '8', 'view', 1.0000, '2026-04-29 19:55:09'),
(184, '1', '8', 'view', 1.0000, '2026-04-29 20:05:17'),
(185, '1', '12', 'view', 1.0000, '2026-04-29 20:11:23'),
(186, '1', '12', 'view', 1.0000, '2026-04-29 20:11:35'),
(187, '1', '18', 'view', 1.0000, '2026-04-29 20:12:08'),
(188, '1', '18', 'view', 1.0000, '2026-04-29 20:27:23'),
(189, '1', '11', 'view', 1.0000, '2026-04-29 20:35:50'),
(190, '1', '11', 'view', 1.0000, '2026-04-29 20:38:20'),
(191, '1', '11', 'view', 1.0000, '2026-04-29 20:39:39'),
(192, '1', '11', 'view', 1.0000, '2026-04-29 20:41:00'),
(193, '1', '11', 'view', 1.0000, '2026-04-29 20:45:55'),
(194, '1', '11', 'view', 1.0000, '2026-04-29 21:03:17'),
(195, '1', '11', 'view', 1.0000, '2026-04-29 21:14:12'),
(196, '1', '11', 'view', 1.0000, '2026-04-29 21:15:11'),
(197, '1', '11', 'view', 1.0000, '2026-04-29 21:17:09'),
(198, '1', '8', 'view', 1.0000, '2026-04-29 21:49:05'),
(199, '1', '3', 'add_cart', 5.0000, '2026-04-30 09:26:57'),
(200, '1', '11', 'view', 1.0000, '2026-04-30 09:32:20'),
(201, '1', '17', 'view', 1.0000, '2026-04-30 09:40:11'),
(202, '1', '4', 'view', 1.0000, '2026-04-30 09:57:35'),
(203, '1', '4', 'view', 1.0000, '2026-04-30 10:16:21'),
(204, '1', '11', 'view', 1.0000, '2026-04-30 10:17:40'),
(205, '1', '18', 'view', 1.0000, '2026-04-30 10:30:00'),
(206, '1', '9', 'view', 1.0000, '2026-04-30 10:30:25'),
(207, '1', '21', 'view', 1.0000, '2026-04-30 10:31:38'),
(208, '1', '9', 'view', 1.0000, '2026-04-30 10:31:52'),
(209, '1', '9', 'view', 1.0000, '2026-04-30 10:32:24'),
(210, '1', '34', 'view', 1.0000, '2026-04-30 10:46:08'),
(211, '1', '11', 'view', 1.0000, '2026-04-30 10:46:29'),
(212, '1', '11', 'view', 1.0000, '2026-04-30 11:05:24'),
(213, '1', '11', 'view', 1.0000, '2026-04-30 11:07:21'),
(214, '1', '11', 'view', 1.0000, '2026-04-30 11:10:41'),
(215, '1', '7', 'view', 1.0000, '2026-04-30 11:12:10'),
(216, '1', '36', 'view', 1.0000, '2026-04-30 11:13:51'),
(217, '1', '3', 'view', 1.0000, '2026-04-30 11:15:02'),
(218, '1', '3', 'add_cart', 5.0000, '2026-04-30 11:16:53'),
(219, '1', '3', 'add_cart', 5.0000, '2026-04-30 11:16:53'),
(220, '1', '3', 'wishlist', 3.0000, '2026-04-30 11:16:54'),
(221, '1', '37', 'view', 1.0000, '2026-04-30 11:33:36'),
(222, '1', '37', 'view', 1.0000, '2026-04-30 11:36:59'),
(223, '1', '37', 'view', 1.0000, '2026-04-30 11:38:57'),
(224, '1', '21', 'view', 1.0000, '2026-04-30 11:44:31'),
(225, '1', '9', 'view', 1.0000, '2026-04-30 11:46:27'),
(226, '2', '12', 'view', 1.0000, '2026-04-30 11:56:13'),
(227, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:22'),
(228, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:22'),
(229, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:24'),
(230, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:27'),
(231, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:29'),
(232, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:32'),
(233, '2', '12', 'add_cart', 5.0000, '2026-04-30 11:56:34'),
(234, '1', '11', 'add_cart', 5.0000, '2026-04-30 19:54:52'),
(235, '1', '11', 'wishlist', 3.0000, '2026-04-30 19:54:55'),
(236, '1', '4', 'wishlist', 3.0000, '2026-04-30 19:55:40'),
(237, '1', '12', 'wishlist', 3.0000, '2026-04-30 19:55:41'),
(238, '1', '9', 'wishlist', 3.0000, '2026-04-30 19:55:42'),
(239, '1', '11', 'view', 1.0000, '2026-04-30 20:23:45'),
(240, '1', '11', 'add_cart', 5.0000, '2026-04-30 20:27:14'),
(241, '1', '11', 'view', 1.0000, '2026-04-30 20:29:15'),
(242, '1', '12', 'add_cart', 5.0000, '2026-04-30 22:06:39'),
(243, '1', '8', 'wishlist', 3.0000, '2026-04-30 22:06:50'),
(244, '1', '18', 'wishlist', 3.0000, '2026-04-30 22:06:57'),
(245, '1', '5', 'view', 1.0000, '2026-04-30 22:11:33'),
(246, '1', '5', 'view', 1.0000, '2026-04-30 22:12:45'),
(247, '1', '8', 'view', 1.0000, '2026-04-30 22:14:47'),
(248, '1', '11', 'view', 1.0000, '2026-04-30 22:16:31'),
(249, '1', '18', 'add_cart', 5.0000, '2026-05-01 07:43:01'),
(250, '1', '8', 'view', 1.0000, '2026-05-01 07:51:29'),
(251, '1', '9', 'view', 1.0000, '2026-05-01 07:52:36'),
(252, '1', '9', 'view', 1.0000, '2026-05-01 08:09:32'),
(253, '1', '9', 'add_cart', 5.0000, '2026-05-01 08:20:02'),
(254, '1', '9', 'add_cart', 5.0000, '2026-05-01 08:20:04'),
(255, '1', '9', 'add_cart', 5.0000, '2026-05-01 08:20:07'),
(256, '1', '9', 'view', 1.0000, '2026-05-01 08:24:32'),
(257, '1', '6', 'view', 1.0000, '2026-05-01 08:26:31'),
(258, '1', '36', 'view', 1.0000, '2026-05-01 08:45:02'),
(259, '1', '36', 'view', 1.0000, '2026-05-01 08:50:21'),
(260, '1', '9', 'view', 1.0000, '2026-05-01 08:50:38'),
(261, '1', '9', 'view', 1.0000, '2026-05-01 09:03:00'),
(262, '1', '36', 'view', 1.0000, '2026-05-01 09:03:14'),
(263, '1', '9', 'view', 1.0000, '2026-05-01 09:06:22'),
(264, '1', '9', 'view', 1.0000, '2026-05-01 09:13:41'),
(265, '1', '9', 'view', 1.0000, '2026-05-01 09:16:16'),
(266, '1', '19', 'view', 1.0000, '2026-05-01 09:20:08'),
(267, '1', '9', 'view', 1.0000, '2026-05-01 09:24:35'),
(268, '1', '9', 'view', 1.0000, '2026-05-01 09:30:05'),
(269, '1', '37', 'view', 1.0000, '2026-05-01 09:51:55'),
(270, '1', '38', 'view', 1.0000, '2026-05-01 09:52:31'),
(271, '1', '38', 'add_cart', 5.0000, '2026-05-01 09:52:33'),
(272, '1', '9', 'view', 1.0000, '2026-05-01 10:07:13'),
(273, '1', '9', 'add_cart', 5.0000, '2026-05-01 10:12:20'),
(274, '1', '9', 'view', 1.0000, '2026-05-01 10:13:17'),
(275, '1', '9', 'add_cart', 5.0000, '2026-05-01 10:13:23'),
(276, '1', '9', 'wishlist', 3.0000, '2026-05-01 10:13:30'),
(277, '1', '9', 'view', 1.0000, '2026-05-01 10:15:21'),
(278, '1', '9', 'add_cart', 5.0000, '2026-05-01 10:15:30'),
(279, '1', '9', 'view', 1.0000, '2026-05-01 10:31:44'),
(280, '1', '38', 'view', 1.0000, '2026-05-01 11:55:19'),
(281, '1', '38', 'view', 1.0000, '2026-05-01 11:57:34'),
(282, '1', '9', 'add_cart', 5.0000, '2026-05-01 12:22:17'),
(283, '1', '9', 'add_cart', 5.0000, '2026-05-01 12:22:43'),
(284, '1', '38', 'view', 1.0000, '2026-05-01 12:32:50'),
(285, '1', '9', 'view', 1.0000, '2026-05-01 12:33:20'),
(286, '1', '4', 'add_cart', 5.0000, '2026-05-01 22:18:01'),
(287, '1', '4', 'wishlist', 3.0000, '2026-05-01 22:18:05'),
(288, '1', '38', 'view', 1.0000, '2026-05-02 08:37:33'),
(289, '1', '38', 'add_cart', 5.0000, '2026-05-02 08:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `tblai_user_search_history`
--

CREATE TABLE `tblai_user_search_history` (
  `search_id` bigint(20) UNSIGNED NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `search_text` varchar(255) NOT NULL,
  `search_tokens` varchar(255) NOT NULL DEFAULT '',
  `search_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblai_user_search_history`
--

INSERT INTO `tblai_user_search_history` (`search_id`, `cus_id`, `search_text`, `search_tokens`, `search_date`) VALUES
(1, '1', 'sam', 'sam', '2026-05-01 12:20:37'),
(2, '1', 'ip', 'ip', '2026-05-01 22:17:22');

-- --------------------------------------------------------

--
-- Table structure for table `tblbrand`
--

CREATE TABLE `tblbrand` (
  `brand_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(250) NOT NULL,
  `image` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblbrand`
--

INSERT INTO `tblbrand` (`brand_id`, `name`, `description`, `image`, `status`) VALUES
(1, 'Samsung', 'Discover the latest in electronic & smart appliance technology with Samsung. Find the next big thing from smartphones & tablets to laptops & tvs & more.', '20260427060205_4790.jpg', 1),
(2, 'Apple', 'Discover the innovative world of Apple and shop everything iPhone, iPad, Apple Watch, Mac, and Apple TV, plus explore accessories, entertainment', '20260427060315_1692.png', 1),
(3, 'Redmi', 'Best Phones', '20260428044236_4127.jpg', 1),
(4, 'Huawei', 'Huawei is a leading global provider of information and communications technology (ICT) infrastructure and smart devices.', '20260429072036_3476.jpg', 1),
(5, 'OnePlus', 'Well known for “flagship killer” phones, offering fast performance and clean software at competitive prices.', '20260429072144_1239.jpg', 1),
(6, 'Google', 'Produces Pixel phones, famous for pure Android experience and industry-leading camera software.', '20260429072220_3359.webp', 1),
(7, 'Oppo', 'Focuses on stylish designs and strong camera technology, popular in Asian and global markets.', '20260429072248_8737.jpg', 1),
(8, 'Nokia', 'Known for durability and simple Android One devices, focusing on long-lasting build quality and security updates.', '20260429072348_6108.jpg', 1),
(9, 'Realme', 'A fast-growing brand known for budget-friendly smartphones with strong performance and stylish designs, popular among young users.', '20260429072436_7825.png', 1),
(10, 'Vivo', 'Known for camera innovation, especially selfie features, and sleek mid-range smartphones', '20260429072549_2984.jpg', 1),
(12, 'Anker', 'Anker accessories and devices', 'logo.jpg', 1),
(21, 'Poco', 'Explore the latest POCO smartphones, prices, features, and launches', '20260501060659_4225.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblbundle`
--

CREATE TABLE `tblbundle` (
  `bundle_id` int(10) UNSIGNED NOT NULL,
  `bundle_name` varchar(150) NOT NULL,
  `bundle_model` varchar(120) NOT NULL DEFAULT '',
  `bundle_image` varchar(255) NOT NULL DEFAULT '',
  `bundle_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblbundle`
--

INSERT INTO `tblbundle` (`bundle_id`, `bundle_name`, `bundle_model`, `bundle_image`, `bundle_price`, `expiry_date`, `status`, `created_at`) VALUES
(1, 'iBundle', 'Apple iPhone 15 Pro Max', '20260429170220_bundle_4474.jpg', 150000.00, '2026-04-30', 1, '2026-04-29 18:24:09'),
(2, 'iBundle2', 'Apple iPhone 15 Pro Max', '20260429171056_bundle_3350.jpg', 1000000.00, '2026-04-30', 1, '2026-04-29 20:40:56'),
(3, 'iBundle3', 'Apple iPhone 15 Pro Max', '20260429174508_bundle_8870.webp', 500000.00, '2026-05-01', 1, '2026-04-29 21:15:08'),
(4, 'SamsungBundles', 'Galaxy S24 Ultra', '20260430081132_bundle_2485.jpg', 450000.00, '2026-05-01', 1, '2026-04-30 11:41:32'),
(5, 'May_Bundle', 'Redmi Note 14 Pro 4G', '20260501062137_bundle_9972.jpg', 100000.00, '2026-05-31', 1, '2026-05-01 09:51:37');

-- --------------------------------------------------------

--
-- Table structure for table `tblbundle_item`
--

CREATE TABLE `tblbundle_item` (
  `bundle_item_id` bigint(20) UNSIGNED NOT NULL,
  `bundle_id` int(10) UNSIGNED NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblbundle_item`
--

INSERT INTO `tblbundle_item` (`bundle_item_id`, `bundle_id`, `product_id`, `sort_order`) VALUES
(27, 1, '11', 1),
(28, 1, '36', 2),
(29, 2, '11', 1),
(30, 2, '36', 2),
(31, 3, '11', 1),
(32, 3, '36', 2),
(38, 4, '9', 1),
(39, 4, '30', 2),
(40, 4, '19', 3),
(41, 4, '21', 4),
(42, 4, '20', 5),
(43, 5, '7', 1),
(44, 5, '37', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tblcart`
--

CREATE TABLE `tblcart` (
  `cart_id` varchar(10) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(10) NOT NULL,
  `price` int(10) NOT NULL,
  `added_date` date NOT NULL,
  `selected_feature` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcart`
--

INSERT INTO `tblcart` (`cart_id`, `cus_id`, `product_id`, `quantity`, `price`, `added_date`, `selected_feature`) VALUES
('C69F1D3085', 1, 9, 6, 459999, '2026-04-29', 'Storage: 256GB 12GB RAM'),
('C69F1D3087', 1, 21, 2, 950, '2026-04-29', ''),
('C69F2D3097', 1, 3, 3, 340000, '2026-04-30', ''),
('C69F2F60E6', 2, 12, 1, 329999, '2026-04-30', ''),
('C69F4D9418', 1, 4, 1, 1500, '2026-05-01', 'Color: Green'),
('C69F56A854', 1, 38, 1, 35000, '2026-05-02', 'Storage: 32GB 2GB RAM');

-- --------------------------------------------------------

--
-- Table structure for table `tblcart_bundle`
--

CREATE TABLE `tblcart_bundle` (
  `cart_bundle_id` varchar(20) NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `bundle_id` int(10) UNSIGNED NOT NULL,
  `bundle_name` varchar(150) NOT NULL,
  `bundle_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `selected_variants` text DEFAULT NULL,
  `bundle_items_json` text DEFAULT NULL,
  `added_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcart_bundle`
--

INSERT INTO `tblcart_bundle` (`cart_bundle_id`, `cus_id`, `bundle_id`, `bundle_name`, `bundle_price`, `quantity`, `selected_variants`, `bundle_items_json`, `added_date`) VALUES
('CB69F4217B', '1', 4, 'SamsungBundles', 450000.00, 2, '[]', '[{\"product_id\":\"9\",\"name\":\"Samsung Galaxy S24 Ultra\",\"selected_feature\":\"\"},{\"product_id\":\"30\",\"name\":\"Metal Lens Ring Glass\",\"selected_feature\":\"\"},{\"product_id\":\"19\",\"name\":\"Soft Leather Deer Head Back Cases\",\"selected_feature\":\"\"},{\"product_id\":\"21\",\"name\":\"Samsung Galaxy S24 Ultra Tempered Glass\",\"selected_feature\":\"\"},{\"product_id\":\"20\",\"name\":\"Magsafe Magnetic Wireless Charge Phone Case\",\"selected_feature\":\"\"}]', '2026-05-01'),
('CB69F45CF0', '1', 3, 'iBundle3', 500000.00, 1, '[]', '[{\"product_id\":\"11\",\"name\":\"Apple iPhone 15 Pro Max\",\"selected_feature\":\"\"},{\"product_id\":\"36\",\"name\":\"Apple Watch Series 9 45mm\",\"selected_feature\":\"\"}]', '2026-05-01');

-- --------------------------------------------------------

--
-- Table structure for table `tblcategory`
--

CREATE TABLE `tblcategory` (
  `cat_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `image` longblob NOT NULL,
  `status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcategory`
--

INSERT INTO `tblcategory` (`cat_id`, `name`, `image`, `status`) VALUES
(3, 'Back Covers', 0x32303236303432373132323834395f393337372e6a7067, 1),
(4, 'Tempered Glass', 0x32303236303432373133353830345f333032362e6a7067, 1),
(5, 'Charger', 0x32303236303432373136333235385f333830332e6a7067, 1),
(6, 'Bluetooth Earbuds', 0x32303236303432393037323730375f313339392e77656270, 1),
(7, 'Memory Card', 0x32303236303432393037323831375f333834392e6a7067, 1),
(8, 'Phone Holder', 0x32303236303432393037323930335f383134352e77656270, 1),
(9, 'Flagship Phones', 0x32303236303432393038353433335f363039342e6a7067, 1),
(11, 'Budget Phones', 0x32303236303432393038353430365f343033312e6a7067, 1),
(14, 'Smart Watches', 0x32303236303432393038353334315f343934332e6a7067, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblchat_session`
--

CREATE TABLE `tblchat_session` (
  `session_id` varchar(10) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `topic` varchar(100) NOT NULL,
  `resolved` tinyint(1) NOT NULL,
  `transcript` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblchat_session`
--

INSERT INTO `tblchat_session` (`session_id`, `cus_id`, `startTime`, `endTime`, `topic`, `resolved`, `transcript`) VALUES
('S69F22B254', 1, '18:00:37', '08:37:46', 'Tell me about samsung m02', 0, '[{\"role\":\"user\",\"text\":\"Tell me about samsung m02\",\"time\":\"2026-04-29 18:00:38\"},{\"role\":\"assistant\",\"text\":\"The Samsung Galaxy M02 is listed on R-TEL and is in stock. Here are its details: The Samsung Galaxy M02 is a smartphone with a 6.5\\u2033 display, powered by a MT6739W chipset, and has a long-lasting 5000 mAh battery. It also comes with 32 GB storage and 3 GB RAM. You can find more information about this product on its product page. It is priced at Rs. 25,000.00, which is a competitive price compared to the compare price of Rs. 30,000.00 on our site. We have 15 units available in stock.\",\"time\":\"2026-04-29 18:00:38\"},{\"role\":\"user\",\"text\":\"show budget phones\",\"time\":\"2026-04-29 18:00:54\"},{\"role\":\"assistant\",\"text\":\"**Budget Phones on R-TEL** We have found 4 budget phones listed on R-TEL. Here are the details: 1. **Samsung Galaxy M02** (Rs. 25,000.00) - Listed on R-TEL, In Stock (15 units available) The Samsung Galaxy M02 is an Android smartphone with a 6.5\\u2033 display, MT6739W chipset, 5000 mAh battery, 32 GB storage, and 3 GB RAM. 2. **Redmi Note 13 Pro** (Rs. 74,900.00) - Listed on R-TEL, In Stock (49 units available) The Redmi Note 13 Pro is a smartphone with features including a display, chipset, battery, storage, and RAM. (Unfortunately, the description is not available on our website.) 3. **OnePlus Nord CE 4** (Rs. 129,999.00) - Listed on R-TEL, In Stock (28 units available) The OnePlus Nord CE 4 is a smartphone from OnePlus. 4. **Google Pixel 7a** (Rs. 169,999.00) - Listed on R-TEL, In Stock (24 units available) The Google Pixel 7a is a Google smartphone. To view more details about these phones, click on their product pages.\",\"time\":\"2026-04-29 18:00:54\"},{\"role\":\"user\",\"text\":\"show phones under 50000\",\"time\":\"2026-04-29 18:01:22\"},{\"role\":\"assistant\",\"text\":\"Phones under Rs. 50,000 on R-TEL: 1. Samsung Galaxy M02 (M02) - Listed on R-TEL. In stock (15 unit(s)). Price: Rs. 25,000.00. Features: Android smartphone, 6.5\\u2033 display, MT6739W chipset, 5000 mAh battery, 32 GB storage, 3 GB RAM. We don\'t have any other phones listed under Rs. 50,000 on R-TEL.\",\"time\":\"2026-04-29 18:01:22\"},{\"role\":\"user\",\"text\":\"hi\",\"time\":\"2026-04-30 05:51:09\"},{\"role\":\"assistant\",\"text\":\"Hello! How can I help you today?\",\"time\":\"2026-04-30 05:51:09\"},{\"role\":\"user\",\"text\":\"hi\",\"time\":\"2026-04-30 05:59:21\"},{\"role\":\"assistant\",\"text\":\"Hello! How can I help you today?\",\"time\":\"2026-04-30 05:59:21\"},{\"role\":\"user\",\"text\":\"samsung m02\",\"time\":\"2026-04-30 06:00:22\"},{\"role\":\"assistant\",\"text\":\"We have found a product that matches your search for \\\"samsung m02\\\". The Samsung Galaxy M02 is listed on R-TEL and is in stock. It has a 6.5-inch display, a Mediatek MT6739W chipset, a 5000 mAh battery, 32 GB storage, and 3 GB RAM. It runs on Android 10 with One UI 2.0. The price is Rs. 25,000.00, and the compare price on our site is Rs. 30,000.00. There are 15 units available. To view more details and buy the Samsung Galaxy M02, please visit its product page: product.php?product_id=5. Would you like to proceed with purchasing this product or search for something else?\",\"time\":\"2026-04-30 06:00:22\"},{\"role\":\"user\",\"text\":\"hi\",\"time\":\"2026-04-30 16:22:04\"},{\"role\":\"assistant\",\"text\":\"Hello! How can I help you today?\",\"time\":\"2026-04-30 16:22:04\"},{\"role\":\"user\",\"text\":\"show samsung phones\",\"time\":\"2026-05-01 04:09:28\"},{\"role\":\"assistant\",\"text\":\"Here\'s the final reply: **Samsung Phones on R-TEL** We have two Samsung phones listed on R-TEL. Here are the details: 1. **Samsung Galaxy M02** Listed on R-TEL: Yes In stock: Yes (15 units available) Price: Rs. 25,000.00 Compare price: Rs. 30,000.00 The Samsung Galaxy M02 features a 6.5-inch PLS LCD display, MT6739W chipset, 5000 mAh battery, 32 GB storage, and 3 GB RAM. It runs on Android 10 with One UI 2.0. 2. **Samsung Galaxy S24 Ultra** Listed on R-TEL: Yes In stock: Yes (18 units available) Price: Rs. 459,999.00 Compare price: Rs. 489,999.00 The Samsung Galaxy S24 Ultra features a 6.8-inch Dynamic LTPO AMOLED 2X display, Qualcomm Snapdragon 8 Gen 3 chipset, 5000 mAh battery, and up to 1TB storage with 12GB RAM. It runs on Android 14 with One UI 8.0. Click on the product links to view more details and buy.\",\"time\":\"2026-05-01 04:09:28\"},{\"role\":\"user\",\"text\":\"samsung galxy m02\",\"time\":\"2026-05-01 04:20:21\"},{\"role\":\"assistant\",\"text\":\"We found 3 products from Samsung that match your search. Here are the details for each: 1. **Samsung Galaxy M02** (Listed on R-TEL, In stock: 15 units available) The Samsung Galaxy M02 is an Android smartphone with a 6.5-inch display, MT6739W chipset, 5000 mAh battery, 32 GB storage, and 3 GB RAM. It runs on Android 10 with One UI 2.0. The price on R-TEL is Rs. 25,000.00. 2. **Galaxy Watch 6 44mm** (Listed on R-TEL, In stock: 14 units available) The Galaxy Watch 6 44mm is a Samsung smartwatch optimized for Android phones. Unfortunately, we don\'t have specific features listed for this product. 3. **Samsung Galaxy S24 Ultra** (Listed on R-TEL, In stock: 18 units available) The Samsung Galaxy S24 Ultra is a high-end smartphone with a 6.8-inch Dynamic LTPO AMOLED 2X display, Qualcomm Snapdragon 8 Gen 3 chipset, 5000 mAh battery, and up to 1TB storage. It runs on Android 14 with One UI 8.0. The price on R-TEL is Rs. 459,999.00.\",\"time\":\"2026-05-01 04:20:21\"},{\"role\":\"user\",\"text\":\"Samsung galaxy m02\",\"time\":\"2026-05-01 04:20:44\"},{\"role\":\"assistant\",\"text\":\"The Samsung Galaxy M02 is listed on R-TEL and it is in stock with 15 units available. The price is Rs. 25,000.00. This smartphone features a 6.5-inch PLS LCD display, a Mediatek MT6739W chipset, a 5000 mAh battery, 32 GB storage, and 3 GB RAM. It runs on Android 10 with One UI 2.0. The camera setup includes a 13 MP primary camera and a 5 MP selfie camera. It also supports USB-C charging. Please visit our product page for more details: <https:\\/\\/yourdomain.com\\/product.php?product_id=5>\",\"time\":\"2026-05-01 04:20:44\"},{\"role\":\"user\",\"text\":\"hi\",\"time\":\"2026-05-01 04:25:51\"},{\"role\":\"assistant\",\"text\":\"Hello! How can I help you today?\",\"time\":\"2026-05-01 04:25:51\"},{\"role\":\"user\",\"text\":\"Poco c40\",\"time\":\"2026-05-01 08:37:33\"},{\"role\":\"assistant\",\"text\":\"I can only help with R-TEL shopping: products, prices, cart, checkout, orders, account, shipping, coupons, ratings, feedback, and site issues (bugs\\/errors). Please rephrase using those topics.\",\"time\":\"2026-05-01 08:37:33\"},{\"role\":\"user\",\"text\":\"tell me about poco c40\",\"time\":\"2026-05-01 08:37:46\"},{\"role\":\"assistant\",\"text\":\"The Poco C40 is listed on R-TEL. It is currently in stock with 14 units available. The price is Rs. 35,000.00. Here are its key specifications: - Announced: 2022, June 06 - Display: 6.71 inches, IPS LCD with 400 nits, 82.0% screen-to-body ratio - Operating System: Android 11 with MIUI 13 for POCO - Processor: JLQ JR510 (11 nm), Octa-core (4x2.0 GHz Cortex-A55 & 4x1.5 GHz Cortex-A55) - Graphics: Mali-G57 MC1 - Main Camera: 13 MP with f\\/2.2, PDAF, and an auxiliary lens - Selfie Camera: 5 MP with f\\/2.2 - Battery: Li-Po 6000 mAh with 18W wired charging - RAM Options: 32GB with 2GB, 3GB, or 4GB RAM, or 64GB with 4GB RAM\",\"time\":\"2026-05-01 08:37:46\"}]');

-- --------------------------------------------------------

--
-- Table structure for table `tblcomment`
--

CREATE TABLE `tblcomment` (
  `com_id` int(11) NOT NULL,
  `cus_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `comment` varchar(250) NOT NULL,
  `status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcomment`
--

INSERT INTO `tblcomment` (`com_id`, `cus_id`, `name`, `comment`, `status`) VALUES
(1, 2, 'Iflal Ahamed', 'Good Website', 1),
(2, NULL, 'Fathima', 'Great experience! The AI assistant helped me find the best phone under my budget. Highly recommended!', 1),
(3, NULL, 'Mohamed Ismi Mohamed Aaqil', 'The website is very easy to use. I found what I needed within minutes.', 1),
(4, NULL, 'MIM FAHMI', 'I like how the AI compares products clearly. Very helpful feature', 1),
(5, 1, 'Iflal Ahamed', 'Hello', 1),
(6, 1, 'MIM FAHMI', 'Good', 1),
(8, 1, 'IFLAL AHAMED', 'Good website', 0),
(9, 1, 'IFLAL AHAMED', 'Good website', 0),
(10, 1, 'IFLAL AHAMED', 'Good website', 0),
(11, 1, 'IFLAL AHAMED', 'Good website', 0),
(12, 1, 'IFLAL AHAMED', 'Good website', 0),
(13, 1, 'IFLAL AHAMED', 'Good website', 0),
(14, 1, 'IFLAL AHAMED', 'Good website', 0),
(15, 1, 'IFLAL AHAMED', 'Good website', 0),
(16, 1, 'IFLAL AHAMED', 'Good website', 0),
(17, 1, 'IFLAL AHAMED', 'Good website', 0),
(18, 1, 'IFLAL AHAMED', 'Good website', 0),
(19, 1, 'IFLAL AHAMED', 'Good website', 0),
(20, 1, 'IFLAL AHAMED', 'Good website', 0),
(21, 1, 'IFLAL AHAMED', 'Good website', 0),
(22, 1, 'IFLAL AHAMED', 'Good website', 0),
(23, 1, 'IFLAL AHAMED', 'Good website', 0),
(24, 1, 'IFLAL AHAMED', 'Hi', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tblcontact`
--

CREATE TABLE `tblcontact` (
  `no` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(250) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `email` varchar(250) NOT NULL,
  `whatsapp` varchar(250) NOT NULL,
  `insta` varchar(250) NOT NULL,
  `fb` varchar(250) NOT NULL,
  `whatsapp_status` tinyint(1) NOT NULL DEFAULT 1,
  `insta_status` tinyint(1) NOT NULL DEFAULT 1,
  `fb_status` tinyint(1) NOT NULL DEFAULT 1,
  `seasonal_effect_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `seasonal_effect_theme` varchar(30) NOT NULL DEFAULT 'auto',
  `seasonal_effect_emojis` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcontact`
--

INSERT INTO `tblcontact` (`no`, `name`, `address`, `phone`, `email`, `whatsapp`, `insta`, `fb`, `whatsapp_status`, `insta_status`, `fb_status`, `seasonal_effect_enabled`, `seasonal_effect_theme`, `seasonal_effect_emojis`) VALUES
(1, 'R-tel Mobile Shop', '43B, Main Street , Eravur, Sri Lanka', '0777777777', 'rtel@gmail.com', 'wa', '', '', 1, 0, 0, 0, 'christmas', '');

-- --------------------------------------------------------

--
-- Table structure for table `tblcoupon`
--

CREATE TABLE `tblcoupon` (
  `coupon_id` varchar(10) NOT NULL,
  `order_id` varchar(10) NOT NULL,
  `cus_id` varchar(250) NOT NULL DEFAULT '',
  `code` varchar(10) NOT NULL,
  `dispercentage` int(3) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date NOT NULL,
  `coupon_type` varchar(20) NOT NULL DEFAULT 'available',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `min_order` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_scope` varchar(20) NOT NULL DEFAULT 'all',
  `coupon_ref_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcoupon`
--

INSERT INTO `tblcoupon` (`coupon_id`, `order_id`, `cus_id`, `code`, `dispercentage`, `discount_amount`, `expiry_date`, `coupon_type`, `status`, `min_order`, `coupon_scope`, `coupon_ref_id`, `created_at`) VALUES
('CA02AC4C65', '', '', 'TEST111', 5, 0.00, '2026-05-01', 'available', 1, 50000.00, 'all', 9, '2026-04-30 19:23:54'),
('CA4F2695B3', '', '', 'M20014', 5, 0.00, '2026-05-09', 'available', 1, 50000.00, 'all', 1, '2026-04-28 04:01:22'),
('CA6BE24660', '', '', 'AS903', 2, 0.00, '2026-05-01', 'available', 1, 1500.00, 'all', 5, '2026-04-30 08:29:18'),
('CA6EF84DB0', '', '', 'IFL1026', 5, 0.00, '2026-05-02', 'available', 1, 2500.00, 'all', 6, '2026-04-30 08:30:07'),
('CP69F015C7', 'O69F015C7B', '1', 'M20014', 5, 34000.00, '2026-04-30', 'redeemed', 1, 0.00, 'all', 2, '2026-04-28 07:34:55'),
('CP69F0D924', 'O69F0D9247', '1', 'SHAHANI', 2, 6800.00, '2026-04-30', 'redeemed', 1, 0.00, 'all', 4, '2026-04-28 21:28:28'),
('CP69F2F75D', 'O69F2F75D3', '1', 'IFL1026', 5, 4499.95, '2026-05-02', 'redeemed', 1, 0.00, 'all', 7, '2026-04-30 12:01:57'),
('CP69F3684B', 'O69F3684B9', '1', 'AS903', 2, 47.00, '2026-05-01', 'redeemed', 1, 0.00, 'all', 8, '2026-04-30 20:03:47');

-- --------------------------------------------------------

--
-- Table structure for table `tblcustomer`
--

CREATE TABLE `tblcustomer` (
  `cus_id` int(11) NOT NULL,
  `email` varchar(250) NOT NULL,
  `name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `dob` date NOT NULL,
  `gender` varchar(10) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `status_reason` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcustomer`
--

INSERT INTO `tblcustomer` (`cus_id`, `email`, `name`, `password`, `dob`, `gender`, `status`, `status_reason`, `profile_image`) VALUES
(1, 'iflaliflal401@gmail.com', 'IFLAL AHAMED', '$2y$10$GuIeonM1smglLKQrjImywusVRE7WgFGNz313kpKt2YntL9y4/Hx4i', '1999-11-04', 'male', 0, NULL, ''),
(2, 'ismi@gmail.com', 'Mohamed Ismi Mohamed Aaqil', '$2y$10$2VwLt/g8.vB1ceDQEpFUyeCr3yq6WX3slL3REwPYBfWfFbUaH0fBm', '1998-10-15', 'male', 1, '', ''),
(3, 'hima@gmail.com', 'hima', '$2y$10$SOQvFBi5MkZWAdXIF5ZtwOyTD4hSX9l9psirO69mWM7jNvMw.85PC', '2006-01-10', 'female', 0, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbldiscount_policy`
--

CREATE TABLE `tbldiscount_policy` (
  `discount_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `customer_group` varchar(20) NOT NULL DEFAULT 'regular',
  `discount_type` varchar(20) NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbldiscount_policy`
--

INSERT INTO `tbldiscount_policy` (`discount_id`, `title`, `customer_group`, `discount_type`, `discount_value`, `min_order`, `start_date`, `end_date`, `status`, `note`, `created_at`) VALUES
(1, 'Seasonal Discount', 'all', 'fixed', 500.00, 1500.00, '2026-04-08', '2026-04-30', 0, 'Get', '2026-04-28 04:06:00'),
(2, 'MAY_OFFER', 'all', 'percent', 2.00, 1500.00, '2026-04-30', '2026-05-09', 1, 'Enjoy our offer', '2026-04-30 08:33:45');

-- --------------------------------------------------------

--
-- Table structure for table `tblfree_delivery_setting`
--

CREATE TABLE `tblfree_delivery_setting` (
  `setting_id` tinyint(4) NOT NULL,
  `free_for_new` tinyint(1) NOT NULL DEFAULT 0,
  `free_for_regular` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblfree_delivery_setting`
--

INSERT INTO `tblfree_delivery_setting` (`setting_id`, `free_for_new`, `free_for_regular`, `updated_at`) VALUES
(1, 0, 1, '2026-04-30 11:57:45');

-- --------------------------------------------------------

--
-- Table structure for table `tblimage`
--

CREATE TABLE `tblimage` (
  `product_id` int(11) NOT NULL,
  `image_1` varchar(250) DEFAULT NULL,
  `image_2` varchar(250) DEFAULT NULL,
  `image_3` varchar(250) DEFAULT NULL,
  `image_4` varchar(250) DEFAULT NULL,
  `image_5` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblimage`
--

INSERT INTO `tblimage` (`product_id`, `image_1`, `image_2`, `image_3`, `image_4`, `image_5`) VALUES
(3, '20260427062810_4650_0.jpg', '20260427062810_4650_0.jpg', '20260427062810_4650_0.jpg', '20260427062810_4650_0.jpg', '20260427062810_4650_0.jpg'),
(4, '20260427123233_9761_0.jpg', '20260427123233_8012_1.jpg', '20260427123233_4743_2.jpg', '20260427123233_1829_3.jpg', '20260427123233_3910_4.jpg'),
(5, '20260427143208_5959_0.webp', '20260427143208_1128_1.jpg', '20260427143208_6395_2.jpg', '20260427143208_5959_0.webp', '20260427143208_5959_0.webp'),
(6, '20260427164959_9330_0.webp', '20260427164959_7475_1.webp', '20260427164959_3989_2.webp', '20260427164959_6576_3.jpg', '20260427164959_9330_0.webp'),
(7, '20260428044448_2170_0.jpg', '20260428044448_8683_1.webp', '20260428044448_6643_2.webp', '20260428044448_2170_0.jpg', '20260428044448_2170_0.jpg'),
(9, '20260429080543_3654_0.jpg', '20260429080543_4208_1.jpg', '20260429080543_5132_2.jpg', '20260429080543_3654_0.jpg', '20260429080543_3654_0.jpg'),
(11, '20260429084523_8368_0.jpg', '20260429084523_8149_1.jpg', '20260429084523_7629_2.jpg', '20260429084523_8368_0.jpg', '20260429084523_8368_0.jpg'),
(12, '20260429082257_5363_0.jpg', '20260429082257_3541_1.jpg', '20260429082257_2562_2.png', '20260429082257_5363_0.jpg', '20260429082257_5363_0.jpg'),
(16, '20260429081933_4873_0.png', '20260429081933_6078_1.jpg', '20260429081933_4873_0.png', '20260429081933_4873_0.png', '20260429081933_4873_0.png'),
(17, '20260429081818_4058_0.jpg', '20260429081818_6445_1.jpg', '20260429081818_5053_2.jpg', '20260429081818_4058_0.jpg', '20260429081818_4058_0.jpg'),
(18, '20260429081646_4766_0.webp', '20260429081646_4006_1.webp', '20260429081646_3966_2.jpg', '20260429081646_4766_0.webp', '20260429081646_4766_0.webp'),
(19, '20260429075808_4636_0.webp', '20260429075808_6053_1.webp', '20260429075808_6824_2.jpg', '20260429075808_1731_3.jpg', '20260429075808_4636_0.webp'),
(20, '20260429080808_6924_0.jpg', '20260429080808_8405_1.jpg', '20260429080808_6951_2.jpg', '20260429080808_1979_3.jpg', '20260429080808_8437_4.jpg'),
(21, '20260429081135_8918_0.webp', '20260429081135_9353_1.jpg', '20260429081135_9547_2.jpg', '20260429081135_3073_3.jpg', '20260429081135_6039_4.jpg'),
(23, '20260429084853_8247_0.jpg', '20260429084853_6330_1.jpg', '20260429084853_1356_2.png', '20260429084853_9821_3.jpg', '20260429084853_8247_0.jpg'),
(25, '20260429084727_8816_0.jpg', '20260429084727_8816_0.jpg', '20260429084727_8816_0.jpg', '20260429084727_8816_0.jpg', '20260429084727_8816_0.jpg'),
(26, '20260429084354_1533_0.jpg', '20260429084354_2860_1.jpg', '20260429084354_3878_2.jpg', '20260429084354_1533_0.jpg', '20260429084354_1533_0.jpg'),
(27, '20260429084234_7685_0.jpg', '20260429084234_2981_1.jpg', '20260429084234_7987_2.jpg', '20260429084234_6229_3.jpg', '20260429084234_8365_4.jpg'),
(28, '20260429084018_1497_0.webp', '20260429084018_5309_1.webp', '20260429084018_1170_2.webp', '20260429084018_6779_3.webp', '20260429084018_5008_4.jpg'),
(29, '20260429083904_9726_0.jpg', '20260429083904_9513_1.jpg', '20260429083904_1322_2.jpg', '20260429083904_9726_0.jpg', '20260429083904_9726_0.jpg'),
(30, '20260429083840_6206_0.jpg', '20260429083840_7067_1.jpg', '20260429083840_6174_2.jpg', '20260429083840_6693_3.jpg', '20260429083840_7029_4.jpg'),
(31, '20260429083317_6791_0.png', '20260429083317_5482_1.png', '20260429083317_4658_2.png', '20260429083317_6791_0.png', '20260429083317_6791_0.png'),
(34, '20260429082548_8750_0.jpg', '20260429082548_4821_1.webp', '20260429082548_8750_0.jpg', '20260429082548_8750_0.jpg', '20260429082548_8750_0.jpg'),
(35, '20260429083814_6649_0.jpg', '20260429083814_3007_1.png', '20260429083814_6649_0.jpg', '20260429083814_6649_0.jpg', '20260429083814_6649_0.jpg'),
(36, '20260429083656_8889_0.jpg', '20260429083656_8889_0.jpg', '20260429083656_8889_0.jpg', '20260429083656_8889_0.jpg', '20260429083656_8889_0.jpg'),
(37, '20260430080321_5120_0.webp', '20260430080321_4267_1.jpg', '20260430080321_5120_0.webp', '20260430080321_5120_0.webp', '20260430080321_5120_0.webp'),
(38, '20260501060933_4392_0.jpg', '20260501060933_4392_0.jpg', '20260501060933_4392_0.jpg', '20260501060933_4392_0.jpg', '20260501060933_4392_0.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `tblmobile_specs`
--

CREATE TABLE `tblmobile_specs` (
  `mspecs_id` varchar(20) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `ram` varchar(100) NOT NULL,
  `rom` varchar(100) NOT NULL,
  `os` varchar(100) NOT NULL,
  `processor` varchar(100) NOT NULL,
  `display` varchar(100) NOT NULL,
  `camera` varchar(400) NOT NULL,
  `battery` varchar(400) NOT NULL,
  `sim_type` varchar(400) NOT NULL,
  `connectivity` varchar(400) NOT NULL,
  `warranty` varchar(400) NOT NULL,
  `material` varchar(400) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblmobile_specs`
--

INSERT INTO `tblmobile_specs` (`mspecs_id`, `product_id`, `ram`, `rom`, `os`, `processor`, `display`, `camera`, `battery`, `sim_type`, `connectivity`, `warranty`, `material`) VALUES
('MS0010', '10', '8 GB', '256 GB', 'Android 14', 'Exynos 1480', '6.6-inch Super AMOLED', '50MP + 12MP + 5MP', '5000 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0011', '11', '8 GB', '256 GB', 'iOS 17', 'Apple A17 Pro', '6.7-inch Super Retina XDR', '48MP + 12MP + 12MP', '4441 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0012', '12', '6 GB', '128 GB', 'iOS 17', 'Apple A15 Bionic', '6.1-inch Super Retina XDR', '12MP + 12MP', '3279 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0013', '13', '12 GB', '256 GB', 'Android 14', 'Snapdragon 8 Gen 3', '6.36-inch AMOLED', '50MP + 50MP + 50MP', '4610 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0014', '14', '8 GB', '256 GB', 'Android 13', 'Snapdragon 7s Gen 2', '6.67-inch AMOLED', '200MP + 8MP + 2MP', '5100 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0015', '15', '16 GB', '512 GB', 'Android 14', 'Snapdragon 8 Gen 3', '6.82-inch LTPO AMOLED', '50MP + 64MP + 48MP', '5400 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0016', '16', '8 GB', '256 GB', 'Android 14', 'Snapdragon 7 Gen 3', '6.7-inch AMOLED', '50MP + 8MP', '5500 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0017', '17', '12 GB', '256 GB', 'Android 14', 'Google Tensor G3', '6.7-inch LTPO OLED', '50MP + 48MP + 48MP', '5050 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal'),
('MS0018', '18', '8 GB', '128 GB', 'Android 14', 'Google Tensor G2', '6.1-inch OLED', '64MP + 13MP', '4385 mAh', 'Dual SIM', '5G, WiFi, Bluetooth, NFC', '12 Months', 'Glass/Metal');

-- --------------------------------------------------------

--
-- Table structure for table `tblorder`
--

CREATE TABLE `tblorder` (
  `order_id` varchar(10) NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `ordered_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `status_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblorder`
--

INSERT INTO `tblorder` (`order_id`, `cus_id`, `ordered_date`, `status`, `status_reason`) VALUES
('O69EEF8BD5', '2', '2026-04-27', 'Pending', NULL),
('O69EF7935D', '1', '2026-04-27', 'Deleted', 'You deleted the order'),
('O69F015C7B', '1', '2026-04-28', 'Deleted', 'You deleted the order'),
('O69F0B91B8', '1', '2026-04-28', 'Rejected', 'Rejected by admin'),
('O69F0D9247', '1', '2026-04-28', 'Completed', ''),
('O69F2DB890', '1', '2026-04-30', 'Completed', ''),
('O69F2F75D3', '1', '2026-04-30', 'Rejected', 'Not available'),
('O69F3684B9', '1', '2026-04-30', 'Completed', ''),
('O69F36DD40', '1', '2026-04-30', 'Completed', ''),
('O69F40C805', '1', '2026-05-01', 'Delivered', ''),
('O69F4110A8', '1', '2026-05-01', 'On the way', ''),
('O69F4116F7', '1', '2026-05-01', 'Delivered', ''),
('O69F42A97B', '1', '2026-05-01', 'Completed', ''),
('O69F4DA372', '1', '2026-05-01', 'Completed', ''),
('O69F5691D5', '1', '2026-05-02', 'Deleted', 'You deleted the order');

-- --------------------------------------------------------

--
-- Table structure for table `tblorder_charge`
--

CREATE TABLE `tblorder_charge` (
  `order_id` varchar(10) NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(30) NOT NULL DEFAULT '',
  `coupon_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `special_discount_label` varchar(120) NOT NULL DEFAULT '',
  `special_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL,
  `shipping_address_id` varchar(20) NOT NULL DEFAULT '',
  `shipping_phone_id` varchar(20) NOT NULL DEFAULT '',
  `shipping_address_text` varchar(255) NOT NULL DEFAULT '',
  `shipping_phone` varchar(20) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblorder_charge`
--

INSERT INTO `tblorder_charge` (`order_id`, `cus_id`, `subtotal`, `coupon_code`, `coupon_discount`, `special_discount_label`, `special_discount`, `shipping_fee`, `grand_total`, `created_at`, `shipping_address_id`, `shipping_phone_id`, `shipping_address_text`, `shipping_phone`) VALUES
('O69EEF8BD5', '2', 712000.00, '', 0.00, '', 0.00, 0.00, 712000.00, '2026-04-27 07:48:45', 'A002', 'P002', '67 under 8b Kandy Road, Mawanella, Sri Lanka, Kandy, central', '0775588625'),
('O69EEF9089', '1', 1625000.00, '', 0.00, '', 0.00, 200.00, 1625200.00, '2026-04-27 07:50:00', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69EF7935D', '1', 850.00, '', 0.00, '', 0.00, 200.00, 1050.00, '2026-04-27 16:56:53', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F015C7B', '1', 680000.00, 'M20014', 34000.00, '', 0.00, 200.00, 646200.00, '2026-04-28 04:04:55', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F0B91B8', '1', 74900.00, '', 0.00, 'NEW USER DISCOUNT', 10000.00, 0.00, 64900.00, '2026-04-28 15:41:47', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F0D9247', '1', 340000.00, 'SHAHANI', 6800.00, 'Seasonal Discount', 10000.00, 0.00, 323200.00, '2026-04-28 17:58:28', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F2DB890', '1', 2400.00, '', 0.00, 'Seasonal Discount', 500.00, 0.00, 1900.00, '2026-04-30 06:33:13', 'A001', 'P003', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0764939771'),
('O69F2F75D3', '1', 89999.00, 'IFL1026', 4499.95, 'Seasonal Discount', 500.00, 0.00, 84999.05, '2026-04-30 08:31:57', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F3684B9', '1', 2350.00, 'AS903', 47.00, 'MAY_OFFER', 47.00, 0.00, 2256.00, '2026-04-30 16:33:47', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F36DD40', '1', 999998.00, '', 0.00, 'MAY_OFFER', 19999.96, 0.00, 979998.04, '2026-04-30 16:57:24', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F40C805', '1', 175999.00, '', 0.00, 'MAY_OFFER', 3519.98, 0.00, 172479.02, '2026-05-01 04:14:24', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F4110A8', '1', 329999.00, '', 0.00, 'MAY_OFFER', 6599.98, 0.00, 323399.02, '2026-05-01 04:33:46', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F4116F7', '1', 1500.00, '', 0.00, 'MAY_OFFER', 30.00, 0.00, 1470.00, '2026-05-01 04:35:27', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F42A97B', '1', 35000.00, '', 0.00, 'MAY_OFFER', 700.00, 0.00, 34300.00, '2026-05-01 06:22:47', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F4DA372', '1', 2299995.00, '', 0.00, 'MAY_OFFER', 45999.90, 0.00, 2253995.10, '2026-05-01 18:52:07', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426'),
('O69F5691D5', '1', 459999.00, '', 0.00, 'MAY_OFFER', 9199.98, 0.00, 450799.02, '2026-05-02 05:01:49', 'A001', 'P001', '114/A, Ladies School Road Eravur -03A, Batticaloa, eastern', '0757418426');

-- --------------------------------------------------------

--
-- Table structure for table `tblorder_details`
--

CREATE TABLE `tblorder_details` (
  `orderdetails_id` varchar(10) NOT NULL,
  `order_id` varchar(10) NOT NULL,
  `product_id` varchar(10) NOT NULL,
  `quantity` int(10) NOT NULL,
  `unitprice` int(10) NOT NULL,
  `selected_feature` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblorder_details`
--

INSERT INTO `tblorder_details` (`orderdetails_id`, `order_id`, `product_id`, `quantity`, `unitprice`, `selected_feature`) VALUES
('D69EEF8BD5', 'O69EEF8BD5', '3', 2, 356000, ''),
('D69EEF908A', 'O69EEF9089', '1', 5, 325000, ''),
('D69EF7935D', 'O69EF7935D', '6', 1, 850, ''),
('D69F015C7B', 'O69F015C7B', '3', 2, 340000, ''),
('D69F0B91B8', 'O69F0B91B8', '7', 1, 74900, ''),
('D69F0D9247', 'O69F0D9247', '3', 1, 340000, ''),
('D69F2DB890', 'O69F2DB890', '20', 2, 1200, ''),
('D69F2F75D3', 'O69F2F75D3', '35', 1, 89999, ''),
('D69F3684B9', 'O69F3684B9', '6', 1, 850, ''),
('D69F3684B9', 'O69F3684B9', '4', 1, 1500, 'Color: Green'),
('D69F36DD41', 'O69F36DD40', '11', 2, 499999, ''),
('D69F40C806', 'O69F40C805', '18', 1, 169999, ''),
('D69F40C806', 'O69F40C805', '4', 4, 1500, ''),
('D69F4110AA', 'O69F4110A8', '12', 1, 329999, ''),
('D69F4116F7', 'O69F4116F7', '4', 1, 1500, 'Color: Pink'),
('D69F42A97B', 'O69F42A97B', '38', 1, 35000, ''),
('D69F4DA372', 'O69F4DA372', '9', 5, 459999, 'Storage: 256GB 12GB RAM'),
('D69F5691D5', 'O69F5691D5', '9', 1, 459999, 'Storage: 256GB 12GB RAM');

-- --------------------------------------------------------

--
-- Table structure for table `tblpayment`
--

CREATE TABLE `tblpayment` (
  `payment_id` varchar(10) NOT NULL,
  `order_id` varchar(10) NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `method` varchar(20) NOT NULL,
  `gateway_ref` varchar(120) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'LKR',
  `payment_status` varchar(20) NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpayment`
--

INSERT INTO `tblpayment` (`payment_id`, `order_id`, `cus_id`, `method`, `gateway_ref`, `amount`, `currency`, `payment_status`, `paid_at`, `created_at`) VALUES
('P69EEF8BD5', 'O69EEF8BD5', '2', 'cod', 'COD', 712000.00, 'LKR', 'Pending', NULL, '2026-04-27 07:48:45'),
('P69EEF908A', 'O69EEF9089', '1', 'cod', 'COD', 1625200.00, 'LKR', 'Pending', NULL, '2026-04-27 07:50:00'),
('P69EF7935D', 'O69EF7935D', '1', 'cod', 'COD', 1050.00, 'LKR', 'Pending', NULL, '2026-04-27 16:56:53'),
('P69F015C7C', 'O69F015C7B', '1', 'cod', 'COD', 646200.00, 'LKR', 'Pending', NULL, '2026-04-28 04:04:55'),
('P69F0B91B8', 'O69F0B91B8', '1', 'stripe', 'pi_3TRBt65GhvrCsT5J19f9P4bn', 64900.00, 'LKR', 'Paid', '2026-04-28 15:41:47', '2026-04-28 15:41:47'),
('P69F0D9247', 'O69F0D9247', '1', 'cod', 'COD', 323200.00, 'LKR', 'Pending', NULL, '2026-04-28 17:58:28'),
('P69F2DB891', 'O69F2DB890', '1', 'cod', 'COD', 1900.00, 'LKR', 'Pending', NULL, '2026-04-30 06:33:13'),
('P69F2F75D4', 'O69F2F75D3', '1', 'cod', 'COD', 84999.05, 'LKR', 'Pending', NULL, '2026-04-30 08:31:57'),
('P69F3684BA', 'O69F3684B9', '1', 'stripe', 'pi_3TRvcB5GhvrCsT5J02fLAmgc', 2256.00, 'LKR', 'Paid', '2026-04-30 16:33:47', '2026-04-30 16:33:47'),
('P69F36DD41', 'O69F36DD40', '1', 'cod', 'COD', 979998.04, 'LKR', 'Pending', NULL, '2026-04-30 16:57:24'),
('P69F40C806', 'O69F40C805', '1', 'stripe', 'pi_3TS6al5GhvrCsT5J08YkaLQL', 172479.02, 'LKR', 'Paid', '2026-05-01 04:14:24', '2026-05-01 04:14:24'),
('P69F4110AA', 'O69F4110A8', '1', 'cod', 'COD', 323399.02, 'LKR', 'Pending', NULL, '2026-05-01 04:33:46'),
('P69F4116F7', 'O69F4116F7', '1', 'cod', 'COD', 1470.00, 'LKR', 'Paid', '2026-05-01 04:36:31', '2026-05-01 04:35:27'),
('P69F42A97B', 'O69F42A97B', '1', 'cod', 'COD', 34300.00, 'LKR', 'Paid', '2026-05-01 06:24:03', '2026-05-01 06:22:47'),
('P69F4DA373', 'O69F4DA372', '1', 'cod', 'COD', 2253995.10, 'LKR', 'Paid', '2026-05-01 18:54:17', '2026-05-01 18:52:07'),
('P69F5691D5', 'O69F5691D5', '1', 'stripe', 'pi_3TSTny5GhvrCsT5J1Y5ZhSav', 450799.02, 'LKR', 'Paid', '2026-05-02 05:01:49', '2026-05-02 05:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `tblphone`
--

CREATE TABLE `tblphone` (
  `phone_id` varchar(10) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `phone` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblphone`
--

INSERT INTO `tblphone` (`phone_id`, `cus_id`, `phone`) VALUES
('P001', 1, '0757418426'),
('P002', 2, '0775588625'),
('P003', 3, '0754555555');

-- --------------------------------------------------------

--
-- Table structure for table `tblproduct`
--

CREATE TABLE `tblproduct` (
  `product_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `modal` varchar(50) NOT NULL,
  `description` varchar(250) NOT NULL,
  `price` float NOT NULL,
  `cprice` float NOT NULL,
  `quantity` int(11) NOT NULL,
  `added_date` datetime NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblproduct`
--

INSERT INTO `tblproduct` (`product_id`, `cat_id`, `brand_id`, `name`, `modal`, `description`, `price`, `cprice`, `quantity`, `added_date`, `status`) VALUES
(3, 9, 2, 'iPhone 17 Pro Max', '17 Pro Max', '', 340000, 0, 0, '2026-04-27 09:58:10', 1),
(4, 3, 2, 'iPhone 17 Pro Max Back Covers', 'iPhone 17 Pro Max', 'A soft microfiber lining on the inside helps protect your iPhone.  \r\nOn the outside, the silky, soft-touch finish of the silicone exterior feels great in your hand.  \r\nAnd you can keep it on all the time, even when you’re charging wirelessly.  \r\nJust', 1500, 0, 8, '2026-04-27 16:02:33', 1),
(5, 9, 1, 'Samsung Galaxy M02', 'M02', 'Samsung Galaxy M02 Android smartphone. Announced Feb 2021. Features 6.5″ display, MT6739W chipset, 5000 mAh battery, 32 GB storage, 3 GB RAM.', 25000, 30000, 15, '2026-04-27 18:02:08', 1),
(6, 4, NULL, 'IPhone 17 Pro Max 5G Alloy Black Lens Protector Cover', 'IPhone 17 Pro Max', 'Metal Camera Tempered Glass For IPhone 17 Pro Max 5G Alloy Black Lens Protector Cover For IPhone 17 Air 17 Pro Max Lens Ring Cap', 850, 1000, 13, '2026-04-27 20:19:59', 1),
(7, 9, 3, 'Redmi Note 13 Pro', 'Redmi Note 13 Pro', '', 74900, 0, 49, '2026-04-28 08:14:48', 1),
(9, 9, 1, 'Samsung Galaxy S24 Ultra', 'S24-Ultra', 'Samsung Galaxy S24 Ultra - Samsung smartphone.', 459999, 489999, 12, '2026-04-29 11:16:48', 1),
(11, 9, 2, 'Apple iPhone 15 Pro Max', 'Apple iPhone 15 Pro Max', 'Apple iPhone 15 Pro Max - Apple smartphone.', 499999, 539999, 12, '2026-04-29 11:17:12', 1),
(12, 9, 2, 'Apple iPhone 14', 'i14-128', 'Apple iPhone 14 - Apple smartphone.', 329999, 359999, 21, '2026-04-29 11:17:12', 1),
(16, 9, 5, 'OnePlus Nord CE 4', 'Nord-CE4', 'OnePlus Nord CE 4 - OnePlus smartphone.', 129999, 144999, 28, '2026-04-29 11:17:12', 1),
(17, 9, 6, 'Google Pixel 8 Pro', 'Pixel-8-Pro', 'Google Pixel 8 Pro - Google smartphone.', 319999, 349999, 12, '2026-04-29 11:17:12', 1),
(18, 9, 6, 'Google Pixel 7a', 'Pixel-7a', 'Google Pixel 7a - Google smartphone.', 169999, 184999, 23, '2026-04-29 11:17:12', 1),
(19, 3, NULL, 'Soft Leather Deer Head Back Cases', 'Samsung Galaxy S24 Ultra', '', 1500, 2000, 50, '2026-04-29 11:28:08', 1),
(20, 3, NULL, 'Magsafe Magnetic Wireless Charge Phone Case', 'Samsung Galaxy S24 Ultra', '', 1200, 1500, 48, '2026-04-29 11:38:08', 1),
(21, 4, NULL, 'Samsung Galaxy S24 Ultra Tempered Glass', 'Samsung Galaxy S24 Ultra', '• Samsung Galaxy S24 Ultra Tempered Glass with Full Glue\r\n• Provides 1 layer of protection for your phone screen\r\n• Compatible with Samsung Galaxy S24 Ultra\r\n• Easy to install and use\r\n• Made with high-quality materials for durability\r\n• Protects you', 950, 0, 50, '2026-04-29 11:41:35', 1),
(23, 5, NULL, 'USB-C to USB-C 100W Cable', 'USB-C to USB-C 100W Cable', 'Durable fast charging cable for USB-C phones.', 2999, 3499, 75, '2026-04-29 11:49:18', 1),
(25, 5, NULL, 'USB-C to Lightning Cable', 'iphone', 'Fast charge cable for iPhone with USB-C adapter.', 3499, 3999, 55, '2026-04-29 11:49:18', 1),
(26, 3, NULL, 'Samsung Galaxy S24 Ultra Rugged Cover', 'Samsung Galaxy S24 Ultra', 'Shockproof back cover for Galaxy S24 Ultra.', 4999, 5999, 40, '2026-04-29 11:49:18', 1),
(27, 3, NULL, 'Samsung Galaxy A55 Silicone Cover', 'Samsung Galaxy A55', 'Soft silicone case for Samsung Galaxy A55 5G.', 3999, 4599, 45, '2026-04-29 11:49:18', 1),
(28, 3, NULL, 'iPhone 15 Pro Max Clear Cover', 'iPhone 15 Pro Max', 'Slim clear back cover for iPhone 15 Pro Max.', 5499, 6299, 38, '2026-04-29 11:49:18', 1),
(29, 3, NULL, 'Google Pixel 8 Pro Matte Cover', 'Google Pixel 8 Pro', 'Matte protective cover for Pixel 8 Pro.', 4299, 4899, 34, '2026-04-29 11:49:18', 1),
(30, 4, NULL, 'Metal Lens Ring Glass', 'Galaxy S24 Ultra', 'Metal Lens Ring Glass for S24 Ultra.', 3299, 3999, 50, '2026-04-29 11:49:18', 1),
(31, 4, 17, 'iPhone 15 Pro Max Tempered Glass', 'Apple iPhone 15 Pro Max', 'Tempered glass protector for iPhone 15 Pro Max.', 3499, 4199, 46, '2026-04-29 11:49:18', 1),
(34, 6, 12, 'Soundcore R50i Earbuds', 'ANK-R50I', 'True wireless earbuds for daily phone use.', 12499, 13999, 29, '2026-04-29 11:49:18', 1),
(35, 14, 1, 'Galaxy Watch 6 44mm', 'Samsung Galaxy', 'Samsung smartwatch optimized for Android phones.', 89999, 94999, 14, '2026-04-29 11:49:18', 1),
(36, 14, 2, 'Apple Watch Series 9 45mm', 'Apple', 'Apple smartwatch designed for iPhone users.', 169999, 179999, 11, '2026-04-29 11:49:18', 1),
(37, 11, 3, 'Redmi Note 14 Pro 4G', 'Redmi Note 14 Pro 4G', '', 69000, 75000, 5, '2026-04-30 11:33:21', 1),
(38, 11, 21, 'Poco C40', 'Poco C40', '', 35000, 0, 14, '2026-05-01 09:39:33', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblproduct_feature`
--

CREATE TABLE `tblproduct_feature` (
  `feature_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `feature_name` varchar(120) NOT NULL,
  `feature_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblproduct_feature`
--

INSERT INTO `tblproduct_feature` (`feature_id`, `product_id`, `feature_name`, `feature_value`) VALUES
(48, 4, 'Color', 'Green'),
(49, 4, 'Color', 'Pink'),
(50, 4, 'Color', 'Maroon'),
(51, 4, 'Color', 'Light Blue'),
(105, 19, 'Color', 'Green'),
(106, 19, 'Color', 'Red'),
(107, 19, 'Color', 'Blue'),
(108, 19, 'Color', 'Brown'),
(121, 9, 'Announced', '2024, January 17'),
(122, 9, 'Display Type', 'Dynamic LTPO AMOLED 2X, 120Hz, HDR10+, 2600 nits (peak)'),
(123, 9, 'Display Size', '6.8 inches, 113.5 cm2 (~88.5% screen-to-body ratio)'),
(124, 9, 'OS', 'Android 14, up to 7 major Android upgrades, One UI 8.0'),
(125, 9, 'Chipset', 'Qualcomm SM8650-AC Snapdragon 8 Gen 3 (4 nm)'),
(126, 9, 'CPU', '8-core (1x3.39GHz Cortex-X4 & 3x3.1GHz Cortex-A720 & 2x2.9GHz Cortex-A720 & 2x2.2GHz Cortex-A520)'),
(127, 9, 'GPU', 'Adreno 750 (1 GHz)'),
(128, 9, 'Main Camera', '200 MP, f/1.7, 24mm (wide), 1/1.3\", 0.6µm, multi-directional PDAF, OIS 10 MP, f/2.4, 67mm (telephoto), 1/3.52\", 1.12µm, PDAF, OIS, 3x optical zoom 50 MP, f/3.4, 111mm (periscope telephoto), 1/2.52\", 0.7µm, PDAF, OIS, 5x optical zoom 12 MP, f/2.2, 13mm, 120˚ (ultrawide), 1/2.55\", 1.4µm, dual pixel PDAF, Super Steady video'),
(129, 9, 'Selfie Camera', '12 MP, f/2.2, 26mm (wide), 1/3.2\", 1.12µm, dual pixel PDAF'),
(130, 9, 'Battery', 'Li-Ion 5000 mAh'),
(131, 9, 'Charging', '45W wired, PD3.0, 65% in 30 min 15W wireless (Qi) 4.5W reverse wireless'),
(132, 9, 'RAM Option', '256GB 12GB RAM'),
(133, 9, 'RAM Option', '512GB 12GB RAM'),
(134, 9, 'RAM Option', '1TB 12GB RAM'),
(135, 20, 'Color', 'Red'),
(136, 20, 'Color', 'Green'),
(137, 20, 'Color', 'Blue'),
(138, 20, 'Color', 'Black'),
(139, 20, 'Color', 'Grey'),
(155, 17, 'Color', 'Green'),
(156, 17, 'Color', 'Blue'),
(157, 17, 'Color', 'Gold'),
(158, 17, 'Announced', '2023, October 04'),
(159, 17, 'Display Type', 'LTPO OLED, 120Hz, HDR10+, 1600 nits (HBM), 2400 nits (peak)'),
(160, 17, 'Display Size', '6.7 inches, 108.7 cm2 (~87.4% screen-to-body ratio)'),
(161, 17, 'OS', 'Android 14, upgradable to Android 16, up to 7 major Android upgrades'),
(162, 17, 'Chipset', 'Google Tensor G3 (4 nm)'),
(163, 17, 'CPU', 'Nona-core (1x3.0 GHz Cortex-X3 & 4x2.45 GHz Cortex-A715 & 4x2.15 GHz Cortex-A510)'),
(164, 17, 'GPU', 'Immortalis-G715s MC10'),
(165, 17, 'Memory', '128GB 12GB RAM, 256GB 12GB RAM, 512GB 12GB RAM, 1TB 12GB RAM'),
(166, 17, 'Main Camera', '50 MP, f/1.7, 25mm (wide), 1/1.31\", 1.2µm, dual pixel PDAF, OIS 48 MP, f/2.8, 113mm (periscope telephoto), 1/2.55\", 0.7µm, dual pixel PDAF, OIS, 5x optical zoom 48 MP, f/2.0, 126˚ (ultrawide), 1/2.0\", 0.8µm, dual pixel PDAF'),
(167, 17, 'Selfie Camera', '10.5 MP, f/2.2, 20mm (ultrawide), 1/3.1\", 1.22µm, PDAF'),
(168, 17, 'Battery', 'Li-Ion 5050 mAh'),
(169, 17, 'Charging', '30W wired, PD3.0, PPS, 50% in 30 min 23W wireless Reverse wireless Bypass charging'),
(215, 9, 'Charging Port', 'USB-C'),
(216, 9, 'Ecosystem', 'Android'),
(231, 17, 'Charging Port', 'USB-C'),
(232, 17, 'Ecosystem', 'Android'),
(286, 36, 'Announced', '2023, September 12'),
(287, 36, 'Display Type', 'Retina LTPO OLED, 2000 nits (peak)'),
(288, 36, 'Display Size', '1.9 inches'),
(289, 36, 'OS', 'watchOS 10, upgradable to watchOS 26.4'),
(290, 36, 'Chipset', 'Apple S9'),
(291, 36, 'CPU', 'Dual-core'),
(292, 36, 'GPU', 'PowerVR'),
(293, 36, 'Battery', 'Li-Ion 308 mAh'),
(294, 36, 'Charging', 'Wireless, 0-80% in 45 min'),
(295, 36, 'RAM Option', '64GB'),
(298, 29, 'Color', 'Black'),
(299, 29, 'Color', 'Green'),
(300, 29, 'Accessory Type', 'Back Cover'),
(301, 29, 'Compatibility', 'Google Pixel 8 Pro'),
(302, 28, 'Accessory Type', 'Back Cover'),
(303, 28, 'Compatibility', 'Apple iPhone 15 Pro Max'),
(311, 26, 'Accessory Type', 'Back Cover'),
(312, 26, 'Compatibility', 'Samsung Galaxy S24 Ultra'),
(313, 11, 'Color', 'Titanium Gold'),
(314, 11, 'Announced', '2023, September 12'),
(315, 11, 'Display Type', 'LTPO Super Retina XDR OLED, 120Hz, HDR10, Dolby Vision, 1000 nits (typ), 2000 nits (HBM)'),
(316, 11, 'Display Size', '6.7 inches, 110.2 cm2 (~89.8% screen-to-body ratio)'),
(317, 11, 'OS', 'iOS 17, upgradable to iOS 26.4.2'),
(318, 11, 'Chipset', 'Apple A17 Pro (3 nm)'),
(319, 11, 'CPU', 'Hexa-core (2x3.78 GHz + 4x2.11 GHz)'),
(320, 11, 'GPU', 'Apple GPU (6-core graphics)'),
(321, 11, 'Memory', '256GB 8GB RAM, 512GB 8GB RAM, 1TB 8GB RAM'),
(322, 11, 'Main Camera', '48 MP, f/1.8, 24mm (wide), 1/1.28\", 1.22µm, dual pixel PDAF, sensor-shift OIS 12 MP, f/2.8, 120mm (periscope telephoto), 1/3.06\", 1.12µm, dual pixel PDAF, 3D sensor‑shift OIS, 5x optical zoom 12 MP, f/2.2, 13mm, 120˚ (ultrawide), 1/2.55\", 1.4µm, dual pixel PDAF TOF 3D LiDAR scanner (depth)'),
(323, 11, 'Selfie Camera', '12 MP, f/1.9, 23mm (wide), 1/3.6\", 1.0µm, PDAF, OIS SL 3D, (depth/biometrics sensor)'),
(324, 11, 'Battery', 'Li-Ion 4441 mAh'),
(325, 11, 'Charging', 'Wired, PD2.0, 50% in 30 min 15W wireless (MagSafe) 15W wireless (Qi2) - requires iOS 17.2 update 4.5W reverse wired'),
(326, 25, 'Connector', 'Lightning'),
(327, 25, 'Accessory Type', 'Cable'),
(328, 25, 'Compatibility', 'iPhone / iOS'),
(329, 23, 'Connector', 'USB-C'),
(330, 23, 'Accessory Type', 'Cable'),
(331, 23, 'Compatibility', 'USB-C phones'),
(332, 34, 'Accessory Type', 'Earbuds'),
(333, 34, 'Connection', 'Bluetooth'),
(334, 34, 'Compatibility', 'Android / iOS'),
(337, 30, 'Accessory Type', 'Tempered Glass'),
(338, 30, 'Compatibility', 'Samsung Galaxy S24 Ultra'),
(339, 27, 'Color', 'Red'),
(340, 27, 'Color', 'Green'),
(341, 27, 'Color', 'Blue'),
(342, 27, 'Color', 'Peach'),
(343, 27, 'Color', 'Black'),
(344, 27, 'Accessory Type', 'Back Cover'),
(345, 27, 'Compatibility', 'Samsung Galaxy A55 5G'),
(346, 16, 'Color', 'Silver'),
(347, 16, 'Color', 'Green'),
(348, 16, 'Announced', '2024, April 01'),
(349, 16, 'Display Type', 'Fluid AMOLED, 1B colors, 120Hz, HDR10+, 900 nits (HBM), 1100 nits (peak)'),
(350, 16, 'Display Size', '6.7 inches, 108.0 cm2 (~88.3% screen-to-body ratio)'),
(351, 16, 'OS', 'Android 14, upgradable to Android 16, OxygenOS 16'),
(352, 16, 'Chipset', 'Qualcomm SM7550-AB Snapdragon 7 Gen 3 (4 nm)'),
(353, 16, 'CPU', 'Octa-core (1x2.63 GHz Cortex-A715 & 4x2.4 GHz Cortex-A715 & 3x1.8 GHz Cortex-A510)'),
(354, 16, 'GPU', 'Adreno 720'),
(355, 16, 'Main Camera', '50 MP, f/1.8, 26mm (wide), 1/1.95\", 0.8µm, PDAF, OIS 8 MP, f/2.2, 16mm, 112˚ (ultrawide), 1/4.0\", 1.12µm'),
(356, 16, 'Selfie Camera', '16 MP, f/2.4, 26mm (wide), 1/3.0\", 1.0µm'),
(357, 16, 'Battery', '5500 mAh'),
(358, 16, 'Charging', '100W wired, 100% in 29 min'),
(359, 16, 'RAM Option', '128GB 8GB RAM'),
(360, 16, 'RAM Option', '256GB 8GB RAM'),
(363, 18, 'Color', 'Orange'),
(364, 18, 'Color', 'Light Blue'),
(365, 18, 'Color', 'Grey'),
(366, 18, 'Announced', '2023, May 10'),
(367, 18, 'Display Type', 'OLED, HDR, 90Hz'),
(368, 18, 'Display Size', '6.1 inches, 90.7 cm2 (~81.8% screen-to-body ratio)'),
(369, 18, 'OS', 'Android 13, upgradable to Android 16, up to 5 major Android upgrades'),
(370, 18, 'Chipset', 'Google Tensor G2 (5 nm)'),
(371, 18, 'CPU', 'Octa-core (2x2.85 GHz Cortex-X1 & 2x2.35 GHz Cortex-A78 & 4x1.80 GHz Cortex-A55)'),
(372, 18, 'GPU', 'Mali-G710 MP7'),
(373, 18, 'Main Camera', '64 MP, f/1.9, 26mm (wide), 1/1.73\", 0.8µm, dual pixel PDAF, OIS 13 MP, f/2.2, 120˚ (ultrawide), 1.12µm'),
(374, 18, 'Selfie Camera', '13 MP, f/2.2, 20mm (ultrawide), 1.12µm'),
(375, 18, 'Battery', 'Li-Po 4385 mAh'),
(376, 18, 'Charging', '18W wired, PD3.0 7.5W wireless'),
(377, 18, 'Charging Port', 'USB-C'),
(378, 18, 'Ecosystem', 'Android'),
(379, 18, 'RAM Option', '128GB 8GB RAM'),
(397, 7, 'Announced', '2023, September 21'),
(398, 7, 'Display Type', 'AMOLED, 68B colors, 120Hz, 1920Hz PWM, Dolby Vision, 500 nits (typ), 1200 nits (HBM), 1800 nits (peak)'),
(399, 7, 'Display Size', '6.67 inches, 107.4 cm2 (~89.8% screen-to-body ratio)'),
(400, 7, 'OS', 'Android 13, upgradable to Android 16, HyperOS 3, up to 3 major Android upgrades'),
(401, 7, 'Chipset', 'Qualcomm SM7435-AB Snapdragon 7s Gen 2 (4 nm)'),
(402, 7, 'CPU', 'Octa-core (4x2.40 GHz Cortex-A78 & 4x1.95 GHz Cortex-A55)'),
(403, 7, 'GPU', 'Adreno 710'),
(404, 7, 'Main Camera', '200 MP, f/1.7, 23mm (wide), 1/1.4\", 0.56µm, multi-directional PDAF, OIS 8 MP, f/2.2, 118˚ (ultrawide), 1/4.0\", 1.12µm 2 MP (macro)'),
(405, 7, 'Selfie Camera', '16 MP, f/2.4, (wide), 1/3.06\", 1.0µm'),
(406, 7, 'Battery', 'Li-Po 5100 mAh'),
(407, 7, 'Charging', '67W wired, PD3.0, 50% in 17 min, 100% in 44 min'),
(408, 7, 'Hello', 'Iflal'),
(409, 7, 'Charging Port', 'USB-C'),
(410, 7, 'Ecosystem', 'Android'),
(411, 7, 'RAM Option', '128GB 8GB RAM'),
(412, 7, 'RAM Option', '256GB 8GB RAM'),
(413, 7, 'RAM Option', '256GB 12GB RAM'),
(414, 7, 'RAM Option', '512GB 12GB RAM'),
(415, 7, 'RAM Option', '512GB 16GB RAM'),
(416, 5, 'Announced', '2021, February 02'),
(417, 5, 'Display Type', 'PLS LCD'),
(418, 5, 'Display Size', '6.5 inches, 102.0 cm2 (~81.9% screen-to-body ratio)'),
(419, 5, 'OS', 'Android 10, One UI 2.0'),
(420, 5, 'Chipset', 'Mediatek MT6739W (28 nm)'),
(421, 5, 'CPU', 'Quad-core 1.5 GHz Cortex-A53'),
(422, 5, 'GPU', 'PowerVR GE8100'),
(423, 5, 'Main Camera', '13 MP, f/1.9, (wide), AF 2 MP (macro)'),
(424, 5, 'Selfie Camera', '5 MP, f/2.0'),
(425, 5, 'Battery', 'Li-Po 5000 mAh'),
(426, 5, 'Charging Port', 'USB-C'),
(427, 5, 'Ecosystem', 'Android'),
(428, 5, 'RAM Option', '32GB 2GB RAM'),
(429, 5, 'RAM Option', '32GB 3GB RAM'),
(432, 31, 'Accessory Type', 'Tempered Glass'),
(433, 31, 'Compatibility', 'Apple iPhone 15 Pro Max'),
(436, 3, 'Charging Port', 'Lightning'),
(437, 3, 'Ecosystem', 'iOS'),
(453, 37, 'Color', 'Lavender'),
(454, 37, 'Color', 'Mint Green'),
(455, 37, 'Announced', '2025, January 10'),
(456, 37, 'Display Type', 'AMOLED, 1B colors, 120Hz, 1920Hz PWM, 1200 nits (HBM), 1800 nits (peak)'),
(457, 37, 'Display Size', '6.67 inches, 107.4 cm2 (~88.4% screen-to-body ratio)'),
(458, 37, 'OS', 'Android 14, HyperOS'),
(459, 37, 'Chipset', 'Mediatek Helio G100 Ultra (6 nm)'),
(460, 37, 'CPU', 'Octa-core (2x2.2 GHz Cortex-A76 & 6x2.0 GHz Cortex-A55)'),
(461, 37, 'GPU', 'Mali-G57 MC2'),
(462, 37, 'Main Camera', '200 MP, f/1.7, 23mm (wide), 1/1.4\", 0.56µm, multi-directional PDAF, OIS 8 MP, f/2.2, 15mm, 120˚ (ultrawide), 1/4.4\", 1.0µm 2 MP (macro)'),
(463, 37, 'Selfie Camera', '32 MP, f/2.2, (wide)'),
(464, 37, 'Battery', '5500 mAh'),
(465, 37, 'Charging', '45W wired'),
(466, 37, 'RAM Option', '128GB 8GB RAM'),
(495, 38, 'Announced', '2022, June 06'),
(496, 38, 'Display Type', 'IPS LCD, 400 nits'),
(497, 38, 'Display Size', '6.71 inches, 106.5 cm2 (~82.0% screen-to-body ratio)'),
(498, 38, 'OS', 'Android 11, MIUI 13 for POCO'),
(499, 38, 'Chipset', 'JLQ JR510 (11 nm)'),
(500, 38, 'CPU', 'Octa-core (4x2.0 GHz Cortex-A55 & 4x1.5 GHz Cortex-A55)'),
(501, 38, 'GPU', 'Mali-G57 MC1'),
(502, 38, 'Main Camera', '13 MP, f/2.2, (wide), PDAF Auxiliary lens'),
(503, 38, 'Selfie Camera', '5 MP, f/2.2'),
(504, 38, 'Battery', 'Li-Po 6000 mAh'),
(505, 38, 'Charging', '18W wired'),
(506, 38, 'RAM Option', '32GB 2GB RAM'),
(507, 38, 'RAM Option', '32GB 3GB RAM'),
(508, 38, 'RAM Option', '32GB 4GB RAM'),
(509, 38, 'RAM Option', '64GB 4GB RAM'),
(540, 12, 'Color', 'White'),
(541, 12, 'Color', 'Baby Pink');

-- --------------------------------------------------------

--
-- Table structure for table `tblpromotion`
--

CREATE TABLE `tblpromotion` (
  `promotion_id` int(11) NOT NULL,
  `promotion_scope` varchar(20) NOT NULL DEFAULT 'offer',
  `title` varchar(150) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT '',
  `link_url` varchar(255) NOT NULL DEFAULT '',
  `scope_type` varchar(20) NOT NULL DEFAULT '',
  `scope_id` varchar(20) NOT NULL DEFAULT '',
  `offer_type` varchar(20) NOT NULL DEFAULT 'percent',
  `offer_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpromotion`
--

INSERT INTO `tblpromotion` (`promotion_id`, `promotion_scope`, `title`, `description`, `image`, `link_url`, `scope_type`, `scope_id`, `offer_type`, `offer_value`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'offer', 'New Offer', 'iPhone under Promotion', '', '', 'brand', '2', 'fixed', 1500.00, '2026-04-07', '2026-04-30', 1, '2026-04-28 04:21:59'),
(3, 'home', 'May Promotion', 'Enjoy our super discounts', 'home_promo_20260501_092341_10bd82d0.webp', 'promotions.php', '', '', 'percent', 0.00, NULL, NULL, 1, '2026-05-01 06:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `tblratings`
--

CREATE TABLE `tblratings` (
  `rating_id` int(11) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `orderdetails_id` varchar(10) NOT NULL,
  `order_id` varchar(10) NOT NULL,
  `product_id` varchar(10) NOT NULL,
  `rating` int(2) NOT NULL,
  `review_text` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblratings`
--

INSERT INTO `tblratings` (`rating_id`, `cus_id`, `orderdetails_id`, `order_id`, `product_id`, `rating`, `review_text`, `created_at`, `updated_at`, `status`) VALUES
(1, 1, 'D69EEF908A', 'O69EEF9089', '1', 4, 'Good & Well packing', '2026-04-27 09:41:50', '2026-04-27 09:42:12', 1),
(2, 1, 'D69F2DB890', 'O69F2DB890', '20', 5, 'Super quality', '2026-04-30 06:52:30', NULL, 1),
(3, 1, 'D69F0D9247', 'O69F0D9247', '3', 5, 'Quality Products', '2026-04-30 16:40:41', '2026-04-30 16:43:37', 1),
(6, 1, 'D69F4DA372', 'O69F4DA372', '9', 4, 'very good smart phone', '2026-05-01 18:55:24', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblshipping_rate`
--

CREATE TABLE `tblshipping_rate` (
  `province` varchar(250) NOT NULL,
  `district` varchar(250) NOT NULL,
  `rate` float NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblshipping_rate`
--

INSERT INTO `tblshipping_rate` (`province`, `district`, `rate`, `status`) VALUES
('Eastern Province', 'Batticaloa', 200, 1),
('Western Province', 'Colombo', 350, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblweb_banner`
--

CREATE TABLE `tblweb_banner` (
  `id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `heading` varchar(255) NOT NULL,
  `sub_heading` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblweb_banner`
--

INSERT INTO `tblweb_banner` (`id`, `image`, `heading`, `sub_heading`, `status`, `display_order`) VALUES
(2, 'banner_20260427_080445_3496.jpg', 'Explore RTEL!', 'Get Everything In One Place', 1, 2),
(3, 'banner_20260427_080612_8342.jpg', 'AI-Assistant', 'Ask any doubts at anytime!', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblwish_list`
--

CREATE TABLE `tblwish_list` (
  `wishlist_id` varchar(10) NOT NULL,
  `cus_id` varchar(250) NOT NULL,
  `product_id` varchar(10) NOT NULL,
  `added_date` date NOT NULL,
  `selected_feature` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblwish_list`
--

INSERT INTO `tblwish_list` (`wishlist_id`, `cus_id`, `product_id`, `added_date`, `selected_feature`) VALUES
('W69F2ECCE2', '1', '3', '2026-04-30', ''),
('W69F366379', '1', '11', '2026-04-30', ''),
('W69F366644', '1', '4', '2026-04-30', 'Color: Pink'),
('W69F36665A', '1', '12', '2026-04-30', 'Color: White'),
('W69F36666E', '1', '9', '2026-04-30', 'Storage: 512GB 12GB RAM'),
('W69F38522C', '1', '8', '2026-04-30', ''),
('W69F4D945C', '1', '4', '2026-05-01', 'Color: Pink');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_password_reset`
--

CREATE TABLE `tbl_password_reset` (
  `reset_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(250) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `account_type` varchar(20) NOT NULL DEFAULT 'customer',
  `account_id` varchar(50) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbladdress`
--
ALTER TABLE `tbladdress`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_tbladdress_cus` (`cus_id`);

--
-- Indexes for table `tbladmin`
--
ALTER TABLE `tbladmin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `tbladmin_alert_read`
--
ALTER TABLE `tbladmin_alert_read`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `tbladmin_log`
--
ALTER TABLE `tbladmin_log`
  ADD PRIMARY KEY (`adminlog_id`);

--
-- Indexes for table `tbladmin_page_permission`
--
ALTER TABLE `tbladmin_page_permission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin_page` (`admin_id`,`page_key`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indexes for table `tblai_setting`
--
ALTER TABLE `tblai_setting`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `tblai_user_behavior`
--
ALTER TABLE `tblai_user_behavior`
  ADD PRIMARY KEY (`behavior_id`),
  ADD KEY `idx_ai_user_date` (`cus_id`,`event_date`),
  ADD KEY `idx_ai_product` (`product_id`);

--
-- Indexes for table `tblai_user_search_history`
--
ALTER TABLE `tblai_user_search_history`
  ADD PRIMARY KEY (`search_id`),
  ADD KEY `idx_ai_search_user_date` (`cus_id`,`search_date`);

--
-- Indexes for table `tblbrand`
--
ALTER TABLE `tblbrand`
  ADD PRIMARY KEY (`brand_id`);

--
-- Indexes for table `tblbundle`
--
ALTER TABLE `tblbundle`
  ADD PRIMARY KEY (`bundle_id`);

--
-- Indexes for table `tblbundle_item`
--
ALTER TABLE `tblbundle_item`
  ADD PRIMARY KEY (`bundle_item_id`);

--
-- Indexes for table `tblcart`
--
ALTER TABLE `tblcart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fkcart_customer` (`cus_id`),
  ADD KEY `fkcart_product` (`product_id`);

--
-- Indexes for table `tblcart_bundle`
--
ALTER TABLE `tblcart_bundle`
  ADD PRIMARY KEY (`cart_bundle_id`),
  ADD KEY `idx_cart_bundle_user` (`cus_id`),
  ADD KEY `idx_cart_bundle_bundle` (`bundle_id`);

--
-- Indexes for table `tblcategory`
--
ALTER TABLE `tblcategory`
  ADD PRIMARY KEY (`cat_id`);

--
-- Indexes for table `tblchat_session`
--
ALTER TABLE `tblchat_session`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `fkcat_customer` (`cus_id`);

--
-- Indexes for table `tblcomment`
--
ALTER TABLE `tblcomment`
  ADD PRIMARY KEY (`com_id`),
  ADD KEY `fkcommnet_customer` (`cus_id`);

--
-- Indexes for table `tblcontact`
--
ALTER TABLE `tblcontact`
  ADD PRIMARY KEY (`no`);

--
-- Indexes for table `tblcoupon`
--
ALTER TABLE `tblcoupon`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `coupon_ref_id` (`coupon_ref_id`),
  ADD UNIQUE KEY `uq_tblcoupon_available_code` (`code`,`coupon_type`),
  ADD KEY `idx_tblcoupon_type_status_expiry` (`coupon_type`,`status`,`expiry_date`),
  ADD KEY `idx_tblcoupon_scope` (`coupon_scope`);

--
-- Indexes for table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  ADD PRIMARY KEY (`cus_id`);

--
-- Indexes for table `tbldiscount_policy`
--
ALTER TABLE `tbldiscount_policy`
  ADD PRIMARY KEY (`discount_id`);

--
-- Indexes for table `tblfree_delivery_setting`
--
ALTER TABLE `tblfree_delivery_setting`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `tblimage`
--
ALTER TABLE `tblimage`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fkproduct_image` (`product_id`);

--
-- Indexes for table `tblmobile_specs`
--
ALTER TABLE `tblmobile_specs`
  ADD PRIMARY KEY (`mspecs_id`);

--
-- Indexes for table `tblorder`
--
ALTER TABLE `tblorder`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `tblorder_charge`
--
ALTER TABLE `tblorder_charge`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `tblpayment`
--
ALTER TABLE `tblpayment`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `tblphone`
--
ALTER TABLE `tblphone`
  ADD PRIMARY KEY (`phone_id`),
  ADD KEY `idx_tblphone_cus` (`cus_id`);

--
-- Indexes for table `tblproduct`
--
ALTER TABLE `tblproduct`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `tblproduct_feature`
--
ALTER TABLE `tblproduct_feature`
  ADD PRIMARY KEY (`feature_id`),
  ADD KEY `idx_tblproduct_feature_product_id` (`product_id`);

--
-- Indexes for table `tblpromotion`
--
ALTER TABLE `tblpromotion`
  ADD PRIMARY KEY (`promotion_id`),
  ADD KEY `idx_tblpromotion_scope_status` (`promotion_scope`,`status`);

--
-- Indexes for table `tblratings`
--
ALTER TABLE `tblratings`
  ADD PRIMARY KEY (`rating_id`);

--
-- Indexes for table `tblshipping_rate`
--
ALTER TABLE `tblshipping_rate`
  ADD PRIMARY KEY (`province`,`district`);

--
-- Indexes for table `tblweb_banner`
--
ALTER TABLE `tblweb_banner`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_password_reset`
--
ALTER TABLE `tbl_password_reset`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `idx_email_used` (`email`,`used`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbladmin_page_permission`
--
ALTER TABLE `tbladmin_page_permission`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tblai_user_behavior`
--
ALTER TABLE `tblai_user_behavior`
  MODIFY `behavior_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=290;

--
-- AUTO_INCREMENT for table `tblai_user_search_history`
--
ALTER TABLE `tblai_user_search_history`
  MODIFY `search_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tblbrand`
--
ALTER TABLE `tblbrand`
  MODIFY `brand_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `tblbundle`
--
ALTER TABLE `tblbundle`
  MODIFY `bundle_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblbundle_item`
--
ALTER TABLE `tblbundle_item`
  MODIFY `bundle_item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `tblcategory`
--
ALTER TABLE `tblcategory`
  MODIFY `cat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tblcomment`
--
ALTER TABLE `tblcomment`
  MODIFY `com_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tblcontact`
--
ALTER TABLE `tblcontact`
  MODIFY `no` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblcoupon`
--
ALTER TABLE `tblcoupon`
  MODIFY `coupon_ref_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  MODIFY `cus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbldiscount_policy`
--
ALTER TABLE `tbldiscount_policy`
  MODIFY `discount_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tblproduct_feature`
--
ALTER TABLE `tblproduct_feature`
  MODIFY `feature_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=542;

--
-- AUTO_INCREMENT for table `tblpromotion`
--
ALTER TABLE `tblpromotion`
  MODIFY `promotion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblratings`
--
ALTER TABLE `tblratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tblweb_banner`
--
ALTER TABLE `tblweb_banner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_password_reset`
--
ALTER TABLE `tbl_password_reset`
  MODIFY `reset_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbladdress`
--
ALTER TABLE `tbladdress`
  ADD CONSTRAINT `fkcustomer_address` FOREIGN KEY (`cus_id`) REFERENCES `tblcustomer` (`cus_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblcart`
--
ALTER TABLE `tblcart`
  ADD CONSTRAINT `fkcart_customer` FOREIGN KEY (`cus_id`) REFERENCES `tblcustomer` (`cus_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkcart_product` FOREIGN KEY (`product_id`) REFERENCES `tblproduct` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblchat_session`
--
ALTER TABLE `tblchat_session`
  ADD CONSTRAINT `fkcat_customer` FOREIGN KEY (`cus_id`) REFERENCES `tblcustomer` (`cus_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblcomment`
--
ALTER TABLE `tblcomment`
  ADD CONSTRAINT `fkcommnet_customer` FOREIGN KEY (`cus_id`) REFERENCES `tblcustomer` (`cus_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblimage`
--
ALTER TABLE `tblimage`
  ADD CONSTRAINT `fk_tblimage_product` FOREIGN KEY (`product_id`) REFERENCES `tblproduct` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblphone`
--
ALTER TABLE `tblphone`
  ADD CONSTRAINT `fkcustomer_phone` FOREIGN KEY (`cus_id`) REFERENCES `tblcustomer` (`cus_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblproduct_feature`
--
ALTER TABLE `tblproduct_feature`
  ADD CONSTRAINT `fk_tblproduct_feature_product` FOREIGN KEY (`product_id`) REFERENCES `tblproduct` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
