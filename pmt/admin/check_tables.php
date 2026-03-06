<?php
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Table Check</h2>";

// Check projects table
$sql = "SHOW TABLES LIKE 'projects'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo "<h3>Projects Table Structure:</h3>";
    $sql = "DESCRIBE projects";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";

    echo "<h3>Projects Table Data:</h3>";
    $sql = "SELECT * FROM projects";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Projects table does not exist!</p>";
}

// Check students table
$sql = "SHOW TABLES LIKE 'students'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo "<h3>Students Table Structure:</h3>";
    $sql = "DESCRIBE students";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";

    echo "<h3>Students Table Data:</h3>";
    $sql = "SELECT * FROM students";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Students table does not exist!</p>";
}

// Check documents table
$sql = "SHOW TABLES LIKE 'documents'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo "<h3>Documents Table Structure:</h3>";
    $sql = "DESCRIBE documents";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";

    echo "<h3>Documents Table Data:</h3>";
    $sql = "SELECT * FROM documents";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Documents table does not exist!</p>";
}

// Check evaluations table
$sql = "SHOW TABLES LIKE 'evaluations'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo "<h3>Evaluations Table Structure:</h3>";
    $sql = "DESCRIBE evaluations";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";

    echo "<h3>Evaluations Table Data:</h3>";
    $sql = "SELECT * FROM evaluations";
    $result = mysqli_query($conn, $sql);
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Evaluations table does not exist!</p>";
}

// Check for any SQL errors
if (mysqli_error($conn)) {
    echo "<p style='color: red;'>Database Error: " . mysqli_error($conn) . "</p>";
}
?> 