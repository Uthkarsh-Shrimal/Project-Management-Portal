<?php
// Database configuration
$host = 'localhost';
$dbname = 'pmt_db';
$username = 'root';
$password = '';

// Create server connection first so database creation works even on fresh installs
$conn = mysqli_connect($host, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . $dbname . " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (mysqli_query($conn, $sql)) {
    mysqli_select_db($conn, $dbname);
} else {
    die("ERROR: Could not create database. " . mysqli_error($conn));
}

// Set charset to ensure proper handling of special characters
mysqli_set_charset($conn, "utf8mb4");

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS students (
        student_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        department VARCHAR(50) NOT NULL,
        year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS admins (
        admin_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        department VARCHAR(255) NOT NULL DEFAULT ''
    )",
    
    "CREATE TABLE IF NOT EXISTS projects (
        project_id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        student_id INT,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        synopsis_deadline DATE,
        final_deadline DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS evaluations (
        evaluation_id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT,
        marks DECIMAL(5,2),
        comments TEXT,
        evaluated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(project_id),
        FOREIGN KEY (evaluated_by) REFERENCES admins(admin_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS documents (
        doc_id INT PRIMARY KEY AUTO_INCREMENT,
        project_id INT,
        doc_type ENUM('synopsis', 'report', 'presentation', 'source_code'),
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(project_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS messages (
        message_id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT,
        receiver_id INT,
        sender_type ENUM('student', 'admin') DEFAULT NULL,
        receiver_type ENUM('student', 'admin') DEFAULT NULL,
        message TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $sql) {
    if (!mysqli_query($conn, $sql)) {
        die("ERROR: Could not create table. " . mysqli_error($conn));
    }
}

// Backward-compatibility migration for installs that created admins without department
$department_check = mysqli_query($conn, "SHOW COLUMNS FROM admins LIKE 'department'");
if ($department_check && mysqli_num_rows($department_check) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE admins ADD COLUMN department VARCHAR(255) NOT NULL DEFAULT '' AFTER created_at")) {
        die("ERROR: Could not update admins table. " . mysqli_error($conn));
    }
}

// Backward-compatibility migration: add message type columns if missing
$sender_type_check = mysqli_query($conn, "SHOW COLUMNS FROM messages LIKE 'sender_type'");
if ($sender_type_check && mysqli_num_rows($sender_type_check) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE messages ADD COLUMN sender_type ENUM('student', 'admin') DEFAULT NULL AFTER receiver_id")) {
        die("ERROR: Could not update messages table (sender_type). " . mysqli_error($conn));
    }
}

$receiver_type_check = mysqli_query($conn, "SHOW COLUMNS FROM messages LIKE 'receiver_type'");
if ($receiver_type_check && mysqli_num_rows($receiver_type_check) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE messages ADD COLUMN receiver_type ENUM('student', 'admin') DEFAULT NULL AFTER sender_type")) {
        die("ERROR: Could not update messages table (receiver_type). " . mysqli_error($conn));
    }
}

// Best-effort backfill for old messages where user IDs don't overlap.
mysqli_query($conn, "UPDATE messages m
    LEFT JOIN admins a ON m.sender_id = a.admin_id
    LEFT JOIN students s ON m.sender_id = s.student_id
    SET m.sender_type = CASE
        WHEN a.admin_id IS NOT NULL AND s.student_id IS NULL THEN 'admin'
        WHEN s.student_id IS NOT NULL AND a.admin_id IS NULL THEN 'student'
        ELSE m.sender_type
    END
    WHERE m.sender_type IS NULL");

mysqli_query($conn, "UPDATE messages m
    LEFT JOIN admins a ON m.receiver_id = a.admin_id
    LEFT JOIN students s ON m.receiver_id = s.student_id
    SET m.receiver_type = CASE
        WHEN a.admin_id IS NOT NULL AND s.student_id IS NULL THEN 'admin'
        WHEN s.student_id IS NOT NULL AND a.admin_id IS NULL THEN 'student'
        ELSE m.receiver_type
    END
    WHERE m.receiver_type IS NULL");
?>
