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
$evaluations = [];
$departments = [];
$years = [];
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query
$sql = "SELECT e.*, p.title as project_title, p.status as project_status,
        s.name as student_name, s.department, s.year,
        a.name as evaluator_name
        FROM evaluations e
        JOIN projects p ON e.project_id = p.project_id
        JOIN students s ON p.student_id = s.student_id
        JOIN admins a ON e.evaluated_by = a.admin_id
        WHERE 1=1";

$params = [];
$types = '';

// Add filters
if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

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
    $sql .= " AND (p.title LIKE ? OR s.name LIKE ? OR a.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$sql .= " ORDER BY e.created_at DESC";

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
            $evaluations[] = $row;
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
echo "Number of evaluations found: " . count($evaluations) . "\n";
echo "Error message: " . $error . "\n";
echo "-->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluations - Project Management System</title>
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
        .evaluation-card {
            transition: transform 0.2s;
        }
        .evaluation-card:hover {
            transform: translateY(-5px);
        }
        .marks-badge {
            font-size: 1.1em;
            padding: 5px 15px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Project Evaluations</h2>
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
                           placeholder="Project title, student name, or evaluator">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Project Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="evaluations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Evaluations Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (empty($evaluations)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-star fa-3x mb-3"></i>
                            <p class="mb-0">No evaluations found</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($evaluations as $eval): ?>
                    <div class="col">
                        <div class="card h-100 evaluation-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($eval['project_title']); ?></h5>
                                <div class="mb-3">
                                    <span class="badge bg-<?php 
                                        echo $eval['project_status'] === 'pending' ? 'warning' : 
                                            ($eval['project_status'] === 'approved' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($eval['project_status']); ?>
                                    </span>
                                </div>
                                <p class="card-text">
                                    <strong>Student:</strong> <?php echo htmlspecialchars($eval['student_name']); ?><br>
                                    <strong>Department:</strong> <?php echo htmlspecialchars($eval['department']); ?><br>
                                    <strong>Year:</strong> <?php echo $eval['year']; ?><br>
                                    <strong>Evaluator:</strong> <?php echo htmlspecialchars($eval['evaluator_name']); ?><br>
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($eval['created_at'])); ?>
                                </p>
                                <div class="text-center my-3">
                                    <span class="badge bg-primary marks-badge">
                                        <?php echo $eval['marks']; ?>/100
                                    </span>
                                </div>
                                <p class="card-text">
                                    <strong>Comments:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($eval['comments'])); ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between">
                                    <a href="view_project.php?id=<?php echo $eval['project_id']; ?>" 
                                       class="btn btn-sm btn-info" title="View Project">
                                        <i class="fas fa-eye"></i> View Project
                                    </a>
                                    <a href="evaluate_project.php?id=<?php echo $eval['project_id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Re-evaluate">
                                        <i class="fas fa-star"></i> Re-evaluate
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
