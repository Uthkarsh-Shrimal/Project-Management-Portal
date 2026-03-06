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
$admin_name = $_SESSION['name'];
$error = '';

// Fetch all projects with student information
$sql = "SELECT p.*, s.name as student_name, s.department, s.year,
        (SELECT COUNT(*) FROM documents WHERE project_id = p.project_id) as doc_count,
        (SELECT COUNT(*) FROM evaluations WHERE project_id = p.project_id) as eval_count
        FROM projects p 
        JOIN students s ON p.student_id = s.student_id
        ORDER BY p.created_at DESC";

$projects = [];
if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }
} else {
    $error = "Error fetching projects: " . mysqli_error($conn);
}

// Debug information
echo "<!-- Debug Info:\n";
echo "Number of projects found: " . count($projects) . "\n";
echo "Error message: " . $error . "\n";
echo "-->";

// Get statistics
$total_projects = count($projects);
$pending_projects = count(array_filter($projects, function($p) { return $p['status'] == 'pending'; }));
$approved_projects = count(array_filter($projects, function($p) { return $p['status'] == 'approved'; }));
$rejected_projects = count(array_filter($projects, function($p) { return $p['status'] == 'rejected'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .main-content {
            padding: 20px;
        }
        .project-card {
            transition: transform 0.2s;
        }
        .project-card:hover {
            transform: translateY(-5px);
        }
        .dashboard-stats {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 5px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h3 class="text-white text-center mb-4">Admin Portal</h3>
                <nav>
                    <a href="dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
                    <a href="projects.php"><i class="fas fa-project-diagram me-2"></i> Projects</a>
                    <a href="students.php"><i class="fas fa-user-graduate me-2"></i> Students</a>
                    <a href="evaluations.php"><i class="fas fa-star me-2"></i> Evaluations</a>
                    <a href="messages.php"><i class="fas fa-envelope me-2"></i> Messages</a>
                    <a href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($admin_name); ?></h2>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="dashboard-stats">
                    <div class="row row-cols-1 row-cols-md-4 g-4">
                        <div class="col">
                            <div class="stat-card">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <h3><?php echo $total_projects; ?></h3>
                                <p class="text-muted mb-0">Total Projects</p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-card">
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3><?php echo $pending_projects; ?></h3>
                                <p class="text-muted mb-0">Pending Projects</p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-card">
                                <div class="stat-icon text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3><?php echo $approved_projects; ?></h3>
                                <p class="text-muted mb-0">Approved Projects</p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-card">
                                <div class="stat-icon text-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h3><?php echo $rejected_projects; ?></h3>
                                <p class="text-muted mb-0">Rejected Projects</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Projects</h5>
                    </div>
                    <div class="card-body">
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
                                                <td class="action-buttons">
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
                        <?php if (count($projects) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="projects.php" class="btn btn-outline-primary">View All Projects</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
