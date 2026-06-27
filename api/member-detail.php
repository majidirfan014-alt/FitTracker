<?php
session_start();
require_once __DIR__ . '/helpers.php';

$membersFile = getReadFile('members.json');
$workoutFile = getReadFile('workouts.json');

$members = file_exists($membersFile) ? json_decode(file_get_contents($membersFile), true) : [];
$workouts = file_exists($workoutFile) ? json_decode(file_get_contents($workoutFile), true) : [];

$memberId = $_GET['id'] ?? null;
$member = null;
foreach ($members as $m) {
    if ($m['id'] == $memberId) {
        $member = $m;
        break;
    }
}

if (!$member) {
    header('Location: /progress');
    exit;
}

$memberWorkouts = array_filter($workouts, function($w) use ($memberId) {
    return $w['member_id'] == $memberId;
});
usort($memberWorkouts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$totalSessions = count($memberWorkouts);
$totalDuration = 0;
$totalCalories = 0;
$workoutTypes = [];
$dates = [];
$caloriesPerDate = [];
$durationPerDate = [];
$latestWeight = null;
$latestFat = null;
$latestBmi = null;
$weightHistory = [];
$fatHistory = [];

foreach ($memberWorkouts as $w) {
    $totalDuration += $w['duration'];
    $totalCalories += $w['calories'];
    $workoutTypes[$w['workout_type']] = ($workoutTypes[$w['workout_type']] ?? 0) + 1;

    if (isset($w['weight'])) {
        $latestWeight = $w['weight'];
        $weightHistory[] = ['date' => $w['date'], 'value' => $w['weight']];
    }
    if (isset($w['body_fat'])) {
        $latestFat = $w['body_fat'];
        $fatHistory[] = ['date' => $w['date'], 'value' => $w['body_fat']];
    }
    if (isset($w['bmi'])) {
        $latestBmi = $w['bmi'];
    }

    $caloriesPerDate[$w['date']] = ($caloriesPerDate[$w['date']] ?? 0) + $w['calories'];
    $durationPerDate[$w['date']] = ($durationPerDate[$w['date']] ?? 0) + $w['duration'];
}

$avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0;
$avgCalories = $totalSessions > 0 ? round($totalCalories / $totalSessions) : 0;

// Body fat distribution estimation
function estimateBodyPartFat($totalFat, $gender) {
    if ($gender === 'male') {
        return [
            'Dada' => round($totalFat * 1.15, 1),
            'Perut' => round($totalFat * 1.35, 1),
            'Punggung' => round($totalFat * 1.1, 1),
            'Lengan' => round($totalFat * 0.7, 1),
            'Paha' => round($totalFat * 0.6, 1),
            'Bokong' => round($totalFat * 0.55, 1),
        ];
    } else {
        return [
            'Dada' => round($totalFat * 0.9, 1),
            'Perut' => round($totalFat * 1.25, 1),
            'Punggung' => round($totalFat * 0.95, 1),
            'Lengan' => round($totalFat * 0.8, 1),
            'Paha' => round($totalFat * 1.3, 1),
            'Bokong' => round($totalFat * 1.2, 1),
        ];
    }
}

$bodyFatParts = $latestFat ? estimateBodyPartFat($latestFat, $member['gender'] ?? 'male') : [];
$gender = $member['gender'] ?? 'male';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progres <?= htmlspecialchars($member['name']) ?> - FitTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <style>
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        .stat-mini {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 0.75rem;
            text-align: center;
        }
        .stat-mini .val { font-size: 1.4rem; font-weight: 700; }
        .stat-mini .lbl { font-size: 0.75rem; opacity: 0.85; }

        .body-svg-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .body-svg-container svg { max-height: 420px; }
        .fat-part {
            transition: all 0.3s;
            cursor: pointer;
        }
        .fat-part:hover { filter: brightness(1.2); stroke: #333; stroke-width: 2; }
        .fat-label {
            font-size: 11px;
            font-weight: 600;
            fill: #333;
            pointer-events: none;
        }
        .fat-legend { list-style: none; padding: 0; }
        .fat-legend li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .fat-legend .swatch {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .timeline-item {
            border-left: 3px solid #667eea;
            padding: 0 0 1.5rem 1.5rem;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            position: absolute;
            left: -7.5px;
            top: 4px;
        }
        .timeline-card {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: transform 0.2s;
        }
        .timeline-card:hover { transform: translateX(4px); }

        .chart-bar {
            height: 20px;
            border-radius: 6px;
            transition: width 0.6s ease;
        }

        @media print {
            .no-print { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
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
                    <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/members"><i class="bi bi-people"></i> Members</a></li>
                    <li class="nav-item"><a class="nav-link" href="/progress"><i class="bi bi-graph-up"></i> Progress</a></li>
                    <li class="nav-item"><a class="nav-link" href="/workout"><i class="bi bi-lightning"></i> Latihan</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="mb-3 no-print">
            <a href="/progress" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali ke Progress</a>
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Cetak</button>
        </div>

        <!-- Profile Card -->
        <div class="profile-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="profile-avatar"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                </div>
                <div class="col-md-4">
                    <h4 class="mb-1"><?= htmlspecialchars($member['name']) ?></h4>
                    <p class="mb-1 opacity-75"><?= htmlspecialchars($member['email']) ?></p>
                    <p class="mb-0 opacity-75"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($member['goal'] ?? '-') ?></p>
                    <span class="badge <?= $member['gender'] === 'male' ? 'bg-info' : 'bg-danger' ?> mt-1">
                        <?= $member['gender'] === 'male' ? 'Laki-laki' : 'Perempuan' ?> | <?= $member['age'] ?> thn
                    </span>
                </div>
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-3"><div class="stat-mini"><div class="val"><?= $totalSessions ?></div><div class="lbl">Total Sesi</div></div></div>
                        <div class="col-3"><div class="stat-mini"><div class="val"><?= $totalDuration ?></div><div class="lbl">Total Menit</div></div></div>
                        <div class="col-3"><div class="stat-mini"><div class="val"><?= $totalCalories ?></div><div class="lbl">Total Kalori</div></div></div>
                        <div class="col-3"><div class="stat-mini"><div class="val"><?= $avgDuration ?></div><div class="lbl">Rata-rata/Mnt</div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Body Fat Visualization -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-body-text"></i> Distribusi Lemak Tubuh</h5></div>
                    <div class="card-body">
                        <?php if ($latestFat): ?>
                            <div class="body-svg-container">
                                <svg viewBox="0 0 220 450" width="180" xmlns="http://www.w3.org/2000/svg" style="overflow:visible">
                                    <!-- Head -->
                                    <ellipse cx="110" cy="35" rx="30" ry="35" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <!-- Neck -->
                                    <rect x="97" y="68" width="26" height="18" rx="5" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <!-- Torso -->
                                    <path d="M60,86 L160,86 L165,230 L55,230 Z" rx="10" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <!-- Chest area -->
                                    <ellipse class="fat-part" cx="85" cy="115" rx="25" ry="22" fill="<?= $bodyFatParts['Dada'] > 20 ? '#ff6b6b' : ($bodyFatParts['Dada'] > 12 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Dada"/>
                                    <text class="fat-label" x="85" y="119" text-anchor="middle"><?= $bodyFatParts['Dada'] ?>%</text>
                                    <ellipse class="fat-part" cx="135" cy="115" rx="25" ry="22" fill="<?= $bodyFatParts['Dada'] > 20 ? '#ff6b6b' : ($bodyFatParts['Dada'] > 12 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Dada"/>
                                    <!-- Belly / Abdomen -->
                                    <rect class="fat-part" x="65" y="140" width="90" height="70" rx="15" fill="<?= $bodyFatParts['Perut'] > 25 ? '#ff6b6b' : ($bodyFatParts['Perut'] > 15 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Perut"/>
                                    <text class="fat-label" x="110" y="180" text-anchor="middle">Perut <?= $bodyFatParts['Perut'] ?>%</text>
                                    <!-- Back indicator (side label) -->
                                    <text class="fat-label" x="175" y="155" text-anchor="start" style="font-size:10px;fill:#888">Punggung <?= $bodyFatParts['Punggung'] ?>%</text>
                                    <line x1="165" y1="150" x2="172" y2="150" stroke="#888" stroke-width="1"/>
                                    <!-- Left Arm -->
                                    <path d="M60,86 L40,90 L25,170 L35,172 L50,105 L60,100" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <ellipse class="fat-part" cx="38" cy="130" rx="10" ry="25" fill="<?= $bodyFatParts['Lengan'] > 18 ? '#ff6b6b' : ($bodyFatParts['Lengan'] > 10 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Lengan"/>
                                    <text class="fat-label" x="38" y="134" text-anchor="middle" style="font-size:9px"><?= $bodyFatParts['Lengan'] ?>%</text>
                                    <!-- Right Arm -->
                                    <path d="M160,86 L180,90 L195,170 L185,172 L170,105 L160,100" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <ellipse class="fat-part" cx="182" cy="130" rx="10" ry="25" fill="<?= $bodyFatParts['Lengan'] > 18 ? '#ff6b6b' : ($bodyFatParts['Lengan'] > 10 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Lengan"/>
                                    <!-- Hips / Bokong -->
                                    <path d="M55,230 Q50,260 60,280 L160,280 Q170,260 165,230" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <ellipse class="fat-part" cx="85" cy="255" rx="28" ry="18" fill="<?= $bodyFatParts['Bokong'] > 22 ? '#ff6b6b' : ($bodyFatParts['Bokong'] > 13 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Bokong"/>
                                    <ellipse class="fat-part" cx="135" cy="255" rx="28" ry="18" fill="<?= $bodyFatParts['Bokong'] > 22 ? '#ff6b6b' : ($bodyFatParts['Bokong'] > 13 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Bokong"/>
                                    <text class="fat-label" x="110" y="259" text-anchor="middle">Bokong <?= $bodyFatParts['Bokong'] ?>%</text>
                                    <!-- Left Leg -->
                                    <path d="M60,280 L55,400 Q55,420 80,420 L85,420 Q90,420 90,400 L95,280" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <ellipse class="fat-part" cx="75" cy="340" rx="16" ry="45" fill="<?= $bodyFatParts['Paha'] > 22 ? '#ff6b6b' : ($bodyFatParts['Paha'] > 13 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Paha"/>
                                    <text class="fat-label" x="75" y="344" text-anchor="middle" style="font-size:9px"><?= $bodyFatParts['Paha'] ?>%</text>
                                    <!-- Right Leg -->
                                    <path d="M125,280 L130,400 Q130,420 140,420 L145,420 Q150,420 150,400 L160,280" fill="#f5deb3" stroke="#d2a679" stroke-width="1"/>
                                    <ellipse class="fat-part" cx="145" cy="340" rx="16" ry="45" fill="<?= $bodyFatParts['Paha'] > 22 ? '#ff6b6b' : ($bodyFatParts['Paha'] > 13 ? '#ffd93d' : '#6bcb77') ?>" opacity="0.75" data-part="Paha"/>
                                </svg>
                                <div>
                                    <ul class="fat-legend">
                                        <?php foreach ($bodyFatParts as $part => $val): ?>
                                            <li>
                                                <span class="swatch" style="background:<?= $val > 20 ? '#ff6b6b' : ($val > 12 ? '#ffd93d' : '#6bcb77') ?>"></span>
                                                <strong><?= $part ?>:</strong> <?= $val ?>%
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="mt-2 small">
                                        <span class="badge bg-success">Hijau = Ideal</span>
                                        <span class="badge bg-warning text-dark">Kuning = Rata-rata</span>
                                        <span class="badge bg-danger">Merah = Tinggi</span>
                                    </div>
                                    <div class="mt-2 small text-muted">
                                        Lemak total: <strong><?= $latestFat ?>%</strong> |
                                        BMI: <strong><?= $latestBmi ?? '-' ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-body-text fs-1"></i>
                                <p>Belum ada data untuk visualisasi tubuh</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Workout Type Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-pie-chart"></i> Distribusi Latihan</h5></div>
                    <div class="card-body">
                        <?php if (empty($workoutTypes)): ?>
                            <div class="text-center py-4 text-muted"><p>Belum ada data latihan</p></div>
                        <?php else: ?>
                            <?php foreach ($workoutTypes as $type => $count):
                                $pct = $totalSessions > 0 ? round(($count / $totalSessions) * 100) : 0;
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="badge workout-<?= strtolower(str_replace(' ', '', $type)) ?>"><?= $type ?></span>
                                        <span class="text-muted"><?= $count ?> sesi (<?= $pct ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 12px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <hr>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="fw-bold fs-5 text-primary"><?= $totalCalories ?></div>
                                    <small class="text-muted">Total Kalori</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold fs-5 text-success"><?= $avgDuration ?></div>
                                    <small class="text-muted">Rata-rata/Mnt</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold fs-5 text-warning"><?= $avgCalories ?></div>
                                    <small class="text-muted">Rata-rata Kalori</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calorie & Duration Chart -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-fire"></i> Kalori per Tanggal</h5></div>
                    <div class="card-body">
                        <?php if (empty($caloriesPerDate)): ?>
                            <div class="text-center py-4 text-muted"><p>Belum ada data</p></div>
                        <?php else: ?>
                            <?php 
                            ksort($caloriesPerDate);
                            $maxCal = max($caloriesPerDate);
                            foreach ($caloriesPerDate as $date => $cal): 
                                $w = $maxCal > 0 ? round(($cal / $maxCal) * 100) : 0;
                            ?>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-muted small" style="width:80px;flex-shrink:0"><?= date('d/m', strtotime($date)) ?></span>
                                    <div class="progress flex-grow-1" style="height:18px;">
                                        <div class="progress-bar chart-bar bg-danger" style="width:<?= $w ?>%"><?= $cal ?> kcal</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Durasi per Tanggal</h5></div>
                    <div class="card-body">
                        <?php if (empty($durationPerDate)): ?>
                            <div class="text-center py-4 text-muted"><p>Belum ada data</p></div>
                        <?php else: ?>
                            <?php 
                            ksort($durationPerDate);
                            $maxDur = max($durationPerDate);
                            foreach ($durationPerDate as $date => $dur): 
                                $w = $maxDur > 0 ? round(($dur / $maxDur) * 100) : 0;
                            ?>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-muted small" style="width:80px;flex-shrink:0"><?= date('d/m', strtotime($date)) ?></span>
                                    <div class="progress flex-grow-1" style="height:18px;">
                                        <div class="progress-bar chart-bar bg-primary" style="width:<?= $w ?>%"><?= $dur ?> mnt</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workout History Timeline -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Latihan</h5></div>
            <div class="card-body">
                <?php if (empty($memberWorkouts)): ?>
                    <div class="text-center py-4 text-muted"><i class="bi bi-clipboard-x fs-1"></i><p class="mt-2">Belum ada riwayat latihan</p></div>
                <?php else: ?>
                    <?php foreach ($memberWorkouts as $w):
                        $intensityClass = '';
                        $intensityText = '';
                        switch ($w['intensity'] ?? '') {
                            case 'low': $intensityClass = 'bg-info'; $intensityText = 'Rendah'; break;
                            case 'medium': $intensityClass = 'bg-warning'; $intensityText = 'Sedang'; break;
                            case 'high': $intensityClass = 'bg-danger'; $intensityText = 'Tinggi'; break;
                        }
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge workout-<?= strtolower(str_replace(' ', '', $w['workout_type'])) ?> me-1"><?= $w['workout_type'] ?></span>
                                        <span class="badge <?= $intensityClass ?>"><?= $intensityText ?></span>
                                    </div>
                                    <span class="text-muted small"><?= date('d M Y', strtotime($w['date'])) ?></span>
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col-3">
                                        <div class="fw-bold text-primary"><?= $w['duration'] ?> mnt</div>
                                        <small class="text-muted">Durasi</small>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold text-danger"><?= $w['calories'] ?? '-' ?> kcal</div>
                                        <small class="text-muted">Kalori</small>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold text-warning">RPE <?= $w['rpe'] ?? '-' ?>/10</div>
                                        <small class="text-muted">Intensitas</small>
                                    </div>
                                    <div class="col-3">
                                        <div class="fw-bold text-info"><?= $w['body_fat'] ?? '-' ?>%</div>
                                        <small class="text-muted">Lemak</small>
                                    </div>
                                </div>
                                <?php if (isset($w['height'])): ?>
                                    <div class="mt-2 small text-muted">
                                        BB: <?= $w['weight'] ?? '-' ?>kg | TB: <?= $w['height'] ?? '-' ?>cm | BMI: <?= $w['bmi'] ?? '-' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5 no-print">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 FitTrack - Monitoring Fitness App</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.fat-part').forEach(part => {
            part.addEventListener('mouseenter', function() {
                const name = this.dataset.part;
                document.querySelectorAll('.fat-legend li').forEach(li => {
                    if (li.textContent.includes(name)) {
                        li.style.background = '#f0f0f0';
                        li.style.borderRadius = '6px';
                    }
                });
            });
            part.addEventListener('mouseleave', function() {
                document.querySelectorAll('.fat-legend li').forEach(li => {
                    li.style.background = '';
                });
            });
        });
    </script>
</body>
</html>
