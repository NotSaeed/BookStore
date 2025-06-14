-- BookStore Database Schema
-- Created: 2024
-- Description: Complete database structure for BookStore Seller Hub

-- Set charset and collation
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS bookstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookstore;

-- Seller Users Table
CREATE TABLE seller_users (
    seller_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_name VARCHAR(255) NOT NULL,
    seller_email VARCHAR(255) UNIQUE NOT NULL,
    seller_password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    bio TEXT,
    website VARCHAR(255),
    location VARCHAR(100),
    business_name VARCHAR(255),
    business_type VARCHAR(50),
    business_address TEXT,
    business_phone VARCHAR(20),
    business_email VARCHAR(255),
    tax_id VARCHAR(50),
    profile_photo VARCHAR(255),
    dark_mode TINYINT(1) DEFAULT 0,
    compact_view TINYINT(1) DEFAULT 0,
    email_notifications TINYINT(1) DEFAULT 1,
    language VARCHAR(5) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Asia/Kuala_Lumpur',
    currency VARCHAR(3) DEFAULT 'MYR',
    notify_orders TINYINT(1) DEFAULT 1,
    notify_messages TINYINT(1) DEFAULT 1,
    notify_reviews TINYINT(1) DEFAULT 1,
    notify_system TINYINT(1) DEFAULT 1,
    notify_marketing TINYINT(1) DEFAULT 0,
    sms_notifications TINYINT(1) DEFAULT 0,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(32),
    password_changed_at TIMESTAMP NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seller_email (seller_email),
    INDEX idx_registration_date (registration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Books Table
CREATE TABLE seller_books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20),
    category VARCHAR(100),
    genre VARCHAR(100),
    condition_type ENUM('new', 'like_new', 'very_good', 'good', 'acceptable') DEFAULT 'new',
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    stock_quantity INT DEFAULT 1,
    description TEXT,
    cover_image VARCHAR(255),
    is_public TINYINT(1) DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    view_count INT DEFAULT 0,
    sales_count INT DEFAULT 0,
    publisher VARCHAR(255),
    publication_year YEAR,
    language VARCHAR(50) DEFAULT 'English',
    pages INT,
    weight DECIMAL(8,2),
    dimensions VARCHAR(100),
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category),
    INDEX idx_genre (genre),
    INDEX idx_price (price),
    INDEX idx_public (is_public),
    INDEX idx_featured (is_featured),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Log Table
CREATE TABLE seller_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    book_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE SET NULL,
    INDEX idx_seller_id (seller_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_book_id (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews Table
CREATE TABLE seller_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    customer_id INT,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    rating TINYINT(1) CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    is_approved TINYINT(1) DEFAULT 1,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE CASCADE,
    INDEX idx_book_id (book_id),
    INDEX idx_rating (rating),
    INDEX idx_approved (is_approved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders Table
CREATE TABLE seller_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    book_id INT NOT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    order_status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    shipping_address TEXT,
    tracking_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_book_id (book_id),
    INDEX idx_status (order_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database Audit Log Table
CREATE TABLE db_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    operation VARCHAR(255) NOT NULL,
    table_affected VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE SET NULL,
    INDEX idx_seller_id (seller_id),
    INDEX idx_operation (operation),
    INDEX idx_table_affected (table_affected),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Logs Table
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_email (email),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Tokens Table
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions Table (for better session management)
CREATE TABLE seller_sessions (
    id VARCHAR(128) PRIMARY KEY,
    seller_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE seller_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_type (type),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Book Images Table (for multiple images per book)
CREATE TABLE book_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_type ENUM('cover', 'gallery', 'thumbnail') DEFAULT 'gallery',
    sort_order INT DEFAULT 0,
    alt_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE CASCADE,
    INDEX idx_book_id (book_id),
    INDEX idx_type (image_type),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Create default admin user (password: admin123)
INSERT INTO seller_users (seller_name, seller_email, seller_password, business_name) VALUES 
('Admin User', 'admin@bookstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'BookStore Admin');

-- Sample data for testing
INSERT INTO seller_users (seller_name, seller_email, seller_password, business_name, phone, location) VALUES 
('John Smith', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Smith Books Store', '+60123456789', 'Kuala Lumpur'),
('Sarah Johnson', 'sarah@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Literary Corner', '+60987654321', 'Penang');
