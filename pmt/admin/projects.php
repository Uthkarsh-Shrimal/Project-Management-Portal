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
$projects = [];
$departments = [];
$years = [];
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query
$sql = "SELECT p.*, s.name as student_name, s.department, s.year,
        (SELECT COUNT(*) FROM documents WHERE project_id = p.project_id) as doc_count,
        (SELECT COUNT(*) FROM evaluations WHERE project_id = p.project_id) as eval_count
        FROM projects p 
        JOIN students s ON p.student_id = s.student_id
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
    $sql .= " AND (p.title LIKE ? OR s.name LIKE ? OR s.roll_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$sql .= " ORDER BY p.created_at DESC";

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
            $projects[] = $row;
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
echo "Number of projects found: " . count($projects) . "\n";
echo "Error message: " . $error . "\n";
echo "-->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Project Management System</title>
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
        .status-badge {
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
            <h2>Projects</h2>
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
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Project title, student name, or roll number">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="projects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Projects Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Project Title</th>
                        <th>Student</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Evaluations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-3"></i>
                                    <p class="mb-0">No projects found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($project['department']); ?></td>
                                <td><?php echo $project['year']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $project['status'] === 'pending' ? 'warning' : 
                                            ($project['status'] === 'approved' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($project['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $project['doc_count']; ?></td>
                                <td><?php echo $project['eval_count']; ?></td>
                                <td>
                                    <a href="view_project.php?id=<?php echo $project['project_id']; ?>" 
                                       class="btn btn-sm btn-info" title="View Project">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="evaluate_project.php?id=<?php echo $project['project_id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Evaluate Project">
                                        <i class="fas fa-star"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
