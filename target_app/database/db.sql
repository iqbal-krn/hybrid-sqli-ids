CREATE DATABASE IF NOT EXISTS sqli_demo;
USE sqli_demo;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(50)
);

-- Masukkan akun admin dan 2 akun dummy untuk pengujian
INSERT INTO users (username, password) VALUES ('admin', 'rahasia123'),('iqbal', 'ganteng123'),('rizki', 'imut123');