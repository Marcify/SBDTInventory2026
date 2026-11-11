CREATE DATABASE IF NOT EXISTS sbdt_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sbdt_inventory;

DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS item_returns;
DROP TABLE IF EXISTS borrowing_details;
DROP TABLE IF EXISTS borrowings;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','dancer') NOT NULL DEFAULT 'dancer',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Items table
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    total_stock INT NOT NULL DEFAULT 0,
    available_stock INT NOT NULL DEFAULT 0,
    damaged_stock INT NOT NULL DEFAULT 0,
    borrowed_stock INT NOT NULL DEFAULT 0,
    lost_stock INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT
);


-- Borrowings table

CREATE TABLE borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('borrowed','partial','returned') NOT NULL DEFAULT 'borrowed',
    borrowed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_borrowings_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Borrowing Details table
CREATE TABLE borrowing_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrowing_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_borrowed INT NOT NULL,
    quantity_returned INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_bd_borrowing FOREIGN KEY (borrowing_id) REFERENCES borrowings(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_bd_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_bd_qty CHECK (quantity_returned BETWEEN 0 AND quantity_borrowed)
);

-- Item Returns table
CREATE TABLE item_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    borrowing_detail_id INT NOT NULL,
    condition_enum ENUM('good','damaged','lost') NOT NULL,
    quantity INT NOT NULL,
    returned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_returns_bd FOREIGN KEY (borrowing_detail_id) REFERENCES borrowing_details(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_returns_qty CHECK (quantity > 0)
);

-- Stock Movements table
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_item FOREIGN KEY (item_id) REFERENCES items(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- INDEXES
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_borrowings_user ON borrowings(user_id);
CREATE INDEX idx_borrowings_status ON borrowings(status);
CREATE INDEX idx_bd_borrowing ON borrowing_details(borrowing_id);
CREATE INDEX idx_bd_item ON borrowing_details(item_id);
CREATE INDEX idx_returns_bd ON item_returns(borrowing_detail_id);
CREATE INDEX idx_sm_item ON stock_movements(item_id);
CREATE INDEX idx_sm_created ON stock_movements(created_at);