<?php
require_once __DIR__.'/config.php';

function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Error de conexión a BD: " . $conn->connect_error);
        die("Error en el sistema. Por favor intente más tarde.");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Crear tabla si no existe
$conn = get_db_connection();
$conn->query("
CREATE TABLE IF NOT EXISTS uploaded_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    file_path VARCHAR(512) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('pending','syncing','completed','failed') DEFAULT 'pending',
    sync_attempts INT DEFAULT 0,
    last_sync_time TIMESTAMP NULL,
    last_error TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)");
$conn->close();
?>
