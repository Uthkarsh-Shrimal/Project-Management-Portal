<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['user_id'];
$selected_student = isset($_GET['student']) ? (int)$_GET['student'] : null;

if ($selected_student) {
    $sql = "SELECT m.*, 
            CASE 
                WHEN m.sender_type = 'admin' AND m.sender_id = ? THEN 'sent'
                ELSE 'received'
            END as message_type
            FROM messages m 
            WHERE (m.sender_type = 'admin' AND m.sender_id = ? AND m.receiver_type = 'student' AND m.receiver_id = ?) 
            OR (m.sender_type = 'student' AND m.sender_id = ? AND m.receiver_type = 'admin' AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";
            
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiii", $admin_id, $admin_id, $selected_student, $selected_student, $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        // Mark messages as read
        $sql = "UPDATE messages SET is_read = 1 
                WHERE receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'student' AND sender_id = ? AND is_read = 0";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $admin_id, $selected_student);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['messages' => $messages]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No student selected']);
}
?> 
