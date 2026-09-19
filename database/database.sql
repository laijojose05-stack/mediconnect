CREATE DATABASE IF NOT EXISTS mediconnect;

USE mediconnect;


-- ============================================
-- ADMIN TABLE
-- ============================================

CREATE TABLE admins (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ============================================
-- USERS TABLE
-- ============================================

CREATE TABLE users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    phone VARCHAR(20),

    address TEXT,

    status ENUM('active', 'inactive') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ============================================
-- HOSPITALS TABLE
-- ============================================

CREATE TABLE hospitals (

    id INT AUTO_INCREMENT PRIMARY KEY,

    hospital_name VARCHAR(150) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    phone VARCHAR(20),

    address TEXT,

    city VARCHAR(100),

    specialty VARCHAR(150),

    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ============================================
-- PHARMACIES TABLE
-- ============================================

CREATE TABLE pharmacies (

    id INT AUTO_INCREMENT PRIMARY KEY,

    pharmacy_name VARCHAR(150) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    phone VARCHAR(20),

    address TEXT,

    city VARCHAR(100),

    license_number VARCHAR(100),

    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ============================================
-- MEDICINES TABLE
-- ============================================

CREATE TABLE medicines (

    id INT AUTO_INCREMENT PRIMARY KEY,

    medicine_name VARCHAR(150) NOT NULL,

    generic_name VARCHAR(150),

    category VARCHAR(100),

    description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- ============================================
-- DEFAULT ADMIN ACCOUNT
-- ============================================

INSERT INTO admins (

    name,
    email,
    password

)

VALUES (

    'Administrator',

    'admin@mediconnect.com',

    'admin123'

);

CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    doctor_name VARCHAR(150) NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    hospital_id INT NOT NULL,
    doctor_id INT NULL,
    patient_name VARCHAR(150) NOT NULL,
    patient_phone VARCHAR(20),
    doctor_name VARCHAR(150),
    notes TEXT,
    appointment_date DATE NOT NULL,
    appointment_time TIME,
    status ENUM('pending','approved','rejected','completed','cancelled')
    DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hospital_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    department_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    patient_name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    gender VARCHAR(20),
    age INT,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    doctor_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
        ON DELETE CASCADE,

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS doctor_exceptions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    hospital_id INT NOT NULL,
    doctor_id INT NOT NULL,

    exception_date DATE NOT NULL,

    status ENUM(
        'unavailable',
        'leave'
    ) NOT NULL DEFAULT 'unavailable',

    reason VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_exception_hospital
        FOREIGN KEY (hospital_id)
        REFERENCES hospitals(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_exception_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE
);

USE mediconnect;

-- =====================================================
-- MEDICONNECT USER MODULE
-- Based on the current database structure
-- =====================================================


-- =====================================================
-- 1. USERS TABLE
-- Already exists in your database
-- No need to recreate it
-- =====================================================


-- =====================================================
-- 2. ADD FOREIGN KEY FOR USER
-- (user_id already exists in the CREATE TABLE above;
--  this only adds the FK constraint)
-- =====================================================

ALTER TABLE appointments
ADD CONSTRAINT fk_appointments_user
FOREIGN KEY (user_id)
REFERENCES users(id)
ON DELETE CASCADE;


-- =====================================================
-- 4. MEDICINE REQUESTS
-- Allows users to request medicines from pharmacies
-- =====================================================

CREATE TABLE IF NOT EXISTS medicine_requests (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    pharmacy_id INT NOT NULL,

    medicine_id INT NOT NULL,

    quantity INT NOT NULL DEFAULT 1,

    notes TEXT,

    status ENUM(
        'pending',
        'accepted',
        'rejected',
        'ready',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_medicine_request_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_medicine_request_pharmacy
        FOREIGN KEY (pharmacy_id)
        REFERENCES pharmacies(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_medicine_request_medicine
        FOREIGN KEY (medicine_id)
        REFERENCES medicines(id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- 5. USER NOTIFICATIONS
-- Allows hospitals/pharmacies/system to notify users
-- =====================================================

CREATE TABLE IF NOT EXISTS user_notifications (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    title VARCHAR(200) NOT NULL,

    message TEXT NOT NULL,

    type VARCHAR(50) DEFAULT 'general',

    related_id INT DEFAULT NULL,

    is_read TINYINT(1) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- 6. OPTIONAL: PRESCRIPTION UPLOAD
-- Used when requesting prescription medicines
-- =====================================================

ALTER TABLE medicine_requests
ADD COLUMN prescription_image VARCHAR(255) NULL AFTER quantity;


-- =====================================================
-- 7. CHECK USER MODULE TABLES
-- =====================================================

SELECT
    id,
    name,
    email,
    phone,
    address,
    status,
    created_at
FROM users;


SELECT
    id,
    medicine_name,
    generic_name,
    category,
    description
FROM medicines;


SELECT
    id,
    hospital_name,
    email,
    phone,
    address,
    city,
    specialty,
    status
FROM hospitals
WHERE status = 'approved';


SELECT
    id,
    pharmacy_name,
    email,
    phone,
    address,
    city,
    license_number,
    status
FROM pharmacies
WHERE status = 'approved';


SELECT
    id,
    hospital_id,
    doctor_name,
    specialization,
    phone,
    email
FROM doctors;


SELECT
    id,
    doctor_id,
    day_of_week,
    start_time,
    end_time,
    is_available
FROM doctor_schedules;


SELECT
    id,
    user_id,
    hospital_id,
    patient_name,
    patient_phone,
    doctor_name,
    appointment_date,
    appointment_time,
    status,
    created_at
FROM appointments;


SELECT
    id,
    user_id,
    pharmacy_id,
    medicine_id,
    quantity,
    status,
    created_at
FROM medicine_requests;


SELECT
    id,
    user_id,
    title,
    message,
    type,
    is_read,
    created_at
FROM user_notifications;
-- =====================================================
-- 8. NOTIFICATION TABLES + PHARMACY-MEDICINE PRICING
-- (added for Railway completeness � matches the live DB)
-- =====================================================

CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    admin_id INT(11) NOT NULL DEFAULT 1,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    related_id INT(11) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hospital_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    hospital_id INT(11) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    related_id INT(11) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pharmacy_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    pharmacy_id INT(11) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    related_id INT(11) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pharmacy_medicines (
    id INT(11) NOT NULL AUTO_INCREMENT,
    pharmacy_id INT(11) NOT NULL,
    medicine_id INT(11) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    stock INT(11) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pharm_med (pharmacy_id, medicine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
