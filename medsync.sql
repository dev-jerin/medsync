-- This is the complete and updated database schema for MedSync.
-- Version 2.2
-- This version includes the updated Lab Order workflow.
-- Key changes include:
-- 1. Renamed 'lab_results' to 'lab_orders' to better reflect its function.
-- 2. Added an 'ordered' status to the lab orders table.
-- 3. Added an 'ordered_at' timestamp to track when a doctor places an order.

CREATE DATABASE IF NOT EXISTS `medsync` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `medsync`;

--
-- Table structure for table `roles`
--
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Initialize roles
--
INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'user'),
(2, 'doctor'),
(3, 'staff'),
(4, 'admin');

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_user_id` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `profile_picture` VARCHAR(255) NULL DEFAULT 'default.png',
  `phone` varchar(25) NULL DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `session_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `display_user_id` (`display_user_id`),
  KEY `name` (`name`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `activity_logs`
--
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'ID of the user who performed the action',
  `action` varchar(255) NOT NULL COMMENT 'e.g., create_user, update_user, delete_medicine',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'ID of the user who was affected by the action',
  `details` text DEFAULT NULL COMMENT 'A human-readable description of the action',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `target_user_id` (`target_user_id`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activity_logs_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `departments`
--
--
-- Table structure for table `departments`
--
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `head_of_department_id` int(11) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_dept_head` (`head_of_department_id`),
  CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_of_department_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `role_counters`
--
CREATE TABLE `role_counters` (
  `role_prefix` char(1) NOT NULL,
  `last_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `doctors`
--
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `qualifications` varchar(255) DEFAULT NULL COMMENT 'e.g., MBBS, MD',
  `department_id` int(11) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Available, 0 = On Leave',
  `slots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of available time slots',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_doctors_department` (`department_id`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_doctors_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `staff`
--
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shift` enum('day','night','off') NOT NULL DEFAULT 'day',
  `assigned_department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_staff_department` (`assigned_department_id`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_department` FOREIGN KEY (`assigned_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `callback_requests`
--
CREATE TABLE `callback_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_contacted` tinyint(1) NOT NULL DEFAULT 0,
   PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `medicines`
--
CREATE TABLE `medicines` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `low_stock_threshold` INT(11) NOT NULL DEFAULT 10,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `blood_inventory`
--
CREATE TABLE `blood_inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `quantity_ml` INT(11) NOT NULL DEFAULT 0 COMMENT 'Quantity in milliliters',
  `low_stock_threshold_ml` INT(11) NOT NULL DEFAULT 5000 COMMENT 'Threshold in milliliters',
  `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `wards`
--
CREATE TABLE `wards` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'e.g., General Ward, ICU, Pediatric Ward',
  `capacity` INT(11) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `accommodations`
--
CREATE TABLE `accommodations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` ENUM('bed', 'room') NOT NULL,
  `number` VARCHAR(50) NOT NULL COMMENT 'Bed number or room number',
  `ward_id` INT(11) DEFAULT NULL COMMENT 'Applicable only if type is bed',
  `status` ENUM('available', 'occupied', 'reserved', 'cleaning') NOT NULL DEFAULT 'available',
  `patient_id` INT(11) DEFAULT NULL,
  `doctor_id` INT(11) DEFAULT NULL,
  `occupied_since` DATETIME DEFAULT NULL,
  `reserved_since` DATETIME DEFAULT NULL,
  `price_per_day` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_number_ward_unique` (`type`, `number`, `ward_id`),
  KEY `fk_accommodations_ward` (`ward_id`),
  KEY `fk_accommodations_patient` (`patient_id`),
  KEY `fk_accommodations_doctor` (`doctor_id`),
  CONSTRAINT `fk_accommodations_ward` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_accommodations_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_accommodations_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `admissions`
--
CREATE TABLE `admissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `accommodation_id` int(11) DEFAULT NULL,
  `admission_date` datetime NOT NULL,
  `discharge_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `fk_admissions_accommodation` (`accommodation_id`),
  CONSTRAINT `fk_admissions_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admissions_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_admissions_accommodation` FOREIGN KEY (`accommodation_id`) REFERENCES `accommodations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `appointments`
--
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `slot_start_time` time DEFAULT NULL,
  `slot_end_time` time DEFAULT NULL,
  `token_number` int(11) DEFAULT NULL,
  `token_status` enum('waiting','in_consultation','completed','skipped') NOT NULL DEFAULT 'waiting',
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `idx_doctor_slot_token` (`doctor_id`,`appointment_date`,`slot_start_time`,`token_number`),
  CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `transactions`
--
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admission_id` INT(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('payment','refund') NOT NULL DEFAULT 'payment',
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payment_mode` enum('unpaid','cash','card','online') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_transaction_admission` (`admission_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transaction_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `notifications`
--
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_role` varchar(50) DEFAULT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_user_id` (`recipient_user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `system_settings`
--
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(255) NOT NULL,
  `setting_value` TEXT NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('gmail_app_password', 'sswyqzegdpyixbyw');

--
-- Table structure for table `lab_orders` (Previously lab_results)
--
CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL COMMENT 'Staff member who processed the result',
  `ordered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `test_name` varchar(255) NOT NULL,
  `test_date` date DEFAULT NULL,
  `status` enum('ordered','pending','processing','completed') NOT NULL DEFAULT 'ordered',
  `result_details` text DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `attachment_path` varchar(255) DEFAULT NULL COMMENT 'Path to an uploaded file (e.g., PDF)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `fk_lab_orders_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lab_orders_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lab_orders_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `conversations`
--
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_one_id` int(11) NOT NULL,
  `user_two_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user_one_id`,`user_two_id`),
  KEY `fk_conversations_user_two` (`user_two_id`),
  CONSTRAINT `fk_conversations_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conversations_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `messages`
--
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `prescriptions`
--
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `admission_id` INT(11) DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `status` enum('pending','dispensed','partial','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `prescription_date` (`prescription_date`),
  KEY `fk_prescription_admission` (`admission_id`),
  CONSTRAINT `fk_prescriptions_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prescriptions_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prescription_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `prescription_items`
--
CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `quantity_prescribed` int(11) NOT NULL DEFAULT 1,
  `quantity_dispensed` int(11) NOT NULL DEFAULT 0,
  `is_dispensed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `fk_items_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--Table for automated discharge process
--
CREATE TABLE `discharge_clearance` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admission_id` INT(11) NOT NULL,
  `clearance_step` ENUM('nursing', 'pharmacy', 'billing') NOT NULL,
  `is_cleared` TINYINT(1) NOT NULL DEFAULT 0,
  `cleared_by_user_id` INT(11) DEFAULT NULL,
  `cleared_at` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_clearance_step` (`admission_id`, `clearance_step`),
  CONSTRAINT `fk_clearance_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clearance_user` FOREIGN KEY (`cleared_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Table structure for table `feedback`
--
CREATE TABLE `feedback` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) NOT NULL,
  `appointment_id` INT(11) DEFAULT NULL COMMENT 'Link feedback to a specific appointment',
  `admission_id` INT(11) DEFAULT NULL COMMENT 'Link feedback to a hospital stay',
  `overall_rating` TINYINT(1) NOT NULL COMMENT 'Rating from 1 to 5',
  `doctor_rating` TINYINT(1) DEFAULT NULL,
  `nursing_rating` TINYINT(1) DEFAULT NULL,
  `staff_rating` TINYINT(1) DEFAULT NULL,
  `cleanliness_rating` TINYINT(1) DEFAULT NULL,
  `comments` TEXT DEFAULT NULL,
  `feedback_type` ENUM('Suggestion', 'Complaint', 'Praise') NOT NULL DEFAULT 'Suggestion',
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `admission_id` (`admission_id`),
  CONSTRAINT `fk_feedback_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_feedback_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `pharmacy_bills`
--
CREATE TABLE `pharmacy_bills` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` INT(11) NOT NULL,
  `transaction_id` INT(11) NOT NULL,
  `created_by_staff_id` INT(11) NOT NULL,
  `total_amount` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prescription_bill` (`prescription_id`),
  CONSTRAINT `fk_bill_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bill_staff` FOREIGN KEY (`created_by_staff_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- NEW: Table structure for table `ip_tracking`
--
CREATE TABLE `ip_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ip_tracking_user` (`user_id`),
  CONSTRAINT `fk_ip_tracking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- NEW: Table structure for table `ip_blocks`
--
CREATE TABLE `ip_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;