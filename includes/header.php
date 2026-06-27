<?php
// includes/header.php
// Reusable header component
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'FitTrack' ?> - FitTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="logo_ssfc-removebg-preview.png" alt="Logo SSFC" style="height: 60px;">
                <div style="border-left: 2px solid rgba(255,255,255,0.5); height: 50px; margin: 0 8px;"></div>
                <img src="active_human_logo-removebg-preview.png" alt="Active Human" style="height: 60px; background: white; border-radius: 8px; padding: 4px;">
                <span class="fw-bold text-white fs-4">FitTracker</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'members.php' ? 'active' : '' ?>" href="members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'progress.php' ? 'active' : '' ?>" href="progress.php">
                            <i class="bi bi-graph-up"></i> Progress
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'workout.php' ? 'active' : '' ?>" href="workout.php">
                            <i class="bi bi-lightning"></i> Latihan
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
