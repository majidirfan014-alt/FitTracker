<?php
session_start();
require_once __DIR__ . '/helpers.php';

$membersFile = getReadFile('members.json');
$workoutFileRead = getReadFile('workouts.json');
$workoutFileWrite = getWriteFile('workouts.json');

$members = file_exists($membersFile) ? json_decode(file_get_contents($membersFile), true) : [];
$workouts = file_exists($workoutFileRead) ? json_decode(file_get_contents($workoutFileRead), true) : [];

$activeMembers = array_filter($members, function($m) {
    return $m['status'] === 'active';
});

// ============================================================
// RUMUS KALORI: Compendium of Physical Activities (Ainsworth 2011)
// Rumus: Kalori = MET × BB(kg) × Waktu(jam)
// Referensi: Ainsworth BE et al. Med Sci Sports Exerc. 2011
// MET disesuaikan berdasarkan RPE Borg CR-10
// ============================================================
function getMET($workoutType, $rpe) {
    // MET values dari Compendium of Physical Activities (Ainsworth 2011)
    $baseMET = [
        'Strength Training' => 5.0,   // Code 02050: resistance training, general
        'Cardio' => 8.0,              // Code 12050: running, general
        'Flexibility' => 2.5,         // Code 02101: stretching, Hatha yoga
        'HIIT' => 9.0,                // Code 02058: calisthenics, vigorous
    ];
    $met = $baseMET[$workoutType] ?? 5.0;
    // Adjust MET berdasarkan RPE Borg CR-10
    // RPE 1-3 = Very Light/Light (50-65% max HR)
    // RPE 4-6 = Moderate (65-80% max HR)
    // RPE 7-10 = Hard/Vigorous (80-100% max HR)
    $rpeFactor = 0.6 + ($rpe / 10) * 0.8;
    return $met * $rpeFactor;
}

function calculateCalories($workoutType, $duration, $weight, $rpe) {
    $met = getMET($workoutType, $rpe);
    // Standar ACSM: Kalori = MET × BB(kg) × Waktu(jam)
    return round($met * $weight * ($duration / 60));
}

// ============================================================
// INTENSITAS: Borg CR-10 Scale (ACSM Guidelines)
// RPE 1-2: Very Light | 3-4: Light | 5-6: Moderate
// RPE 7-8: Hard | 9-10: Maximum
// ============================================================
function getIntensityFromRPE($rpe) {
    if ($rpe <= 2) return 'low';      // Very Light
    if ($rpe <= 4) return 'low';      // Light
    if ($rpe <= 6) return 'medium';   // Moderate
    return 'high';                     // Hard/Maximum
}

function getIntensityLabel($rpe) {
    if ($rpe <= 2) return 'Sangat Ringan';
    if ($rpe <= 4) return 'Ringan';
    if ($rpe <= 6) return 'Sedang';
    if ($rpe <= 8) return 'Berat';
    return 'Sangat Berat';
}

// ============================================================
// BMI: WHO Standard (World Health Organization)
// BMI = BB(kg) / TB(m)²
// Kategori Asia: <18.5 Kurus, 18.5-22.9 Normal, 23-24.9 Overweight, ≥25 Obese
// Kategori Internasional: <18.5, 18.5-24.9, 25-29.9, ≥30
// ============================================================
function calculateBMI($weight, $height) {
    $heightM = $height / 100;
    return round($weight / ($heightM * $heightM), 1);
}

// ============================================================
// BODY FAT: Deurenberg Formula ( digunakan di jurnal kesehatan Indonesia)
// BF% = (1.20 × BMI) + (0.23 × Usia) - (10.8 × Gender) - 5.4
// Gender: Laki-laki = 1, Perempuan = 0
// Referensi: Deurenberg P et al. Br J Nutr. 1998
// Validasi: digunakan di Riskesdas & Kementerian Kesehatan RI
// ============================================================
function estimateBodyFat($bmi, $age, $gender) {
    $g = ($gender === 'male') ? 1 : 0;
    $bf = (1.20 * $bmi) + (0.23 * $age) - (10.8 * $g) - 5.4;
    return round(max(3, min(60, $bf)), 1);
}

// ============================================================
// KATEGORI BMI: WHO + Kemenkes RI (Asia Pacific)
// ============================================================
function getBMICategory($bmi) {
    if ($bmi < 18.5) return ['Kurus', 'text-info'];
    if ($bmi < 23) return ['Normal', 'text-success'];       // Asia Pacific threshold
    if ($bmi < 25) return ['Pre-Overweight', 'text-warning'];
    if ($bmi < 30) return ['Overweight', 'text-warning'];
    return ['Obesitas', 'text-danger'];
}

// ============================================================
// KATEGORI BODY FAT: ACE (American Council on Exercise)
// Laki-laki: Essential 2-5%, Athletes 6-13%, Fitness 14-17%,
//            Average 18-24%, Obese 25%+
// Perempuan: Essential 10-13%, Athletes 14-20%, Fitness 21-24%,
//            Average 25-31%, Obese 32%+
// ============================================================
function getFatCategory($fat, $gender) {
    if ($gender === 'male') {
        if ($fat < 6) return ['Essential Fat', 'text-info'];
        if ($fat < 14) return ['Atletis', 'text-info'];
        if ($fat < 18) return ['Fitness', 'text-success'];
        if ($fat < 25) return ['Rata-rata', 'text-warning'];
        return ['Obesitas', 'text-danger'];
    } else {
        if ($fat < 14) return ['Essential Fat', 'text-info'];
        if ($fat < 21) return ['Atletis', 'text-info'];
        if ($fat < 25) return ['Fitness', 'text-success'];
        if ($fat < 32) return ['Rata-rata', 'text-warning'];
        return ['Obesitas', 'text-danger'];
    }
}

// ============================================================
// SARAN: Berdasarkan ACSM Guidelines & Kemenkes RI
// ============================================================
function generateAdvice($workoutType, $rpe, $duration, $calories, $bmi, $fat, $age, $gender) {
    $advice = [];
    // Intensitas berdasarkan Borg CR-10
    if ($rpe >= 8) $advice[] = "Intensitas sangat berat (RPE 8-10). Wajib istirahat 7-9 jam & hidrasi minimal 500ml/jam.";
    elseif ($rpe >= 6) $advice[] = "Intensitas sedang-berat (RPE 6-7). Pertahankan di zona ini untuk hasil optimal.";
    elseif ($rpe <= 3) $advice[] = "Intensitas masih ringan (RPE 1-3). Tingkatkan bertahap untuk stimulasi yang cukup.";
    // BMI berdasarkan WHO Asia Pacific
    if ($bmi > 25) $advice[] = "BMI ≥25 (WHO). Kombinasikan kardio 150mnt/minggu + deficit kalori 500kcal/hari.";
    elseif ($bmi < 18.5) $advice[] = "BMI <18.5 (Kurus). Surplus kalori + strength training untuk tambah massa otot.";
    // Body fat berdasarkan ACE
    $isMale = ($gender === 'male');
    if ($fat > ($isMale ? 25 : 32)) $advice[] = "Body fat obesitas (ACE). Kardio 150-300mnt/minggu + pantau asupan kalori.";
    elseif ($fat > ($isMale ? 18 : 25)) $advice[] = "Body fat rata-rata. Tambah strength training 2-3x/minggu untuk tingkatkan lean mass.";
    // Jenis latihan
    if ($workoutType === 'Cardio' && $duration < 30) $advice[] = "Durasi kardio <30 menit. ACSM rekomendasikan minimal 30 menit sesi.";
    if ($workoutType === 'Strength Training') $advice[] = "Protein 1.6-2.2g/kg BB/hari (ACSM) untuk pemulihan otot optimal.";
    if ($workoutType === 'Flexibility') $advice[] = "Flexibility training. Lakukan 2-3x/minggu, tahan 15-30 detik per gerakan.";
    if ($workoutType === 'HIIT') $advice[] = "HIIT. Batasi 2-3x/minggu, beri jeda 48 jam antar sesi.";
    if ($age > 40) $advice[] = "Usia >40 tahun. Panjangkan pemanasan 10-15 menit & monitoring HR max (220-usia).";
    if (empty($advice)) $advice[] = "Pertahankan latihan rutin. Variasikan jenis latihan untuk hasil optimal.";
    return $advice;
}

$showResult = false;
$result = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $deleteId = $_POST['workout_id'];
        $workouts = array_filter($workouts, function($w) use ($deleteId) {
            return $w['id'] !== $deleteId;
        });
        $workouts = array_values($workouts);
        file_put_contents($workoutFileWrite, json_encode($workouts, JSON_PRETTY_PRINT));
        header('Location: /workout?success=deleted');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $memberId = $_POST['member_id'];
        $date = $_POST['date'];
        $workoutType = $_POST['workout_type'] === 'Lainnya' ? htmlspecialchars($_POST['custom_workout_type']) : $_POST['workout_type'];
        if (empty($workoutType)) $workoutType = 'Lainnya';
        $duration = (int)$_POST['duration'];
        $rpe = (int)$_POST['rpe'];
        $height = (float)$_POST['height'];
        $weight = (float)$_POST['weight'];
        $age = (int)$_POST['age'];

        $gender = 'male';
        foreach ($members as $m) {
            if ($m['id'] == $memberId) { $gender = $m['gender'] ?? 'male'; break; }
        }

        $intensity = getIntensityFromRPE($rpe);
        $calories = calculateCalories($workoutType, $duration, $weight, $rpe);
        $bmi = calculateBMI($weight, $height);
        $fat = estimateBodyFat($bmi, $age, $gender);
        $bmiInfo = getBMICategory($bmi);
        $fatInfo = getFatCategory($fat, $gender);
        $advice = generateAdvice($workoutType, $rpe, $duration, $calories, $bmi, $fat, $age, $gender);

        $newWorkout = [
            'id' => uniqid(),
            'member_id' => $memberId,
            'workout_type' => $workoutType,
            'duration' => $duration,
            'calories' => $calories,
            'intensity' => $intensity,
            'rpe' => $rpe,
            'height' => $height,
            'weight' => $weight,
            'age' => $age,
            'bmi' => $bmi,
            'body_fat' => $fat,
            'date' => $date,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $workouts[] = $newWorkout;
        file_put_contents($workoutFileWrite, json_encode($workouts, JSON_PRETTY_PRINT));

        $result = [
            'workout_type' => $workoutType,
            'duration' => $duration,
            'calories' => $calories,
            'intensity' => getIntensityLabel($rpe),
            'intensity_class' => $intensity === 'low' ? 'bg-info' : ($intensity === 'medium' ? 'bg-warning' : 'bg-danger'),
            'fat' => $fat,
            'fat_class' => $fatInfo[1],
            'fat_label' => $fatInfo[0],
            'bmi' => $bmi,
            'bmi_class' => $bmiInfo[1],
            'bmi_label' => $bmiInfo[0],
            'rpe' => $rpe,
            'advice' => $advice,
        ];
        $showResult = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latihan - FitTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    <style>
        .result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .result-item {
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .result-item .value { font-size: 1.8rem; font-weight: 700; }
        .result-item .label { font-size: 0.85rem; opacity: 0.9; }
        .advice-item {
            background: rgba(255,255,255,0.1);
            border-left: 4px solid #fff;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 8px 8px 0;
        }
        .rpe-display { display: flex; gap: 4px; margin-top: 5px; }
        .rpe-dot {
            width: 24px; height: 24px; border-radius: 50%;
            background: #dee2e6; display: flex; align-items: center;
            justify-content: center; font-size: 0.7rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .rpe-dot.active { background: #0d6efd; color: white; transform: scale(1.15); }
        .rpe-dot.active.low { background: #0dcaf0; }
        .rpe-dot.active.mid { background: #ffc107; }
        .rpe-dot.active.high { background: #dc3545; }
        .detail-popup .result-card { margin: 0; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
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
                    <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/members"><i class="bi bi-people"></i> Members</a></li>
                    <li class="nav-item"><a class="nav-link" href="/progress"><i class="bi bi-graph-up"></i> Progress</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/workout"><i class="bi bi-lightning"></i> Latihan</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reports"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold"><i class="bi bi-lightning"></i> Latihan</h2>
                <p class="text-muted">Catat dan kelola data latihan member</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWorkoutModal">
                    <i class="bi bi-plus-circle"></i> Input Latihan
                </button>
            </div>
        </div>

        <?php if ($showResult): ?>
            <div class="card mb-4 result-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-check-circle"></i> Hasil Latihan</h4>
                            <p class="opacity-75 mb-0"><?= $result['date'] ?? date('Y-m-d') ?> | RPE <?= $result['rpe'] ?>/10</p>
                        </div>
                        <span class="badge <?= $result['intensity_class'] ?> fs-6"><?= $result['intensity'] ?></span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">Jenis Latihan</div>
                                <div class="value" style="font-size:1rem;"><?= $result['workout_type'] ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">Durasi</div>
                                <div class="value"><?= $result['duration'] ?> <small>mnt</small></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">Kalori</div>
                                <div class="value"><?= $result['calories'] ?> <small>kcal</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">BMI</div>
                                <div class="value" style="font-size:1.2rem;"><?= $result['bmi'] ?> <small class="<?= $result['bmi_class'] ?>">(<?= $result['bmi_label'] ?>)</small></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">Lemak Tubuh</div>
                                <div class="value" style="font-size:1.2rem;"><?= $result['fat'] ?>% <small class="<?= $result['fat_class'] ?>">(<?= $result['fat_label'] ?>)</small></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-item">
                                <div class="label">Status</div>
                                <div class="value" style="font-size:0.9rem;"><?= $result['fat_label'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <h6 class="mb-2"><i class="bi bi-lightbulb"></i> Saran Hari Ini:</h6>
                            <?php foreach (array_slice($result['advice'], 0, 3) as $a): ?>
                                <div class="advice-item"><?= $a ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2"><i class="bi bi-flag"></i> Target Sesi Berikutnya:</h6>
                            <?php
                            $nextTargets = [];
                            if ($result['fat_label'] === 'Tinggi' || $result['fat_label'] === 'Rata-rata') {
                                $nextTargets[] = "Tingkatkan kardio minimal 30 menit untuk pembakaran lemak.";
                            }
                            if ($result['rpe'] < 5) {
                                $nextTargets[] = "Naikkan RPE target ke " . ($result['rpe'] + 2) . "/10 untuk progres.";
                            } elseif ($result['rpe'] >= 8) {
                                $nextTargets[] = "Turunkan RPE ke 6-7 untuk recovery yang lebih baik.";
                            }
                            if ($result['duration'] < 45) {
                                $nextTargets[] = "Target durasi: " . ($result['duration'] + 15) . " menit sesi depan.";
                            }
                            if ($result['bmi_label'] === 'Gemuk' || $result['bmi_label'] === 'Obesitas') {
                                $nextTargets[] = "Fokus strength training 2-3x/minggu untuk naikkan massa otot.";
                            }
                            if (empty($nextTargets)) {
                                $nextTargets[] = "Pertahankan ritme latihan saat ini. Variasikan jenis latihan.";
                                $nextTargets[] = "Target: tambah 1 sesi latihan minggu depan.";
                            }
                            foreach ($nextTargets as $t):
                            ?>
                                <div class="advice-item"><?= $t ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_GET['success'] === 'deleted' ? 'Latihan berhasil dihapus!' : 'Latihan berhasil dicatat!' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="card-title">Jenis Latihan:</h6>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge workout-strength">Strength Training</span>
                    <span class="badge workout-cardio">Cardio</span>
                    <span class="badge workout-flexibility">Flexibility</span>
                    <span class="badge workout-hiit">HIIT</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Riwayat Latihan</h5>
            </div>
            <div class="card-body">
                <?php if (empty($workouts)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                        <h5 class="mt-3">Belum ada data latihan</h5>
                        <p class="text-muted">Klik "Input Latihan" untuk menambahkan data latihan baru</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Member</th>
                                    <th>Jenis Latihan</th>
                                    <th>Durasi</th>
                                    <th>Kalori</th>
                                    <th>Intensitas</th>
                                    <th>RPE</th>
                                    <th>Lemak</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recentWorkouts = array_slice($workouts, -15, 15, true);
                                foreach (array_reverse($recentWorkouts, true) as $wid => $workout): 
                                    $memberName = 'Unknown';
                                    $memberGender = 'male';
                                    foreach ($members as $m) {
                                        if ($m['id'] == $workout['member_id']) {
                                            $memberName = $m['name'];
                                            $memberGender = $m['gender'] ?? 'male';
                                            break;
                                        }
                                    }
                                    $intensityClass = '';
                                    $intensityText = '';
                                    switch ($workout['intensity'] ?? '') {
                                        case 'low': $intensityClass = 'bg-info'; $intensityText = 'Ringan'; break;
                                        case 'medium': $intensityClass = 'bg-warning'; $intensityText = 'Sedang'; break;
                                        case 'high': $intensityClass = 'bg-danger'; $intensityText = 'Berat'; break;
                                    }
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($workout['date'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="member-photo me-2" style="width:30px;height:30px;font-size:0.8rem;">
                                                    <?= strtoupper(substr($memberName, 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($memberName) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge workout-<?= strtolower(str_replace(' ', '', $workout['workout_type'])) ?>">
                                                <?= $workout['workout_type'] ?>
                                            </span>
                                        </td>
                                        <td><?= $workout['duration'] ?> mnt</td>
                                        <td><?= $workout['calories'] ?? '-' ?> kcal</td>
                                        <td><span class="badge <?= $intensityClass ?>"><?= $intensityText ?></span></td>
                                        <td><?= $workout['rpe'] ?? '-' ?>/10</td>
                                        <td><?= $workout['body_fat'] ?? '-' ?>%</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" title="Detail & Download"
                                                onclick='showDetail(<?= json_encode($workout) ?>, <?= json_encode($memberName) ?>, <?= json_encode($memberGender) ?>)'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus latihan ini?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="workout_id" value="<?= $workout['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

    <!-- Detail & Download Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i> Detail Latihan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body detail-popup" id="detailContent">
                    <!-- Filled by JS -->
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="printDetail()"><i class="bi bi-printer"></i> Cetak</button>
                    <button type="button" class="btn btn-success" onclick="downloadDetail()"><i class="bi bi-download"></i> Download</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Workout Modal -->
    <div class="modal fade" id="addWorkoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Input Latihan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">1. Pilih Member *</label>
                            <select class="form-select" name="member_id" id="memberSelect" required>
                                <option value="">-- Pilih Member --</option>
                                <?php foreach ($activeMembers as $member): ?>
                                    <option value="<?= $member['id'] ?>" data-age="<?= $member['age'] ?>" data-gender="<?= $member['gender'] ?>">
                                        <?= htmlspecialchars($member['name']) ?> (<?= $member['age'] ?> thn)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">2. Tanggal *</label>
                            <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">3. Jenis Latihan *</label>
                            <select class="form-select" name="workout_type" id="workoutTypeSelect" required onchange="toggleCustomType()">
                                <option value="">-- Pilih Jenis Latihan --</option>
                                <option value="Strength Training">Strength Training</option>
                                <option value="Cardio">Cardio</option>
                                <option value="Flexibility">Flexibility</option>
                                <option value="HIIT">HIIT</option>
                                <option value="Lainnya">Lainnya (isi sendiri)</option>
                            </select>
                            <input type="text" class="form-control mt-2" id="customWorkoutType" name="custom_workout_type" placeholder="Tulis jenis latihan..." style="display:none;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">4. Durasi Latihan (menit) *</label>
                            <input type="number" class="form-control" name="duration" min="1" max="300" placeholder="Contoh: 45" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">5. RPE (Rate of Perceived Exertion) *</label>
                            <small class="text-muted d-block mb-2">Skala 1-10: 1 (sangat ringan) - 10 (sangat berat)</small>
                            <input type="hidden" name="rpe" id="rpeInput" value="5" required>
                            <div class="rpe-display" id="rpeDisplay">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <div class="rpe-dot <?= $i <= 5 ? 'active mid' : '' ?>" data-value="<?= $i ?>"><?= $i ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">6. Tinggi Badan (cm) *</label>
                                <input type="number" class="form-control" name="height" min="100" max="250" step="0.1" placeholder="170" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">7. Berat Badan (kg) *</label>
                                <input type="number" class="form-control" name="weight" min="30" max="300" step="0.1" placeholder="70" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">8. Usia (tahun) *</label>
                                <input type="number" class="form-control" name="age" min="10" max="100" placeholder="25" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Simpan & Lihat Hasil</button>
                    </div>
                </form>
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
        // RPE Selector
        document.querySelectorAll('.rpe-dot').forEach(dot => {
            dot.addEventListener('click', function() {
                const val = parseInt(this.dataset.value);
                document.getElementById('rpeInput').value = val;
                document.querySelectorAll('.rpe-dot').forEach(d => {
                    const v = parseInt(d.dataset.value);
                    d.classList.remove('active', 'low', 'mid', 'high');
                    if (v <= val) {
                        d.classList.add('active');
                        if (v <= 3) d.classList.add('low');
                        else if (v <= 6) d.classList.add('mid');
                        else d.classList.add('high');
                    }
                });
            });
        });

        // Auto-fill age when member is selected
        document.getElementById('memberSelect').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const age = selected.dataset.age;
            if (age) document.querySelector('input[name="age"]').value = age;
        });

        // Custom workout type toggle
        function toggleCustomType() {
            const sel = document.getElementById('workoutTypeSelect');
            const custom = document.getElementById('customWorkoutType');
            if (sel.value === 'Lainnya') {
                custom.style.display = 'block';
                custom.required = true;
                custom.focus();
            } else {
                custom.style.display = 'none';
                custom.required = false;
                custom.value = '';
            }
        }

        // Detail popup
        function showDetail(w, memberName, memberGender) {
            const intensityMap = {low: 'Ringan', medium: 'Sedang', high: 'Berat'};
            const intensityClassMap = {low: 'bg-info', medium: 'bg-warning', high: 'bg-danger'};
            const intensity = intensityMap[w.intensity] || '-';
            const intensityClass = intensityClassMap[w.intensity] || 'bg-secondary';

            // BMI: WHO Asia Pacific
            let bmiCat = '', bmiColor = '';
            if (w.bmi) {
                if (w.bmi < 18.5) { bmiCat = 'Kurus'; bmiColor = 'text-info'; }
                else if (w.bmi < 23) { bmiCat = 'Normal'; bmiColor = 'text-success'; }
                else if (w.bmi < 25) { bmiCat = 'Pre-Overweight'; bmiColor = 'text-warning'; }
                else if (w.bmi < 30) { bmiCat = 'Overweight'; bmiColor = 'text-warning'; }
                else { bmiCat = 'Obesitas'; bmiColor = 'text-danger'; }
            }

            // Body Fat: ACE categories
            let fatCat = '', fatColor = '';
            if (w.body_fat) {
                if (memberGender === 'male') {
                    if (w.body_fat < 6) { fatCat = 'Essential Fat'; fatColor = 'text-info'; }
                    else if (w.body_fat < 14) { fatCat = 'Atletis'; fatColor = 'text-info'; }
                    else if (w.body_fat < 18) { fatCat = 'Fitness'; fatColor = 'text-success'; }
                    else if (w.body_fat < 25) { fatCat = 'Rata-rata'; fatColor = 'text-warning'; }
                    else { fatCat = 'Obesitas'; fatColor = 'text-danger'; }
                } else {
                    if (w.body_fat < 14) { fatCat = 'Essential Fat'; fatColor = 'text-info'; }
                    else if (w.body_fat < 21) { fatCat = 'Atletis'; fatColor = 'text-info'; }
                    else if (w.body_fat < 25) { fatCat = 'Fitness'; fatColor = 'text-success'; }
                    else if (w.body_fat < 32) { fatCat = 'Rata-rata'; fatColor = 'text-warning'; }
                    else { fatCat = 'Obesitas'; fatColor = 'text-danger'; }
                }
            }

            let advice = [];
            if (w.rpe >= 8) advice.push('Intensitas sangat berat. Istirahat 7-9 jam & hidrasi 500ml/jam.');
            else if (w.rpe >= 6) advice.push('Intensitas sedang-berat. Pertahankan zona ini.');
            else if (w.rpe <= 3) advice.push('Intensitas ringan. Tingkatkan bertahap.');
            if (w.bmi > 25) advice.push('BMI ≥25. Kardio 150mnt/minggu + deficit 500kcal/hari.');
            else if (w.bmi < 18.5) advice.push('BMI <18.5. Surplus kalori + strength training.');
            if (w.body_fat > 25) advice.push('Body fat tinggi. Kardio 150-300mnt/minggu.');
            if (w.workout_type === 'Strength Training') advice.push('Protein 1.6-2.2g/kg BB (ACSM).');
            if (w.workout_type === 'Flexibility') advice.push('Flexibility 2-3x/minggu, tahan 15-30 detik.');
            if (w.workout_type === 'HIIT') advice.push('HIIT maks 3x/minggu, jeda 48 jam.');
            if (w.age > 40) advice.push('Pemanasan 10-15 menit. HR max = 220-usia.');
            if (advice.length === 0) advice.push('Pertahankan latihan konsisten & variatif.');

            let targets = [];
            if (fatCat === 'Obesitas' || fatCat === 'Rata-rata') targets.push('Kardio 3-4x/minggu, 30+ menit.');
            if (w.rpe < 5) targets.push('Naikkan RPE ke ' + (w.rpe + 2) + '/10.');
            else if (w.rpe >= 8) targets.push('Turunkan RPE ke 6-7 untuk recovery.');
            if (w.duration < 45) targets.push('Target durasi: ' + (w.duration + 15) + ' menit.');
            if (bmiCat === 'Overweight' || bmiCat === 'Obesitas') targets.push('Fokus strength 2-3x/minggu.');
            if (targets.length === 0) { targets.push('Pertahankan ritme saat ini.'); targets.push('Tambah 1 sesi minggu depan.'); }

            const html = `
                <div class="result-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="mb-1">${memberName}</h5>
                            <p class="opacity-75 mb-0" style="font-size:0.85rem">${w.date} | Usia ${w.age} thn | BB ${w.weight}kg | TB ${w.height}cm</p>
                        </div>
                        <span class="badge ${intensityClass}">${intensity} (RPE ${w.rpe})</span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><div class="result-item"><div class="label">Jenis</div><div class="value" style="font-size:1rem">${w.workout_type}</div></div></div>
                        <div class="col-4"><div class="result-item"><div class="label">Durasi</div><div class="value">${w.duration} mnt</div></div></div>
                        <div class="col-4"><div class="result-item"><div class="label">Kalori</div><div class="value">${w.calories} kcal</div></div></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><div class="result-item"><div class="label">BMI (WHO)</div><div class="value" style="font-size:1.1rem">${w.bmi || '-'} <small class="${bmiColor}">(${bmiCat})</small></div></div></div>
                        <div class="col-4"><div class="result-item"><div class="label">Body Fat (ACE)</div><div class="value" style="font-size:1.1rem">${w.body_fat || '-'}% <small class="${fatColor}">(${fatCat})</small></div></div></div>
                        <div class="col-4"><div class="result-item"><div class="label">MET</div><div class="value" style="font-size:1.1rem">${(w.calories / (w.weight * (w.duration/60))).toFixed(1)}</div></div></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <h6 class="mb-2"><i class="bi bi-lightbulb"></i> Saran (ACSM):</h6>
                            ${advice.map(a => '<div class="advice-item">' + a + '</div>').join('')}
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2"><i class="bi bi-flag"></i> Target Berikutnya:</h6>
                            ${targets.map(t => '<div class="advice-item">' + t + '</div>').join('')}
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('detailContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }

        function printDetail() {
            const content = document.getElementById('detailContent').innerHTML;
            const win = window.open('', '_blank');
            win.document.write(`<!DOCTYPE html><html><head><title>Detail Latihan - FitTrack</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body{padding:20px;font-family:sans-serif;font-size:13px;}
                    .result-card{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border-radius:12px;padding:1.2rem;}
                    .result-item{background:rgba(255,255,255,0.15);border-radius:8px;padding:0.6rem;text-align:center;}
                    .result-item .value{font-size:1.3rem;font-weight:700;}
                    .result-item .label{font-size:0.75rem;opacity:0.9;}
                    .advice-item{background:rgba(255,255,255,0.1);border-left:3px solid #fff;padding:0.5rem 0.75rem;margin-bottom:0.4rem;border-radius:0 6px 6px 0;font-size:0.85rem;}
                </style></head><body>${content}</body></html>`);
            win.document.close();
            setTimeout(() => { win.print(); win.close(); }, 400);
        }

        function downloadDetail() {
            const content = document.getElementById('detailContent').innerHTML;
            const blob = new Blob([`<!DOCTYPE html><html><head><title>Detail Latihan - FitTrack</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body{padding:20px;font-family:sans-serif;font-size:13px;}
                    .result-card{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border-radius:12px;padding:1.2rem;}
                    .result-item{background:rgba(255,255,255,0.15);border-radius:8px;padding:0.6rem;text-align:center;}
                    .result-item .value{font-size:1.3rem;font-weight:700;}
                    .result-item .label{font-size:0.75rem;opacity:0.9;}
                    .advice-item{background:rgba(255,255,255,0.1);border-left:3px solid #fff;padding:0.5rem 0.75rem;margin-bottom:0.4rem;border-radius:0 6px 6px 0;font-size:0.85rem;}
                </style></head><body>${content}</body></html>`], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'detail-latihan-' + Date.now() + '.html';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
