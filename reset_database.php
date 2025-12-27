<?php
// c:\xampp\htdocs\BarberShop\reset_database.php

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

try {
    echo "<h1>Resetting Database...</h1>";

    // 1. Drop Tables
    // Disable foreign key checks to avoid constraint errors during drop
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $pdo->exec("DROP VIEW IF EXISTS view_cancelled_bookings");
    
    $tables = ['appointments', 'admins', 'staff', 'services'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "Dropped table: $table<br>";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Create Tables
    
    // Services
    $pdo->exec("CREATE TABLE services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category ENUM('Barber', 'Spa', 'Wellness', 'Packages') NOT NULL,
        subcategory VARCHAR(50) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2) NOT NULL,
        duration_minutes INT DEFAULT 30,
        image_url VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created table: services<br>";

    // Staff
    $pdo->exec("CREATE TABLE staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        role ENUM('Master Barber', 'Barber', 'Esthetician', 'Massage Therapist') NOT NULL,
        bio TEXT,
        profile_image VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created table: staff<br>";

    // Appointments
    $pdo->exec("CREATE TABLE appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(100) NOT NULL,
        client_email VARCHAR(100) NOT NULL,
        client_phone VARCHAR(20) NOT NULL,
        service_id INT NOT NULL,
        staff_id INT NOT NULL,
        appointment_date DATETIME NOT NULL,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        mpesa_code VARCHAR(50) DEFAULT NULL,
        amount_paid DECIMAL(10, 2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
    )");
    echo "Created table: appointments<br>";

    // Admins
    $pdo->exec("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        reset_token_hash VARCHAR(64) NULL,
        reset_token_expires_at DATETIME NULL,
        last_login DATETIME
    )");
    echo "Created table: admins<br>";

    // 3. Create Views
    $pdo->exec("CREATE OR REPLACE VIEW view_cancelled_bookings AS
            SELECT 
                a.id AS appointment_id,
                a.client_name,
                a.client_email,
                a.client_phone,
                s.name AS service_name,
                s.price AS service_price,
                st.name AS staff_name,
                a.appointment_date AS scheduled_date,
                COALESCE(a.updated_at, a.appointment_date) AS cancellation_date,
                a.notes AS cancellation_reason
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE a.status = 'cancelled'");
    echo "Created view: view_cancelled_bookings<br>";

    // 4. Insert Sample Data

    // Services
    $sql = "INSERT INTO services (name, category, subcategory, description, price, duration_minutes) VALUES 
    ('Skin Fade', 'Barber', 'Haircuts', 'Modern fade with foil finish.', 35.00, 45),
    ('Executive Beard Trim', 'Barber', 'Beard & Shave', 'Shaping with hot towel and razor finish.', 25.00, 30),
    ('Deep Pore Facial', 'Spa', 'Facials', 'Cleansing facial for oily or congested skin.', 65.00, 50),
    ('Oxygen Eye Lift', 'Spa', 'Skin Treatments', 'Reducing puffiness and dark circles.', 30.00, 20),
    ('Sport Massage', 'Wellness', 'Massages', 'Deep tissue focus on neck and shoulders.', 45.00, 30),
    ('Paraffin Hand Wax', 'Wellness', 'Hand & Foot Care', 'Hydrating wax treatment for rough hands.', 15.00, 15),
    ('The King Treatment', 'Packages', 'Bundles', 'Haircut, Shave, and 30min Facial.', 110.00, 100)";
    $pdo->exec($sql);
    echo "Inserted sample services<br>";

    // Staff
    $sql = "INSERT INTO staff (name, role, bio) VALUES 
    ('Julian Thorne', 'Master Barber', 'Specialist in classic scissor cuts and fades.'),
    ('Sophia Chen', 'Esthetician', 'Expert in male skincare and restorative facials.'),
    ('Marcus Reed', 'Barber', 'The go-to guy for beard sculpting and design.')";
    $pdo->exec($sql);
    echo "Inserted sample staff<br>";

    // Admins (Default: admin / admin123)
    $admin_pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admin', 'admin@bladeandtrim.com', $admin_pass_hash, 'Shop Manager']);
    echo "Inserted default admin (admin / admin123)<br>";

    // Appointments (Dynamic dates relative to today)
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $appointments_sql = "INSERT INTO appointments (client_name, client_email, client_phone, service_id, staff_id, appointment_date, status) VALUES 
    ('Mike Ross', 'mike@example.com', '555-0101', 1, 1, '$today 10:00:00', 'confirmed'),
    ('Harvey Specter', 'harvey@example.com', '555-0102', 2, 3, '$today 11:30:00', 'pending'),
    ('Louis Litt', 'louis@example.com', '555-0103', 3, 2, '$today 14:00:00', 'completed'),
    ('Donna Paulsen', 'donna@example.com', '555-0104', 4, 2, '$yesterday 09:00:00', 'completed'),
    ('Rachel Zane', 'rachel@example.com', '555-0105', 6, 2, '$yesterday 15:00:00', 'completed'),
    ('Jessica Pearson', 'jessica@example.com', '555-0106', 7, 1, '$tomorrow 10:00:00', 'confirmed'),
    ('Alex Williams', 'alex@example.com', '555-0107', 1, 3, '$tomorrow 13:00:00', 'pending')";
    
    $pdo->exec($appointments_sql);
    echo "Inserted sample appointments<br>";

    echo "<h3 style='color:green'>Database reset complete!</h3>";
    echo "<p><a href='login.php'>Go to Login</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>