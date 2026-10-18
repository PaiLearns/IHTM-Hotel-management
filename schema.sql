-- Current IHTM schema. Safe to import again: existing table data is retained,
-- and the starter account/rooms are inserted only when their unique keys are absent.
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
    status ENUM('available', 'occupied', 'dirty', 'maintenance') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_room_number (room_number)
) ENGINE=InnoDB;

ALTER TABLE rooms
    MODIFY COLUMN room_type ENUM('single', 'double', 'twin', 'deluxe', 'suite', 'family') NOT NULL,
    MODIFY COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 1,
    MODIFY COLUMN status ENUM('available', 'occupied', 'dirty', 'maintenance') NOT NULL DEFAULT 'available';

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

CREATE TABLE IF NOT EXISTS room_transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    from_room_id INT UNSIGNED NOT NULL,
    from_room_number VARCHAR(20) NOT NULL,
    to_room_id INT UNSIGNED NOT NULL,
    to_room_number VARCHAR(20) NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    transferred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_room_transfers_reservation (reservation_id, transferred_at),
    KEY idx_room_transfers_from_room (from_room_id),
    KEY idx_room_transfers_to_room (to_room_id),
    CONSTRAINT fk_room_transfers_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_room_transfers_from_room FOREIGN KEY (from_room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_room_transfers_to_room FOREIGN KEY (to_room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_room_transfers_user FOREIGN KEY (changed_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_categories_name (name),
    KEY idx_inventory_categories_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    sku VARCHAR(64) NULL,
    name VARCHAR(120) NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'piece',
    reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_items_sku (sku),
    KEY idx_inventory_items_category_active (category_id, is_active),
    KEY idx_inventory_items_name (name),
    CONSTRAINT fk_inventory_items_category FOREIGN KEY (category_id)
        REFERENCES inventory_categories (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_stock (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NULL DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    item_condition ENUM('new', 'good', 'fair', 'damaged', 'needs_replacement') NOT NULL DEFAULT 'good',
    status ENUM('available', 'in_use', 'needs_attention', 'missing', 'unavailable') NOT NULL DEFAULT 'available',
    notes TEXT NULL,
    purchase_date DATE NULL,
    last_checked_at DATETIME NULL,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_stock_item_location (item_id, room_id),
    KEY idx_inventory_stock_room_status (room_id, status),
    KEY idx_inventory_stock_attention (status, item_condition),
    CONSTRAINT fk_inventory_stock_item FOREIGN KEY (item_id)
        REFERENCES inventory_items (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_stock_room FOREIGN KEY (room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_id INT UNSIGNED NOT NULL,
    action ENUM('received', 'assigned', 'transferred', 'returned_to_storage', 'quantity_adjustment', 'condition_update', 'status_update', 'archived') NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    from_room_id INT UNSIGNED NULL DEFAULT NULL,
    from_room_number VARCHAR(20) NULL,
    to_room_id INT UNSIGNED NULL DEFAULT NULL,
    to_room_number VARCHAR(20) NULL,
    from_condition ENUM('new', 'good', 'fair', 'damaged', 'needs_replacement') NULL,
    to_condition ENUM('new', 'good', 'fair', 'damaged', 'needs_replacement') NULL,
    from_status ENUM('available', 'in_use', 'needs_attention', 'missing', 'unavailable') NULL,
    to_status ENUM('available', 'in_use', 'needs_attention', 'missing', 'unavailable') NULL,
    notes VARCHAR(500) NULL,
    changed_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_movements_item_date (item_id, created_at),
    KEY idx_inventory_movements_from_room (from_room_id),
    KEY idx_inventory_movements_to_room (to_room_id),
    KEY idx_inventory_movements_user (changed_by),
    CONSTRAINT fk_inventory_movements_item FOREIGN KEY (item_id)
        REFERENCES inventory_items (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movements_from_room FOREIGN KEY (from_room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_movements_to_room FOREIGN KEY (to_room_id)
        REFERENCES rooms (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventory_movements_user FOREIGN KEY (changed_by)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO inventory_categories (name, description)
VALUES
    ('Bedding', 'Sheets, pillowcases, duvet covers, blankets, and comforters'),
    ('Towels', 'Bath, hand, and face towels'),
    ('Curtains', 'Curtains and drapes'),
    ('Pillows', 'Pillows and pillow protectors'),
    ('Mattresses', 'Mattresses and mattress protectors'),
    ('Amenities', 'Guest amenities and minibar items'),
    ('Cleaning supplies', 'Housekeeping and cleaning supplies')
ON DUPLICATE KEY UPDATE name = VALUES(name);

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
