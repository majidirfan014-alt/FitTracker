<?php
session_start();
require_once __DIR__ . '/helpers.php';

$membersFile = getReadFile('members.json');
$workoutFile = getReadFile('workouts.json');

$members = file_exists($membersFile) ? json_decode(file_get_contents($membersFile), true) : [];
$workouts = file_exists($workoutFile) ? json_decode(file_get_contents($workoutFile), true) : [];

// Calculate stats per member
$memberStats = [];
foreach ($members as $member) {
    if ($member['status'] === 'active') {
        $memberWorkouts = array_filter($workouts, function($w) use ($member) {
            return $w['member_id'] === $member['id'];
        });
        
        $totalSessions = count($memberWorkouts);
        $totalDuration = 0;
        $totalCalories = 0;
        $workoutTypes = [];
        
        foreach ($memberWorkouts as $w) {
            $totalDuration += $w['duration'];
            $totalCalories += $w['calories'];
            $workoutTypes[$w['workout_type']] = ($workoutTypes[$w['workout_type']] ?? 0) + 1;
        }
        
        $memberStats[] = [
            'id' => $member['id'],
            'name' => $member['name'],
            'goal' => $member['goal'],
            'total_sessions' => $totalSessions,
            'total_duration' => $totalDuration,
            'total_calories' => $totalCalories,
            'avg_duration' => $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0,
            'workout_types' => $workoutTypes
        ];
    }
}

// Sort by total sessions descending
usort($memberStats, function($a, $b) {
    return $b['total_sessions'] <=> $a['total_sessions'];
});

// Get workout type distribution
$typeDistribution = [];
foreach ($workouts as $w) {
    $typeDistribution[$w['workout_type']] = ($typeDistribution[$w['workout_type']] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress - FitTrack</title>
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
                        <a class="nav-link" href="/"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/members"><i class="bi bi-people"></i> Members</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/progress"><i class="bi bi-graph-up"></i> Progress</a>
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

    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold"><i class="bi bi-graph-up"></i> Progress</h2>
                <p class="text-muted">Pantau progres latihan setiap member</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count($memberStats) ?></h3>
                        <p class="mb-0">Member Aktif</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count($workouts) ?></h3>
                        <p class="mb-0">Total Sesi Latihan</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= array_sum(array_column($workouts, 'calories')) ?></h3>
                        <p class="mb-0">Total Kalori Terbakar</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Member Progress Table -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Progres Member</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($memberStats)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-graph-up fs-1 text-muted"></i>
                                <h5 class="mt-3">Belum ada data progres</h5>
                                <p class="text-muted">Data progres akan muncul setelah ada data latihan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Nama</th>
                                            <th>Sesi</th>
                                            <th>Durasi</th>
                                            <th>Kalori</th>
                                            <th>Rata-rata/Sesi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($memberStats as $index => $stats): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $rankBadge = '';
                                                    switch ($index) {
                                                        case 0:
                                                            $rankBadge = 'bg-warning';
                                                            $rankIcon = 'bi-trophy';
                                                            break;
                                                        case 1:
                                                            $rankBadge = 'bg-secondary';
                                                            $rankIcon = 'bi-award';
                                                            break;
                                                        case 2:
                                                            $rankBadge = 'bg-danger';
                                                            $rankIcon = 'bi-star';
                                                            break;
                                                        default:
                                                            $rankBadge = 'bg-light text-dark';
                                                            $rankIcon = '';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $rankBadge ?> <?= $index < 3 ? 'fs-6' : '' ?>">
                                                        <?php if ($index < 3): ?>
                                                            <i class="bi <?= $rankIcon ?>"></i>
                                                        <?php else: ?>
                                                            #<?= $index + 1 ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="member-photo me-2">
                                                            <?= strtoupper(substr($stats['name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($stats['name']) ?></strong>
                                                            <?php if ($stats['goal']): ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($stats['goal']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><strong><?= $stats['total_sessions'] ?></strong></td>
                                                <td><?= $stats['total_duration'] ?> menit</td>
                                                <td><?= $stats['total_calories'] ?> kcal</td>
                                                <td><?= $stats['avg_duration'] ?> menit</td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewDetail('<?= $stats['id'] ?>')">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Workout Type Distribution -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Distribusi Jenis Latihan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($typeDistribution)): ?>
                            <div class="text-center py-4">
                                <p class="text-muted">Belum ada data</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $totalWorkouts = array_sum($typeDistribution);
                            foreach ($typeDistribution as $type => $count): 
                                $percentage = $totalWorkouts > 0 ? round(($count / $totalWorkouts) * 100) : 0;
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="badge workout-<?= strtolower(str_replace(' ', '', $type)) ?>"><?= $type ?></span>
                                        <span class="text-muted"><?= $count ?> sesi (<?= $percentage ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-primary" 
                                             style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 FitTrack - Monitoring Fitness App</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetail(memberId) {
            window.location.href = '/member-detail?id=' + memberId;
        }
    </script>
</body>
</html>
