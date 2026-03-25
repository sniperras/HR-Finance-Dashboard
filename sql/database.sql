-- Create database
CREATE DATABASE IF NOT EXISTS hr_finance_dashboard;
USE hr_finance_dashboard;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role ENUM('hr', 'director') DEFAULT 'hr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Master Data Table (Performance Data)
CREATE TABLE master_performance_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_month DATE NOT NULL,
    department VARCHAR(20) NOT NULL,
    indicator_name VARCHAR(100) NOT NULL,
    actual_value DECIMAL(10,2),
    target_value DECIMAL(10,2),
    percentage_achievement DECIMAL(10,2),
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_by INT,
    verified_at TIMESTAMP NULL,
    verified_by_2 INT,
    verified_at_2 TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    FOREIGN KEY (verified_by_2) REFERENCES users(id)
);

-- Data History/Audit Table
CREATE TABLE data_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT,
    action ENUM('insert', 'update', 'verify', 'reject'),
    old_data TEXT,
    new_data TEXT,
    performed_by INT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- Insert default users
INSERT INTO users (username, password, full_name, role) VALUES 
('hr_user', '$2y$10$YourHashedPasswordHere', 'HR Manager', 'hr'),
('director_user', '$2y$10$YourHashedPasswordHere', 'Director', 'director');

-- Sample data for December 2025
INSERT INTO master_performance_data (data_month, department, indicator_name, actual_value, target_value, percentage_achievement, remarks, created_by, verification_status) VALUES
('2025-12-01', 'BMT', 'Team Leaders Clock-in Data', 92.00, 100.00, 92.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'LMT', 'Team Leaders Clock-in Data', 85.00, 100.00, 85.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'CMT', 'Team Leaders Clock-in Data', 89.00, 100.00, 89.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'EMT', 'Team Leaders Clock-in Data', 89.00, 100.00, 89.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'AEP', 'Team Leaders Clock-in Data', 60.00, 100.00, 60.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'QA', 'Team Leaders Clock-in Data', 86.00, 100.00, 86.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'MRO HR', 'Team Leaders Clock-in Data', 86.00, 100.00, 86.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'MSM', 'Team Leaders Clock-in Data', 86.00, 100.00, 86.00, 'Team Leaders clock in data December', 1, 'verified'),
('2025-12-01', 'BMT', 'Lost Time Justification', 85.00, 100.00, 85.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'LMT', 'Lost Time Justification', 76.00, 100.00, 76.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'CMT', 'Lost Time Justification', 8.50, 100.00, 8.50, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'EMT', 'Lost Time Justification', 60.00, 100.00, 60.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'AEP', 'Lost Time Justification', 85.00, 100.00, 85.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'MSM', 'Lost Time Justification', 90.00, 100.00, 90.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'QA', 'Lost Time Justification', 90.00, 100.00, 90.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'MRO HR', 'Lost Time Justification', 100.00, 100.00, 100.00, 'Dec 1-25, 2025', 1, 'verified'),
('2025-12-01', 'BMT', 'Productivity', 93.90, 100.00, 93.90, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'LMT', 'Productivity', 95.30, 100.00, 95.30, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'CMT', 'Productivity', 107.00, 100.00, 107.00, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'EMT', 'Productivity', 92.00, 100.00, 92.00, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'AEP', 'Productivity', 96.00, 100.00, 96.00, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'MSM', 'Productivity', 96.20, 100.00, 96.20, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'QA', 'Productivity', 85.60, 100.00, 85.60, 'Productive HR and Total HR', 1, 'verified'),
('2025-12-01', 'MRO HR', 'Productivity', 94.50, 100.00, 94.50, 'Productive HR and Total HR', 1, 'verified');