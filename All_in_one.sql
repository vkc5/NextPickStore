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
