-- This is the complete and updated database schema for MedSync.
-- Version 2.3


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
-- Table structure for table `role_counters`
--
CREATE TABLE `role_counters` (
  `role_prefix` char(1) NOT NULL,
  `last_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Initialize role counters for user ID generation
--
INSERT INTO `role_counters` (`role_prefix`, `last_id`) VALUES
('A', 0), -- Counter for Admins (starts at A0001)
('D', 0), -- Counter for Doctors (starts at D0001)
('S', 0), -- Counter for Staff (starts at S0001)
('U', 0); -- Counter for Users/Patients (starts at U0001)

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
-- Table structure for table `specialities`
--
CREATE TABLE `specialities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `doctors`
--
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialty_id` int(11) DEFAULT NULL,
  `qualifications` varchar(255) DEFAULT NULL COMMENT 'e.g., MBBS, MD',
  `department_id` int(11) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Available, 0 = On Leave',
  `slots` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of available time slots',
  `office_floor` VARCHAR(50) NULL DEFAULT 'Ground Floor',
  `office_room_number` VARCHAR(50) NULL DEFAULT 'N/A',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_doctors_department` (`department_id`),
  KEY `fk_doctors_specialty` (`specialty_id`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_doctors_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_doctors_specialty` FOREIGN KEY (`specialty_id`) REFERENCES `specialities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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

--
-- Table structure for table `lab_orders` (Previously lab_results)
--
CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL COMMENT 'Staff member who processed the result',
  `encounter_id` INT(11) NULL DEFAULT NULL,
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
  `encounter_id` INT(11) NULL DEFAULT NULL,
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
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`clearance_step` ENUM('nursing', 'pharmacy', 'billing') NOT NULL,
`is_cleared` TINYINT(1) NOT NULL DEFAULT 0,
`cleared_by_user_id` INT(11) DEFAULT NULL,
`cleared_at` TIMESTAMP NULL DEFAULT NULL,
`notes` TEXT DEFAULT NULL,
`discharge_date` DATE DEFAULT NULL COMMENT 'Date of discharge, used for summary
PDF',
`summary_text` TEXT DEFAULT NULL COMMENT 'Narrative summary for discharge, used for
PDF',
`doctor_id` INT(11) DEFAULT NULL COMMENT 'Doctor who authored the discharge
summary',
PRIMARY KEY (`id`),
UNIQUE KEY `unique_clearance_step` (`admission_id`, `clearance_step`),
KEY `discharge_date` (`discharge_date`),
KEY `fk_discharge_clearance_doctor` (`doctor_id`),
CONSTRAINT `fk_clearance_admission` FOREIGN KEY (`admission_id`) REFERENCES
`admissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
CONSTRAINT `fk_clearance_user` FOREIGN KEY (`cleared_by_user_id`) REFERENCES
`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
CONSTRAINT `fk_discharge_clearance_doctor` FOREIGN KEY (`doctor_id`) REFERENCES
`users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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

--
-- NEW: Table structure for table `patient_encounters`
--
CREATE TABLE `patient_encounters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `encounter_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `chief_complaint` text DEFAULT NULL,
  `vitals` json DEFAULT NULL,
  `soap_subjective` text DEFAULT NULL,
  `soap_objective` text DEFAULT NULL,
  `soap_assessment` text DEFAULT NULL,
  `soap_plan` text DEFAULT NULL,
  `diagnosis_icd10` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `fk_encounter_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DEMO DATA
--
--
-- Initialize an admin user (Change password after first login) - change it with real data
--
INSERT INTO `users` (`display_user_id`,`username`,`email`,`password`,`role_id`,`name`,`phone`,`is_active`) VALUES ('A0001','admin','admin@email.com','$2y$10$st0OkWHJKIYaSe7DxNNp2.X506p38taUUBSUT0y/pd2gfCGPDI/qO',4,'Admin','+910000000000',1);

UPDATE `role_counters` SET `last_id` = 1 WHERE `role_prefix` = 'A';

--
-- Initialize specialities
--
INSERT INTO `specialities` (`name`, `description`) VALUES
('Anesthesiology', 'Focuses on perioperative care, developing anesthetic plans, and the administration of anesthetics.'),
('Cardiology', 'Deals with disorders of the heart as well as some parts of the circulatory system.'),
('Dermatology', 'Concerned with the diagnosis and treatment of diseases of the skin, hair, and nails.'),
('Emergency Medicine', 'Focuses on the immediate decision making and action necessary to prevent death or any further disability.'),
('Endocrinology', 'Deals with the diagnosis and treatment of diseases related to hormones.'),
('Gastroenterology', 'Focuses on the digestive system and its disorders.'),
('General Surgery', 'A surgical specialty that focuses on abdominal contents including esophagus, stomach, small intestine, large intestine, liver, pancreas, gallbladder, appendix and bile ducts, and often the thyroid gland.'),
('Hematology', 'The study of blood, the blood-forming organs, and blood diseases.'),
('Infectious Disease', 'Deals with the diagnosis and treatment of complex infections.'),
('Nephrology', 'A specialty of medicine that concerns itself with the kidneys.'),
('Neurology', 'Deals with disorders of the nervous system.'),
('Obstetrics and Gynecology (OB/GYN)', 'Focuses on female reproductive health and childbirth.'),
('Oncology', 'A branch of medicine that deals with the prevention, diagnosis, and treatment of cancer.'),
('Ophthalmology', 'Deals with the diagnosis and treatment of eye disorders.'),
('Orthopedics', 'The branch of surgery concerned with conditions involving the musculoskeletal system.'),
('Otolaryngology (ENT)', 'A surgical subspecialty within medicine that deals with the surgical and medical management of conditions of the head and neck.'),
('Pediatrics', 'The branch of medicine that involves the medical care of infants, children, and adolescents.'),
('Psychiatry', 'The medical specialty devoted to the diagnosis, prevention, and treatment of mental disorders.'),
('Pulmonology', 'A medical speciality that deals with diseases involving the respiratory tract.'),
('Radiology', 'A medical specialty that uses medical imaging to diagnose and treat diseases within the bodies of animals and humans.'),
('Rheumatology', 'A sub-specialty in internal medicine and pediatrics, devoted to the diagnosis and therapy of rheumatic diseases.'),
('Urology', 'Focuses on surgical and medical diseases of the male and female urinary-tract system and the male reproductive organs.');

--
-- Insert initial blood groups
--
INSERT INTO `blood_inventory` (`blood_group`, `quantity_ml`, `low_stock_threshold_ml`) VALUES
('A+', 15000, 5000),
('A-', 8000, 3000),
('B+', 12000, 5000),
('B-', 7500, 3000),
('AB+', 5000, 2000),
('AB-', 3000, 1500),
('O+', 20000, 7000),
('O-', 10000, 4000);

--
-- Initialize system settings for email (Change with real data)
--
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES 
('system_email', 'your_email@gmail.com'), 
('gmail_app_password', 'your_gmail_app_password') 
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

--
-- Inserting department data.
--
INSERT INTO `departments` (`name`, `head_of_department_id`, `is_active`) VALUES
('Anesthesiology', NULL, 1),
('Cardiology', NULL, 1), 
('Dermatology', NULL, 1),
('Emergency Medicine', NULL, 1),
('Endocrinology', NULL, 1),
('Gastroenterology', NULL, 1),
('General Medicine', NULL, 1),
('General Surgery', NULL, 1),
('Hematology', NULL, 1),
('Infectious Disease', NULL, 1),
('Intensive Care Unit (ICU)', NULL, 1),
('Nephrology', NULL, 1),
('Neurology', NULL, 1), 
('Obstetrics and Gynecology (OB/GYN)', NULL, 1),
('Oncology', NULL, 1),
('Ophthalmology', NULL, 1),
('Orthopedics', NULL, 1),
('Otolaryngology (ENT)', NULL, 1),
('Pediatrics', NULL, 1),
('Pharmacy Services', NULL, 1),
('Psychiatry', NULL, 1),
('Pulmonology', NULL, 1),
('Radiology and Imaging', NULL, 1),
('Rheumatology', NULL, 1),
('Urology', NULL, 1);

-- Insert a comprehensive list of hospital wards
INSERT INTO `wards` (`name`, `capacity`, `description`, `is_active`) VALUES
('General Ward', 30, 'For general medical and post-surgical recovery patients.', 1),
('Intensive Care Unit (ICU)', 12, 'For critically ill patients requiring intensive monitoring and care.', 1),
('Pediatric Ward', 15, 'Dedicated care for infants, children, and adolescents.', 1),
('Maternity Ward', 18, 'Provides care for women during pregnancy, childbirth, and the postpartum period.', 1),
('Cardiology Ward', 20, 'Specialized unit for patients with acute and chronic heart conditions.', 1),
('Neurology Ward', 16, 'For patients with disorders of the nervous system, including stroke and brain injuries.', 1),
('Orthopedic Ward', 22, 'Focuses on patients recovering from musculoskeletal surgeries and injuries.', 1),
('Oncology Ward', 14, 'Provides comprehensive care for patients undergoing cancer treatment.', 1),
('Emergency Observation Unit', 10, 'For short-term observation of emergency patients before admission or discharge.', 1),
('Geriatric Ward', 15, 'Specialized care tailored to the needs of elderly patients.', 1),
('Psychiatric Ward', 12, 'Secure unit for the assessment and treatment of mental health conditions.', 1),
('Surgical Ward', 25, 'For patients recovering from various surgical procedures.', 1),
('Isolation Ward', 8, 'For patients with contagious diseases requiring isolation from others.', 1),
 

--
-- Insert comprehensive data for accommodations
--
INSERT INTO `accommodations` (`type`, `number`, `ward_id`, `status`, `patient_id`, `doctor_id`, `occupied_since`, `reserved_since`, `price_per_day`) VALUES
-- General Ward (ward_id: 1) - Economy Beds
('bed', 'GW-B01', 1, 'available', NULL, NULL, NULL, NULL, 1500.00),
('bed', 'GW-B02', 1, 'available', NULL, NULL, NULL, NULL, 1500.00),
('bed', 'GW-B03', 1, 'occupied', NULL, NULL, NULL, NULL, 1500.00),
('bed', 'GW-B04', 1, 'cleaning', NULL, NULL, NULL, NULL, 1500.00),
('bed', 'GW-B05', 1, 'available', NULL, NULL, NULL, NULL, 1500.00),
-- Cardiology Ward (ward_id: 2) - Private Rooms and Semi-Private Beds
('room', 'CW-R101', 2, 'available', NULL, NULL, NULL, NULL, 5500.00),
('bed', 'CW-B01', 2, 'available', NULL, NULL, NULL, NULL, 2800.00),
('bed', 'CW-B02', 2, 'available', NULL, NULL, NULL, NULL, 2800.00),
-- Neurology Ward (ward_id: 3) - Standard Beds
('bed', 'NW-B01', 3, 'available', NULL, NULL, NULL, NULL, 2200.00),
('bed', 'NW-B02', 3, 'available', NULL, NULL, NULL, NULL, 2200.00),
('bed', 'NW-B03', 3, 'available', NULL, NULL, NULL, NULL, 2200.00),
-- Intensive Care Unit (ICU) (ward_id: 4) - Critical Care Beds
('bed', 'ICU-B01', 4, 'occupied', NULL, NULL, NULL, NULL, 12000.00),
('bed', 'ICU-B02', 4, 'available', NULL, NULL, NULL, NULL, 12000.00),
('bed', 'ICU-B03', 4, 'cleaning', NULL, NULL, NULL, NULL, 12000.00),
-- Pediatric Ward (ward_id: 5, assuming this ward exists)
('room', 'PW-R201', 5, 'available', NULL, NULL, NULL, NULL, 4000.00),
('room', 'PW-R202', 5, 'available', NULL, NULL, NULL, NULL, 4000.00),
-- Deluxe Private Ward (ward_id: 6, assuming this ward exists)
('room', 'DX-R301', 6, 'available', NULL, NULL, NULL, NULL, 9500.00),
('room', 'DX-R302', 6, 'occupied', NULL, 2, '2025-10-06 15:00:00', NULL, 9500.00), -- VIP Patient
('room', 'DX-R303', 6, 'available', NULL, NULL, NULL, NULL, 9500.00),
-- Maternity Ward (ward_id: 7, assuming this ward exists)
('room', 'MW-R401', 7, 'available', NULL, NULL, NULL, NULL, 6000.00),
('room', 'MW-R402', 7, 'available', NULL, NULL, NULL, NULL, 6000.00);

-- Add new columns to the users table for notification preferences
-- We set DEFAULT 1 (true) to match the 'checked' status in your original HTML

-- newly added by user1
ALTER TABLE `users`
  ADD COLUMN `notify_appointments` TINYINT(1) NOT NULL DEFAULT 1 AFTER `date_of_birth`,
  ADD COLUMN `notify_billing` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_appointments`,
  ADD COLUMN `notify_labs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_billing`,
  ADD COLUMN `notify_prescriptions` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_labs`;