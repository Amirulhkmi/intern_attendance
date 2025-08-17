-- Create database
CREATE DATABASE IF NOT EXISTS intern_attendance_db;
USE intern_attendance_db;

-- Users table (supervisor + interns)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('supervisor', 'intern') NOT NULL DEFAULT 'intern',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    clock_in DATETIME DEFAULT NULL,
    clock_out DATETIME DEFAULT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Sample accounts
INSERT INTO users (username, password, full_name, role)
VALUES
('supervisor1', MD5('password123'), 'Supervisor Account', 'supervisor'),
('intern1', MD5('password123'), 'Intern One', 'intern'),
('intern2', MD5('password123'), 'Intern Two', 'intern');
