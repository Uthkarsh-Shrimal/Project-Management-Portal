<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];
    
    // Additional fields for students
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $year = isset($_POST['year']) ? trim($_POST['year']) : '';

    // Debug information
    error_log("Registration attempt - User Type: " . $user_type);
    error_log("Name: " . $name . ", Email: " . $email);
    error_log("Department: " . $department . ", Year: " . $year);

    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must have at least 6 characters.";
    } elseif ($user_type == 'student') {
        if (empty($department)) {
            $error = "Please enter your department.";
        } elseif (empty($year)) {
            $error = "Please select your year.";
        }
    }

    if (empty($error)) {
        // Check if email already exists
        $table = ($user_type == 'student') ? 'students' : 'admins';
        $id_column = ($user_type == 'student') ? 'student_id' : 'admin_id';
        $sql = "SELECT $id_column FROM $table WHERE email = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "This email is already registered.";
            } else {
                // Insert new user
                if ($user_type == 'student') {
                    $sql = "INSERT INTO students (name, email, password, department, year) VALUES (?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $hashed_password, $department, $year);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Registration successful! You can now login.";
                            error_log("Student registration successful for: " . $email);
                        } else {
                            $error = "Error: " . mysqli_error($conn);
                            error_log("Student registration failed: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Error preparing statement: " . mysqli_error($conn);
                        error_log("Error preparing student insert statement: " . mysqli_error($conn));
                    }
                } else {
                    $sql = "INSERT INTO admins (name, email, password) VALUES (?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hashed_password);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Registration successful! You can now login.";
                            error_log("Admin registration successful for: " . $email);
                        } else {
                            $error = "Error: " . mysqli_error($conn);
                            error_log("Admin registration failed: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Error preparing statement: " . mysqli_error($conn);
                        error_log("Error preparing admin insert statement: " . mysqli_error($conn));
                    }
                }
            }
        } else {
            $error = "Error preparing statement: " . mysqli_error($conn);
            error_log("Error preparing email check statement: " . mysqli_error($conn));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
        }
        .student-fields {
            display: none;
        }
    </style>
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Register</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="registerForm">
                        <div class="mb-3">
                            <label for="user_type" class="form-label">Register As</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="student">Student</option>
                                <option value="admin">Faculty</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <!-- Student-specific fields -->
                        <div class="student-fields">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department">
                            </div>
                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" name="year">
                                    <option value="">Select Year</option>
                                    <option value="1">First Year</option>
                                    <option value="2">Second Year</option>
                                    <option value="3">Third Year</option>
                                    <option value="4">Fourth Year</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('user_type').addEventListener('change', function() {
            const studentFields = document.querySelector('.student-fields');
            if (this.value === 'student') {
                studentFields.style.display = 'block';
                document.getElementById('department').required = true;
                document.getElementById('year').required = true;
            } else {
                studentFields.style.display = 'none';
                document.getElementById('department').required = false;
                document.getElementById('year').required = false;
            }
        });

        // Trigger the change event on page load to set initial state
        document.getElementById('user_type').dispatchEvent(new Event('change'));
    </script>
</body>
</html> 

