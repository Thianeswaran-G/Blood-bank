-- Create database
CREATE DATABASE IF NOT EXISTS blood_bank;
USE blood_bank;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    user_type ENUM('donor', 'receiver') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create donors table
CREATE TABLE IF NOT EXISTS donors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    last_donation DATE,
    health_conditions TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create receivers table
CREATE TABLE IF NOT EXISTS receivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    emergency_contact VARCHAR(100) NOT NULL,
    emergency_phone VARCHAR(20) NOT NULL,
    medical_history TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create blood_requests table
CREATE TABLE IF NOT EXISTS blood_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receiver_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units INT NOT NULL,
    urgency ENUM('normal', 'urgent', 'emergency') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create request_acceptances table
CREATE TABLE IF NOT EXISTS request_acceptances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create request_declines table
CREATE TABLE IF NOT EXISTS request_declines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create donations table
CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE
);

-- Create sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    request_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE
);

-- Create function to calculate distance between two points
DELIMITER //
CREATE FUNCTION calculate_distance(
    lat1 DECIMAL(10, 8),
    lon1 DECIMAL(11, 8),
    lat2 DECIMAL(10, 8),
    lon2 DECIMAL(11, 8)
) RETURNS DECIMAL(10, 2)
DETERMINISTIC
BEGIN
    DECLARE earth_radius DECIMAL(10, 2);
    DECLARE d_lat DECIMAL(10, 8);
    DECLARE d_lon DECIMAL(11, 8);
    DECLARE a DECIMAL(10, 8);
    DECLARE c DECIMAL(10, 8);
    DECLARE distance DECIMAL(10, 2);

    SET earth_radius = 6371; -- Earth's radius in kilometers

    SET lat1 = RADIANS(lat1);
    SET lon1 = RADIANS(lon1);
    SET lat2 = RADIANS(lat2);
    SET lon2 = RADIANS(lon2);

    SET d_lat = lat2 - lat1;
    SET d_lon = lon2 - lon1;

    SET a = SIN(d_lat/2) * SIN(d_lat/2) +
            COS(lat1) * COS(lat2) *
            SIN(d_lon/2) * SIN(d_lon/2);

    SET c = 2 * ATAN2(SQRT(a), SQRT(1-a));
    SET distance = earth_radius * c;

    RETURN ROUND(distance, 2);
END //
DELIMITER ; 