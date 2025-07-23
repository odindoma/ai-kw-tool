-- Создание базы данных
CREATE DATABASE IF NOT EXISTS excel_data_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE excel_data_db;

-- Таблица для хранения информации о загруженных документах
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    agent_type VARCHAR(50) NOT NULL,
    country_code VARCHAR(10) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    records_count INT DEFAULT 0,
    INDEX idx_agent_type (agent_type),
    INDEX idx_country_code (country_code),
    INDEX idx_upload_date (upload_date)
);

-- Таблица для хранения данных из Excel файлов
CREATE TABLE IF NOT EXISTS excel_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    keyword VARCHAR(500) NOT NULL,
    yahoo_show_rate VARCHAR(20),
    advertiser VARCHAR(500),
    est_rpc DECIMAL(10,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_document_id (document_id),
    INDEX idx_keyword (keyword(100)),
    INDEX idx_est_rpc (est_rpc),
    INDEX idx_advertiser (advertiser(100))
);

-- Создание пользователя для приложения (опционально)
-- CREATE USER IF NOT EXISTS 'excel_app'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON excel_data_db.* TO 'excel_app'@'localhost';
-- FLUSH PRIVILEGES;

