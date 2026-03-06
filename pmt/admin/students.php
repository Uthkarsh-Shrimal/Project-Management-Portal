<?php
session_start();
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Initialize variables
$students = [];
$departments = [];
$years = [];
$error = '';

// Get filter parameters
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM projects WHERE student_id = s.student_id) as project_count,
        (SELECT COUNT(*) FROM messages 
         WHERE (sender_type = 'student' AND sender_id = s.student_id)
            OR (receiver_type = 'student' AND receiver_id = s.student_id)) as message_count
        FROM students s
        WHERE 1=1";

$params = [];
$types = '';

// Add filters
if (!empty($department_filter)) {
    $sql .= " AND s.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if (!empty($year_filter)) {
    $sql .= " AND s.year = ?";
    $params[] = $year_filter;
    $types .= 'i';
}

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sql .= " ORDER BY s.name ASC";

// Debug information
echo "<!-- Debug Info:\n";
echo "SQL Query: " . $sql . "\n";
echo "Parameters: " . print_r($params, true) . "\n";
echo "Parameter Types: " . $types . "\n";
echo "-->";

// Execute the query
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
    } else {
        $error = "Error executing query: " . mysqli_stmt_error($stmt);
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Error preparing query: " . mysqli_error($conn);
}

// Get unique departments and years for filters
$sql = "SELECT DISTINCT department FROM students ORDER BY department";
if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row['department'];
    }
}

$sql = "SELECT DISTINCT year FROM students ORDER BY year";
if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $years[] = $row['year'];
    }
}

// Debug information
echo "<!-- Debug Info:\n";
echo "Number of students found: " . count($students) . "\n";
echo "Error message: " . $error . "\n";
echo "-->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .student-card {
            transition: transform 0.2s;
        }
        .student-card:hover {
            transform: translateY(-5px);
        }
        .stats-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Students</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">All</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <option value="">All</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" 
                                    <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="students.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Students Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (empty($students)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-user-graduate fa-3x mb-3"></i>
                            <p class="mb-0">No students found</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($students as $student): ?>
                    <div class="col">
                        <div class="card h-100 student-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($student['name']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($student['email']); ?><br>
                                    <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($student['department']); ?><br>
                                    <i class="fas fa-calendar me-2"></i>Year <?php echo $student['year']; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="badge bg-info stats-badge">
                                        <i class="fas fa-project-diagram me-1"></i>
                                        <?php echo $student['project_count']; ?> Projects
                                    </span>
                                    <span class="badge bg-secondary stats-badge">
                                        <i class="fas fa-comments me-1"></i>
                                        <?php echo $student['message_count']; ?> Messages
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <a href="view_student.php?id=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="messages.php?student=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Send Message">
                                        <i class="fas fa-envelope"></i> Message
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

