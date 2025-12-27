# Blade & Trim - Barber Shop & Spa Management System

A comprehensive, web-based management solution designed for barber shops and spas. This application features a responsive public landing page for client bookings and a robust admin panel for managing daily operations, staff, finances, and system settings.

## 🚀 Features

### 🌐 Public Interface
*   **Landing Page:** Modern, responsive design showcasing services, about section, and contact details.
*   **Online Booking:** Clients can book appointments by selecting specific services, preferred staff members, and available time slots.
*   **Conflict Detection:** Intelligent system that prevents double-booking staff members within the same hour.

### 🔐 Admin Dashboard
*   **Real-time Overview:** Dashboard with key metrics (Today's Bookings, Revenue, Active Staff).
*   **Alert System:** Notifications for stale pending bookings, unpaid completed services, and cancellations.
*   **Appointment Management:**
    *   View, filter, and search appointments.
    *   Workflow: Pending → Confirmed → Completed / Cancelled.
    *   **Payment Tracking:** Record M-Pesa codes and amounts for completed jobs.
    *   **Receipts:** Generate printable receipts for clients.
*   **Staff Management:** Add/Edit staff profiles, roles, bios, and upload profile images.
*   **Service Management:** Configure service categories, pricing, and duration.
*   **Customer Database:** View client history and contact information.
*   **Reports & Analytics:**
    *   Visual revenue charts (Monthly breakdown).
    *   Detailed transaction logs.
    *   Cancellation reports with restore functionality.
*   **Gallery:** Manage portfolio images displayed on the site.
*   **Activity Log:** Audit trail of all admin actions (Login, Delete, Backup, etc.).
*   **System Settings:**
    *   Shop details configuration.
    *   **Database Tools:** One-click database backup (.sql download) and restore.
    *   Admin account management.

## 🛠️ Tech Stack

*   **Backend:** PHP (Vanilla, PDO)
*   **Frontend:** HTML5, CSS3, JavaScript
*   **Database:** MySQL
*   **Server:** Apache (XAMPP/WAMP recommended)
*   **Libraries:** FontAwesome (Icons), Chart.js (Analytics)

## ⚙️ Installation & Setup

### 1. Prerequisites
*   A local server environment like **XAMPP**, **WAMP**, or **MAMP**.
*   PHP 7.4 or higher.
*   MySQL 5.7 or higher.

### 2. Deployment
1.  Clone or download this repository.
2.  Place the `BarberShop` folder inside your server's root directory (e.g., `C:\xampp\htdocs\`).

### 3. Database Configuration
1.  Open **phpMyAdmin** or your preferred SQL client.
2.  Create a new database named `barber_shop_spa`.
3.  Open `db_connect.php` and ensure the credentials match your local setup:
    ```php
    $host = 'localhost';
    $db   = 'barber_shop_spa';
    $user = 'root'; // Default XAMPP user
    $pass = '';     // Default XAMPP password
    ```

### 4. Initialize Database
To create the necessary tables and insert sample data (including the default admin account), run the reset script in your browser:

*   **URL:** `http://localhost/BarberShop/reset_database.php`

> **Note:** This script will drop existing tables and re-seed the database.

### 5. M-Pesa Configuration (Optional)
To enable M-Pesa integration features, configure `mpesa_config.php` with your Daraja API credentials.

## 📖 Usage

### Admin Login
*   **URL:** `http://localhost/BarberShop/login.php`
*   **Default Username:** `admin`
*   **Default Password:** `admin123`

### Public Booking
*   **URL:** `http://localhost/BarberShop/index.php`

## 📂 Key Files Structure

*   `index.php` - Public landing page and booking form.
*   `dashboard.php` - Main admin overview.
*   `appointments.php` - Booking management logic.
*   `reports.php` - Financial analytics and logs.
*   `settings.php` - System configuration and DB tools.
*   `reset_database.php` - Database installation script.
*   `db_connect.php` - Database connection settings.

## 🛡️ Security
*   **Session Management:** Secure session handling for admin authentication.
*   **Input Sanitization:** Protection against XSS and SQL Injection using PDO prepared statements.
*   **Activity Logging:** Tracks critical system changes.

---

*Developed for Blade & Trim Barber Shop.*
