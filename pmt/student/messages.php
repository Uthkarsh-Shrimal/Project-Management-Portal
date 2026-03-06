<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle new message submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        $error = "Message cannot be empty.";
    } else {
        $sql = "INSERT INTO messages (sender_id, receiver_id, sender_type, receiver_type, message) VALUES (?, ?, 'student', 'admin', ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", $student_id, $receiver_id, $message);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Message sent successfully!";
                header("Location: messages.php?faculty=" . $receiver_id);
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

$student_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'You';

// Get all faculty members
$faculty = array();
$sql = "SELECT admin_id, name, email FROM admins ORDER BY name";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $faculty[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get conversation with selected faculty
$selected_faculty = isset($_GET['faculty']) ? (int)$_GET['faculty'] : null;
$messages = array();
$selected_faculty_name = '';

if ($selected_faculty) {
    $sql = "SELECT m.*, 
            CASE 
                WHEN m.sender_type = 'student' AND m.sender_id = ? THEN 'sent'
                ELSE 'received'
            END as message_type
            FROM messages m 
            WHERE (m.sender_type = 'student' AND m.sender_id = ? AND m.receiver_type = 'admin' AND m.receiver_id = ?) 
            OR (m.sender_type = 'admin' AND m.sender_id = ? AND m.receiver_type = 'student' AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";
            
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiii", $student_id, $student_id, $selected_faculty, $selected_faculty, $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Mark messages as read
    $sql = "UPDATE messages SET is_read = 1 
            WHERE receiver_type = 'student' AND receiver_id = ? AND sender_type = 'admin' AND sender_id = ? AND is_read = 0";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $student_id, $selected_faculty);
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
            background-color: #007bff;
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
        .faculty-list {
            height: 400px;
            overflow-y: auto;
        }
        .faculty-item {
            cursor: pointer;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .faculty-item:hover {
            background-color: #f8f9fa;
        }
        .faculty-item.active {
            background-color: #e9ecef;
        }
        .unread-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.8em;
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
                    <!-- Faculty List -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Faculty Members</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="faculty-list">
                                    <?php foreach ($faculty as $member): ?>
                                        <a href="?faculty=<?php echo $member['admin_id']; ?>" 
                                           class="d-block text-decoration-none text-dark faculty-item <?php echo $selected_faculty == $member['admin_id'] ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($member['email']); ?></div>
                                                </div>
                                                <?php
                                                // Count unread messages
                                                $sql = "SELECT COUNT(*) as unread FROM messages 
                                                        WHERE sender_type = 'admin' AND sender_id = ? AND receiver_type = 'student' AND receiver_id = ? AND is_read = 0";
                                                if ($stmt = mysqli_prepare($conn, $sql)) {
                                                    mysqli_stmt_bind_param($stmt, "ii", $member['admin_id'], $student_id);
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
                                <?php if ($selected_faculty): 
                                    foreach ($faculty as $member) {
                                        if ($member['admin_id'] == $selected_faculty) {
                                            $selected_faculty_name = $member['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <h5 class="mb-0">Conversation with <?php echo htmlspecialchars($selected_faculty_name); ?></h5>
                                <?php else: ?>
                                    <h5 class="mb-0">Select a faculty member to start conversation</h5>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($selected_faculty): ?>
                                    <!-- Messages Container -->
                                    <div class="message-container mb-3">
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="message <?php echo $msg['message_type']; ?>">
                                                <div class="message-content">
                                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <?php echo htmlspecialchars($msg['message_type'] === 'sent' ? $student_name : $selected_faculty_name); ?>
                                                </div>
                                                <div class="message-time text-end">
                                                    <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Message Form -->
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?faculty=<?php echo $selected_faculty; ?>" method="post">
                                        <input type="hidden" name="receiver_id" value="<?php echo $selected_faculty; ?>">
                                        <div class="mb-3">
                                            <textarea class="form-control" name="message" rows="3" placeholder="Type your message..." required></textarea>
                                        </div>
                                        <button type="submit" name="send_message" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Message
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p>Select a faculty member from the list to view and send messages.</p>
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
            const selectedFaculty = <?php echo $selected_faculty ? $selected_faculty : 'null'; ?>;
            if (!selectedFaculty) return;

            fetch(`get_messages.php?faculty=${selectedFaculty}`)
                .then(response => response.json())
                .then(data => {
                    if (data.messages) {
                        const studentName = <?php echo json_encode($student_name); ?>;
                        const facultyName = <?php echo json_encode($selected_faculty_name); ?>;
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
                                    ${msg.message_type === 'sent' ? escapeHtml(studentName) : escapeHtml(facultyName)}
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

