CREATE DATABASE IF NOT EXISTS sqli_demo;
USE sqli_demo;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(50)
);

-- Tabel untuk tracking login history
CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username_input VARCHAR(255),
    password_input VARCHAR(255),
    nfa_score FLOAT,
    is_sqli_detected BOOLEAN,
    login_status VARCHAR(50),
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(50),
    description TEXT
);

-- Masukkan akun admin dan 2 akun dummy untuk pengujian
INSERT INTO users (username, password) VALUES ('admin', 'rahasia123'),('iqbal', 'ganteng123'),('rizki', 'imut123');