<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle new message submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        $error = "Message cannot be empty.";
    } else {
        $sql = "INSERT INTO messages (sender_id, receiver_id, sender_type, receiver_type, message) VALUES (?, ?, 'admin', 'student', ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", $admin_id, $receiver_id, $message);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Message sent successfully!";
                header("Location: messages.php?student=" . $receiver_id);
                exit();
            } else {
                $error = "Error sending message.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get success message from session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'You';

// Get all students
$students = array();
$sql = "SELECT s.student_id, s.name, s.email, s.department, s.year 
        FROM students s 
        ORDER BY s.name";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get conversation with selected student
$selected_student = isset($_GET['student']) ? (int)$_GET['student'] : null;
$messages = array();
$selected_student_name = '';

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
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Mark messages as read
    $sql = "UPDATE messages SET is_read = 1 
            WHERE receiver_type = 'admin' AND receiver_id = ? AND sender_type = 'student' AND sender_id = ? AND is_read = 0";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $admin_id, $selected_student);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .message-container {
            height: 400px;
            overflow-y: auto;
        }
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
        }
        .sent .message-content {
            background-color: #28a745;
            color: white;
        }
        .received .message-content {
            background-color: #f1f1f1;
        }
        .message-time {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
        .student-list {
            height: 400px;
            overflow-y: auto;
        }
        .student-item {
            cursor: pointer;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .student-item:hover {
            background-color: #f8f9fa;
        }
        .student-item.active {
            background-color: #e9ecef;
        }
        .unread-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        .student-info {
            font-size: 0.85em;
            color: #666;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-4">Messages</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Student List -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Students</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="student-list">
                                    <?php foreach ($students as $student): ?>
                                        <a href="?student=<?php echo $student['student_id']; ?>" 
                                           class="d-block text-decoration-none text-dark student-item <?php echo $selected_student == $student['student_id'] ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                                    <div class="student-info">
                                                        <?php echo htmlspecialchars($student['department']); ?> - Year <?php echo $student['year']; ?>
                                                    </div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                                <?php
                                                // Count unread messages
                                                $sql = "SELECT COUNT(*) as unread FROM messages 
                                                        WHERE sender_type = 'student' AND sender_id = ? AND receiver_type = 'admin' AND receiver_id = ? AND is_read = 0";
                                                if ($stmt = mysqli_prepare($conn, $sql)) {
                                                    mysqli_stmt_bind_param($stmt, "ii", $student['student_id'], $admin_id);
                                                    mysqli_stmt_execute($stmt);
                                                    $result = mysqli_stmt_get_result($stmt);
                                                    $unread = mysqli_fetch_assoc($result)['unread'];
                                                    mysqli_stmt_close($stmt);
                                                    
                                                    if ($unread > 0) {
                                                        echo '<span class="unread-badge">' . $unread . '</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <?php if ($selected_student): 
                                    foreach ($students as $student) {
                                        if ($student['student_id'] == $selected_student) {
                                            $selected_student_name = $student['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <h5 class="mb-0">Conversation with <?php echo htmlspecialchars($selected_student_name); ?></h5>
                                <?php else: ?>
                                    <h5 class="mb-0">Select a student to start conversation</h5>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($selected_student): ?>
                                    <!-- Messages Container -->
                                    <div class="message-container mb-3">
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="message <?php echo $msg['message_type']; ?>">
                                                <div class="message-content">
                                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <?php echo htmlspecialchars($msg['message_type'] === 'sent' ? $admin_name : $selected_student_name); ?>
                                                </div>
                                                <div class="message-time text-end">
                                                    <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Message Form -->
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?student=<?php echo $selected_student; ?>" method="post">
                                        <input type="hidden" name="receiver_id" value="<?php echo $selected_student; ?>">
                                        <div class="mb-3">
                                            <textarea class="form-control" name="message" rows="3" placeholder="Type your message..." required></textarea>
                                        </div>
                                        <button type="submit" name="send_message" class="btn btn-success">
                                            <i class="fas fa-paper-plane"></i> Send Message
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p>Select a student from the list to view and send messages.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom of messages container
        const messageContainer = document.querySelector('.message-container');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }

        // Function to fetch new messages
        function fetchNewMessages() {
            const selectedStudent = <?php echo $selected_student ? $selected_student : 'null'; ?>;
            if (!selectedStudent) return;

            fetch(`get_messages.php?student=${selectedStudent}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages) {
                        const adminName = <?php echo json_encode($admin_name); ?>;
                        const studentName = <?php echo json_encode($selected_student_name); ?>;
                        const escapeHtml = (str) => String(str)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                        const container = document.querySelector('.message-container');
                        const currentMessages = container.innerHTML;
                        const newMessages = data.messages.map(msg => `
                            <div class="message ${msg.message_type}">
                                <div class="message-content">
                                    ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
                                </div>
                                <div class="small text-muted mt-1">
                                    ${msg.message_type === 'sent' ? escapeHtml(adminName) : escapeHtml(studentName)}
                                </div>
                                <div class="message-time text-end">
                                    ${new Date(msg.created_at).toLocaleString()}
                                </div>
                            </div>
                        `).join('');
                        
                        if (currentMessages !== newMessages) {
                            container.innerHTML = newMessages;
                            container.scrollTop = container.scrollHeight;
                        }
                    }
                })
                .catch(error => console.error('Error fetching messages:', error));
        }

        // Check for new messages every 5 seconds
        setInterval(fetchNewMessages, 5000);
    </script>
</body>
</html> 

