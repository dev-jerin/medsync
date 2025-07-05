# MedSync Healthcare Platform

MedSync is a state-of-the-art, web-based Healthcare Information System (HIS) designed to streamline hospital operations, enhance patient care, and improve administrative efficiency for medium to large hospitals. Built with a robust technology stack, it automates critical workflows including appointment scheduling, live token tracking, billing, prescription management, admissions, and discharges. The platform provides a secure, responsive, and user-friendly interface for Administrators, Doctors, Staff, and Patients, ensuring tailored functionality and seamless communication across all departments.

## ✨ Key Features

* **Role-Based Access Control (RBAC)**: Four distinct user roles (Administrator, Doctor, Staff, Patient) with specific permissions and tailored dashboards to ensure data security and operational efficiency.
* **Comprehensive User Management**: Secure OTP-based registration and password resets via email, session management with a 30-minute timeout, and detailed audit logs for critical actions. Administrators and Staff have designated user management capabilities.
* **Dynamic Appointment & Token System**: Real-time appointment booking based on doctor availability, a "Search Doctor" function, and a flexible live token system for queue management. The system accommodates late arrivals without disrupting the queue flow.
* **Automated & Enhanced Discharge Process**: A multi-step, sequential workflow initiated by a doctor and cleared by nursing and pharmacy teams before final bill generation and settlement. Discharge summaries and related documents are automatically generated and emailed to the patient.
* **Integrated Healthcare Services**: Digital prescription management, secure access to patient medical records, and management of lab results.
* **Resource & Inventory Management**: A color-coded interface to track bed availability (available, occupied, reserved, cleaning) and real-time monitoring of medicine and blood inventories with low-stock alerts.
* **Robust Notification System**: Automated email and system alerts for appointments, billing, discharges, and other critical events, powered by PHPMailer.
* **Advanced Security Protocols**: Measures include password hashing, CSRF token protection for all forms, prepared statements to prevent SQL injection, and secure session management.
* **Modern UI/UX**: A responsive and intuitive design combining glassmorphism and neumorphism, enhanced with GSAP and AOS animations for a professional and smooth user experience.
* **In-depth Reporting**: Generation of comprehensive reports on revenue, bed occupancy, and overall hospital performance, which can be exported to PDF.

---

## 💻 Technology Stack

* **Backend**: PHP
* **Database**: MySQL
* **Frontend**: HTML, CSS (no frameworks), JavaScript
* **UI/UX Libraries**:
    * **GSAP**: For advanced animations.
    * **AOS (Animate On Scroll)**: For scroll-based animations.
* **Email Notifications**: PHPMailer
* **PDF Generation**: dompdf
* **Server Environment**: XAMPP
* **Icons & Fonts**:
    * **Font Awesome**: For iconography.
    * **Poppins**: Primary font for readability.

---

## 👥 User Roles & Functionalities

The platform supports four distinct user roles, each with a dedicated dashboard and specific permissions:

### **Patient**

* **Appointments**: Book, view live token status, and cancel appointments.
* **Medical Information**: Access personal prescriptions, bills, and lab results, with options to download PDFs.
* **Profile Management**: Edit personal details and manage account security.
* **Communication**: Receive system notifications and submit feedback.

### **Doctor**

* **Patient Care**: Manage patient admissions, appointments, and prescriptions. Can add medications and tests during discharge.
* **Discharge Process**: Initiate patient discharge requests, which triggers the automated multi-step clearance process.
* **Record Management**: Access and manage patient medical records and input lab results.
* **Scheduling**: Manage personal availability and define appointment time slots.

### **Staff**

* **User Management**: Add, edit, and remove patient and doctor accounts.
* **Admissions & Discharge**: Manage the full admission process and execute the multi-step discharge confirmation, including nursing and pharmacy clearances, bill settlement, and final physical discharge.
* **Medication Dispensing**: View pending prescriptions, mark medications as 'given' or 'not available', and generate corresponding bills.
* **Inventory & Resource Management**: Track and update the status of beds, medicines, and blood inventory.
* **Shift Management**: View assigned work shifts.

### **Administrator**

* **Comprehensive User Management**: Add, edit, and remove all user accounts, including other admins, doctors, and staff. Can also perform bulk user uploads via CSV.
* **System Configuration**: Manage system-wide settings, including schedules, pricing, and ward/room details.
* **Reporting & Auditing**: Generate detailed reports on revenue and resource usage, and view activity logs for all critical system actions.
* **Staff Management**: Assign and manage staff shifts.
* **System Maintenance**: Perform database backups directly from the dashboard.

---

## 🛠️ Installation and Configuration

Follow these steps to set up the MedSync Healthcare Platform locally.

### **Prerequisites**

* XAMPP (with PHP >= 8.3, MySQL >= 8.0)

### **1. Clone the Repository**

```bash
git clone [https://github.com/your-username/medsync-healthcare-platform.git](https://github.com/your-username/medsync-healthcare-platform.git)
cd medsync-healthcare-platform
```

### **2. Database Setup**

1.  Start Apache and MySQL services from the XAMPP Control Panel.
2.  Open your web browser and navigate to `http://localhost/phpmyadmin`.
3.  Create a new database named `medsync`.
4.  Import the provided `medsync.sql` file to create all necessary tables and relationships.

### **3. Configure the Application**

Create a `config.php` file in the root directory with your database credentials:

```php
<?php
// config.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$db = 'medsync';

// Establish Database Connection
$conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Character Set
$conn->set_charset("utf8mb4");

// Function to get the database connection
function getDbConnection() {
    global $conn;
    return $conn;
}

// Initialize CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
```

### **4. Install Dependencies**

* **PHPMailer**: Download and place the PHPMailer library into a `vendor/` directory.
* **dompdf**: Download and place the dompdf library into a `vendor/` directory.
* **GSAP & AOS**: Download the library files and place them in a `public/js/` directory or link them via CDN in your HTML files.
    * GSAP CDN: `https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js`
    * AOS CDN: `https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js`

### **5. Configure Email (PHPMailer)**

For email functionalities like OTP and notifications, configure your SMTP settings in the relevant PHP files or a dedicated mail configuration file. This will require setting up `sendmail.ini` in your XAMPP installation or using an external SMTP service.

---

## 🚀 Usage

1.  Place the entire project folder inside the `htdocs` directory of your XAMPP installation.
2.  Open your web browser and navigate to `http://localhost/medsync-healthcare-platform/`.
3.  The home page will provide options to "Book Appointment" or "Patient Login". New users can register for an account, which involves an OTP verification step sent to their email.
4.  Log in using one of the predefined roles to access the corresponding dashboard and functionalities.

---

## 🎯 Expected Outcomes

The MedSync platform is designed to achieve tangible improvements in hospital management:

* **Reduce appointment scheduling delays by 40%**.
* **Minimize billing errors by 25%**.
* **Reduce manual paperwork by 35%**.
* Enhance patient satisfaction through real-time updates and improved communication.
* Optimize resource utilization and operational planning with real-time dashboards and reports.
