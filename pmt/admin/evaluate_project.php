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

// Get project ID from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id === 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch project details
$sql = "SELECT p.*, s.name as student_name, s.department, s.year,
        (SELECT GROUP_CONCAT(doc_type) FROM documents WHERE project_id = p.project_id) as documents
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $marks = isset($_POST['marks']) ? (float)$_POST['marks'] : -1;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
    $allowed_statuses = ['pending', 'approved', 'rejected', 'completed'];

    if (!in_array($status, $allowed_statuses, true)) {
        $error = "Invalid project status selected.";
    } elseif ($marks < 0 || $marks > 100) {
        $error = "Marks must be between 0 and 100.";
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
            // Redirect to prevent form resubmission
            header("Location: evaluate_project.php?id=" . $project_id . "&success=1");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $success = "Evaluation submitted successfully!";
}

// Fetch previous evaluations
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate Project - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-list {
            list-style: none;
            padding: 0;
        }
        .document-list li {
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 5px;
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
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Evaluate Project</h2>
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

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Project Details -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Project Details</h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                        <p class="text-muted">
                            By <?php echo htmlspecialchars($project['student_name']); ?> 
                            (<?php echo htmlspecialchars($project['department']); ?> - Year <?php echo $project['year']; ?>)
                        </p>
                        <hr>
                        <h6>Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        
                        <h6 class="mt-4">Submitted Documents</h6>
                        <ul class="document-list">
                            <?php 
                            if ($project['documents']) {
                                $documents = explode(',', $project['documents']);
                                foreach ($documents as $doc): 
                            ?>
                                <li>
                                    <i class="fas fa-file-alt me-2"></i>
                                    <?php echo ucfirst($doc); ?>
                                    <a href="../uploads/projects/<?php echo $project_id; ?>/<?php echo $doc; ?>" 
                                       class="btn btn-sm btn-outline-primary float-end" target="_blank">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </li>
                            <?php 
                                endforeach;
                            } else {
                                echo "<li class='text-muted'>No documents submitted yet.</li>";
                            }
                            ?>
                        </ul>
                    </div>
                </div>

                <!-- Previous Evaluations -->
                <?php if (!empty($evaluations)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Previous Evaluations</h5>
                        </div>
                        <div class="card-body">
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
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Evaluation Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Submit Evaluation</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="status" class="form-label">Project Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?php echo $project['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $project['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $project['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="marks" class="form-label">Marks (out of 100)</label>
                                <input type="number" class="form-control" id="marks" name="marks" 
                                       min="0" max="100" step="0.5" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comments" class="form-label">Comments</label>
                                <textarea class="form-control" id="comments" name="comments" 
                                          rows="4" required></textarea>
                            </div>
                            
                            <button type="submit" name="submit_evaluation" class="btn btn-primary">
                                Submit Evaluation
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

