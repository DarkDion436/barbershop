-- 1. DATABASE INITIALIZATION
CREATE DATABASE IF NOT EXISTS barber_shop_spa;
USE barber_shop_spa;

-- 2. SERVICES TABLE
-- Stores both Barber and Spa offerings with hierarchy
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category ENUM('Barber', 'Spa', 'Wellness', 'Packages') NOT NULL,
    subcategory VARCHAR(50) NOT NULL, -- e.g., 'Haircuts', 'Facials', 'Massages'
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration_minutes INT DEFAULT 30,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. STAFF TABLE
-- Handles Barbers, Estheticians, and Therapists
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('Master Barber', 'Barber', 'Esthetician', 'Massage Therapist') NOT NULL,
    bio TEXT,
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. APPOINTMENTS TABLE
-- The core engine for the booking system
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL,
    client_email VARCHAR(100) NOT NULL,
    client_phone VARCHAR(20) NOT NULL,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- 5. ADMIN USERS TABLE
-- Secure credentials for the admin panel access
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    reset_token_hash VARCHAR(64) NULL,
    reset_token_expires_at DATETIME NULL,
    last_login DATETIME
);

-- 6. GALLERY TABLE
-- Stores hairstyle images and captions
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. SAMPLE DATA INSERTION (FOR TESTING)

-- Inserting Services with Subcategories
INSERT INTO services (name, category, subcategory, description, price, duration_minutes) VALUES 
('Skin Fade', 'Barber', 'Haircuts', 'Modern fade with foil finish.', 35.00, 45),
('Executive Beard Trim', 'Barber', 'Beard & Shave', 'Shaping with hot towel and razor finish.', 25.00, 30),
('Deep Pore Facial', 'Spa', 'Facials', 'Cleansing facial for oily or congested skin.', 65.00, 50),
('Oxygen Eye Lift', 'Spa', 'Skin Treatments', 'Reducing puffiness and dark circles.', 30.00, 20),
('Sport Massage', 'Wellness', 'Massages', 'Deep tissue focus on neck and shoulders.', 45.00, 30),
('Paraffin Hand Wax', 'Wellness', 'Hand & Foot Care', 'Hydrating wax treatment for rough hands.', 15.00, 15),
('The King Treatment', 'Packages', 'Bundles', 'Haircut, Shave, and 30min Facial.', 110.00, 100);

-- Inserting Professional Staff
INSERT INTO staff (name, role, bio) VALUES 
('Julian Thorne', 'Master Barber', 'Specialist in classic scissor cuts and fades.'),
('Sophia Chen', 'Esthetician', 'Expert in male skincare and restorative facials.'),
('Marcus Reed', 'Barber', 'The go-to guy for beard sculpting and design.');

-- Creating a Default Admin (Password: admin123 - Use password_hash in PHP later)
INSERT INTO admins (username, email, password, full_name) VALUES 
('admin', 'admin@bladeandtrim.com', '$2y$10$vI8A.SByM6e27/5gq.lSDeK/Jq6jPZ.mHj9.mO1vE8mGkU1/N6Fqy', 'Shop Manager');