-- =========================================
-- DROP TABLES
-- =========================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS 
    nps_report_rows,
    nps_reports,
    nps_admin_logs,
    nps_product_views,
    nps_ratings,
    nps_comments,
    nps_product_images,
    nps_order_items,
    nps_orders,
    nps_products,
    nps_categories,
    nps_users,
    nps_roles;

SET FOREIGN_KEY_CHECKS = 1;
DROP PROCEDURE IF EXISTS sp_orders_report;
DROP PROCEDURE IF EXISTS sp_popular_products_report;
DROP PROCEDURE IF EXISTS sp_seller_product_performance;

DROP TRIGGER IF EXISTS trg_before_delete_comment_admin_log;

-- =========================================
-- CREATE TABLES
-- =========================================

CREATE TABLE nps_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE nps_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    phone_number VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES nps_roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_users_status
        CHECK (status IN ('active', 'inactive', 'blocked'))
);

CREATE TABLE nps_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
);

CREATE TABLE nps_products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    short_description VARCHAR(255) NOT NULL,
    full_description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    publish_status VARCHAR(20) NOT NULL DEFAULT 'published',
    brand VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_products_seller
        FOREIGN KEY (seller_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES nps_categories(category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_products_price CHECK (price >= 0),
    CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0),
    CONSTRAINT chk_products_publish_status
        CHECK (publish_status IN ('draft', 'published', 'hidden'))
);

CREATE TABLE nps_product_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES nps_products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE nps_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_product
        FOREIGN KEY (product_id) REFERENCES nps_products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE nps_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating_value INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ratings_user
        FOREIGN KEY (user_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_ratings_product
        FOREIGN KEY (product_id) REFERENCES nps_products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_ratings_user_product UNIQUE (user_id, product_id),
    CONSTRAINT chk_ratings_value CHECK (rating_value BETWEEN 1 AND 5)
);

CREATE TABLE nps_product_views (
    view_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    view_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_views_product
        FOREIGN KEY (product_id) REFERENCES nps_products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_product_views_user
        FOREIGN KEY (user_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);
CREATE TABLE nps_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_address VARCHAR(255) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    
    CONSTRAINT fk_orders_buyer
        FOREIGN KEY (buyer_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_orders_total_amount
        CHECK (total_amount >= 0),

    CONSTRAINT chk_orders_status
        CHECK (order_status IN ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled'))
);

CREATE TABLE nps_order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES nps_orders(order_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES nps_products(product_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_order_items_quantity
        CHECK (quantity > 0),

    CONSTRAINT chk_order_items_unit_price
        CHECK (unit_price >= 0),

    CONSTRAINT chk_order_items_subtotal
        CHECK (subtotal >= 0)
);

CREATE TABLE nps_admin_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_id INT NOT NULL,
    target_table VARCHAR(100) NOT NULL,
    action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_admin
        FOREIGN KEY (admin_id) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE nps_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    report_title VARCHAR(150) NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    procedure_name VARCHAR(100) NULL,
    filters_json LONGTEXT NULL,
    export_file_path VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'generated',

    CONSTRAINT fk_reports_created_by
        FOREIGN KEY (created_by) REFERENCES nps_users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_reports_status
        CHECK (status IN ('generated', 'exported', 'archived'))
);

CREATE TABLE nps_report_rows (
    row_id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    row_no INT NOT NULL,
    row_data LONGTEXT NOT NULL,

    CONSTRAINT fk_report_rows_report
        FOREIGN KEY (report_id) REFERENCES nps_reports(report_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- =========================================
-- INDEXES
-- =========================================

CREATE INDEX idx_users_role_id ON nps_users(role_id);
CREATE INDEX idx_products_seller_id ON nps_products(seller_id);
CREATE INDEX idx_products_category_id ON nps_products(category_id);
CREATE INDEX idx_products_created_at ON nps_products(created_at);
CREATE INDEX idx_products_publish_status ON nps_products(publish_status);
CREATE INDEX idx_comments_product_id ON nps_comments(product_id);
CREATE INDEX idx_comments_user_id ON nps_comments(user_id);
CREATE INDEX idx_ratings_product_id ON nps_ratings(product_id);
CREATE INDEX idx_ratings_user_id ON nps_ratings(user_id);
CREATE INDEX idx_product_views_product_id ON nps_product_views(product_id);
CREATE INDEX idx_product_views_view_date ON nps_product_views(view_date);
CREATE INDEX idx_orders_buyer_id ON nps_orders(buyer_id);
CREATE INDEX idx_orders_order_date ON nps_orders(order_date);
CREATE INDEX idx_orders_status ON nps_orders(order_status);
CREATE INDEX idx_order_items_order_id ON nps_order_items(order_id);
CREATE INDEX idx_order_items_product_id ON nps_order_items(product_id);

CREATE INDEX idx_reports_type ON nps_reports(report_type);
CREATE INDEX idx_reports_created_by ON nps_reports(created_by);
CREATE INDEX idx_reports_created_at ON nps_reports(created_at);
CREATE INDEX idx_report_rows_report_id ON nps_report_rows(report_id);
CREATE INDEX idx_report_rows_row_no ON nps_report_rows(row_no);

CREATE FULLTEXT INDEX ftx_products_search
ON nps_products(product_name, short_description, full_description);

-- =========================================
-- INSERT ROLES
-- =========================================

INSERT INTO nps_roles (role_name) VALUES
('Admin'),
('Seller'),
('Buyer');

-- =========================================
-- INSERT USERS
-- Password note:
-- These are placeholder hashes for demo/testing.
-- Replace later using PHP password_hash().
-- =========================================

INSERT INTO nps_users
(role_id, full_name, email, password_hash, created_at, status, phone_number, address)
VALUES
(1, 'System Admin', 'admin@nextpick.com', '$2y$10$lM8lGyvx0JJN1bZKkVcAee56ScLK9PE2LYMC58yxGgZxoRn1965Dm', '2026-04-01 09:00:00', 'active', '+97333000001', 'Manama, Bahrain'),
(2, 'Ahmed Electronics', 'ahmed@nextpick.com', '$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS', '2026-04-01 10:00:00', 'active', '+97333000002', 'Muharraq, Bahrain'),
(2, 'Sara Tech Store', 'sara@nextpick.com', '$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS', '2026-04-01 10:15:00', 'active', '+97333000003', 'Riffa, Bahrain'),
(2, 'Omar Digital Hub', 'omar@nextpick.com', '$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS', '2026-04-01 10:30:00', 'active', '+97333000004', 'Isa Town, Bahrain'),
(3, 'Mohammed Alhalal', 'mohammed@nextpick.com', '$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu', '2026-04-02 08:20:00', 'active', '+97333000005', 'Sitra, Bahrain'),
(3, 'Fatima Ali', 'fatima@nextpick.com', '$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu', '2026-04-02 08:35:00', 'active', '+97333000006', 'Budaiya, Bahrain'),
(3, 'Yousef Hassan', 'yousef@nextpick.com', '$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu', '2026-04-02 08:50:00', 'active', '+97333000007', 'Hamad Town, Bahrain'),
(3, 'Noor Khalid', 'noor@nextpick.com', '$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu', '2026-04-02 09:10:00', 'active', '+97333000008', 'Juffair, Bahrain');

-- =========================================
-- INSERT CATEGORIES
-- =========================================

INSERT INTO nps_categories (category_name, description) VALUES
('Laptops', 'Portable computers for study, work, and gaming'),
('Smartphones', 'Modern phones with advanced features'),
('Headphones', 'Wireless and wired headphones and earbuds'),
('Smartwatches', 'Wearable smart devices and fitness trackers'),
('Cameras', 'Digital cameras and content creation gear'),
('Speakers', 'Portable and home audio speakers');

-- =========================================
-- INSERT PRODUCTS (20 records)
-- At least 5 in one category included: Laptops has 5
-- =========================================

INSERT INTO nps_products
(seller_id, category_id, product_name, short_description, full_description, price, stock_quantity, publish_status, brand, created_at, updated_at)
VALUES
-- Laptops (5)
(2, 1, 'Apple MacBook Air M2 13-inch', 'Lightweight laptop with Apple M2 chip', 'The Apple MacBook Air M2 13-inch offers fast performance, long battery life, a Retina display, and a slim lightweight design ideal for students and professionals.', 449.90, 12, 'published', 'Apple', '2026-04-03 10:00:00', '2026-04-03 10:00:00'),
(2, 1, 'HP Pavilion 15', 'Everyday laptop with Full HD display', 'HP Pavilion 15 is suitable for office work, online classes, and entertainment with a modern design, fast SSD storage, and reliable battery life.', 279.90, 18, 'published', 'HP', '2026-04-03 10:15:00', '2026-04-03 10:15:00'),
(3, 1, 'Lenovo IdeaPad Slim 3', 'Affordable laptop for daily tasks', 'Lenovo IdeaPad Slim 3 provides good value with a clean display, comfortable keyboard, and dependable performance for browsing, documents, and media.', 239.50, 15, 'published', 'Lenovo', '2026-04-03 10:30:00', '2026-04-03 10:30:00'),
(3, 1, 'Dell Inspiron 14', 'Compact laptop for productivity', 'Dell Inspiron 14 delivers solid multitasking performance, portable size, and practical connectivity for work and university use.', 319.00, 10, 'published', 'Dell', '2026-04-03 10:45:00', '2026-04-03 10:45:00'),
(4, 1, 'ASUS TUF Gaming A15', 'Gaming laptop with dedicated graphics', 'ASUS TUF Gaming A15 is built for gaming and heavier workloads with a strong processor, dedicated GPU, durable body, and high refresh-rate display.', 529.00, 8, 'published', 'ASUS', '2026-04-03 11:00:00', '2026-04-03 11:00:00'),

-- Smartphones (4)
(2, 2, 'iPhone 15 128GB', 'Premium smartphone with powerful camera', 'The iPhone 15 128GB features excellent camera quality, smooth performance, vibrant display, and a premium design suitable for everyday users.', 389.90, 20, 'published', 'Apple', '2026-04-04 09:00:00', '2026-04-04 09:00:00'),
(2, 2, 'Samsung Galaxy S24', 'Flagship Android phone with AI features', 'Samsung Galaxy S24 provides a bright display, high-end camera system, advanced software features, and powerful daily performance.', 365.00, 14, 'published', 'Samsung', '2026-04-04 09:15:00', '2026-04-04 09:15:00'),
(3, 2, 'Xiaomi Redmi Note 13', 'Mid-range phone with large battery', 'Xiaomi Redmi Note 13 combines value and practicality with a large battery, smooth display, and capable camera setup.', 119.90, 25, 'published', 'Xiaomi', '2026-04-04 09:30:00', '2026-04-04 09:30:00'),
(4, 2, 'Google Pixel 8', 'Smartphone known for camera and software', 'Google Pixel 8 offers clean Android software, smart AI tools, and impressive photography performance in a comfortable design.', 329.00, 9, 'published', 'Google', '2026-04-04 09:45:00', '2026-04-04 09:45:00'),

-- Headphones (3)
(3, 3, 'Sony WH-1000XM5', 'Noise-cancelling over-ear headphones', 'Sony WH-1000XM5 delivers premium active noise cancellation, rich audio quality, long battery life, and a comfortable fit for travel and work.', 149.90, 16, 'published', 'Sony', '2026-04-05 11:00:00', '2026-04-05 11:00:00'),
(4, 3, 'Apple AirPods Pro 2', 'Wireless earbuds with active noise cancellation', 'AirPods Pro 2 offer seamless Apple integration, improved sound, adaptive transparency, and compact wireless convenience.', 94.50, 22, 'published', 'Apple', '2026-04-05 11:20:00', '2026-04-05 11:20:00'),
(2, 3, 'JBL Tune 760NC', 'Affordable wireless noise-cancelling headphones', 'JBL Tune 760NC gives users wireless freedom, strong bass, and active noise cancelling at a budget-friendly price point.', 49.90, 30, 'published', 'JBL', '2026-04-05 11:35:00', '2026-04-05 11:35:00'),

-- Smartwatches (3)
(4, 4, 'Apple Watch Series 9', 'Smartwatch with health and fitness tracking', 'Apple Watch Series 9 provides activity tracking, health sensors, notifications, and a premium smartwatch experience.', 179.90, 11, 'published', 'Apple', '2026-04-06 10:10:00', '2026-04-06 10:10:00'),
(3, 4, 'Samsung Galaxy Watch 6', 'Android smartwatch with sleek design', 'Galaxy Watch 6 offers sleep tracking, fitness monitoring, and easy smartphone connectivity in a modern wearable design.', 139.00, 13, 'published', 'Samsung', '2026-04-06 10:25:00', '2026-04-06 10:25:00'),
(2, 4, 'Huawei Watch Fit 3', 'Slim smartwatch with long battery life', 'Huawei Watch Fit 3 is a lightweight wearable with strong battery life, workout modes, and health tracking features.', 79.90, 19, 'published', 'Huawei', '2026-04-06 10:40:00', '2026-04-06 10:40:00'),

-- Cameras (2)
(4, 5, 'Canon EOS R50', 'Mirrorless camera for creators', 'Canon EOS R50 is designed for beginners and content creators with strong autofocus, compact body, and excellent image quality.', 299.00, 7, 'published', 'Canon', '2026-04-07 12:00:00', '2026-04-07 12:00:00'),
(3, 5, 'Sony ZV-E10', 'Vlogging camera with interchangeable lens support', 'Sony ZV-E10 is popular for video creators thanks to its clear video quality, microphone support, and creator-focused features.', 339.00, 6, 'published', 'Sony', '2026-04-07 12:20:00', '2026-04-07 12:20:00'),

-- Speakers (3)
(2, 6, 'JBL Flip 6', 'Portable Bluetooth speaker with strong bass', 'JBL Flip 6 is a compact Bluetooth speaker designed for travel, outdoor use, and everyday music listening.', 54.90, 24, 'published', 'JBL', '2026-04-08 09:10:00', '2026-04-08 09:10:00'),
(3, 6, 'Sony SRS-XB100', 'Small portable speaker with extra bass', 'Sony SRS-XB100 is easy to carry and offers clear sound with enhanced bass, making it ideal for daily portable use.', 29.90, 28, 'published', 'Sony', '2026-04-08 09:25:00', '2026-04-08 09:25:00'),
(4, 6, 'Amazon Echo Dot 5', 'Smart speaker with voice assistant support', 'Echo Dot 5 provides voice control, smart home connectivity, and compact room-filling audio for modern homes.', 24.90, 35, 'published', 'Amazon', '2026-04-08 09:40:00', '2026-04-08 09:40:00');

-- =========================================
-- INSERT PRODUCT IMAGES
-- one primary image for each product
-- =========================================

INSERT INTO nps_product_images (product_id, image_path, is_primary) VALUES
(1, 'uploads/products/macbook-air-m2.jpg', TRUE),
(2, 'uploads/products/hp-pavilion-15.jpg', TRUE),
(3, 'uploads/products/lenovo-ideapad-slim3.jpg', TRUE),
(4, 'uploads/products/dell-inspiron-14.jpg', TRUE),
(5, 'uploads/products/asus-tuf-a15.jpg', TRUE),
(6, 'uploads/products/iphone-15.jpg', TRUE),
(7, 'uploads/products/galaxy-s24.jpg', TRUE),
(8, 'uploads/products/redmi-note-13.jpg', TRUE),
(9, 'uploads/products/pixel-8.jpg', TRUE),
(10, 'uploads/products/sony-wh1000xm5.jpg', TRUE),
(11, 'uploads/products/airpods-pro-2.jpg', TRUE),
(12, 'uploads/products/jbl-tune-760nc.jpg', TRUE),
(13, 'uploads/products/apple-watch-series9.jpg', TRUE),
(14, 'uploads/products/galaxy-watch-6.jpg', TRUE),
(15, 'uploads/products/huawei-watch-fit-3.jpg', TRUE),
(16, 'uploads/products/canon-eos-r50.jpg', TRUE),
(17, 'uploads/products/sony-zv-e10.jpg', TRUE),
(18, 'uploads/products/jbl-flip-6.jpg', TRUE),
(19, 'uploads/products/sony-srs-xb100.jpg', TRUE),
(20, 'uploads/products/echo-dot-5.jpg', TRUE);

-- =========================================
-- INSERT COMMENTS
-- buyers are user_id 5,6,7,8
-- =========================================

INSERT INTO nps_comments (user_id, product_id, comment_text, created_at, updated_at) VALUES
(5, 1, 'Very elegant laptop and the battery life is excellent.', '2026-04-09 10:00:00', NULL),
(6, 2, 'Good value for university work and normal daily use.', '2026-04-09 10:12:00', NULL),
(7, 6, 'The camera quality is very good and the phone feels premium.', '2026-04-09 10:25:00', NULL),
(8, 10, 'Amazing noise cancellation and comfortable for long sessions.', '2026-04-09 10:40:00', NULL),
(5, 13, 'Very smooth smartwatch and useful for notifications.', '2026-04-09 11:00:00', NULL),
(6, 18, 'Nice speaker for the size and easy to carry around.', '2026-04-09 11:10:00', NULL),
(7, 17, 'Excellent camera for content creation and beginner vlogging.', '2026-04-09 11:25:00', NULL),
(8, 7, 'Fast phone with a bright screen and nice performance.', '2026-04-09 11:40:00', NULL),
(5, 5, 'Strong gaming performance and solid cooling.', '2026-04-09 11:55:00', NULL),
(6, 11, 'Great sound quality and easy pairing with Apple devices.', '2026-04-09 12:10:00', NULL);

-- =========================================
-- INSERT RATINGS
-- one rating per user per product
-- =========================================

INSERT INTO nps_ratings (user_id, product_id, rating_value, created_at) VALUES
(5, 1, 5, '2026-04-09 13:00:00'),
(6, 2, 4, '2026-04-09 13:05:00'),
(7, 6, 5, '2026-04-09 13:10:00'),
(8, 10, 5, '2026-04-09 13:15:00'),
(5, 13, 4, '2026-04-09 13:20:00'),
(6, 18, 4, '2026-04-09 13:25:00'),
(7, 17, 5, '2026-04-09 13:30:00'),
(8, 7, 4, '2026-04-09 13:35:00'),
(5, 5, 5, '2026-04-09 13:40:00'),
(6, 11, 5, '2026-04-09 13:45:00'),
(7, 3, 4, '2026-04-09 13:50:00'),
(8, 14, 4, '2026-04-09 13:55:00');

-- =========================================
-- INSERT PRODUCT VIEWS
-- includes buyer views and guest views (NULL user_id)
-- =========================================

INSERT INTO nps_product_views (product_id, user_id, view_date) VALUES
(1, 5, '2026-04-10 09:00:00'),
(1, NULL, '2026-04-10 09:05:00'),
(1, 6, '2026-04-10 09:10:00'),
(2, 6, '2026-04-10 09:15:00'),
(2, NULL, '2026-04-10 09:18:00'),
(3, 7, '2026-04-10 09:20:00'),
(4, NULL, '2026-04-10 09:23:00'),
(5, 5, '2026-04-10 09:30:00'),
(5, 8, '2026-04-10 09:32:00'),
(6, 7, '2026-04-10 09:35:00'),
(6, NULL, '2026-04-10 09:38:00'),
(7, 8, '2026-04-10 09:40:00'),
(7, NULL, '2026-04-10 09:42:00'),
(8, 5, '2026-04-10 09:44:00'),
(9, NULL, '2026-04-10 09:46:00'),
(10, 8, '2026-04-10 09:48:00'),
(10, 6, '2026-04-10 09:50:00'),
(11, 6, '2026-04-10 09:52:00'),
(12, NULL, '2026-04-10 09:54:00'),
(13, 5, '2026-04-10 09:56:00'),
(14, 8, '2026-04-10 10:00:00'),
(15, NULL, '2026-04-10 10:05:00'),
(16, 7, '2026-04-10 10:10:00'),
(17, 7, '2026-04-10 10:15:00'),
(18, 6, '2026-04-10 10:20:00'),
(18, NULL, '2026-04-10 10:25:00'),
(19, 8, '2026-04-10 10:30:00'),
(20, NULL, '2026-04-10 10:35:00'),
(20, 5, '2026-04-10 10:40:00'),
(6, 5, '2026-04-10 10:45:00');

-- =========================================
-- INSERT ORDERS
-- =========================================

INSERT INTO nps_orders
(buyer_id, order_date, order_status, total_amount, shipping_address, payment_method)
VALUES
(5, '2026-04-11 10:00:00', 'confirmed', 544.40, 'Sitra, Bahrain', 'Cash on Delivery'),
(6, '2026-04-11 11:15:00', 'delivered', 174.40, 'Budaiya, Bahrain', 'Credit Card'),
(7, '2026-04-11 12:30:00', 'pending', 339.00, 'Hamad Town, Bahrain', 'BenefitPay'),
(8, '2026-04-11 01:45:00', 'shipped', 423.50, 'Juffair, Bahrain', 'Credit Card'),
(5, '2026-04-12 09:20:00', 'delivered', 104.80, 'Sitra, Bahrain', 'Cash on Delivery');

-- =========================================
-- INSERT ORDER ITEMS
-- =========================================

INSERT INTO nps_order_items
(order_id, product_id, quantity, unit_price, subtotal)
VALUES
-- Order 1
(1, 6, 1, 389.90, 389.90),
(1, 11, 1, 94.50, 94.50),
(1, 20, 1, 24.90, 24.90),
(1, 19, 1, 29.90, 29.90),

-- Order 2
(2, 10, 1, 149.90, 149.90),
(2, 19, 1, 29.90, 29.90),

-- Order 3
(3, 17, 1, 339.00, 339.00),

-- Order 4
(4, 7, 1, 365.00, 365.00),
(4, 15, 1, 79.90, 79.90),
(4, 20, 1, 24.90, 24.90),

-- Order 5
(5, 12, 1, 49.90, 49.90),
(5, 18, 1, 54.90, 54.90);

-- =========================================
-- INSERT ADMIN LOGS
-- admin is user_id 1
-- =========================================

INSERT INTO nps_admin_logs (admin_id, action_type, target_id, target_table, action_date) VALUES
(1, 'CREATE_CATEGORY', 1, 'nps_categories', '2026-04-03 08:00:00'),
(1, 'CREATE_CATEGORY', 2, 'nps_categories', '2026-04-03 08:05:00'),
(1, 'CREATE_CATEGORY', 3, 'nps_categories', '2026-04-03 08:10:00'),
(1, 'CREATE_CATEGORY', 4, 'nps_categories', '2026-04-03 08:15:00'),
(1, 'CREATE_CATEGORY', 5, 'nps_categories', '2026-04-03 08:20:00'),
(1, 'CREATE_CATEGORY', 6, 'nps_categories', '2026-04-03 08:25:00'),
(1, 'REVIEW_PRODUCT', 5, 'nps_products', '2026-04-03 12:30:00'),
(1, 'REVIEW_PRODUCT', 10, 'nps_products', '2026-04-05 12:00:00'),
(1, 'REVIEW_COMMENT', 4, 'nps_comments', '2026-04-09 12:30:00'),
(1, 'VIEW_REPORT', 1, 'system_report', '2026-04-10 11:00:00');

-- =========================================
-- procedure TABLES
-- =========================================

DELIMITER $$

-- =========================================================
-- Procedure 1: sp_popular_products_report
-- Purpose:
-- This procedure returns the most popular published products
-- within a given date range.
--
-- It is useful for the Admin Panel report:
-- "Most popular products within a date range"
--
-- The procedure calculates popularity using:
-- - number of views
-- - average rating
-- - number of comments
--
-- Input:
--   in_start_date  -> start of report period
--   in_end_date    -> end of report period
--   in_limit_count -> maximum number of rows to return
--
-- Output:
--   product details + seller + category + views + ratings
--   + comments + calculated popularity score
-- =========================================================
CREATE PROCEDURE sp_popular_products_report (
    IN in_start_date DATETIME,
    IN in_end_date DATETIME,
    IN in_limit_count INT
)
BEGIN
    -- Validate date inputs
    IF in_start_date IS NULL OR in_end_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date and end date are required.';
    END IF;

    -- Make sure the start date is not after the end date
    IF in_start_date > in_end_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date cannot be greater than end date.';
    END IF;

    -- If the limit is missing or invalid, use 10 by default
    IF in_limit_count IS NULL OR in_limit_count <= 0 THEN
        SET in_limit_count = 10;
    END IF;

    SELECT 
        p.product_id,
        p.product_name,
        p.brand,
        c.category_name,
        u.full_name AS seller_name,
        p.price,
        p.publish_status,

        -- Total views during the selected date range
        COUNT(DISTINCT pv.view_id) AS total_views,

        -- Average rating during the selected date range
        ROUND(IFNULL(AVG(r.rating_value), 0), 2) AS average_rating,

        -- Number of ratings during the selected date range
        COUNT(DISTINCT r.rating_id) AS total_ratings,

        -- Number of comments during the selected date range
        COUNT(DISTINCT cm.comment_id) AS total_comments,

        -- Popularity score:
        -- views have weight 0.5
        -- average rating has weight 10
        -- comments have weight 2
        (
            COUNT(DISTINCT pv.view_id) * 0.5 +
            IFNULL(AVG(r.rating_value), 0) * 10 +
            COUNT(DISTINCT cm.comment_id) * 2
        ) AS popularity_score

    FROM nps_products p
    INNER JOIN nps_users u
        ON p.seller_id = u.user_id
    INNER JOIN nps_categories c
        ON p.category_id = c.category_id

    -- Left joins are used so products still appear even if
    -- they have no views, ratings, or comments in that range
    LEFT JOIN nps_product_views pv
        ON p.product_id = pv.product_id
        AND pv.view_date BETWEEN in_start_date AND in_end_date

    LEFT JOIN nps_ratings r
        ON p.product_id = r.product_id
        AND r.created_at BETWEEN in_start_date AND in_end_date

    LEFT JOIN nps_comments cm
        ON p.product_id = cm.product_id
        AND cm.created_at BETWEEN in_start_date AND in_end_date

    -- Only published products are included in the report
    WHERE p.publish_status = 'published'

    GROUP BY 
        p.product_id,
        p.product_name,
        p.brand,
        c.category_name,
        u.full_name,
        p.price,
        p.publish_status

    -- Highest popularity first
    ORDER BY popularity_score DESC, total_views DESC, average_rating DESC

    LIMIT in_limit_count;
END $$


-- =========================================================
-- Procedure 2: sp_seller_product_performance
-- Purpose:
-- This procedure returns all products that belong to one seller
-- with detailed performance information in a date range.
--
-- It is useful for:
-- - Admin report: products created by a specific seller
-- - Seller dashboard: checking product performance
--
-- The procedure shows:
-- - product information
-- - category
-- - stock
-- - publish status
-- - primary image
-- - total views
-- - average rating
-- - total ratings
-- - total comments
-- - a simple performance level
--
-- Input:
--   in_seller_id   -> seller user id
--   in_start_date  -> start of report period
--   in_end_date    -> end of report period
--
-- Output:
--   all products of that seller with performance statistics
-- =========================================================
CREATE PROCEDURE sp_seller_product_performance (
    IN in_seller_id INT,
    IN in_start_date DATETIME,
    IN in_end_date DATETIME
)
BEGIN
    DECLARE v_role_name VARCHAR(50);

    -- Validate seller id
    IF in_seller_id IS NULL OR in_seller_id <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Valid seller ID is required.';
    END IF;

    -- Validate dates
    IF in_start_date IS NULL OR in_end_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date and end date are required.';
    END IF;

    -- Make sure the start date is not after the end date
    IF in_start_date > in_end_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date cannot be greater than end date.';
    END IF;

    -- Get the role name of the given user
    SELECT r.role_name
    INTO v_role_name
    FROM nps_users u
    INNER JOIN nps_roles r
        ON u.role_id = r.role_id
    WHERE u.user_id = in_seller_id
    LIMIT 1;

    -- If the user does not exist
    IF v_role_name IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Seller not found.';
    END IF;

    -- Make sure the user is actually a seller
    IF v_role_name <> 'Seller' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'The provided user is not a seller.';
    END IF;

    SELECT
        p.product_id,
        p.product_name,
        p.brand,
        c.category_name,
        p.price,
        p.stock_quantity,
        p.publish_status,
        p.created_at,
        p.updated_at,

        -- Primary image of the product
        img.image_path AS primary_image,

        -- Total views in the selected range
        COUNT(DISTINCT pv.view_id) AS total_views_in_range,

        -- Average rating in the selected range
        ROUND(IFNULL(AVG(r.rating_value), 0), 2) AS average_rating,

        -- Number of ratings in the selected range
        COUNT(DISTINCT r.rating_id) AS total_ratings,

        -- Number of comments in the selected range
        COUNT(DISTINCT cm.comment_id) AS total_comments,

        -- Simple label to classify the performance
        CASE
            WHEN COUNT(DISTINCT pv.view_id) >= 10 THEN 'High Interest'
            WHEN COUNT(DISTINCT pv.view_id) >= 5 THEN 'Moderate Interest'
            ELSE 'Low Interest'
        END AS performance_level

    FROM nps_products p
    INNER JOIN nps_categories c
        ON p.category_id = c.category_id

    LEFT JOIN nps_product_images img
        ON p.product_id = img.product_id
        AND img.is_primary = TRUE

    LEFT JOIN nps_product_views pv
        ON p.product_id = pv.product_id
        AND pv.view_date BETWEEN in_start_date AND in_end_date

    LEFT JOIN nps_ratings r
        ON p.product_id = r.product_id
        AND r.created_at BETWEEN in_start_date AND in_end_date

    LEFT JOIN nps_comments cm
        ON p.product_id = cm.product_id
        AND cm.created_at BETWEEN in_start_date AND in_end_date

    -- Only this seller's products
    WHERE p.seller_id = in_seller_id

    GROUP BY
        p.product_id,
        p.product_name,
        p.brand,
        c.category_name,
        p.price,
        p.stock_quantity,
        p.publish_status,
        p.created_at,
        p.updated_at,
        img.image_path

    -- Newest products first, then more viewed ones
    ORDER BY p.created_at DESC, total_views_in_range DESC;
END $$

DELIMITER ;

-- =========================================================
-- Procedure 3: sp_orders_report
-- Purpose:
-- This procedure returns customer orders within a selected
-- date range, with an optional filter by order status.
--
-- It is useful for:
-- - Admin order monitoring
-- - Order management report
-- - Reviewing buyer purchase activity
--
-- The procedure shows:
-- - order id
-- - buyer name
-- - order date
-- - order status
-- - total amount
-- - shipping address
-- - payment method
-- - total number of items in the order
--
-- Input:
--   in_start_date   -> start of report period
--   in_end_date     -> end of report period
--   in_order_status -> optional order status filter
--
-- Output:
--   all matching orders with buyer and order summary details
-- =========================================================

DELIMITER $$

CREATE PROCEDURE sp_orders_report (
    IN in_start_date DATETIME,
    IN in_end_date DATETIME,
    IN in_order_status VARCHAR(20)
)
BEGIN
    IF in_start_date IS NULL OR in_end_date IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date and end date are required.';
    END IF;

    IF in_start_date > in_end_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Start date cannot be greater than end date.';
    END IF;

    SELECT
        o.order_id,
        u.full_name AS buyer_name,
        o.order_date,
        o.order_status,
        o.total_amount,
        o.shipping_address,
        o.payment_method,
        COUNT(oi.order_item_id) AS total_items
    FROM nps_orders o
    INNER JOIN nps_users u
        ON o.buyer_id = u.user_id
    LEFT JOIN nps_order_items oi
        ON o.order_id = oi.order_id
    WHERE o.order_date BETWEEN in_start_date AND in_end_date
      AND (
            in_order_status IS NULL
            OR in_order_status = ''
            OR o.order_status = in_order_status
          )
    GROUP BY
        o.order_id,
        u.full_name,
        o.order_date,
        o.order_status,
        o.total_amount,
        o.shipping_address,
        o.payment_method
    ORDER BY o.order_date DESC, o.order_id DESC;
END $$

DELIMITER ;
-- =========================================
-- TRIGGER 
-- Automatically log comment deletion into admin logs
-- =========================================
DELIMITER $$

CREATE TRIGGER trg_before_delete_comment_admin_log
BEFORE DELETE ON nps_comments
FOR EACH ROW
BEGIN
    INSERT INTO nps_admin_logs (
        admin_id,
        action_type,
        target_id,
        target_table,
        action_date
    )
    VALUES (
        COALESCE(@current_admin_id, 1),
        'DELETE_COMMENT',
        OLD.comment_id,
        'nps_comments',
        NOW()
    );
END $$

DELIMITER ;

-- =========================================================

INSERT INTO nps_users (user_id, role_id, full_name, email, password_hash, created_at, status, phone_number, address) VALUES
(9,2,'Bahrain Gadget Zone','bahrain-gadget-zone@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-10 10:09:00','active','+97333100009','Aali, Bahrain'),
(10,2,'Nova Electronics BH','nova-electronics-bh@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-11 10:10:00','active','+97333100010','Sitra, Bahrain'),
(11,2,'FutureTech Store','futuretech-store@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-12 10:11:00','active','+97333100011','Seef, Bahrain'),
(12,2,'Smart Life Bahrain','smart-life-bahrain@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-13 10:12:00','active','+97333100012','Manama, Bahrain'),
(13,2,'Gulf Gaming Gear','gulf-gaming-gear@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-14 10:13:00','active','+97333100013','Muharraq, Bahrain'),
(14,2,'Pixel House Electronics','pixel-house-electronics@nextpick.com','$2y$10$JjJZZWYkR0z3ILP12/8gLu1zspoSlB6eX385AvHSdb39cer4BXlQS','2026-04-15 10:14:00','active','+97333100014','Riffa, Bahrain'),
(15,3,'Ali Mahmood','ali-mahmood@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-16 12:15:00','active','+97333200015','Isa Town, Bahrain'),
(16,3,'Layla Hassan','layla-hassan@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-17 12:16:00','active','+97333200016','Hamad Town, Bahrain'),
(17,3,'Hamad Yusuf','hamad-yusuf@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-18 12:17:00','active','+97333200017','Juffair, Bahrain'),
(18,3,'Mariam Jassim','mariam-jassim@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-19 12:18:00','active','+97333200018','Saar, Bahrain'),
(19,3,'Khalid Abdulla','khalid-abdulla@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-20 12:19:00','active','+97333200019','Budaiya, Bahrain'),
(20,3,'Aisha Nasser','aisha-nasser@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-21 12:20:00','active','+97333200020','Sanad, Bahrain'),
(21,3,'Hussain Ahmed','hussain-ahmed@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-22 12:21:00','active','+97333200021','Aali, Bahrain'),
(22,3,'Dana Almoayyed','dana-almoayyed@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-23 12:22:00','active','+97333200022','Sitra, Bahrain'),
(23,3,'Salman Rashid','salman-rashid@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-24 12:23:00','active','+97333200023','Seef, Bahrain'),
(24,3,'Reem AlKhalifa','reem-alkhalifa@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-01 12:24:00','active','+97333200024','Manama, Bahrain'),
(25,3,'Faisal Noor','faisal-noor@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-02 12:25:00','active','+97333200025','Muharraq, Bahrain'),
(26,3,'Zainab Adel','zainab-adel@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-03 12:26:00','inactive','+97333200026','Riffa, Bahrain'),
(27,3,'Abdulla Saleh','abdulla-saleh@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-04 12:27:00','active','+97333200027','Isa Town, Bahrain'),
(28,3,'Haya Mohammed','haya-mohammed@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-05 12:28:00','active','+97333200028','Hamad Town, Bahrain'),
(29,3,'Jassim Khalid','jassim-khalid@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-06 12:29:00','active','+97333200029','Juffair, Bahrain'),
(30,3,'Mona Ebrahim','mona-ebrahim@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-07 12:30:00','active','+97333200030','Saar, Bahrain'),
(31,3,'Rashed Alawi','rashed-alawi@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-08 12:31:00','active','+97333200031','Budaiya, Bahrain'),
(32,3,'Noura Salman','noura-salman@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-09 12:32:00','active','+97333200032','Sanad, Bahrain'),
(33,3,'Yara Yousef','yara-yousef@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-10 12:33:00','active','+97333200033','Aali, Bahrain'),
(34,3,'Sayed Hasan','sayed-hasan@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-11 12:34:00','active','+97333200034','Sitra, Bahrain'),
(35,3,'Latifa Omar','latifa-omar@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-12 12:35:00','active','+97333200035','Seef, Bahrain'),
(36,3,'Othman Fakhro','othman-fakhro@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-13 12:36:00','active','+97333200036','Manama, Bahrain'),
(37,3,'Amna Jamal','amna-jamal@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-14 12:37:00','active','+97333200037','Muharraq, Bahrain'),
(38,3,'Kareem Mansoor','kareem-mansoor@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-15 12:38:00','active','+97333200038','Riffa, Bahrain'),
(39,3,'Sara Khalifa','sara-khalifa@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-16 12:39:00','inactive','+97333200039','Isa Town, Bahrain'),
(40,3,'Fadhel Abbas','fadhel-abbas@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-17 12:40:00','active','+97333200040','Hamad Town, Bahrain'),
(41,3,'Maha Yousif','maha-yousif@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-18 12:41:00','active','+97333200041','Juffair, Bahrain'),
(42,3,'Ebrahim Nabeel','ebrahim-nabeel@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-19 12:42:00','active','+97333200042','Saar, Bahrain'),
(43,3,'Jana Sami','jana-sami@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-20 12:43:00','active','+97333200043','Budaiya, Bahrain'),
(44,3,'Nawaf Tariq','nawaf-tariq@nextpick.com','$2y$10$H.1bwjwSSSpjk2iJMv8KcuNilSCsp2fI5qWFFLrqe6KL/kSgMEXvu','2026-04-21 12:44:00','active','+97333200044','Sanad, Bahrain'),
(45,1,'Operations Admin','operations-admin@nextpick.com','$2y$10$lM8lGyvx0JJN1bZKkVcAee56ScLK9PE2LYMC58yxGgZxoRn1965Dm','2026-04-20 09:45:00','active','+97333300045','Manama, Bahrain'),
(46,1,'Reports Admin','reports-admin@nextpick.com','$2y$10$lM8lGyvx0JJN1bZKkVcAee56ScLK9PE2LYMC58yxGgZxoRn1965Dm','2026-04-20 09:46:00','active','+97333300046','Manama, Bahrain');

INSERT INTO nps_categories (category_id, category_name, description) VALUES
(7,'Tablets','iPad and Android tablets for study, drawing, and work'),
(8,'Gaming Accessories','Gaming keyboards, mice, controllers, and headsets'),
(9,'Monitors','Computer monitors for gaming, work, and design'),
(10,'Computer Accessories','Mice, keyboards, webcams, adapters, and USB hubs'),
(11,'Networking','Routers, Wi-Fi extenders, mesh systems, and network accessories'),
(12,'Storage','External SSDs, HDDs, USB flash drives, and memory cards'),
(13,'Printers','Home and office printers and printing accessories'),
(14,'Power & Chargers','Power banks, fast chargers, adapters, and cables'),
(15,'Smart Home','Smart lighting, security cameras, and connected home devices'),
(16,'TV & Streaming','Smart TVs, streaming devices, and media accessories');

INSERT INTO nps_products (product_id, seller_id, category_id, product_name, short_description, full_description, price, stock_quantity, publish_status, brand, created_at, updated_at) VALUES
(21,12,1,'MacBook Pro 14 M3','Apple device for practical daily use','MacBook Pro 14 M3 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',799.90,9,'published','Apple','2026-05-22 09:15:00','2026-05-22 10:00:00'),
(22,11,1,'Acer Aspire 5','Acer device for practical daily use','Acer Aspire 5 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',219.90,24,'published','Acer','2026-05-23 10:15:00','2026-05-23 11:00:00'),
(23,14,1,'MSI Katana 15','MSI device for practical daily use','MSI Katana 15 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',599.00,6,'published','MSI','2026-05-24 11:15:00','2026-05-24 12:00:00'),
(24,13,1,'Surface Laptop Go 3','Microsoft device for practical daily use','Surface Laptop Go 3 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',299.90,13,'published','Microsoft','2026-05-25 12:15:00','2026-05-25 13:00:00'),
(25,14,1,'HP Victus 16','HP device for practical daily use','HP Victus 16 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',489.00,7,'published','HP','2026-05-26 13:15:00','2026-05-26 14:00:00'),
(26,11,1,'Dell XPS 13','Dell device for practical daily use','Dell XPS 13 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',649.90,5,'published','Dell','2026-05-27 14:15:00','2026-05-27 15:00:00'),
(27,10,2,'Samsung Galaxy A55','Samsung device for practical daily use','Samsung Galaxy A55 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',159.90,28,'published','Samsung','2026-05-28 15:15:00','2026-05-28 16:00:00'),
(28,12,2,'iPhone 14 128GB','Apple device for practical daily use','iPhone 14 128GB is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',319.90,17,'published','Apple','2026-05-01 16:15:00','2026-05-01 17:00:00'),
(29,9,2,'OnePlus 12R','OnePlus device for practical daily use','OnePlus 12R is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',249.90,11,'published','OnePlus','2026-05-02 17:15:00','2026-05-02 18:00:00'),
(30,11,2,'Nothing Phone 2a','Nothing device for practical daily use','Nothing Phone 2a is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',139.90,16,'published','Nothing','2026-05-03 08:15:00','2026-05-03 09:00:00'),
(31,12,2,'Honor 90','Honor device for practical daily use','Honor 90 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',169.00,19,'published','Honor','2026-05-04 09:15:00','2026-05-04 10:00:00'),
(32,11,2,'Moto G Power 5G','Motorola device for practical daily use','Moto G Power 5G is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',109.90,22,'published','Motorola','2026-05-05 10:15:00','2026-05-05 11:00:00'),
(33,14,3,'Bose QuietComfort Ultra','Bose device for practical daily use','Bose QuietComfort Ultra is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',189.90,8,'published','Bose','2026-05-06 11:15:00','2026-05-06 12:00:00'),
(34,12,3,'Soundcore Space One','Anker device for practical daily use','Soundcore Space One is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,30,'published','Anker','2026-05-07 12:15:00','2026-05-07 13:00:00'),
(35,14,3,'Beats Studio Buds Plus','Beats device for practical daily use','Beats Studio Buds Plus is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,18,'published','Beats','2026-05-08 13:15:00','2026-05-08 14:00:00'),
(36,13,3,'Jabra Elite 8 Active','Jabra device for practical daily use','Jabra Elite 8 Active is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,14,'published','Jabra','2026-05-09 14:15:00','2026-05-09 15:00:00'),
(37,10,3,'Logitech H390 USB Headset','Logitech device for practical daily use','Logitech H390 USB Headset is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',12.90,40,'hidden','Logitech','2026-05-10 15:15:00','2026-05-10 16:00:00'),
(38,13,4,'Garmin Venu 3','Garmin device for practical daily use','Garmin Venu 3 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',169.90,10,'published','Garmin','2026-05-11 16:15:00','2026-05-11 17:00:00'),
(39,9,4,'Amazfit GTR 4','Amazfit device for practical daily use','Amazfit GTR 4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,20,'published','Amazfit','2026-05-12 17:15:00','2026-05-12 18:00:00'),
(40,14,4,'Fitbit Versa 4','Fitbit device for practical daily use','Fitbit Versa 4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',79.90,16,'published','Fitbit','2026-05-13 08:15:00','2026-05-13 09:00:00'),
(41,13,4,'Xiaomi Smart Band 8','Xiaomi device for practical daily use','Xiaomi Smart Band 8 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',19.90,45,'draft','Xiaomi','2026-05-14 09:15:00','2026-05-14 10:00:00'),
(42,10,4,'Huawei Band 9','Huawei device for practical daily use','Huawei Band 9 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',24.90,35,'published','Huawei','2026-05-15 10:15:00','2026-05-15 11:00:00'),
(43,12,5,'Nikon Z30 Creator Kit','Nikon device for practical daily use','Nikon Z30 Creator Kit is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',319.90,5,'published','Nikon','2026-05-16 11:15:00','2026-05-16 12:00:00'),
(44,11,5,'GoPro HERO12 Black','GoPro device for practical daily use','GoPro HERO12 Black is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',179.90,12,'published','GoPro','2026-05-17 12:15:00','2026-05-17 13:00:00'),
(45,10,5,'DJI Osmo Pocket 3','DJI device for practical daily use','DJI Osmo Pocket 3 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',259.90,8,'published','DJI','2026-05-18 13:15:00','2026-05-18 14:00:00'),
(46,11,5,'Insta360 X4','Insta360 device for practical daily use','Insta360 X4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',299.90,6,'published','Insta360','2026-05-19 14:15:00','2026-05-19 15:00:00'),
(47,10,6,'Marshall Emberton II','Marshall device for practical daily use','Marshall Emberton II is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,18,'published','Marshall','2026-05-20 15:15:00','2026-05-20 16:00:00'),
(48,9,6,'Soundcore Motion Plus','Anker device for practical daily use','Soundcore Motion Plus is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,25,'published','Anker','2026-05-21 16:15:00','2026-05-21 17:00:00'),
(49,13,6,'Bose SoundLink Flex','Bose device for practical daily use','Bose SoundLink Flex is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',79.90,12,'published','Bose','2026-05-22 17:15:00','2026-05-22 18:00:00'),
(50,10,6,'JBL Charge 5','JBL device for practical daily use','JBL Charge 5 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',74.90,17,'published','JBL','2026-05-23 08:15:00','2026-05-23 09:00:00'),
(51,9,7,'iPad 10th Gen','Apple device for practical daily use','iPad 10th Gen is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',139.90,20,'published','Apple','2026-05-24 09:15:00','2026-05-24 10:00:00'),
(52,13,7,'Galaxy Tab S9 FE','Samsung device for practical daily use','Galaxy Tab S9 FE is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',179.90,15,'published','Samsung','2026-05-25 10:15:00','2026-05-25 11:00:00'),
(53,14,7,'Lenovo Tab P12','Lenovo device for practical daily use','Lenovo Tab P12 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',129.90,14,'published','Lenovo','2026-05-26 11:15:00','2026-05-26 12:00:00'),
(54,11,7,'Xiaomi Pad 6','Xiaomi device for practical daily use','Xiaomi Pad 6 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',119.90,18,'published','Xiaomi','2026-05-27 12:15:00','2026-05-27 13:00:00'),
(55,14,7,'Surface Go 4','Microsoft device for practical daily use','Surface Go 4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',199.90,9,'published','Microsoft','2026-05-28 13:15:00','2026-05-28 14:00:00'),
(56,12,8,'Logitech G Pro X Keyboard','Logitech device for practical daily use','Logitech G Pro X Keyboard is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.90,23,'published','Logitech','2026-05-01 14:15:00','2026-05-01 15:00:00'),
(57,9,8,'Razer DeathAdder V3','Razer device for practical daily use','Razer DeathAdder V3 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',34.90,27,'published','Razer','2026-05-02 15:15:00','2026-05-02 16:00:00'),
(58,9,8,'DualSense Wireless Controller','Sony device for practical daily use','DualSense Wireless Controller is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',29.90,31,'published','Sony','2026-05-03 16:15:00','2026-05-03 17:00:00'),
(59,9,8,'HyperX Cloud II','HyperX device for practical daily use','HyperX Cloud II is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,21,'published','HyperX','2026-05-04 17:15:00','2026-05-04 18:00:00'),
(60,14,8,'Elgato Stream Deck Mini','Elgato device for practical daily use','Elgato Stream Deck Mini is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.00,10,'published','Elgato','2026-05-05 08:15:00','2026-05-05 09:00:00'),
(61,13,9,'LG UltraGear 27-inch 144Hz','LG device for practical daily use','LG UltraGear 27-inch 144Hz is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',109.90,13,'published','LG','2026-05-06 09:15:00','2026-05-06 10:00:00'),
(62,10,9,'Samsung Odyssey G5 32-inch','Samsung device for practical daily use','Samsung Odyssey G5 32-inch is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',159.90,7,'published','Samsung','2026-05-07 10:15:00','2026-05-07 11:00:00'),
(63,9,9,'Dell 24-inch IPS Monitor','Dell device for practical daily use','Dell 24-inch IPS Monitor is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,25,'published','Dell','2026-05-08 11:15:00','2026-05-08 12:00:00'),
(64,12,9,'ASUS ProArt 27-inch','ASUS device for practical daily use','ASUS ProArt 27-inch is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',189.90,6,'published','ASUS','2026-05-09 12:15:00','2026-05-09 13:00:00'),
(65,12,9,'AOC 27B2H Monitor','AOC device for practical daily use','AOC 27B2H Monitor is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,20,'published','AOC','2026-05-10 13:15:00','2026-05-10 14:00:00'),
(66,9,10,'Logitech MX Master 3S','Logitech device for practical daily use','Logitech MX Master 3S is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,28,'published','Logitech','2026-05-11 14:15:00','2026-05-11 15:00:00'),
(67,14,10,'Logitech MX Keys Mini','Logitech device for practical daily use','Logitech MX Keys Mini is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',44.90,19,'published','Logitech','2026-05-12 15:15:00','2026-05-12 16:00:00'),
(68,12,10,'Anker USB-C Hub 7-in-1','Anker device for practical daily use','Anker USB-C Hub 7-in-1 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',19.90,34,'published','Anker','2026-05-13 16:15:00','2026-05-13 17:00:00'),
(69,10,10,'Ugreen HDMI Adapter','Ugreen device for practical daily use','Ugreen HDMI Adapter is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',9.90,50,'published','Ugreen','2026-05-14 17:15:00','2026-05-14 18:00:00'),
(70,13,10,'Razer Kiyo Webcam','Razer device for practical daily use','Razer Kiyo Webcam is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',29.90,15,'published','Razer','2026-05-15 08:15:00','2026-05-15 09:00:00'),
(71,11,11,'TP-Link Archer AX55','TP-Link device for practical daily use','TP-Link Archer AX55 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,22,'published','TP-Link','2026-05-16 09:15:00','2026-05-16 10:00:00'),
(72,13,11,'Netgear Nighthawk AX4','Netgear device for practical daily use','Netgear Nighthawk AX4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,12,'published','Netgear','2026-05-17 10:15:00','2026-05-17 11:00:00'),
(73,13,11,'Deco X20 Mesh WiFi','TP-Link device for practical daily use','Deco X20 Mesh WiFi is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',89.90,9,'published','TP-Link','2026-05-18 11:15:00','2026-05-18 12:00:00'),
(74,10,11,'D-Link WiFi Extender','D-Link device for practical daily use','D-Link WiFi Extender is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',14.90,32,'hidden','D-Link','2026-05-19 12:15:00','2026-05-19 13:00:00'),
(75,14,11,'UniFi 6 Lite Access Point','Ubiquiti device for practical daily use','UniFi 6 Lite Access Point is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.90,10,'published','Ubiquiti','2026-05-20 13:15:00','2026-05-20 14:00:00'),
(76,9,12,'Samsung T7 Shield 1TB','Samsung device for practical daily use','Samsung T7 Shield 1TB is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.90,26,'published','Samsung','2026-05-21 14:15:00','2026-05-21 15:00:00'),
(77,13,12,'SanDisk Extreme 1TB SSD','SanDisk device for practical daily use','SanDisk Extreme 1TB SSD is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',54.90,18,'published','SanDisk','2026-05-22 15:15:00','2026-05-22 16:00:00'),
(78,10,12,'WD My Passport 2TB','WD device for practical daily use','WD My Passport 2TB is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,22,'published','WD','2026-05-23 16:15:00','2026-05-23 17:00:00'),
(79,13,12,'Kingston DataTraveler 128GB','Kingston device for practical daily use','Kingston DataTraveler 128GB is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',6.90,60,'published','Kingston','2026-05-24 17:15:00','2026-05-24 18:00:00'),
(80,9,12,'Lexar 256GB microSD','Lexar device for practical daily use','Lexar 256GB microSD is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',11.90,44,'published','Lexar','2026-05-25 08:15:00','2026-05-25 09:00:00'),
(81,12,13,'HP DeskJet 2720e','HP device for practical daily use','HP DeskJet 2720e is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',24.90,14,'published','HP','2026-05-26 09:15:00','2026-05-26 10:00:00'),
(82,13,13,'Canon PIXMA G3420','Canon device for practical daily use','Canon PIXMA G3420 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,11,'draft','Canon','2026-05-27 10:15:00','2026-05-27 11:00:00'),
(83,12,13,'Epson EcoTank L3250','Epson device for practical daily use','Epson EcoTank L3250 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,8,'published','Epson','2026-05-28 11:15:00','2026-05-28 12:00:00'),
(84,14,13,'Brother HL-L2350DW','Brother device for practical daily use','Brother HL-L2350DW is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.90,13,'published','Brother','2026-05-01 12:15:00','2026-05-01 13:00:00'),
(85,13,14,'Anker Nano 30W Charger','Anker device for practical daily use','Anker Nano 30W Charger is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',9.90,55,'published','Anker','2026-05-02 13:15:00','2026-05-02 14:00:00'),
(86,12,14,'Belkin 65W Dual USB-C Charger','Belkin device for practical daily use','Belkin 65W Dual USB-C Charger is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',24.90,24,'published','Belkin','2026-05-03 14:15:00','2026-05-03 15:00:00'),
(87,12,14,'Apple MagSafe Charger','Apple device for practical daily use','Apple MagSafe Charger is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',18.90,20,'published','Apple','2026-05-04 15:15:00','2026-05-04 16:00:00'),
(88,13,14,'Baseus 20000mAh Power Bank','Baseus device for practical daily use','Baseus 20000mAh Power Bank is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',19.90,33,'published','Baseus','2026-05-05 16:15:00','2026-05-05 17:00:00'),
(89,12,14,'Ugreen USB-C Cable 100W','Ugreen device for practical daily use','Ugreen USB-C Cable 100W is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',5.90,70,'published','Ugreen','2026-05-06 17:15:00','2026-05-06 18:00:00'),
(90,13,15,'Philips Hue Starter Kit','Philips device for practical daily use','Philips Hue Starter Kit is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',49.90,12,'published','Philips','2026-05-07 08:15:00','2026-05-07 09:00:00'),
(91,9,15,'Google Nest Cam','Google device for practical daily use','Google Nest Cam is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',69.90,9,'published','Google','2026-05-08 09:15:00','2026-05-08 10:00:00'),
(92,14,15,'Ring Video Doorbell','Amazon device for practical daily use','Ring Video Doorbell is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,10,'published','Amazon','2026-05-09 10:15:00','2026-05-09 11:00:00'),
(93,14,15,'Tapo Smart Plug 4-Pack','TP-Link device for practical daily use','Tapo Smart Plug 4-Pack is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',14.90,38,'published','TP-Link','2026-05-10 11:15:00','2026-05-10 12:00:00'),
(94,9,15,'Xiaomi Smart Air Purifier 4','Xiaomi device for practical daily use','Xiaomi Smart Air Purifier 4 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',89.90,7,'published','Xiaomi','2026-05-11 12:15:00','2026-05-11 13:00:00'),
(95,10,16,'Samsung 55-inch Crystal UHD TV','Samsung device for practical daily use','Samsung 55-inch Crystal UHD TV is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',199.90,8,'published','Samsung','2026-05-12 13:15:00','2026-05-12 14:00:00'),
(96,14,16,'LG 50-inch UHD TV','LG device for practical daily use','LG 50-inch UHD TV is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',179.90,9,'published','LG','2026-05-13 14:15:00','2026-05-13 15:00:00'),
(97,11,16,'Chromecast with Google TV','Google device for practical daily use','Chromecast with Google TV is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',19.90,35,'published','Google','2026-05-14 15:15:00','2026-05-14 16:00:00'),
(98,11,16,'Fire TV Stick 4K','Amazon device for practical daily use','Fire TV Stick 4K is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',17.90,40,'published','Amazon','2026-05-15 16:15:00','2026-05-15 17:00:00'),
(99,11,16,'Roku Express 4K','Roku device for practical daily use','Roku Express 4K is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',16.90,25,'published','Roku','2026-05-16 17:15:00','2026-05-16 18:00:00'),
(100,14,10,'AirTag 4 Pack','Apple device for practical daily use','AirTag 4 Pack is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',34.90,30,'published','Apple','2026-05-17 08:15:00','2026-05-17 09:00:00'),
(101,12,10,'SmartTag 2','Samsung device for practical daily use','SmartTag 2 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',12.90,40,'published','Samsung','2026-05-18 09:15:00','2026-05-18 10:00:00'),
(102,14,10,'C920 HD Webcam','Logitech device for practical daily use','C920 HD Webcam is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',22.90,23,'published','Logitech','2026-05-19 10:15:00','2026-05-19 11:00:00'),
(103,11,5,'RF 50mm Lens','Canon device for practical daily use','RF 50mm Lens is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',59.90,8,'published','Canon','2026-05-20 11:15:00','2026-05-20 12:00:00'),
(104,9,5,'ECM-G1 Microphone','Sony device for practical daily use','ECM-G1 Microphone is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,9,'published','Sony','2026-05-21 12:15:00','2026-05-21 13:00:00'),
(105,11,3,'Wave Beam Earbuds','JBL device for practical daily use','Wave Beam Earbuds is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',24.90,34,'published','JBL','2026-05-22 13:15:00','2026-05-22 14:00:00'),
(106,10,3,'USB-C EarPods','Apple device for practical daily use','USB-C EarPods is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',9.90,50,'published','Apple','2026-05-23 14:15:00','2026-05-23 15:00:00'),
(107,11,1,'ThinkPad E14','Lenovo device for practical daily use','ThinkPad E14 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',379.90,10,'published','Lenovo','2026-05-24 15:15:00','2026-05-24 16:00:00'),
(108,11,1,'Zenbook 14 OLED','ASUS device for practical daily use','Zenbook 14 OLED is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',499.90,7,'published','ASUS','2026-05-25 16:15:00','2026-05-25 17:00:00'),
(109,11,3,'Galaxy Buds FE','Samsung device for practical daily use','Galaxy Buds FE is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',39.90,20,'published','Samsung','2026-05-26 17:15:00','2026-05-26 18:00:00'),
(110,13,8,'PlayStation Portal','Sony device for practical daily use','PlayStation Portal is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',89.90,5,'published','Sony','2026-05-27 08:15:00','2026-05-27 09:00:00'),
(111,13,8,'Switch Pro Controller','Nintendo device for practical daily use','Switch Pro Controller is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',24.90,16,'hidden','Nintendo','2026-05-28 09:15:00','2026-05-28 10:00:00'),
(112,13,10,'BenQ ScreenBar','BenQ device for practical daily use','BenQ ScreenBar is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',29.90,12,'published','BenQ','2026-05-01 10:15:00','2026-05-01 11:00:00'),
(113,10,15,'Yeelight Smart Bulb Color','Yeelight device for practical daily use','Yeelight Smart Bulb Color is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',8.90,36,'published','Yeelight','2026-05-02 11:15:00','2026-05-02 12:00:00'),
(114,9,12,'Expansion 4TB Desktop Drive','Seagate device for practical daily use','Expansion 4TB Desktop Drive is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',54.90,14,'published','Seagate','2026-05-03 12:15:00','2026-05-03 13:00:00'),
(115,12,11,'Deco M4 Mesh Twin Pack','TP-Link device for practical daily use','Deco M4 Mesh Twin Pack is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',42.90,18,'published','TP-Link','2026-05-04 13:15:00','2026-05-04 14:00:00'),
(116,12,14,'Anker 737 Power Bank','Anker device for practical daily use','Anker 737 Power Bank is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',54.90,11,'published','Anker','2026-05-05 14:15:00','2026-05-05 15:00:00'),
(117,9,9,'BenQ GW2480 Eye Care Monitor','BenQ device for practical daily use','BenQ GW2480 Eye Care Monitor is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',74.90,16,'published','BenQ','2026-05-06 15:15:00','2026-05-06 16:00:00'),
(118,13,13,'Canon SELPHY CP1500 Photo Printer','Canon device for practical daily use','Canon SELPHY CP1500 Photo Printer is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',44.90,9,'published','Canon','2026-05-07 16:15:00','2026-05-07 17:00:00'),
(119,9,16,'Mi TV Stick 4K','Xiaomi device for practical daily use','Mi TV Stick 4K is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',18.90,30,'published','Xiaomi','2026-05-08 17:15:00','2026-05-08 18:00:00'),
(120,10,7,'Honor Pad 9','Honor device for practical daily use','Honor Pad 9 is a meaningful electronics product suitable for customers who need reliable performance, clean design, and good value. It is useful for home, university, office, gaming, and entertainment depending on the category.',119.00,13,'published','Honor','2026-05-09 08:15:00','2026-05-09 09:00:00');

INSERT INTO nps_product_images (product_id, image_path, is_primary) VALUES
(21,'uploads/products/macbook-pro-14-m3.jpg',TRUE),
(22,'uploads/products/acer-aspire-5.jpg',TRUE),
(23,'uploads/products/msi-katana-15.jpg',TRUE),
(24,'uploads/products/surface-laptop-go-3.jpg',TRUE),
(25,'uploads/products/hp-victus-16.jpg',TRUE),
(26,'uploads/products/dell-xps-13.jpg',TRUE),
(27,'uploads/products/samsung-galaxy-a55.jpg',TRUE),
(28,'uploads/products/iphone-14-128gb.jpg',TRUE),
(29,'uploads/products/oneplus-12r.jpg',TRUE),
(30,'uploads/products/nothing-phone-2a.jpg',TRUE),
(31,'uploads/products/honor-90.jpg',TRUE),
(32,'uploads/products/moto-g-power-5g.jpg',TRUE),
(33,'uploads/products/bose-quietcomfort-ultra.jpg',TRUE),
(34,'uploads/products/soundcore-space-one.jpg',TRUE),
(35,'uploads/products/beats-studio-buds-plus.jpg',TRUE),
(36,'uploads/products/jabra-elite-8-active.jpg',TRUE),
(37,'uploads/products/logitech-h390-usb-headset.jpg',TRUE),
(38,'uploads/products/garmin-venu-3.jpg',TRUE),
(39,'uploads/products/amazfit-gtr-4.jpg',TRUE),
(40,'uploads/products/fitbit-versa-4.jpg',TRUE),
(41,'uploads/products/xiaomi-smart-band-8.jpg',TRUE),
(42,'uploads/products/huawei-band-9.jpg',TRUE),
(43,'uploads/products/nikon-z30-creator-kit.jpg',TRUE),
(44,'uploads/products/gopro-hero12-black.jpg',TRUE),
(45,'uploads/products/dji-osmo-pocket-3.jpg',TRUE),
(46,'uploads/products/insta360-x4.jpg',TRUE),
(47,'uploads/products/marshall-emberton-ii.jpg',TRUE),
(48,'uploads/products/soundcore-motion-plus.jpg',TRUE),
(49,'uploads/products/bose-soundlink-flex.jpg',TRUE),
(50,'uploads/products/jbl-charge-5.jpg',TRUE),
(51,'uploads/products/ipad-10th-gen.jpg',TRUE),
(52,'uploads/products/galaxy-tab-s9-fe.jpg',TRUE),
(53,'uploads/products/lenovo-tab-p12.jpg',TRUE),
(54,'uploads/products/xiaomi-pad-6.jpg',TRUE),
(55,'uploads/products/surface-go-4.jpg',TRUE),
(56,'uploads/products/logitech-g-pro-x-keyboard.jpg',TRUE),
(57,'uploads/products/razer-deathadder-v3.jpg',TRUE),
(58,'uploads/products/dualsense-wireless-controller.jpg',TRUE),
(59,'uploads/products/hyperx-cloud-ii.jpg',TRUE),
(60,'uploads/products/elgato-stream-deck-mini.jpg',TRUE),
(61,'uploads/products/lg-ultragear-27-inch-144hz.jpg',TRUE),
(62,'uploads/products/samsung-odyssey-g5-32-inch.jpg',TRUE),
(63,'uploads/products/dell-24-inch-ips-monitor.jpg',TRUE),
(64,'uploads/products/asus-proart-27-inch.jpg',TRUE),
(65,'uploads/products/aoc-27b2h-monitor.jpg',TRUE),
(66,'uploads/products/logitech-mx-master-3s.jpg',TRUE),
(67,'uploads/products/logitech-mx-keys-mini.jpg',TRUE),
(68,'uploads/products/anker-usb-c-hub-7-in-1.jpg',TRUE),
(69,'uploads/products/ugreen-hdmi-adapter.jpg',TRUE),
(70,'uploads/products/razer-kiyo-webcam.jpg',TRUE),
(71,'uploads/products/tp-link-archer-ax55.jpg',TRUE),
(72,'uploads/products/netgear-nighthawk-ax4.jpg',TRUE),
(73,'uploads/products/deco-x20-mesh-wifi.jpg',TRUE),
(74,'uploads/products/d-link-wifi-extender.jpg',TRUE),
(75,'uploads/products/unifi-6-lite-access-point.jpg',TRUE),
(76,'uploads/products/samsung-t7-shield-1tb.jpg',TRUE),
(77,'uploads/products/sandisk-extreme-1tb-ssd.jpg',TRUE),
(78,'uploads/products/wd-my-passport-2tb.jpg',TRUE),
(79,'uploads/products/kingston-datatraveler-128gb.jpg',TRUE),
(80,'uploads/products/lexar-256gb-microsd.jpg',TRUE),
(81,'uploads/products/hp-deskjet-2720e.jpg',TRUE),
(82,'uploads/products/canon-pixma-g3420.jpg',TRUE),
(83,'uploads/products/epson-ecotank-l3250.jpg',TRUE),
(84,'uploads/products/brother-hl-l2350dw.jpg',TRUE),
(85,'uploads/products/anker-nano-30w-charger.jpg',TRUE),
(86,'uploads/products/belkin-65w-dual-usb-c-charger.jpg',TRUE),
(87,'uploads/products/apple-magsafe-charger.jpg',TRUE),
(88,'uploads/products/baseus-20000mah-power-bank.jpg',TRUE),
(89,'uploads/products/ugreen-usb-c-cable-100w.jpg',TRUE),
(90,'uploads/products/philips-hue-starter-kit.jpg',TRUE),
(91,'uploads/products/google-nest-cam.jpg',TRUE),
(92,'uploads/products/ring-video-doorbell.jpg',TRUE),
(93,'uploads/products/tapo-smart-plug-4-pack.jpg',TRUE),
(94,'uploads/products/xiaomi-smart-air-purifier-4.jpg',TRUE),
(95,'uploads/products/samsung-55-inch-crystal-uhd-tv.jpg',TRUE),
(96,'uploads/products/lg-50-inch-uhd-tv.jpg',TRUE),
(97,'uploads/products/chromecast-with-google-tv.jpg',TRUE),
(98,'uploads/products/fire-tv-stick-4k.jpg',TRUE),
(99,'uploads/products/roku-express-4k.jpg',TRUE),
(100,'uploads/products/airtag-4-pack.jpg',TRUE),
(101,'uploads/products/smarttag-2.jpg',TRUE),
(102,'uploads/products/c920-hd-webcam.jpg',TRUE),
(103,'uploads/products/rf-50mm-lens.jpg',TRUE),
(104,'uploads/products/ecm-g1-microphone.jpg',TRUE),
(105,'uploads/products/wave-beam-earbuds.jpg',TRUE),
(106,'uploads/products/usb-c-earpods.jpg',TRUE),
(107,'uploads/products/thinkpad-e14.jpg',TRUE),
(108,'uploads/products/zenbook-14-oled.jpg',TRUE),
(109,'uploads/products/galaxy-buds-fe.jpg',TRUE),
(110,'uploads/products/playstation-portal.jpg',TRUE),
(111,'uploads/products/switch-pro-controller.jpg',TRUE),
(112,'uploads/products/benq-screenbar.jpg',TRUE),
(113,'uploads/products/yeelight-smart-bulb-color.jpg',TRUE),
(114,'uploads/products/expansion-4tb-desktop-drive.jpg',TRUE),
(115,'uploads/products/deco-m4-mesh-twin-pack.jpg',TRUE),
(116,'uploads/products/anker-737-power-bank.jpg',TRUE),
(117,'uploads/products/benq-gw2480-eye-care-monitor.jpg',TRUE),
(118,'uploads/products/canon-selphy-cp1500-photo-printer.jpg',TRUE),
(119,'uploads/products/mi-tv-stick-4k.jpg',TRUE),
(120,'uploads/products/honor-pad-9.jpg',TRUE);

INSERT INTO nps_comments (user_id, product_id, comment_text, created_at, updated_at) VALUES
(38,6,'Excellent quality and arrived in perfect condition.','2026-06-13 11:49:00',NULL),
(25,23,'Useful product and worth the price for daily use.','2026-06-03 19:17:00',NULL),
(28,64,'The build quality feels premium and reliable.','2026-06-12 17:40:00',NULL),
(43,59,'Fast delivery and product matched the description.','2026-06-12 11:21:00',NULL),
(41,90,'Good value compared with other options in the market.','2026-06-28 15:15:00',NULL),
(21,60,'I use it every day and it performs very well.','2026-06-18 13:11:00',NULL),
(25,117,'Packaging was clean and the item worked immediately.','2026-06-20 08:17:00',NULL),
(32,23,'Battery life and performance are better than expected.','2026-06-08 20:00:00',NULL),
(26,80,'Very helpful for work, study, and entertainment.','2026-06-04 13:34:00',NULL),
(6,119,'Simple setup and smooth experience overall.','2026-06-11 22:50:00',NULL),
(28,82,'Excellent quality and arrived in perfect condition.','2026-06-04 11:50:00',NULL),
(41,63,'Useful product and worth the price for daily use.','2026-06-21 13:16:00',NULL),
(39,115,'The build quality feels premium and reliable.','2026-06-24 10:28:00',NULL),
(7,36,'Fast delivery and product matched the description.','2026-06-11 21:32:00',NULL),
(39,91,'Good value compared with other options in the market.','2026-06-26 11:46:00',NULL),
(27,4,'I use it every day and it performs very well.','2026-06-22 08:44:00',NULL),
(28,54,'Packaging was clean and the item worked immediately.','2026-06-05 11:12:00',NULL),
(8,72,'Battery life and performance are better than expected.','2026-06-19 17:46:00',NULL),
(27,98,'Very helpful for work, study, and entertainment.','2026-06-02 14:27:00',NULL),
(36,102,'Simple setup and smooth experience overall.','2026-06-07 17:35:00',NULL),
(34,82,'Excellent quality and arrived in perfect condition.','2026-06-24 14:36:00',NULL),
(20,116,'Useful product and worth the price for daily use.','2026-06-01 20:02:00',NULL),
(37,69,'The build quality feels premium and reliable.','2026-06-07 20:59:00',NULL),
(17,91,'Fast delivery and product matched the description.','2026-06-12 11:19:00',NULL),
(42,80,'Good value compared with other options in the market.','2026-06-20 09:13:00',NULL),
(36,75,'I use it every day and it performs very well.','2026-06-08 09:26:00',NULL),
(16,88,'Packaging was clean and the item worked immediately.','2026-06-11 10:43:00',NULL),
(30,40,'Battery life and performance are better than expected.','2026-06-23 10:48:00',NULL),
(38,47,'Very helpful for work, study, and entertainment.','2026-06-06 08:13:00',NULL),
(39,63,'Simple setup and smooth experience overall.','2026-06-06 08:42:00',NULL),
(40,31,'Excellent quality and arrived in perfect condition.','2026-06-11 09:14:00',NULL),
(34,47,'Useful product and worth the price for daily use.','2026-06-09 21:12:00',NULL),
(29,29,'The build quality feels premium and reliable.','2026-06-17 22:09:00',NULL),
(32,21,'Fast delivery and product matched the description.','2026-06-17 15:24:00',NULL),
(26,59,'Good value compared with other options in the market.','2026-06-15 20:54:00',NULL),
(25,53,'I use it every day and it performs very well.','2026-06-18 19:48:00',NULL),
(44,87,'Packaging was clean and the item worked immediately.','2026-06-15 18:03:00',NULL),
(30,45,'Battery life and performance are better than expected.','2026-06-25 19:01:00',NULL),
(42,115,'Very helpful for work, study, and entertainment.','2026-06-12 18:19:00',NULL),
(41,29,'Simple setup and smooth experience overall.','2026-06-26 16:08:00',NULL),
(7,55,'Excellent quality and arrived in perfect condition.','2026-06-26 13:18:00',NULL),
(29,50,'Useful product and worth the price for daily use.','2026-06-27 21:50:00',NULL),
(31,19,'The build quality feels premium and reliable.','2026-06-05 12:41:00',NULL),
(35,53,'Fast delivery and product matched the description.','2026-06-14 17:42:00',NULL),
(37,99,'Good value compared with other options in the market.','2026-06-13 15:24:00',NULL),
(22,22,'I use it every day and it performs very well.','2026-06-26 11:53:00',NULL),
(28,67,'Packaging was clean and the item worked immediately.','2026-06-02 21:53:00',NULL),
(33,95,'Battery life and performance are better than expected.','2026-06-10 20:08:00',NULL),
(30,107,'Very helpful for work, study, and entertainment.','2026-06-28 11:47:00',NULL),
(6,72,'Simple setup and smooth experience overall.','2026-06-10 08:00:00',NULL),
(23,21,'Excellent quality and arrived in perfect condition.','2026-06-07 19:51:00',NULL),
(23,24,'Useful product and worth the price for daily use.','2026-06-25 12:34:00',NULL),
(17,47,'The build quality feels premium and reliable.','2026-06-22 15:34:00',NULL),
(17,68,'Fast delivery and product matched the description.','2026-06-01 18:08:00',NULL),
(43,63,'Good value compared with other options in the market.','2026-06-21 12:29:00',NULL),
(35,110,'I use it every day and it performs very well.','2026-06-14 22:33:00',NULL),
(28,3,'Packaging was clean and the item worked immediately.','2026-06-05 15:48:00',NULL),
(17,108,'Battery life and performance are better than expected.','2026-06-22 20:27:00',NULL),
(34,97,'Very helpful for work, study, and entertainment.','2026-06-03 21:58:00',NULL),
(8,30,'Simple setup and smooth experience overall.','2026-06-04 14:05:00',NULL),
(5,120,'Excellent quality and arrived in perfect condition.','2026-06-08 08:07:00',NULL),
(6,76,'Useful product and worth the price for daily use.','2026-06-08 17:59:00',NULL),
(24,59,'The build quality feels premium and reliable.','2026-06-25 13:27:00',NULL),
(31,103,'Fast delivery and product matched the description.','2026-06-08 14:05:00',NULL),
(32,8,'Good value compared with other options in the market.','2026-06-26 20:43:00',NULL),
(34,19,'I use it every day and it performs very well.','2026-06-10 09:59:00',NULL),
(19,86,'Packaging was clean and the item worked immediately.','2026-06-09 08:12:00',NULL),
(41,56,'Battery life and performance are better than expected.','2026-06-24 13:52:00',NULL),
(31,44,'Very helpful for work, study, and entertainment.','2026-06-20 14:48:00',NULL),
(42,44,'Simple setup and smooth experience overall.','2026-06-07 22:39:00',NULL),
(34,9,'Excellent quality and arrived in perfect condition.','2026-06-08 13:24:00',NULL),
(19,108,'Useful product and worth the price for daily use.','2026-06-18 18:28:00',NULL),
(27,44,'The build quality feels premium and reliable.','2026-06-14 09:07:00',NULL),
(42,23,'Fast delivery and product matched the description.','2026-06-12 16:09:00',NULL),
(28,49,'Good value compared with other options in the market.','2026-06-11 12:23:00',NULL),
(32,65,'I use it every day and it performs very well.','2026-06-20 15:04:00',NULL),
(36,114,'Packaging was clean and the item worked immediately.','2026-06-07 13:39:00',NULL),
(33,110,'Battery life and performance are better than expected.','2026-06-05 12:21:00',NULL),
(36,6,'Very helpful for work, study, and entertainment.','2026-06-17 13:41:00',NULL),
(44,13,'Simple setup and smooth experience overall.','2026-06-28 12:10:00',NULL),
(16,87,'Excellent quality and arrived in perfect condition.','2026-06-10 10:50:00',NULL),
(29,9,'Useful product and worth the price for daily use.','2026-06-27 19:14:00',NULL),
(18,82,'Fast delivery and product matched the description.','2026-06-07 17:11:00',NULL),
(30,59,'Good value compared with other options in the market.','2026-06-05 22:30:00',NULL),
(40,50,'I use it every day and it performs very well.','2026-06-12 12:45:00',NULL),
(23,31,'Packaging was clean and the item worked immediately.','2026-06-19 16:50:00',NULL),
(43,85,'Battery life and performance are better than expected.','2026-06-22 19:39:00',NULL),
(32,46,'Very helpful for work, study, and entertainment.','2026-06-13 12:08:00',NULL),
(29,116,'Excellent quality and arrived in perfect condition.','2026-06-16 10:44:00',NULL),
(37,74,'Useful product and worth the price for daily use.','2026-06-11 10:16:00',NULL),
(8,19,'The build quality feels premium and reliable.','2026-06-02 15:07:00',NULL),
(26,21,'Fast delivery and product matched the description.','2026-06-07 09:49:00',NULL),
(35,66,'Good value compared with other options in the market.','2026-06-16 12:38:00',NULL),
(23,19,'I use it every day and it performs very well.','2026-06-27 11:24:00',NULL),
(27,120,'Packaging was clean and the item worked immediately.','2026-06-17 21:23:00',NULL),
(19,117,'Battery life and performance are better than expected.','2026-06-07 20:54:00',NULL),
(26,34,'Very helpful for work, study, and entertainment.','2026-06-02 16:34:00',NULL),
(17,63,'Simple setup and smooth experience overall.','2026-06-20 16:11:00',NULL),
(8,56,'Excellent quality and arrived in perfect condition.','2026-06-10 08:31:00',NULL),
(36,11,'Useful product and worth the price for daily use.','2026-06-03 16:58:00',NULL),
(40,72,'The build quality feels premium and reliable.','2026-06-26 21:57:00',NULL),
(36,70,'Fast delivery and product matched the description.','2026-06-01 21:32:00',NULL),
(20,4,'Good value compared with other options in the market.','2026-06-11 20:04:00',NULL),
(28,40,'I use it every day and it performs very well.','2026-06-08 12:26:00',NULL),
(43,96,'Packaging was clean and the item worked immediately.','2026-06-13 16:05:00',NULL),
(7,112,'Battery life and performance are better than expected.','2026-06-10 18:23:00',NULL),
(33,11,'Very helpful for work, study, and entertainment.','2026-06-21 09:52:00',NULL),
(20,9,'Simple setup and smooth experience overall.','2026-06-20 10:08:00',NULL),
(43,69,'Excellent quality and arrived in perfect condition.','2026-06-06 10:35:00',NULL),
(33,29,'Useful product and worth the price for daily use.','2026-06-27 14:01:00',NULL),
(29,59,'The build quality feels premium and reliable.','2026-06-19 15:44:00',NULL),
(21,106,'Fast delivery and product matched the description.','2026-06-09 20:00:00',NULL),
(38,27,'Good value compared with other options in the market.','2026-06-06 08:59:00',NULL),
(33,38,'I use it every day and it performs very well.','2026-06-04 17:31:00',NULL),
(33,42,'Packaging was clean and the item worked immediately.','2026-06-02 17:19:00',NULL),
(44,80,'Battery life and performance are better than expected.','2026-06-18 22:00:00',NULL),
(31,18,'Very helpful for work, study, and entertainment.','2026-06-27 08:58:00',NULL),
(24,114,'Simple setup and smooth experience overall.','2026-06-04 09:04:00',NULL),
(38,105,'Excellent quality and arrived in perfect condition.','2026-06-22 13:17:00',NULL),
(38,29,'Useful product and worth the price for daily use.','2026-06-12 13:21:00',NULL),
(31,85,'The build quality feels premium and reliable.','2026-06-23 15:57:00',NULL),
(25,80,'Fast delivery and product matched the description.','2026-06-03 21:06:00',NULL),
(21,66,'I use it every day and it performs very well.','2026-06-07 18:57:00',NULL),
(35,91,'Packaging was clean and the item worked immediately.','2026-06-11 11:54:00',NULL),
(19,76,'Battery life and performance are better than expected.','2026-06-22 10:33:00',NULL),
(39,20,'Very helpful for work, study, and entertainment.','2026-06-04 11:41:00',NULL),
(22,33,'Simple setup and smooth experience overall.','2026-06-20 09:20:00',NULL),
(39,67,'Excellent quality and arrived in perfect condition.','2026-06-03 14:41:00',NULL),
(23,22,'Useful product and worth the price for daily use.','2026-06-28 22:36:00',NULL),
(20,11,'The build quality feels premium and reliable.','2026-06-18 19:10:00',NULL),
(21,6,'Fast delivery and product matched the description.','2026-06-08 15:46:00',NULL),
(19,21,'Good value compared with other options in the market.','2026-06-03 21:29:00',NULL),
(25,20,'I use it every day and it performs very well.','2026-06-16 20:58:00',NULL),
(26,68,'Battery life and performance are better than expected.','2026-06-28 18:16:00',NULL),
(36,21,'Very helpful for work, study, and entertainment.','2026-06-18 12:58:00',NULL),
(6,55,'Simple setup and smooth experience overall.','2026-06-20 08:28:00',NULL),
(19,26,'Excellent quality and arrived in perfect condition.','2026-06-21 14:49:00',NULL),
(22,87,'Useful product and worth the price for daily use.','2026-06-02 18:58:00',NULL),
(18,94,'The build quality feels premium and reliable.','2026-06-18 10:25:00',NULL),
(24,12,'Fast delivery and product matched the description.','2026-06-15 18:58:00',NULL),
(16,66,'Good value compared with other options in the market.','2026-06-06 20:18:00',NULL),
(21,118,'I use it every day and it performs very well.','2026-06-13 08:53:00',NULL),
(5,20,'Packaging was clean and the item worked immediately.','2026-06-04 09:00:00',NULL),
(34,34,'Battery life and performance are better than expected.','2026-06-10 19:22:00',NULL),
(21,57,'Very helpful for work, study, and entertainment.','2026-06-20 20:47:00',NULL),
(38,106,'Simple setup and smooth experience overall.','2026-06-26 14:23:00',NULL),
(34,14,'Excellent quality and arrived in perfect condition.','2026-06-04 13:17:00',NULL),
(31,114,'Useful product and worth the price for daily use.','2026-06-09 20:49:00',NULL),
(32,1,'The build quality feels premium and reliable.','2026-06-04 09:25:00',NULL),
(25,57,'Fast delivery and product matched the description.','2026-06-16 18:34:00',NULL),
(35,89,'I use it every day and it performs very well.','2026-06-03 09:56:00',NULL),
(21,78,'Packaging was clean and the item worked immediately.','2026-06-15 10:44:00',NULL),
(39,40,'Battery life and performance are better than expected.','2026-06-18 12:46:00',NULL),
(5,112,'Very helpful for work, study, and entertainment.','2026-06-07 13:31:00',NULL),
(31,97,'Simple setup and smooth experience overall.','2026-06-23 10:58:00',NULL);

INSERT INTO nps_ratings (user_id, product_id, rating_value, created_at) VALUES
(38,6,4,'2026-06-13 11:49:00'),
(25,23,5,'2026-06-03 19:17:00'),
(28,64,4,'2026-06-12 17:40:00'),
(43,59,5,'2026-06-12 11:21:00'),
(41,90,3,'2026-06-28 15:15:00'),
(21,60,5,'2026-06-18 13:11:00'),
(25,117,3,'2026-06-20 08:17:00'),
(32,23,4,'2026-06-08 20:00:00'),
(26,80,4,'2026-06-04 13:34:00'),
(6,119,4,'2026-06-11 22:50:00'),
(28,82,4,'2026-06-04 11:50:00'),
(41,63,4,'2026-06-21 13:16:00'),
(39,115,5,'2026-06-24 10:28:00'),
(7,36,4,'2026-06-11 21:32:00'),
(39,91,5,'2026-06-26 11:46:00'),
(27,4,5,'2026-06-22 08:44:00'),
(28,54,5,'2026-06-05 11:12:00'),
(8,72,3,'2026-06-19 17:46:00'),
(27,98,4,'2026-06-02 14:27:00'),
(36,102,5,'2026-06-07 17:35:00'),
(34,82,5,'2026-06-24 14:36:00'),
(20,116,3,'2026-06-01 20:02:00'),
(37,69,5,'2026-06-07 20:59:00'),
(17,91,5,'2026-06-12 11:19:00'),
(42,80,4,'2026-06-20 09:13:00'),
(36,75,5,'2026-06-08 09:26:00'),
(16,88,5,'2026-06-11 10:43:00'),
(30,40,4,'2026-06-23 10:48:00'),
(38,47,4,'2026-06-06 08:13:00'),
(39,63,4,'2026-06-06 08:42:00'),
(40,31,5,'2026-06-11 09:14:00'),
(34,47,4,'2026-06-09 21:12:00'),
(29,29,4,'2026-06-17 22:09:00'),
(32,21,4,'2026-06-17 15:24:00'),
(26,59,5,'2026-06-15 20:54:00'),
(25,53,4,'2026-06-18 19:48:00'),
(44,87,2,'2026-06-15 18:03:00'),
(30,45,5,'2026-06-25 19:01:00'),
(42,115,5,'2026-06-12 18:19:00'),
(41,29,5,'2026-06-26 16:08:00'),
(7,55,5,'2026-06-26 13:18:00'),
(29,50,3,'2026-06-27 21:50:00'),
(31,19,5,'2026-06-05 12:41:00'),
(35,53,5,'2026-06-14 17:42:00'),
(37,99,3,'2026-06-13 15:24:00'),
(22,22,5,'2026-06-26 11:53:00'),
(28,67,4,'2026-06-02 21:53:00'),
(33,95,3,'2026-06-10 20:08:00'),
(30,107,3,'2026-06-28 11:47:00'),
(6,72,4,'2026-06-10 08:00:00'),
(23,21,5,'2026-06-07 19:51:00'),
(23,24,3,'2026-06-25 12:34:00'),
(17,47,5,'2026-06-22 15:34:00'),
(17,68,4,'2026-06-01 18:08:00'),
(43,63,5,'2026-06-21 12:29:00'),
(35,110,5,'2026-06-14 22:33:00'),
(28,3,3,'2026-06-05 15:48:00'),
(17,108,4,'2026-06-22 20:27:00'),
(34,97,5,'2026-06-03 21:58:00'),
(8,30,4,'2026-06-04 14:05:00'),
(5,120,4,'2026-06-08 08:07:00'),
(6,76,4,'2026-06-08 17:59:00'),
(24,59,4,'2026-06-25 13:27:00'),
(31,103,4,'2026-06-08 14:05:00'),
(32,8,3,'2026-06-26 20:43:00'),
(34,19,4,'2026-06-10 09:59:00'),
(19,86,5,'2026-06-09 08:12:00'),
(41,56,5,'2026-06-24 13:52:00'),
(31,44,3,'2026-06-20 14:48:00'),
(42,44,4,'2026-06-07 22:39:00'),
(34,9,5,'2026-06-08 13:24:00'),
(19,108,4,'2026-06-18 18:28:00'),
(27,44,4,'2026-06-14 09:07:00'),
(42,23,4,'2026-06-12 16:09:00'),
(28,49,5,'2026-06-11 12:23:00'),
(32,65,5,'2026-06-20 15:04:00'),
(36,114,5,'2026-06-07 13:39:00'),
(33,110,5,'2026-06-05 12:21:00'),
(36,6,4,'2026-06-17 13:41:00'),
(44,13,4,'2026-06-28 12:10:00'),
(16,87,5,'2026-06-10 10:50:00'),
(29,9,5,'2026-06-27 19:14:00'),
(18,82,4,'2026-06-07 17:11:00'),
(30,59,5,'2026-06-05 22:30:00'),
(40,50,3,'2026-06-12 12:45:00'),
(23,31,4,'2026-06-19 16:50:00'),
(43,85,4,'2026-06-22 19:39:00'),
(32,46,5,'2026-06-13 12:08:00'),
(29,116,4,'2026-06-16 10:44:00'),
(37,74,5,'2026-06-11 10:16:00'),
(8,19,4,'2026-06-02 15:07:00'),
(26,21,4,'2026-06-07 09:49:00'),
(35,66,5,'2026-06-16 12:38:00'),
(23,19,3,'2026-06-27 11:24:00'),
(27,120,4,'2026-06-17 21:23:00'),
(19,117,4,'2026-06-07 20:54:00'),
(26,34,3,'2026-06-02 16:34:00'),
(17,63,3,'2026-06-20 16:11:00'),
(8,56,5,'2026-06-10 08:31:00'),
(36,11,3,'2026-06-03 16:58:00'),
(40,72,4,'2026-06-26 21:57:00'),
(36,70,5,'2026-06-01 21:32:00'),
(20,4,4,'2026-06-11 20:04:00'),
(28,40,5,'2026-06-08 12:26:00'),
(43,96,4,'2026-06-13 16:05:00'),
(7,112,4,'2026-06-10 18:23:00'),
(33,11,5,'2026-06-21 09:52:00'),
(20,9,4,'2026-06-20 10:08:00'),
(43,69,4,'2026-06-06 10:35:00'),
(33,29,4,'2026-06-27 14:01:00'),
(29,59,5,'2026-06-19 15:44:00'),
(21,106,5,'2026-06-09 20:00:00'),
(38,27,4,'2026-06-06 08:59:00'),
(33,38,5,'2026-06-04 17:31:00'),
(33,42,4,'2026-06-02 17:19:00'),
(44,80,5,'2026-06-18 22:00:00'),
(31,18,5,'2026-06-27 08:58:00'),
(24,114,4,'2026-06-04 09:04:00'),
(38,105,4,'2026-06-22 13:17:00'),
(38,29,5,'2026-06-12 13:21:00'),
(31,85,5,'2026-06-23 15:57:00'),
(25,80,4,'2026-06-03 21:06:00'),
(21,66,4,'2026-06-07 18:57:00'),
(35,91,3,'2026-06-11 11:54:00'),
(19,76,4,'2026-06-22 10:33:00'),
(39,20,5,'2026-06-04 11:41:00'),
(22,33,4,'2026-06-20 09:20:00'),
(39,67,5,'2026-06-03 14:41:00'),
(23,22,4,'2026-06-28 22:36:00'),
(20,11,4,'2026-06-18 19:10:00'),
(21,6,3,'2026-06-08 15:46:00'),
(19,21,4,'2026-06-03 21:29:00'),
(25,20,2,'2026-06-16 20:58:00'),
(26,68,5,'2026-06-28 18:16:00'),
(36,21,3,'2026-06-18 12:58:00'),
(6,55,4,'2026-06-20 08:28:00'),
(19,26,3,'2026-06-21 14:49:00'),
(22,87,5,'2026-06-02 18:58:00'),
(18,94,5,'2026-06-18 10:25:00'),
(24,12,4,'2026-06-15 18:58:00'),
(16,66,5,'2026-06-06 20:18:00'),
(21,118,5,'2026-06-13 08:53:00'),
(5,20,5,'2026-06-04 09:00:00'),
(34,34,5,'2026-06-10 19:22:00'),
(21,57,5,'2026-06-20 20:47:00'),
(38,106,3,'2026-06-26 14:23:00'),
(34,14,5,'2026-06-04 13:17:00'),
(31,114,4,'2026-06-09 20:49:00'),
(32,1,5,'2026-06-04 09:25:00'),
(25,57,3,'2026-06-16 18:34:00'),
(35,89,3,'2026-06-03 09:56:00'),
(21,78,2,'2026-06-15 10:44:00'),
(39,40,5,'2026-06-18 12:46:00'),
(5,112,5,'2026-06-07 13:31:00'),
(31,97,5,'2026-06-23 10:58:00');

INSERT INTO nps_product_views (product_id, user_id, view_date) VALUES
(9,16,'2026-06-20 10:24:00'),
(1,NULL,'2026-06-09 11:04:00'),
(40,NULL,'2026-06-01 03:21:00'),
(55,NULL,'2026-06-07 01:26:00'),
(18,NULL,'2026-06-25 22:39:00'),
(105,NULL,'2026-06-28 07:18:00'),
(14,NULL,'2026-06-26 21:41:00'),
(93,6,'2026-06-26 11:34:00'),
(110,NULL,'2026-06-07 05:27:00'),
(80,NULL,'2026-06-28 02:59:00'),
(97,41,'2026-06-06 04:40:00'),
(23,32,'2026-06-14 03:11:00'),
(95,NULL,'2026-06-15 03:58:00'),
(8,NULL,'2026-06-02 01:32:00'),
(94,NULL,'2026-06-11 22:00:00'),
(40,NULL,'2026-06-14 20:26:00'),
(111,NULL,'2026-06-28 03:02:00'),
(63,17,'2026-06-02 15:57:00'),
(83,30,'2026-06-21 16:30:00'),
(64,26,'2026-06-18 15:25:00'),
(17,5,'2026-06-15 10:10:00'),
(93,41,'2026-06-09 16:38:00'),
(94,24,'2026-06-05 05:53:00'),
(80,NULL,'2026-06-16 17:51:00'),
(113,25,'2026-06-20 22:26:00'),
(85,22,'2026-06-22 11:57:00'),
(71,22,'2026-06-15 05:01:00'),
(119,26,'2026-06-26 19:16:00'),
(92,29,'2026-06-15 10:40:00'),
(59,15,'2026-06-23 19:00:00'),
(16,44,'2026-06-28 03:34:00'),
(65,20,'2026-06-25 06:34:00'),
(115,6,'2026-06-27 03:27:00'),
(50,6,'2026-06-20 14:54:00'),
(103,7,'2026-06-06 11:03:00'),
(26,17,'2026-06-09 21:59:00'),
(82,35,'2026-06-18 06:43:00'),
(51,NULL,'2026-06-08 03:59:00'),
(21,NULL,'2026-06-16 19:29:00'),
(113,23,'2026-06-25 05:40:00'),
(15,16,'2026-06-25 18:09:00'),
(56,35,'2026-06-12 03:32:00'),
(50,42,'2026-06-20 21:27:00'),
(8,20,'2026-06-09 15:28:00'),
(16,32,'2026-06-10 00:13:00'),
(31,37,'2026-06-20 19:52:00'),
(17,NULL,'2026-06-13 00:32:00'),
(120,30,'2026-06-08 04:02:00'),
(2,39,'2026-06-25 19:39:00'),
(62,NULL,'2026-06-12 03:40:00'),
(97,NULL,'2026-06-09 18:51:00'),
(80,42,'2026-06-06 04:52:00'),
(63,7,'2026-06-04 14:23:00'),
(41,36,'2026-06-08 18:26:00'),
(111,31,'2026-06-04 07:18:00'),
(61,NULL,'2026-06-27 12:52:00'),
(39,20,'2026-06-03 22:48:00'),
(30,NULL,'2026-06-07 23:09:00'),
(99,37,'2026-06-04 09:54:00'),
(120,NULL,'2026-06-19 03:17:00'),
(56,20,'2026-06-21 07:20:00'),
(54,NULL,'2026-06-23 13:11:00'),
(88,26,'2026-06-09 09:18:00'),
(116,27,'2026-06-16 04:17:00'),
(19,44,'2026-06-07 16:47:00'),
(30,NULL,'2026-06-20 10:00:00'),
(2,43,'2026-06-26 20:45:00'),
(16,22,'2026-06-12 15:47:00'),
(79,18,'2026-06-10 17:52:00'),
(18,38,'2026-06-24 22:25:00'),
(54,34,'2026-06-11 11:29:00'),
(91,NULL,'2026-06-24 19:22:00'),
(79,5,'2026-06-15 05:36:00'),
(83,NULL,'2026-06-01 11:34:00'),
(66,37,'2026-06-21 17:50:00'),
(47,20,'2026-06-22 19:47:00'),
(41,43,'2026-06-19 13:14:00'),
(18,28,'2026-06-17 13:00:00'),
(12,38,'2026-06-22 15:16:00'),
(95,NULL,'2026-06-27 22:24:00'),
(62,NULL,'2026-06-15 11:39:00'),
(90,NULL,'2026-06-04 18:38:00'),
(107,16,'2026-06-06 14:55:00'),
(16,7,'2026-06-17 04:30:00'),
(64,35,'2026-06-08 07:59:00'),
(30,40,'2026-06-10 23:59:00'),
(120,37,'2026-06-24 08:36:00'),
(87,43,'2026-06-04 22:25:00'),
(50,NULL,'2026-06-02 22:13:00'),
(11,NULL,'2026-06-03 05:32:00'),
(116,43,'2026-06-22 03:02:00'),
(120,25,'2026-06-18 08:43:00'),
(84,6,'2026-06-09 00:52:00'),
(55,23,'2026-06-17 00:21:00'),
(89,31,'2026-06-02 21:51:00'),
(4,37,'2026-06-15 15:01:00'),
(77,38,'2026-06-17 23:16:00'),
(50,NULL,'2026-06-01 16:28:00'),
(104,34,'2026-06-27 12:32:00'),
(119,19,'2026-06-27 11:35:00'),
(44,43,'2026-06-19 04:35:00'),
(78,NULL,'2026-06-18 06:03:00'),
(34,25,'2026-06-04 19:17:00'),
(21,NULL,'2026-06-02 16:37:00'),
(9,NULL,'2026-06-13 00:04:00'),
(53,27,'2026-06-04 16:14:00'),
(33,44,'2026-06-15 20:40:00'),
(66,43,'2026-06-20 13:23:00'),
(72,31,'2026-06-13 01:31:00'),
(100,7,'2026-06-18 21:27:00'),
(69,18,'2026-06-01 11:36:00'),
(13,33,'2026-06-10 08:47:00'),
(92,29,'2026-06-22 07:35:00'),
(91,NULL,'2026-06-10 13:05:00'),
(52,28,'2026-06-21 11:06:00'),
(33,NULL,'2026-06-17 15:06:00'),
(111,5,'2026-06-11 14:53:00'),
(86,15,'2026-06-17 14:48:00'),
(1,31,'2026-06-21 23:12:00'),
(43,19,'2026-06-16 05:28:00'),
(110,NULL,'2026-06-26 12:47:00'),
(43,NULL,'2026-06-01 18:10:00'),
(113,18,'2026-06-08 02:23:00'),
(49,16,'2026-06-10 14:40:00'),
(57,NULL,'2026-06-26 03:16:00'),
(59,36,'2026-06-28 01:11:00'),
(104,NULL,'2026-06-23 06:35:00'),
(75,43,'2026-06-24 21:19:00'),
(88,23,'2026-06-22 11:14:00'),
(73,NULL,'2026-06-02 13:45:00'),
(68,7,'2026-06-22 03:09:00'),
(64,41,'2026-06-09 18:20:00'),
(118,27,'2026-06-28 07:33:00'),
(107,NULL,'2026-06-04 02:59:00'),
(85,NULL,'2026-06-17 05:55:00'),
(70,NULL,'2026-06-11 13:00:00'),
(50,NULL,'2026-06-20 01:31:00'),
(47,39,'2026-06-09 12:08:00'),
(18,40,'2026-06-06 20:37:00'),
(89,31,'2026-06-10 20:06:00'),
(15,29,'2026-06-20 15:35:00'),
(15,17,'2026-06-17 00:52:00'),
(24,32,'2026-06-23 11:18:00'),
(79,NULL,'2026-06-19 20:39:00'),
(27,NULL,'2026-06-06 13:19:00'),
(15,NULL,'2026-06-19 13:50:00'),
(95,43,'2026-06-19 05:32:00'),
(50,19,'2026-06-16 04:00:00'),
(41,NULL,'2026-06-05 01:40:00'),
(48,NULL,'2026-06-25 12:47:00'),
(101,27,'2026-06-10 16:04:00'),
(83,29,'2026-06-22 03:00:00'),
(39,NULL,'2026-06-10 17:36:00'),
(66,42,'2026-06-27 12:19:00'),
(19,NULL,'2026-06-03 16:19:00'),
(36,39,'2026-06-06 00:21:00'),
(13,23,'2026-06-25 15:08:00'),
(47,NULL,'2026-06-22 01:41:00'),
(62,44,'2026-06-20 08:38:00'),
(94,NULL,'2026-06-08 12:49:00'),
(98,NULL,'2026-06-04 01:34:00'),
(104,NULL,'2026-06-16 04:43:00'),
(7,NULL,'2026-06-07 14:34:00'),
(24,18,'2026-06-06 21:59:00'),
(19,32,'2026-06-07 00:41:00'),
(9,22,'2026-06-12 11:26:00'),
(104,NULL,'2026-06-06 04:48:00'),
(82,25,'2026-06-08 14:52:00'),
(97,NULL,'2026-06-09 02:22:00'),
(80,8,'2026-06-18 20:58:00'),
(91,5,'2026-06-11 23:32:00'),
(95,35,'2026-06-05 02:58:00'),
(34,NULL,'2026-06-15 03:19:00'),
(22,NULL,'2026-06-10 19:41:00'),
(82,NULL,'2026-06-15 04:01:00'),
(36,NULL,'2026-06-06 16:04:00'),
(95,37,'2026-06-06 01:38:00'),
(108,NULL,'2026-06-07 22:46:00'),
(28,NULL,'2026-06-13 21:57:00'),
(37,27,'2026-06-27 19:35:00'),
(7,NULL,'2026-06-19 01:34:00'),
(40,41,'2026-06-03 04:35:00'),
(115,NULL,'2026-06-03 16:21:00'),
(48,NULL,'2026-06-26 18:54:00'),
(82,36,'2026-06-13 03:57:00'),
(37,NULL,'2026-06-14 21:18:00'),
(44,26,'2026-06-12 22:33:00'),
(62,NULL,'2026-06-02 05:04:00'),
(86,19,'2026-06-03 18:33:00'),
(2,5,'2026-06-14 11:55:00'),
(9,24,'2026-06-10 21:18:00'),
(20,NULL,'2026-06-11 03:39:00'),
(39,NULL,'2026-06-07 05:45:00'),
(5,41,'2026-06-07 12:36:00'),
(61,29,'2026-06-01 11:09:00'),
(39,7,'2026-06-19 07:21:00'),
(10,NULL,'2026-06-17 11:51:00'),
(42,22,'2026-06-08 08:59:00'),
(29,30,'2026-06-24 06:17:00'),
(66,NULL,'2026-06-14 08:21:00'),
(33,44,'2026-06-02 14:49:00'),
(44,23,'2026-06-24 08:25:00'),
(88,23,'2026-06-19 08:03:00'),
(54,38,'2026-06-09 16:35:00'),
(25,25,'2026-06-13 20:32:00'),
(41,26,'2026-06-23 13:06:00'),
(80,42,'2026-06-03 05:13:00'),
(4,27,'2026-06-11 17:43:00'),
(89,NULL,'2026-06-07 19:52:00'),
(31,8,'2026-06-16 03:20:00'),
(44,28,'2026-06-22 14:40:00'),
(20,NULL,'2026-06-08 12:04:00'),
(90,NULL,'2026-06-28 18:55:00'),
(59,25,'2026-06-22 05:14:00'),
(94,6,'2026-06-08 11:53:00'),
(26,35,'2026-06-24 23:46:00'),
(15,34,'2026-06-08 04:12:00'),
(51,22,'2026-06-06 11:42:00'),
(72,26,'2026-06-24 17:07:00'),
(96,NULL,'2026-06-16 08:30:00'),
(13,NULL,'2026-06-04 06:59:00'),
(72,29,'2026-06-02 19:59:00'),
(95,23,'2026-06-07 08:02:00'),
(111,NULL,'2026-06-06 11:23:00'),
(108,44,'2026-06-03 23:40:00'),
(61,27,'2026-06-16 08:35:00'),
(81,17,'2026-06-01 20:39:00'),
(54,37,'2026-06-11 13:04:00'),
(56,38,'2026-06-18 14:25:00'),
(119,NULL,'2026-06-19 17:14:00'),
(69,17,'2026-06-04 19:54:00'),
(69,NULL,'2026-06-27 03:06:00'),
(83,NULL,'2026-06-19 20:15:00'),
(84,34,'2026-06-27 03:49:00'),
(120,26,'2026-06-21 08:55:00'),
(107,16,'2026-06-04 01:32:00'),
(45,NULL,'2026-06-21 19:25:00'),
(2,NULL,'2026-06-25 02:57:00'),
(20,20,'2026-06-16 13:10:00'),
(76,38,'2026-06-28 02:59:00'),
(26,NULL,'2026-06-01 08:09:00'),
(78,NULL,'2026-06-10 12:12:00'),
(71,NULL,'2026-06-16 15:24:00'),
(62,37,'2026-06-19 05:15:00'),
(16,NULL,'2026-06-08 10:04:00'),
(110,NULL,'2026-06-14 02:38:00'),
(14,27,'2026-06-17 01:07:00'),
(43,43,'2026-06-06 12:48:00'),
(42,29,'2026-06-17 15:21:00'),
(12,17,'2026-06-18 18:50:00'),
(69,29,'2026-06-12 00:20:00'),
(88,32,'2026-06-08 23:28:00'),
(103,44,'2026-06-20 16:48:00'),
(8,NULL,'2026-06-23 06:51:00'),
(42,42,'2026-06-03 09:58:00'),
(92,17,'2026-06-28 19:10:00'),
(52,15,'2026-06-07 08:20:00'),
(112,NULL,'2026-06-16 10:26:00'),
(22,26,'2026-06-26 10:17:00'),
(107,34,'2026-06-22 13:10:00'),
(80,17,'2026-06-06 03:46:00'),
(78,24,'2026-06-28 08:42:00'),
(66,25,'2026-06-27 00:09:00'),
(37,43,'2026-06-18 02:29:00'),
(50,24,'2026-06-17 18:02:00'),
(98,NULL,'2026-06-08 03:58:00'),
(49,42,'2026-06-22 00:29:00'),
(37,44,'2026-06-10 20:41:00'),
(94,38,'2026-06-12 00:36:00'),
(92,NULL,'2026-06-18 00:08:00'),
(85,NULL,'2026-06-19 05:25:00'),
(74,25,'2026-06-04 01:44:00'),
(8,41,'2026-06-09 21:06:00'),
(81,18,'2026-06-22 23:08:00'),
(27,27,'2026-06-18 16:23:00'),
(47,25,'2026-06-24 21:31:00'),
(64,NULL,'2026-06-09 04:24:00'),
(110,NULL,'2026-06-06 01:35:00'),
(12,5,'2026-06-16 22:05:00'),
(26,NULL,'2026-06-16 00:18:00'),
(26,16,'2026-06-01 09:00:00'),
(108,NULL,'2026-06-23 02:49:00'),
(71,NULL,'2026-06-20 03:40:00'),
(36,NULL,'2026-06-10 22:59:00'),
(82,NULL,'2026-06-04 00:50:00'),
(43,NULL,'2026-06-02 04:04:00'),
(26,6,'2026-06-24 20:44:00'),
(22,31,'2026-06-12 01:13:00'),
(35,25,'2026-06-07 13:03:00'),
(34,36,'2026-06-05 06:32:00'),
(109,34,'2026-06-28 18:31:00'),
(10,NULL,'2026-06-04 03:05:00'),
(23,21,'2026-06-21 01:17:00'),
(33,38,'2026-06-19 08:56:00'),
(23,35,'2026-06-18 06:38:00'),
(76,27,'2026-06-25 02:53:00'),
(39,NULL,'2026-06-21 23:49:00'),
(109,NULL,'2026-06-26 18:56:00'),
(69,NULL,'2026-06-25 07:03:00'),
(15,36,'2026-06-27 04:59:00'),
(63,36,'2026-06-22 02:01:00'),
(68,NULL,'2026-06-11 22:49:00'),
(54,NULL,'2026-06-24 08:26:00'),
(104,NULL,'2026-06-13 16:17:00'),
(32,27,'2026-06-01 07:19:00'),
(104,19,'2026-06-08 12:24:00'),
(23,NULL,'2026-06-23 16:18:00'),
(112,37,'2026-06-11 02:21:00'),
(108,29,'2026-06-05 21:48:00'),
(13,43,'2026-06-17 07:07:00'),
(9,24,'2026-06-18 00:08:00'),
(95,NULL,'2026-06-09 14:01:00'),
(57,33,'2026-06-23 22:08:00'),
(94,44,'2026-06-03 06:47:00'),
(78,NULL,'2026-06-17 08:13:00'),
(107,34,'2026-06-28 19:56:00'),
(108,23,'2026-06-21 05:07:00'),
(12,15,'2026-06-19 21:18:00'),
(89,31,'2026-06-24 19:03:00'),
(94,31,'2026-06-19 13:20:00'),
(23,17,'2026-06-24 11:19:00'),
(109,43,'2026-06-07 04:46:00'),
(8,34,'2026-06-01 08:37:00'),
(52,29,'2026-06-10 09:23:00'),
(15,40,'2026-06-03 09:32:00'),
(99,NULL,'2026-06-17 03:03:00'),
(37,41,'2026-06-28 13:15:00'),
(85,NULL,'2026-06-19 22:20:00'),
(91,25,'2026-06-20 10:44:00'),
(22,NULL,'2026-06-17 07:13:00'),
(37,39,'2026-06-21 21:38:00'),
(102,17,'2026-06-23 07:49:00'),
(5,NULL,'2026-06-22 08:44:00'),
(44,16,'2026-06-05 05:22:00'),
(4,NULL,'2026-06-07 05:56:00'),
(47,NULL,'2026-06-13 00:07:00'),
(77,15,'2026-06-28 06:22:00'),
(110,33,'2026-06-17 10:38:00'),
(40,NULL,'2026-06-02 09:35:00'),
(36,NULL,'2026-06-01 23:54:00'),
(112,37,'2026-06-10 17:12:00'),
(116,22,'2026-06-12 09:57:00'),
(75,17,'2026-06-17 03:21:00'),
(33,37,'2026-06-10 03:38:00'),
(68,41,'2026-06-04 14:07:00'),
(34,NULL,'2026-06-08 03:33:00'),
(4,32,'2026-06-03 04:28:00'),
(6,NULL,'2026-06-13 04:22:00'),
(114,7,'2026-06-18 22:16:00'),
(14,29,'2026-06-25 08:28:00'),
(8,35,'2026-06-26 21:45:00'),
(35,7,'2026-06-23 08:59:00'),
(108,25,'2026-06-08 06:40:00'),
(28,NULL,'2026-06-28 12:49:00'),
(40,39,'2026-06-21 06:48:00'),
(11,23,'2026-06-05 04:40:00'),
(8,NULL,'2026-06-13 10:54:00'),
(110,6,'2026-06-11 00:13:00'),
(16,18,'2026-06-06 04:59:00'),
(104,NULL,'2026-06-26 07:27:00'),
(74,40,'2026-06-14 10:29:00'),
(105,NULL,'2026-06-20 05:23:00'),
(89,38,'2026-06-28 07:42:00'),
(79,39,'2026-06-10 16:45:00'),
(73,NULL,'2026-06-07 02:57:00'),
(29,NULL,'2026-06-28 06:19:00'),
(11,NULL,'2026-06-11 15:40:00'),
(72,NULL,'2026-06-01 23:22:00'),
(101,26,'2026-06-08 10:56:00'),
(45,38,'2026-06-13 10:25:00'),
(104,21,'2026-06-14 07:18:00'),
(77,33,'2026-06-27 05:10:00'),
(4,32,'2026-06-20 18:14:00'),
(120,NULL,'2026-06-10 13:33:00'),
(112,NULL,'2026-06-03 05:12:00'),
(58,20,'2026-06-28 06:17:00'),
(110,33,'2026-06-26 10:07:00'),
(42,23,'2026-06-11 00:15:00'),
(55,41,'2026-06-11 22:00:00'),
(106,NULL,'2026-06-11 20:39:00'),
(39,NULL,'2026-06-15 02:07:00'),
(71,NULL,'2026-06-09 13:09:00'),
(83,24,'2026-06-10 22:46:00'),
(27,NULL,'2026-06-19 00:10:00'),
(83,8,'2026-06-15 01:20:00'),
(79,NULL,'2026-06-19 13:45:00'),
(105,NULL,'2026-06-24 22:26:00'),
(110,NULL,'2026-06-20 23:45:00'),
(59,39,'2026-06-10 04:13:00'),
(16,24,'2026-06-22 11:33:00'),
(18,21,'2026-06-13 15:17:00'),
(105,NULL,'2026-06-16 06:50:00'),
(43,36,'2026-06-21 06:47:00'),
(35,32,'2026-06-26 00:16:00'),
(92,28,'2026-06-18 23:28:00'),
(73,23,'2026-06-17 05:29:00'),
(1,NULL,'2026-06-17 12:53:00'),
(9,21,'2026-06-07 14:57:00'),
(77,36,'2026-06-13 21:11:00'),
(88,29,'2026-06-09 16:48:00'),
(103,36,'2026-06-08 05:52:00'),
(69,8,'2026-06-07 03:10:00'),
(71,17,'2026-06-07 12:06:00'),
(14,NULL,'2026-06-04 23:48:00'),
(116,16,'2026-06-21 15:20:00'),
(57,NULL,'2026-06-16 21:08:00'),
(107,27,'2026-06-21 14:18:00'),
(81,NULL,'2026-06-10 13:04:00'),
(7,21,'2026-06-17 21:36:00'),
(95,42,'2026-06-04 17:42:00'),
(50,16,'2026-06-22 20:05:00'),
(10,5,'2026-06-23 05:52:00'),
(41,NULL,'2026-06-14 05:54:00'),
(59,25,'2026-06-28 22:52:00'),
(119,NULL,'2026-06-03 07:29:00'),
(93,36,'2026-06-05 02:59:00'),
(84,33,'2026-06-22 18:11:00'),
(44,35,'2026-06-24 16:44:00'),
(76,NULL,'2026-06-11 14:54:00'),
(99,37,'2026-06-10 02:13:00'),
(9,43,'2026-06-02 00:42:00'),
(98,NULL,'2026-06-06 15:10:00'),
(49,NULL,'2026-06-13 08:39:00'),
(21,17,'2026-06-05 03:41:00'),
(95,NULL,'2026-06-27 07:11:00'),
(112,42,'2026-06-20 16:59:00'),
(22,NULL,'2026-06-27 18:09:00'),
(42,NULL,'2026-06-24 12:16:00'),
(103,5,'2026-06-27 03:53:00'),
(79,6,'2026-06-22 17:35:00'),
(94,NULL,'2026-06-14 16:06:00'),
(61,30,'2026-06-16 06:53:00'),
(36,27,'2026-06-20 15:06:00'),
(41,37,'2026-06-03 12:46:00'),
(22,29,'2026-06-13 12:21:00'),
(43,NULL,'2026-06-04 06:35:00'),
(23,31,'2026-06-25 23:39:00'),
(38,42,'2026-06-01 19:12:00'),
(98,NULL,'2026-06-26 21:05:00'),
(49,41,'2026-06-16 23:47:00'),
(48,40,'2026-06-16 03:05:00'),
(92,15,'2026-06-21 08:35:00'),
(43,18,'2026-06-19 12:56:00'),
(77,33,'2026-06-11 18:00:00'),
(3,NULL,'2026-06-27 19:29:00'),
(114,NULL,'2026-06-09 15:31:00'),
(89,30,'2026-06-10 10:31:00'),
(46,NULL,'2026-06-15 07:59:00'),
(112,37,'2026-06-24 22:50:00'),
(71,37,'2026-06-09 10:23:00');

INSERT INTO nps_orders (order_id, buyer_id, order_date, order_status, total_amount, shipping_address, payment_method) VALUES
(6,26,'2026-06-17 08:40:00','confirmed',209.50,'Riffa, Bahrain','BenefitPay'),
(7,15,'2026-06-15 08:06:00','shipped',526.10,'Isa Town, Bahrain','BenefitPay'),
(8,26,'2026-06-22 22:11:00','confirmed',522.70,'Riffa, Bahrain','BenefitPay'),
(9,5,'2026-06-14 19:00:00','delivered',619.50,'Juffair, Bahrain','Apple Pay'),
(10,30,'2026-06-02 12:13:00','shipped',1786.70,'Saar, Bahrain','Cash on Delivery'),
(11,16,'2026-06-01 12:26:00','confirmed',149.70,'Hamad Town, Bahrain','Apple Pay'),
(12,17,'2026-06-25 22:39:00','delivered',119.70,'Juffair, Bahrain','Credit Card'),
(13,27,'2026-06-21 17:30:00','shipped',1646.70,'Isa Town, Bahrain','Cash on Delivery'),
(14,16,'2026-06-26 09:55:00','pending',19.90,'Hamad Town, Bahrain','Apple Pay'),
(15,5,'2026-06-18 10:55:00','cancelled',373.30,'Juffair, Bahrain','Credit Card'),
(16,30,'2026-06-16 14:47:00','pending',718.50,'Saar, Bahrain','Debit Card'),
(17,26,'2026-06-28 22:01:00','pending',98.50,'Riffa, Bahrain','Apple Pay'),
(18,39,'2026-06-04 20:34:00','pending',1134.10,'Isa Town, Bahrain','Cash on Delivery'),
(19,23,'2026-06-08 21:59:00','delivered',874.20,'Seef, Bahrain','Apple Pay'),
(20,5,'2026-06-03 22:46:00','delivered',94.50,'Juffair, Bahrain','Apple Pay'),
(21,26,'2026-06-14 15:15:00','pending',689.50,'Riffa, Bahrain','Credit Card'),
(22,6,'2026-06-15 22:53:00','delivered',1098.80,'Saar, Bahrain','Apple Pay'),
(23,29,'2026-06-20 16:03:00','confirmed',104.50,'Juffair, Bahrain','Credit Card'),
(24,34,'2026-06-22 21:26:00','pending',155.50,'Sitra, Bahrain','Debit Card'),
(25,7,'2026-06-08 11:39:00','confirmed',549.60,'Budaiya, Bahrain','BenefitPay'),
(26,40,'2026-06-03 22:06:00','pending',49.80,'Hamad Town, Bahrain','Apple Pay'),
(27,6,'2026-06-10 09:46:00','delivered',79.90,'Saar, Bahrain','Cash on Delivery'),
(28,34,'2026-06-18 09:38:00','delivered',819.20,'Sitra, Bahrain','Cash on Delivery'),
(29,38,'2026-06-01 13:16:00','cancelled',319.40,'Riffa, Bahrain','Debit Card'),
(30,7,'2026-06-18 21:13:00','shipped',539.70,'Budaiya, Bahrain','BenefitPay'),
(31,23,'2026-06-28 08:27:00','confirmed',853.30,'Seef, Bahrain','Debit Card'),
(32,42,'2026-06-19 11:26:00','delivered',978.00,'Saar, Bahrain','Credit Card'),
(33,20,'2026-06-19 19:09:00','delivered',139.50,'Sanad, Bahrain','Credit Card'),
(34,37,'2026-06-16 09:38:00','confirmed',1609.10,'Muharraq, Bahrain','Cash on Delivery'),
(35,29,'2026-06-23 10:07:00','confirmed',424.80,'Juffair, Bahrain','BenefitPay'),
(36,34,'2026-06-07 16:47:00','confirmed',799.90,'Sitra, Bahrain','BenefitPay'),
(37,22,'2026-06-23 10:42:00','delivered',935.40,'Sitra, Bahrain','BenefitPay'),
(38,20,'2026-06-24 11:15:00','confirmed',164.70,'Sanad, Bahrain','Cash on Delivery'),
(39,22,'2026-06-02 16:58:00','delivered',256.20,'Sitra, Bahrain','BenefitPay'),
(40,6,'2026-06-06 19:56:00','delivered',159.80,'Saar, Bahrain','Cash on Delivery'),
(41,31,'2026-06-03 12:19:00','delivered',399.30,'Budaiya, Bahrain','BenefitPay'),
(42,41,'2026-06-18 16:13:00','delivered',59.90,'Juffair, Bahrain','Debit Card'),
(43,30,'2026-06-13 14:06:00','confirmed',378.60,'Saar, Bahrain','BenefitPay'),
(44,17,'2026-06-07 17:29:00','pending',219.90,'Juffair, Bahrain','BenefitPay'),
(45,42,'2026-06-26 12:50:00','shipped',1016.70,'Saar, Bahrain','BenefitPay'),
(46,37,'2026-06-22 22:55:00','confirmed',139.70,'Muharraq, Bahrain','Debit Card'),
(47,44,'2026-06-03 18:40:00','cancelled',243.80,'Sanad, Bahrain','Credit Card'),
(48,25,'2026-06-22 11:32:00','delivered',469.60,'Muharraq, Bahrain','Credit Card'),
(49,40,'2026-06-08 21:19:00','delivered',1093.10,'Hamad Town, Bahrain','BenefitPay'),
(50,17,'2026-06-16 14:34:00','pending',874.30,'Juffair, Bahrain','BenefitPay'),
(51,27,'2026-06-24 21:02:00','pending',788.80,'Isa Town, Bahrain','Debit Card'),
(52,18,'2026-06-01 11:22:00','shipped',199.50,'Saar, Bahrain','Credit Card'),
(53,39,'2026-06-18 09:26:00','shipped',171.60,'Isa Town, Bahrain','Cash on Delivery'),
(54,43,'2026-06-16 22:43:00','cancelled',1217.70,'Budaiya, Bahrain','Cash on Delivery'),
(55,22,'2026-06-12 10:21:00','delivered',549.60,'Sitra, Bahrain','BenefitPay'),
(56,18,'2026-06-23 17:31:00','pending',1070.40,'Saar, Bahrain','BenefitPay'),
(57,6,'2026-06-15 18:11:00','delivered',59.90,'Saar, Bahrain','Credit Card'),
(58,31,'2026-06-15 20:52:00','confirmed',419.70,'Budaiya, Bahrain','Credit Card'),
(59,30,'2026-06-08 10:41:00','confirmed',1204.10,'Saar, Bahrain','Credit Card'),
(60,29,'2026-06-07 10:44:00','confirmed',168.70,'Juffair, Bahrain','Apple Pay'),
(61,17,'2026-06-07 22:08:00','confirmed',714.20,'Juffair, Bahrain','Apple Pay'),
(62,25,'2026-06-18 11:24:00','shipped',2449.60,'Muharraq, Bahrain','Apple Pay'),
(63,17,'2026-06-22 09:57:00','pending',344.50,'Juffair, Bahrain','BenefitPay'),
(64,32,'2026-06-18 14:01:00','shipped',1055.20,'Sanad, Bahrain','BenefitPay'),
(65,30,'2026-06-26 20:41:00','shipped',373.40,'Saar, Bahrain','Credit Card');

INSERT INTO nps_order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES
(6,110,1,89.90,89.90),
(6,48,2,39.90,79.80),
(6,41,2,19.90,39.80),
(7,8,2,119.90,239.80),
(7,113,3,8.90,26.70),
(7,15,1,79.90,79.90),
(7,82,3,59.90,179.70),
(8,100,1,34.90,34.90),
(8,31,2,169.00,338.00),
(8,83,1,69.90,69.90),
(8,15,1,79.90,79.90),
(9,44,3,179.90,539.70),
(9,59,2,39.90,79.80),
(10,2,2,279.90,559.80),
(10,26,1,649.90,649.90),
(10,3,2,239.50,479.00),
(10,60,2,49.00,98.00),
(11,61,1,109.90,109.90),
(11,41,2,19.90,39.80),
(12,34,3,39.90,119.70),
(13,25,3,489.00,1467.00),
(13,35,3,59.90,179.70),
(14,88,1,19.90,19.90),
(15,106,2,9.90,19.80),
(15,31,1,169.00,169.00),
(15,105,3,24.90,74.70),
(15,116,2,54.90,109.80),
(16,3,3,239.50,718.50),
(17,86,3,24.90,74.70),
(17,80,2,11.90,23.80),
(18,44,1,179.90,179.90),
(18,45,2,259.90,519.80),
(18,8,3,119.90,359.70),
(18,81,3,24.90,74.70),
(19,50,2,74.90,149.80),
(19,52,2,179.90,359.80),
(19,117,3,74.90,224.70),
(19,51,1,139.90,139.90),
(20,11,1,94.50,94.50),
(21,33,3,189.90,569.70),
(21,103,2,59.90,119.80),
(22,80,1,11.90,11.90),
(22,33,1,189.90,189.90),
(22,16,3,299.00,897.00),
(23,98,2,17.90,35.80),
(23,77,1,54.90,54.90),
(23,79,2,6.90,13.80),
(24,71,3,39.90,119.70),
(24,98,2,17.90,35.80),
(25,13,3,179.90,539.70),
(25,69,1,9.90,9.90),
(26,86,2,24.90,49.80),
(27,40,1,79.90,79.90),
(28,49,2,79.90,159.80),
(28,22,2,219.90,439.80),
(28,39,1,69.90,69.90),
(28,75,3,49.90,149.70),
(29,110,2,89.90,179.80),
(29,106,2,9.90,19.80),
(29,65,2,59.90,119.80),
(30,44,3,179.90,539.70),
(31,44,2,179.90,359.80),
(31,79,2,6.90,13.80),
(31,27,3,159.90,479.70),
(32,25,2,489.00,978.00),
(33,68,3,19.90,59.70),
(33,34,2,39.90,79.80),
(34,48,2,39.90,79.80),
(34,26,2,649.90,1299.80),
(34,85,2,9.90,19.80),
(34,47,3,69.90,209.70),
(35,7,1,365.00,365.00),
(35,19,2,29.90,59.80),
(36,21,1,799.90,799.90),
(37,4,1,319.00,319.00),
(37,110,1,89.90,89.90),
(37,113,3,8.90,26.70),
(37,29,2,249.90,499.80),
(38,114,3,54.90,164.70),
(39,106,2,9.90,19.80),
(39,35,2,59.90,119.80),
(39,119,3,18.90,56.70),
(39,82,1,59.90,59.90),
(40,40,2,79.90,159.80),
(41,8,2,119.90,239.80),
(41,75,2,49.90,99.80),
(41,97,3,19.90,59.70),
(42,35,1,59.90,59.90),
(43,87,1,18.90,18.90),
(43,8,3,119.90,359.70),
(44,22,1,219.90,219.90),
(45,31,3,169.00,507.00),
(45,38,3,169.90,509.70),
(46,104,1,39.90,39.90),
(46,56,2,49.90,99.80),
(47,66,1,39.90,39.90),
(47,31,1,169.00,169.00),
(47,57,1,34.90,34.90),
(48,74,2,14.90,29.80),
(48,22,2,219.90,439.80),
(49,18,3,54.90,164.70),
(49,120,1,119.00,119.00),
(49,69,3,9.90,29.70),
(49,45,3,259.90,779.70),
(50,97,2,19.90,39.80),
(50,117,1,74.90,74.90),
(50,49,2,79.90,159.80),
(50,24,2,299.90,599.80),
(51,11,2,94.50,189.00),
(51,24,2,299.90,599.80),
(52,109,1,39.90,39.90),
(52,36,1,69.90,69.90),
(52,19,3,29.90,89.70),
(53,61,1,109.90,109.90),
(53,112,1,29.90,29.90),
(53,80,1,11.90,11.90),
(53,97,1,19.90,19.90),
(54,25,2,489.00,978.00),
(54,88,1,19.90,19.90),
(54,32,2,109.90,219.80),
(55,116,2,54.90,109.80),
(55,22,2,219.90,439.80),
(56,87,3,18.90,56.70),
(56,4,3,319.00,957.00),
(56,119,3,18.90,56.70),
(57,35,1,59.90,59.90),
(58,51,3,139.90,419.70),
(59,74,3,14.90,44.70),
(59,34,1,39.90,39.90),
(59,27,3,159.90,479.70),
(59,28,2,319.90,639.80),
(60,66,1,39.90,39.90),
(60,119,1,18.90,18.90),
(60,61,1,109.90,109.90),
(61,48,3,39.90,119.70),
(61,109,1,39.90,39.90),
(61,50,1,74.90,74.90),
(61,62,3,159.90,479.70),
(62,56,1,49.90,49.90),
(62,21,3,799.90,2399.70),
(63,82,3,59.90,179.70),
(63,10,1,149.90,149.90),
(63,93,1,14.90,14.90),
(64,115,2,42.90,85.80),
(64,20,2,24.90,49.80),
(64,107,1,379.90,379.90),
(64,96,3,179.90,539.70),
(65,11,3,94.50,283.50),
(65,94,1,89.90,89.90);

INSERT INTO nps_admin_logs (admin_id, action_type, target_id, target_table, action_date) VALUES
(45,'UPDATE_USER_STATUS',16,'nps_users','2026-06-16 10:48:00'),
(45,'REVIEW_COMMENT',38,'nps_comments','2026-06-26 18:34:00'),
(1,'REVIEW_COMMENT',83,'nps_comments','2026-06-20 12:00:00'),
(45,'EXPORT_REPORT',4,'nps_reports','2026-06-12 16:45:00'),
(1,'UPDATE_USER_STATUS',4,'nps_users','2026-06-06 10:47:00'),
(45,'REVIEW_PRODUCT',84,'nps_products','2026-06-21 12:20:00'),
(46,'REVIEW_PRODUCT',4,'nps_products','2026-06-20 20:25:00'),
(46,'REVIEW_COMMENT',95,'nps_comments','2026-06-22 08:40:00'),
(45,'CREATE_USER',30,'nps_users','2026-06-27 12:48:00'),
(1,'CREATE_USER',7,'nps_users','2026-06-08 11:19:00'),
(45,'PUBLISH_PRODUCT',62,'nps_products','2026-06-21 11:43:00'),
(46,'EXPORT_REPORT',1,'nps_reports','2026-06-09 19:27:00'),
(45,'CREATE_USER',13,'nps_users','2026-06-07 20:54:00'),
(46,'VIEW_REPORT',2,'nps_reports','2026-06-21 16:08:00'),
(1,'REVIEW_PRODUCT',28,'nps_products','2026-06-07 16:10:00'),
(1,'VIEW_REPORT',3,'nps_reports','2026-06-08 08:54:00'),
(45,'EXPORT_REPORT',1,'nps_reports','2026-06-10 18:36:00'),
(1,'HIDE_PRODUCT',64,'nps_products','2026-06-16 14:24:00'),
(45,'REVIEW_PRODUCT',116,'nps_products','2026-06-09 11:22:00'),
(1,'HIDE_PRODUCT',43,'nps_products','2026-06-27 18:57:00'),
(1,'REVIEW_PRODUCT',33,'nps_products','2026-06-04 14:52:00'),
(46,'HIDE_PRODUCT',33,'nps_products','2026-06-04 15:19:00'),
(46,'VIEW_REPORT',1,'nps_reports','2026-06-04 09:32:00'),
(1,'VIEW_REPORT',5,'nps_reports','2026-06-23 08:33:00'),
(46,'PUBLISH_PRODUCT',27,'nps_products','2026-06-24 09:17:00'),
(45,'PUBLISH_PRODUCT',112,'nps_products','2026-06-28 12:53:00'),
(1,'HIDE_PRODUCT',50,'nps_products','2026-06-19 20:15:00'),
(1,'VIEW_REPORT',4,'nps_reports','2026-06-15 11:47:00'),
(1,'CREATE_USER',17,'nps_users','2026-06-08 20:39:00'),
(1,'REVIEW_COMMENT',159,'nps_comments','2026-06-08 11:05:00'),
(46,'EXPORT_REPORT',2,'nps_reports','2026-06-09 09:01:00'),
(45,'UPDATE_USER_STATUS',45,'nps_users','2026-06-25 16:17:00'),
(46,'EXPORT_REPORT',2,'nps_reports','2026-06-13 11:58:00'),
(46,'UPDATE_USER_STATUS',41,'nps_users','2026-06-26 14:47:00'),
(46,'CREATE_USER',25,'nps_users','2026-06-02 09:57:00'),
(1,'PUBLISH_PRODUCT',30,'nps_products','2026-06-21 17:45:00'),
(1,'HIDE_PRODUCT',102,'nps_products','2026-06-23 19:43:00'),
(45,'VIEW_REPORT',2,'nps_reports','2026-06-26 14:21:00'),
(1,'VIEW_REPORT',5,'nps_reports','2026-06-18 13:41:00'),
(45,'PUBLISH_PRODUCT',89,'nps_products','2026-06-17 19:00:00'),
(45,'PUBLISH_PRODUCT',68,'nps_products','2026-06-09 09:01:00'),
(45,'CREATE_USER',25,'nps_users','2026-06-23 14:38:00'),
(1,'CREATE_USER',38,'nps_users','2026-06-23 18:26:00'),
(46,'REVIEW_COMMENT',71,'nps_comments','2026-06-15 16:04:00'),
(45,'UPDATE_USER_STATUS',15,'nps_users','2026-06-27 09:12:00'),
(45,'UPDATE_USER_STATUS',2,'nps_users','2026-06-12 10:16:00'),
(46,'EXPORT_REPORT',3,'nps_reports','2026-06-13 09:38:00'),
(45,'UPDATE_USER_STATUS',4,'nps_users','2026-06-01 10:41:00'),
(46,'HIDE_PRODUCT',21,'nps_products','2026-06-12 12:38:00'),
(1,'EXPORT_REPORT',2,'nps_reports','2026-06-09 19:59:00'),
(1,'VIEW_REPORT',2,'nps_reports','2026-06-27 16:03:00'),
(45,'REVIEW_PRODUCT',67,'nps_products','2026-06-18 18:44:00'),
(45,'REVIEW_PRODUCT',26,'nps_products','2026-06-12 14:44:00'),
(1,'PUBLISH_PRODUCT',71,'nps_products','2026-06-05 18:33:00'),
(45,'CREATE_USER',34,'nps_users','2026-06-23 09:55:00'),
(1,'PUBLISH_PRODUCT',100,'nps_products','2026-06-23 10:30:00'),
(45,'REVIEW_COMMENT',57,'nps_comments','2026-06-13 14:42:00'),
(1,'HIDE_PRODUCT',79,'nps_products','2026-06-07 17:46:00'),
(1,'VIEW_REPORT',4,'nps_reports','2026-06-26 09:59:00'),
(46,'VIEW_REPORT',2,'nps_reports','2026-06-13 17:37:00'),
(46,'EXPORT_REPORT',4,'nps_reports','2026-06-15 12:59:00'),
(1,'UPDATE_USER_STATUS',36,'nps_users','2026-06-12 08:47:00'),
(45,'UPDATE_USER_STATUS',4,'nps_users','2026-06-16 20:50:00'),
(45,'HIDE_PRODUCT',106,'nps_products','2026-06-01 16:58:00'),
(46,'EXPORT_REPORT',2,'nps_reports','2026-06-16 08:01:00'),
(1,'REVIEW_COMMENT',98,'nps_comments','2026-06-24 16:10:00'),
(1,'REVIEW_COMMENT',123,'nps_comments','2026-06-03 17:33:00'),
(45,'CREATE_USER',22,'nps_users','2026-06-17 08:26:00'),
(45,'PUBLISH_PRODUCT',48,'nps_products','2026-06-13 10:12:00'),
(45,'UPDATE_USER_STATUS',24,'nps_users','2026-06-20 09:02:00'),
(1,'EXPORT_REPORT',4,'nps_reports','2026-06-26 16:06:00'),
(1,'REVIEW_COMMENT',67,'nps_comments','2026-06-13 10:28:00'),
(46,'HIDE_PRODUCT',115,'nps_products','2026-06-17 09:28:00'),
(46,'HIDE_PRODUCT',64,'nps_products','2026-06-25 13:50:00'),
(46,'UPDATE_USER_STATUS',15,'nps_users','2026-06-13 08:23:00'),
(46,'UPDATE_USER_STATUS',29,'nps_users','2026-06-21 14:51:00'),
(46,'UPDATE_USER_STATUS',12,'nps_users','2026-06-28 10:18:00'),
(45,'REVIEW_PRODUCT',96,'nps_products','2026-06-23 19:18:00'),
(45,'REVIEW_COMMENT',57,'nps_comments','2026-06-05 16:01:00'),
(46,'VIEW_REPORT',5,'nps_reports','2026-06-09 16:30:00'),
(46,'HIDE_PRODUCT',3,'nps_products','2026-06-18 09:42:00'),
(46,'CREATE_USER',14,'nps_users','2026-06-01 17:25:00'),
(45,'PUBLISH_PRODUCT',25,'nps_products','2026-06-11 19:37:00'),
(45,'REVIEW_COMMENT',136,'nps_comments','2026-06-19 08:48:00'),
(46,'EXPORT_REPORT',3,'nps_reports','2026-06-09 11:56:00'),
(46,'HIDE_PRODUCT',109,'nps_products','2026-06-07 17:52:00'),
(45,'CREATE_USER',39,'nps_users','2026-06-24 12:54:00'),
(46,'REVIEW_PRODUCT',20,'nps_products','2026-06-03 12:26:00'),
(45,'CREATE_USER',38,'nps_users','2026-06-02 08:41:00'),
(45,'REVIEW_COMMENT',71,'nps_comments','2026-06-14 17:16:00');

ALTER TABLE nps_users AUTO_INCREMENT = 47;
ALTER TABLE nps_categories AUTO_INCREMENT = 17;
ALTER TABLE nps_products AUTO_INCREMENT = 121;
ALTER TABLE nps_orders AUTO_INCREMENT = 66;
