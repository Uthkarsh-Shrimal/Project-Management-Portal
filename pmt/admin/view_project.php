<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id === 0) {
    header("Location: dashboard.php");
    exit();
}

// Ensure session admin still exists (important after DB reset/cleanup).
$sql = "SELECT admin_id FROM admins WHERE admin_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $admin_exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    if (!$admin_exists) {
        session_unset();
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
}

// Fetch project details with student information
$sql = "SELECT p.*, s.name as student_name, s.student_id as roll_number, s.email as student_email,
        (SELECT GROUP_CONCAT(file_path) FROM documents WHERE project_id = p.project_id) as documents,
        (SELECT COUNT(*) FROM documents WHERE project_id = p.project_id) as doc_count
        FROM projects p 
        JOIN students s ON p.student_id = s.student_id
        WHERE p.project_id = ?";


$project = null;
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $project = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$project) {
    header("Location: dashboard.php");
    exit();
}

// Handle evaluation submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $marks = isset($_POST['marks']) ? (float)$_POST['marks'] : -1;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
    $allowed_statuses = ['pending', 'approved', 'rejected', 'completed'];

    if (!in_array($status, $allowed_statuses, true)) {
        $error_message = "Invalid project status selected.";
    } elseif ($marks < 0 || $marks > 100) {
        $error_message = "Marks must be between 0 and 100.";
    } else {
        try {
            mysqli_begin_transaction($conn);

            // Insert evaluation
            $sql = "INSERT INTO evaluations (project_id, evaluated_by, marks, comments, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            if (!($stmt = mysqli_prepare($conn, $sql))) {
                throw new Exception("Error preparing evaluation query: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "iids", $project_id, $admin_id, $marks, $comments);
            if (!mysqli_stmt_execute($stmt)) {
                $stmt_error = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception("Error submitting evaluation: " . $stmt_error);
            }
            mysqli_stmt_close($stmt);

            // Update project status
            $sql = "UPDATE projects SET status = ? WHERE project_id = ?";
            if (!($update_stmt = mysqli_prepare($conn, $sql))) {
                throw new Exception("Error preparing status update: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($update_stmt, "si", $status, $project_id);
            if (!mysqli_stmt_execute($update_stmt)) {
                $stmt_error = mysqli_stmt_error($update_stmt);
                mysqli_stmt_close($update_stmt);
                throw new Exception("Error updating project status: " . $stmt_error);
            }
            mysqli_stmt_close($update_stmt);

            mysqli_commit($conn);
            $success_message = "Evaluation submitted successfully!";
            // Refresh project data
            header("Location: view_project.php?id=" . $project_id . "&success=1");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

// Fetch existing evaluations
$sql = "SELECT e.*, a.name as evaluator_name 
        FROM evaluations e 
        JOIN admins a ON e.evaluated_by = a.admin_id 
        WHERE e.project_id = ? 
        ORDER BY e.created_at DESC";

$evaluations = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $evaluations[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get document labels
$document_types = [
    'synopsis' => 'Synopsis',
    'report' => 'Project Report',
    'presentation' => 'Presentation Slides',
    'source_code' => 'Source Code'
];

// Fetch documents for this project
$project_documents = [];
$sql = "SELECT doc_type, file_path, uploaded_at FROM documents WHERE project_id = ? ORDER BY uploaded_at DESC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $project_documents[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Check for success message
if (isset($_GET['success'])) {
    $success_message = "Evaluation submitted successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .project-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
        .document-list {
            list-style: none;
            padding: 0;
        }
        .document-list li {
            padding: 15px;
            border: 1px solid #dee2e6;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .document-list li:hover {
            background-color: #f8f9fa;
        }
        .evaluation-card {
            margin-bottom: 20px;
        }
        .evaluation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <!-- Back Button -->
                <a href="dashboard.php" class="btn btn-outline-secondary mb-4">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Student Information -->
                <div class="student-info">
                    <h4>Student Information</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($project['student_name']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($project['roll_number']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($project['student_email']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Project Header -->
                <div class="project-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><?php echo htmlspecialchars($project['title']); ?></h2>
                        <span class="badge bg-<?php 
                            echo $project['status'] == 'approved' ? 'success' : 
                                ($project['status'] == 'pending' ? 'warning' : 'danger'); 
                        ?> status-badge">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </div>
                    <p class="text-muted mb-0">
                        Submitted on <?php echo date('F d, Y', strtotime($project['created_at'])); ?>
                    </p>
                </div>

                <div class="row">
                    <!-- Project Details -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Project Description</h5>
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Submitted Documents</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($project_documents)): ?>
                                    <ul class="document-list">
                                        <?php 
                                        foreach ($project_documents as $doc):
                                            $doc_type = trim($doc['doc_type']);
                                            $file_path = trim($doc['file_path']);

                                            // Normalize legacy absolute/local paths into a web path
                                            if (stripos($file_path, 'uploads/') === false && stripos($file_path, '\\uploads\\') !== false) {
                                                $uploads_pos = stripos($file_path, 'uploads');
                                                $file_path = str_replace('\\', '/', substr($file_path, $uploads_pos));
                                            } else {
                                                $file_path = str_replace('\\', '/', $file_path);
                                            }

                                            if (strpos($file_path, 'uploads/') === 0) {
                                                $file_url = '../' . $file_path;
                                            } else {
                                                $file_url = $file_path;
                                            }

                                            if (isset($document_types[$doc_type])):
                                        ?>
                                            <li>
                                                <div>
                                                    <i class="fas fa-file-alt me-2"></i>
                                                    <?php echo htmlspecialchars($document_types[$doc_type]); ?>
                                                </div>
                                                <a href="<?php echo htmlspecialchars($file_url); ?>" 
                                                   class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No documents submitted yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Evaluation Form -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Submit Evaluation</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="marks" class="form-label">Marks (0-100)</label>
                                        <input type="number" class="form-control" id="marks" name="marks" 
                                               min="0" max="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments</label>
                                        <textarea class="form-control" id="comments" name="comments" 
                                                  rows="4" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Project Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="pending" <?php echo $project['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $project['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $project['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="submit_evaluation" class="btn btn-primary">
                                        Submit Evaluation
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Previous Evaluations -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Previous Evaluations</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($evaluations)): ?>
                                    <?php 
                                    $total_evaluations = count($evaluations);
                                    foreach ($evaluations as $index => $eval): 
                                    ?>
                                        <div class="evaluation-card">
                                            <div class="evaluation-header">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($eval['evaluator_name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($eval['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="mb-2">
                                                <strong>Marks:</strong> <?php echo $eval['marks']; ?>/100
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($eval['comments'])); ?></p>
                                        </div>
                                        <?php if ($index < $total_evaluations - 1): ?>
                                            <hr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No evaluations yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

