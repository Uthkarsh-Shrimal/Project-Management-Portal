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

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload'])) {
    $project_id = $_POST['project_id'];
    $doc_type = $_POST['doc_type'];
    
    // Create project directory if it doesn't exist
    $upload_dir = "../uploads/projects/" . $project_id;
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $file = $_FILES['document'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed file extensions
        $allowed = array('pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar');
        
        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 10485760) { // 10MB max
                $new_file_name = $doc_type . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir . '/' . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Save document info to database
                    $sql = "INSERT INTO documents (project_id, doc_type, file_path) VALUES (?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $relative_path = "uploads/projects/" . $project_id . "/" . $new_file_name;
                        mysqli_stmt_bind_param($stmt, "iss", $project_id, $doc_type, $relative_path);
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Document uploaded successfully!";
                        } else {
                            $error = "Error saving document information.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $error = "Error uploading file.";
                }
            } else {
                $error = "File size too large. Maximum size is 10MB.";
            }
        } else {
            $error = "Invalid file type. Allowed types: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR";
        }
    }
}

// Get student's projects
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM documents WHERE project_id = p.project_id) as doc_count 
        FROM projects p 
        WHERE p.student_id = ? 
        ORDER BY p.created_at DESC";
$projects = array();

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $projects[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-card {
            transition: transform 0.2s;
        }
        .document-card:hover {
            transform: translateY(-5px);
        }
        .upload-form {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-4">My Documents</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upload New Document</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="upload-form">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="project_id" class="form-label">Select Project</label>
                                        <select class="form-select" id="project_id" name="project_id" required>
                                            <option value="">Choose Project</option>
                                            <?php foreach ($projects as $project): ?>
                                                <option value="<?php echo $project['project_id']; ?>">
                                                    <?php echo htmlspecialchars($project['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="doc_type" class="form-label">Document Type</label>
                                        <select class="form-select" id="doc_type" name="doc_type" required>
                                            <option value="">Select Type</option>
                                            <option value="synopsis">Synopsis</option>
                                            <option value="report">Report</option>
                                            <option value="presentation">Presentation</option>
                                            <option value="source_code">Source Code</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="document" class="form-label">Choose File</label>
                                        <input type="file" class="form-control" id="document" name="document" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="upload" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Projects and Documents List -->
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card document-card">
                                <div class="card-header">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($project['title']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Status: <span class="badge bg-<?php echo $project['status'] == 'approved' ? 'success' : ($project['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($project['status']); ?>
                                    </span></p>
                                    
                                    <?php
                                    // Get documents for this project
                                    $sql = "SELECT * FROM documents WHERE project_id = ? ORDER BY uploaded_at DESC";
                                    if ($stmt = mysqli_prepare($conn, $sql)) {
                                        mysqli_stmt_bind_param($stmt, "i", $project['project_id']);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        
                                        if (mysqli_num_rows($result) > 0): ?>
                                            <h6 class="mt-3">Uploaded Documents:</h6>
                                            <ul class="list-group">
                                                <?php while ($doc = mysqli_fetch_assoc($result)): ?>
                                                    <?php
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
                                                    ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="fas fa-file-alt"></i>
                                                            <?php echo ucfirst($doc['doc_type']); ?>
                                                            <small class="text-muted">
                                                                (<?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?>)
                                                            </small>
                                                        </div>
                                                        <a href="<?php echo htmlspecialchars($file_url); ?>" class="btn btn-sm btn-primary" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted">No documents uploaded yet.</p>
                                        <?php endif;
                                        mysqli_stmt_close($stmt);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

