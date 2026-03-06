<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student") {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$student_name = $_SESSION["name"];
$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Remove project upload directory recursively
function deleteDirectoryRecursively($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursively($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_project'])) {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!hash_equals($csrf_token, $token)) {
        $error = "Invalid request token. Please refresh and try again.";
    } elseif ($project_id <= 0) {
        $error = "Invalid project selected.";
    } else {
        try {
            mysqli_begin_transaction($conn);

            // Ensure project belongs to current student
            $sql = "SELECT project_id FROM projects WHERE project_id = ? AND student_id = ?";
            if (!($stmt = mysqli_prepare($conn, $sql))) {
                throw new Exception("Could not prepare project validation.");
            }
            mysqli_stmt_bind_param($stmt, "ii", $project_id, $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $is_owner = mysqli_num_rows($result) > 0;
            mysqli_stmt_close($stmt);

            if (!$is_owner) {
                throw new Exception("Project not found or access denied.");
            }

            // Delete project; related rows are removed by foreign key cascades.
            $sql = "DELETE FROM projects WHERE project_id = ? AND student_id = ?";
            if (!($stmt = mysqli_prepare($conn, $sql))) {
                throw new Exception("Could not prepare delete query.");
            }
            mysqli_stmt_bind_param($stmt, "ii", $project_id, $student_id);
            if (!mysqli_stmt_execute($stmt)) {
                $stmt_error = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception("Could not delete project: " . $stmt_error);
            }
            $deleted_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($deleted_rows < 1) {
                throw new Exception("Project not found or already deleted.");
            }

            mysqli_commit($conn);

            $project_upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "projects" . DIRECTORY_SEPARATOR . $project_id;
            deleteDirectoryRecursively($project_upload_dir);

            header("Location: dashboard.php?deleted=1");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = "Project deleted successfully.";
}

// Fetch student's projects
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM documents WHERE project_id = p.project_id) as doc_count,
        (SELECT COUNT(*) FROM evaluations WHERE project_id = p.project_id) as eval_count
        FROM projects p 
        WHERE p.student_id = ? 
        ORDER BY p.created_at DESC";

$projects = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Project Management System</title>
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
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h3 class="text-white text-center mb-4">Student Portal</h3>
                <nav>
                    <a href="dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
                    <a href="submit_project.php"><i class="fas fa-plus me-2"></i> Submit Project</a>
                    <a href="documents.php"><i class="fas fa-file-alt me-2"></i> Documents</a>
                    <a href="messages.php"><i class="fas fa-envelope me-2"></i> Messages</a>
                    <a href="profile.php"><i class="fas fa-user me-2"></i> Profile</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($student_name); ?></h2>
                    <a href="submit_project.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Project
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Project Status Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Projects</h5>
                                <h2 class="card-text"><?php echo count($projects); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Approved</h5>
                                <h2 class="card-text">
                                    <?php echo count(array_filter($projects, function($p) { return $p['status'] == 'approved'; })); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Pending</h5>
                                <h2 class="card-text">
                                    <?php echo count(array_filter($projects, function($p) { return $p['status'] == 'pending'; })); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Rejected</h5>
                                <h2 class="card-text">
                                    <?php echo count(array_filter($projects, function($p) { return $p['status'] == 'rejected'; })); ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects List -->
                <h3 class="mb-4">Your Projects</h3>
                <div class="row">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You haven't submitted any projects yet. Click the "New Project" button to get started.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card project-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                        <p class="card-text text-muted">
                                            <?php echo substr(htmlspecialchars($project['description']), 0, 100) . '...'; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php 
                                                echo $project['status'] == 'approved' ? 'success' : 
                                                    ($project['status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <i class="fas fa-file-alt me-1"></i>
                                                <?php echo $project['doc_count']; ?> docs
                                            </span>
                                            <span>
                                                <i class="fas fa-star me-1"></i>
                                                <?php echo $project['eval_count']; ?> evaluations
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <div class="d-flex gap-2">
                                                <a href="projects.php?id=<?php echo $project['project_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary w-100">
                                                    View Details
                                                </a>
                                                <form method="POST" action="" onsubmit="return confirm('Delete this project? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="project_id" value="<?php echo (int)$project['project_id']; ?>">
                                                    <button type="submit" name="delete_project" class="btn btn-sm btn-outline-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

