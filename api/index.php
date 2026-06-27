<?php
session_start();
require_once __DIR__ . '/helpers.php';

// Load data from JSON files
$membersFile = getReadFile('members.json');
$workoutsFile = getReadFile('workouts.json');

$members = file_exists($membersFile) ? json_decode(file_get_contents($membersFile), true) : [];
$workouts = file_exists($workoutsFile) ? json_decode(file_get_contents($workoutsFile), true) : [];

// Calculate statistics
$totalMembers = count($members);
$activeMembers = 0;
$totalSessions = count($workouts);
$totalCalories = array_sum(array_column($workouts, 'calories'));

foreach ($members as $member) {
    if ($member['status'] === 'active') {
        $activeMembers++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitTrack - Monitoring Fitness</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <img src="/logo_ssfc-removebg-preview.png" alt="Logo SSFC" style="height: 60px;">
                <div style="border-left: 2px solid rgba(255,255,255,0.5); height: 50px; margin: 0 8px;"></div>
                <img src="/active_human_logo-removebg-preview.png" alt="Active Human" style="height: 60px; background: white; border-radius: 8px; padding: 4px;">
                <span class="fw-bold text-white fs-4">FitTracker</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/members"><i class="bi bi-people"></i> Members</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/progress"><i class="bi bi-graph-up"></i> Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/workout"><i class="bi bi-lightning"></i> Latihan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/reports"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold"><i class="bi bi-speedometer2"></i> Dashboard</h2>
                <p class="text-muted">Monitoring progres latihan dan pendaftaran member fitness</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Total Members</h6>
                                <h2 class="card-title mb-0"><?= $totalMembers ?></h2>
                            </div>
                            <i class="bi bi-people fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Member Aktif</h6>
                                <h2 class="card-title mb-0"><?= $activeMembers ?></h2>
                            </div>
                            <i class="bi bi-person-check fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Total Sesi</h6>
                                <h2 class="card-title mb-0"><?= $totalSessions ?></h2>
                            </div>
                            <i class="bi bi-lightning fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">Total Kalori</h6>
                                <h2 class="card-title mb-0"><?= number_format($totalCalories) ?></h2>
                            </div>
                            <i class="bi bi-fire fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Aksi Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/members" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-person-plus"></i> Tambah Member
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/workout" class="btn btn-outline-success w-100">
                                    <i class="bi bi-plus-circle"></i> Input Latihan
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/progress" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-graph-up"></i> Lihat Progres
                                </a>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <a href="/reports" class="btn btn-outline-info w-100">
                                    <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Members & Recent Workouts -->
        <div class="row">
            <!-- Recent Members -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Member Terbaru</h5>
                        <a href="/members" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-person-x fs-1"></i>
                                <p class="mt-2">Belum ada member terdaftar</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Status</th>
                                            <th>Tanggal Daftar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentMembers = array_slice($members, -5, 5, true);
                                        foreach (array_reverse($recentMembers, true) as $id => $member): 
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($member['name']) ?></td>
                                                <td>
                                                    <span class="badge <?= $member['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $member['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($member['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Workouts -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Latihan Terakhir</h5>
                        <a href="/workout" class="btn btn-sm btn-outline-success">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($workouts)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-clipboard-x fs-1"></i>
                                <p class="mt-2">Belum ada data latihan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Jenis</th>
                                            <th>Kalori</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentWorkouts = array_slice($workouts, -5, 5, true);
                                        foreach (array_reverse($recentWorkouts, true) as $id => $w): 
                                            $memberName = 'Unknown';
                                            foreach ($members as $m) {
                                                if ($m['id'] == $w['member_id']) {
                                                    $memberName = $m['name'];
                                                    break;
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($memberName) ?></td>
                                                <td><span class="badge workout-<?= strtolower(str_replace(' ', '', $w['workout_type'])) ?>"><?= htmlspecialchars($w['workout_type']) ?></span></td>
                                                <td><?= $w['calories'] ?? '-' ?> kcal</td>
                                                <td><?= date('d/m/Y', strtotime($w['date'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 FitTrack - Monitoring Fitness App</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
