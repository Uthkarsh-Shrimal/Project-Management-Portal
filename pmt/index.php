<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Project Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            color: white;
        }
        .feature-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
    </style>
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">PMT System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">College Project Management System</h1>
            <p class="lead mb-4">Streamline your academic projects with our comprehensive management platform</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="login.php" class="btn btn-primary btn-lg">Login</a>
                <a href="register.php" class="btn btn-outline-light btn-lg">Register</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Key Features</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-project-diagram fa-3x mb-3 text-primary"></i>
                            <h5 class="card-title">Project Management</h5>
                            <p class="card-text">Submit and track your academic projects with ease</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-comments fa-3x mb-3 text-primary"></i>
                            <h5 class="card-title">Communication</h5>
                            <p class="card-text">Direct communication between students and faculty</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                            <h5 class="card-title">Document Management</h5>
                            <p class="card-text">Upload and manage all project-related documents</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">About the System</h2>
            <div class="row">
                <div class="col-md-6">
                    <h4>For Students</h4>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Submit project proposals</li>
                        <li><i class="fas fa-check text-success me-2"></i>Upload project documents</li>
                        <li><i class="fas fa-check text-success me-2"></i>Track project status</li>
                        <li><i class="fas fa-check text-success me-2"></i>Receive faculty feedback</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h4>For Faculty</h4>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Review project proposals</li>
                        <li><i class="fas fa-check text-success me-2"></i>Evaluate submissions</li>
                        <li><i class="fas fa-check text-success me-2"></i>Provide feedback</li>
                        <li><i class="fas fa-check text-success me-2"></i>Monitor progress</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; 2024 College Project Management System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

