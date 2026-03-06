-- Create database if not exists
CREATE DATABASE IF NOT EXISTS pmt_db;
USE pmt_db;

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS admins;

-- Create students table
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create admins table
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    department VARCHAR(255) NOT NULL DEFAULT ''
);

-- Create projects table
CREATE TABLE projects (
    project_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    student_id INT,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    synopsis_deadline DATE,
    final_deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Create evaluations table
CREATE TABLE evaluations (
    evaluation_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    marks DECIMAL(5,2),
    comments TEXT,
    evaluated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (evaluated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- Create documents table
CREATE TABLE documents (
    doc_id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    doc_type ENUM('synopsis', 'report', 'presentation', 'source_code'),
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
);

-- Create messages table
CREATE TABLE messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT,
    receiver_id INT,
    sender_type ENUM('student', 'admin') DEFAULT NULL,
    receiver_type ENUM('student', 'admin') DEFAULT NULL,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- No seed data. Register new users and submit new projects through the app UI.
