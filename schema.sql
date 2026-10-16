CREATE DATABASE IF NOT EXISTS hotel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hotel_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('admin', 'receptionist') NOT NULL DEFAULT 'receptionist',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_number VARCHAR(20) NOT NULL,
    room_type ENUM('single', 'double', 'twin', 'deluxe', 'suite', 'family') NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 1,
    nightly_rate DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_room_number (room_number)
) ENGINE=InnoDB;

ALTER TABLE rooms
    MODIFY COLUMN room_type ENUM('single', 'double', 'twin', 'deluxe', 'suite', 'family') NOT NULL,
    MODIFY COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS guests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_guests_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guest_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reservations_guest (guest_id),
    KEY idx_reservations_room_dates (room_id, check_in, check_out),
    KEY idx_reservations_user (user_id),
    KEY idx_reservations_status (status),
    CONSTRAINT fk_reservations_guest FOREIGN KEY (guest_id)
        REFERENCES guests (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_room FOREIGN KEY (room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO users (username, password_hash, full_name, role, status)
VALUES (
    'receptionist',
    '$2y$10$otj98TJ.xwz/nfny4ekcF.hwpr0zS..fCGn06mnYPFBv5v.CJXxEm',
    'Front Desk Receptionist',
    'receptionist',
    'active'
)
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO rooms (room_number, room_type, capacity, nightly_rate, status)
VALUES
    ('101', 'single', 1, 85.00, 'available'),
    ('102', 'double', 2, 125.00, 'available'),
    ('103', 'twin', 2, 125.00, 'available'),
    ('201', 'suite', 2, 220.00, 'available'),
    ('202', 'family', 4, 185.00, 'available')
ON DUPLICATE KEY UPDATE room_number = VALUES(room_number);
