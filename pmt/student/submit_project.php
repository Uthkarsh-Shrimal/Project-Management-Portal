<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student") {
    header("location: ../login.php");
    exit;
}

$student_id = $_SESSION["user_id"];
$error = '';
$success = '';

// Ensure session user still exists (important after DB reset/cleanup).
$sql = "SELECT student_id FROM students WHERE student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    if (!$student_exists) {
        session_unset();
        session_destroy();
        header("location: ../login.php?error=session_expired");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($title) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert project
            $sql = "INSERT INTO projects (title, description, student_id) VALUES (?, ?, ?)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssi", $title, $description, $student_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $project_id = mysqli_insert_id($conn);
                    
                    // Handle file uploads
                    $upload_dir = "../uploads/projects/" . $project_id . "/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $allowed_types = [
                        'synopsis' => ['pdf', 'doc', 'docx'],
                        'report' => ['pdf', 'doc', 'docx'],
                        'presentation' => ['ppt', 'pptx', 'pdf'],
                        'source_code' => ['zip', 'rar']
                    ];
                    
                    $upload_success = true;
                    foreach ($_FILES as $type => $file) {
                        if ($file['error'] == 0) {
                            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            
                            if (in_array($file_ext, $allowed_types[$type])) {
                                $new_filename = $type . '_' . time() . '.' . $file_ext;
                                $file_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                    $sql = "INSERT INTO documents (project_id, doc_type, file_path) VALUES (?, ?, ?)";
                                    if ($stmt2 = mysqli_prepare($conn, $sql)) {
                                        $relative_path = "uploads/projects/" . $project_id . "/" . $new_filename;
                                        mysqli_stmt_bind_param($stmt2, "iss", $project_id, $type, $relative_path);
                                        mysqli_stmt_execute($stmt2);
                                        mysqli_stmt_close($stmt2);
                                    }
                                } else {
                                    $upload_success = false;
                                    $error = "Failed to upload one or more files.";
                                }
                            } else {
                                $upload_success = false;
                                $error = "Invalid file type for " . $type;
                            }
                        }
                    }
                    
                    if ($upload_success) {
                        mysqli_commit($conn);
                        $success = "Project submitted successfully!";
                    } else {
                        mysqli_rollback($conn);
                    }
                } else {
                    mysqli_rollback($conn);
                    $error = "Project submission failed: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                mysqli_rollback($conn);
                $error = "Project submission failed: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Project - Project Management System</title>
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
        .file-upload {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .file-upload:hover {
            border-color: #007bff;
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
                    <a href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
                    <a href="submit_project.php" class="active"><i class="fas fa-plus me-2"></i> Submit Project</a>
                    <a href="documents.php"><i class="fas fa-file-alt me-2"></i> Documents</a>
                    <a href="messages.php"><i class="fas fa-envelope me-2"></i> Messages</a>
                    <a href="profile.php"><i class="fas fa-user me-2"></i> Profile</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Submit New Project</h2>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Project Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Project Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Project Synopsis</label>
                                <div class="file-upload" onclick="document.getElementById('synopsis').click()">
                                    <i class="fas fa-file-pdf fa-2x mb-2"></i>
                                    <p class="mb-0">Click to upload synopsis (PDF, DOC, DOCX)</p>
                                    <input type="file" id="synopsis" name="synopsis" class="d-none" accept=".pdf,.doc,.docx">
                                </div>
                                <div id="synopsis-name" class="text-muted"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Project Report</label>
                                <div class="file-upload" onclick="document.getElementById('report').click()">
                                    <i class="fas fa-file-word fa-2x mb-2"></i>
                                    <p class="mb-0">Click to upload report (PDF, DOC, DOCX)</p>
                                    <input type="file" id="report" name="report" class="d-none" accept=".pdf,.doc,.docx">
                                </div>
                                <div id="report-name" class="text-muted"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Presentation</label>
                                <div class="file-upload" onclick="document.getElementById('presentation').click()">
                                    <i class="fas fa-file-powerpoint fa-2x mb-2"></i>
                                    <p class="mb-0">Click to upload presentation (PPT, PPTX, PDF)</p>
                                    <input type="file" id="presentation" name="presentation" class="d-none" accept=".ppt,.pptx,.pdf">
                                </div>
                                <div id="presentation-name" class="text-muted"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Source Code</label>
                                <div class="file-upload" onclick="document.getElementById('source_code').click()">
                                    <i class="fas fa-file-archive fa-2x mb-2"></i>
                                    <p class="mb-0">Click to upload source code (ZIP, RAR)</p>
                                    <input type="file" id="source_code" name="source_code" class="d-none" accept=".zip,.rar">
                                </div>
                                <div id="source_code-name" class="text-muted"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Submit Project</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle file input changes
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                const displayElement = document.getElementById(this.id + '-name');
                if (fileName) {
                    displayElement.textContent = 'Selected file: ' + fileName;
                } else {
                    displayElement.textContent = '';
                }
            });
        });
    </script>
</body>
</html> 

