<?php
session_start();

$membersFile = __DIR__ . '/../data/members.json';
$workoutFile = __DIR__ . '/../data/workouts.json';

$members = file_exists($membersFile) ? json_decode(file_get_contents($membersFile), true) : [];
$workouts = file_exists($workoutFile) ? json_decode(file_get_contents($workoutFile), true) : [];

$activeMembers = array_filter($members, function($m) {
    return $m['status'] === 'active';
});

$selectedMember = $_GET['member_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');

$filteredWorkouts = $workouts;
if ($selectedMember) {
    $filteredWorkouts = array_filter($filteredWorkouts, function($w) use ($selectedMember) {
        return $w['member_id'] == $selectedMember;
    });
}
$filteredWorkouts = array_filter($filteredWorkouts, function($w) use ($dateFrom) {
    return $w['date'] == $dateFrom;
});
usort($filteredWorkouts, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

$totalSessions = count($filteredWorkouts);
$totalDuration = 0;
$totalCalories = 0;
$avgRpe = 0;
$rpeCount = 0;
$latestFat = null;
$latestWeight = null;
$latestBmi = null;
$latestHeight = null;
$latestAge = null;
$memberInfo = null;
$firstDate = null;
$lastDate = null;

foreach ($filteredWorkouts as $w) {
    $totalDuration += $w['duration'];
    $totalCalories += $w['calories'] ?? 0;
    if (isset($w['rpe'])) { $avgRpe += $w['rpe']; $rpeCount++; }
    if (isset($w['body_fat'])) $latestFat = $w['body_fat'];
    if (isset($w['weight'])) $latestWeight = $w['weight'];
    if (isset($w['bmi'])) $latestBmi = $w['bmi'];
    if (isset($w['height'])) $latestHeight = $w['height'];
    if (isset($w['age'])) $latestAge = $w['age'];
    if (!$firstDate || $w['date'] < $firstDate) $firstDate = $w['date'];
    if (!$lastDate || $w['date'] > $lastDate) $lastDate = $w['date'];
}

$avgRpeVal = $rpeCount > 0 ? round($avgRpe / $rpeCount, 1) : '-';
$avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0;
$avgCalories = $totalSessions > 0 ? round($totalCalories / $totalSessions) : 0;
$weekSpan = $firstDate && $lastDate ? max(1, round((strtotime($lastDate) - strtotime($firstDate)) / 604800)) : 1;
$sessionsPerWeek = round($totalSessions / $weekSpan, 1);

if ($selectedMember) {
    foreach ($members as $m) {
        if ($m['id'] == $selectedMember) {
            $memberInfo = $m;
            break;
        }
    }
}

// Kategori BMI: WHO Asia Pacific
function getBmiInfo($bmi) {
    if (!$bmi) return ['-', '', ''];
    if ($bmi < 18.5) return [$bmi, 'Kurus', 'text-info'];
    if ($bmi < 23) return [$bmi, 'Normal', 'text-success'];
    if ($bmi < 25) return [$bmi, 'Pre-Overweight', 'text-warning'];
    if ($bmi < 30) return [$bmi, 'Overweight', 'text-warning'];
    return [$bmi, 'Obesitas', 'text-danger'];
}

// Kategori Body Fat: ACE
function getFatInfo($fat, $gender) {
    if (!$fat) return ['-', '', ''];
    if ($gender === 'male') {
        if ($fat < 6) return [$fat, 'Essential', 'text-info'];
        if ($fat < 14) return [$fat, 'Atletis', 'text-info'];
        if ($fat < 18) return [$fat, 'Fitness', 'text-success'];
        if ($fat < 25) return [$fat, 'Rata-rata', 'text-warning'];
        return [$fat, 'Obesitas', 'text-danger'];
    } else {
        if ($fat < 14) return [$fat, 'Essential', 'text-info'];
        if ($fat < 21) return [$fat, 'Atletis', 'text-info'];
        if ($fat < 25) return [$fat, 'Fitness', 'text-success'];
        if ($fat < 32) return [$fat, 'Rata-rata', 'text-warning'];
        return [$fat, 'Obesitas', 'text-danger'];
    }
}

$bmiInfo = getBmiInfo($latestBmi);
$gender = $memberInfo['gender'] ?? 'male';
$fatInfo = getFatInfo($latestFat, $gender);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Latihan - FitTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @page { size: A4; margin: 12mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; font-size: 11px !important; margin: 0; padding: 0; }
            .container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; margin-bottom: 6px !important; break-inside: avoid; }
            .card-body { padding: 8px 10px !important; }
            .report-header { padding: 10px 15px !important; margin-bottom: 8px !important; border-radius: 8px !important; }
            .report-header h4 { font-size: 16px !important; margin-bottom: 2px !important; }
            .report-header p { font-size: 10px !important; margin-bottom: 1px !important; }
            .summary-stat { padding: 4px !important; }
            .summary-stat .val { font-size: 14px !important; }
            .summary-stat .lbl { font-size: 9px !important; }
            .stat-badge { padding: 4px 8px !important; }
            .stat-badge .stat-val { font-size: 14px !important; }
            .stat-badge .stat-lbl { font-size: 8px !important; }
            .stat-badge .stat-cat { font-size: 9px !important; }
            .table { font-size: 10px !important; }
            .table th { padding: 3px 5px !important; font-size: 9px !important; }
            .table td { padding: 2px 5px !important; }
            .eval-section { padding: 6px 10px !important; }
            .eval-section h6 { font-size: 10px !important; margin-bottom: 3px !important; }
            .eval-section li { font-size: 10px !important; margin-bottom: 2px !important; }
            .print-footer { display: block !important; margin-top: 8px; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 4px; }
        }
        .print-footer { display: none; }
        body { background: #f4f6f9; }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border-radius: 15px; padding: 25px;
        }
        .summary-stat {
            background: rgba(255,255,255,0.15); border-radius: 10px;
            padding: 12px; text-align: center;
        }
        .summary-stat .val { font-size: 1.6rem; font-weight: 700; }
        .summary-stat .lbl { font-size: 0.75rem; opacity: 0.85; }
        .stat-badge {
            border-radius: 12px; padding: 10px 15px; text-align: center;
            color: white; position: relative; overflow: hidden;
        }
        .stat-badge::after {
            content: ''; position: absolute; top: -20px; right: -20px;
            width: 60px; height: 60px; border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .stat-badge .stat-icon { font-size: 1.5rem; opacity: 0.9; }
        .stat-badge .stat-val { font-size: 1.3rem; font-weight: 700; }
        .stat-badge .stat-lbl { font-size: 0.75rem; opacity: 0.85; }
        .stat-badge .stat-cat { font-size: 0.8rem; font-weight: 600; display: block; margin-top: 2px; }
        .stat-bmi { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-fat { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-rpe { background: linear-gradient(135deg, #ffd200 0%, #f7971e 100%); }
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; font-size: 0.75rem; padding: 6px 5px;
        }
        .table tbody tr:hover { background: rgba(102, 126, 234, 0.05); }
        .table tfoot td {
            background: #f8f9fa; font-weight: 600; font-size: 0.75rem; padding: 5px;
        }
        .eval-card { border-left: 4px solid; border-radius: 0 10px 10px 0; }
        .eval-result { border-color: #28a745; }
        .eval-target { border-color: #ffc107; }
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
                    <li class="nav-item"><a class="nav-link active" href="/reports"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-3 no-print">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-3">
                    <img src="/logo_ssfc-removebg-preview.png" alt="Logo SSFC" style="height: 60px;">
                    <img src="/active_human_logo-removebg-preview.png" alt="Active Human" style="height: 60px; background: white; border-radius: 8px; padding: 4px;">
                    <div>
                        <h2 class="fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Latihan</h2>
                        <p class="text-muted mb-0">Laporan hasil latihan individual member</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Cetak / Download</button>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-3 no-print">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1" style="font-size:0.85rem">Member</label>
                        <select class="form-select form-select-sm" name="member_id">
                            <option value="">-- Semua Member --</option>
                            <?php foreach ($activeMembers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $selectedMember == $m['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1" style="font-size:0.85rem">Tanggal Latihan</label>
                        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= $dateFrom ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Header -->
        <div class="report-header mb-3">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <h4 class="mb-1">
                        <?php if ($memberInfo): ?>
                            <i class="bi bi-person-badge"></i> <?= htmlspecialchars($memberInfo['name']) ?>
                        <?php else: ?>
                            <i class="bi bi-people"></i> Semua Member
                        <?php endif; ?>
                    </h4>
                    <p class="mb-1">
                        Tanggal: <?= $dateFrom ? date('d M Y', strtotime($dateFrom)) : '-' ?>
                    </p>
                    <?php if ($memberInfo): ?>
                        <p class="mb-0 opacity-75" style="font-size:0.85rem">
                            <?= $memberInfo['gender'] === 'male' ? 'Laki-laki' : 'Perempuan' ?> | <?= $memberInfo['age'] ?> thn | <?= htmlspecialchars($memberInfo['goal'] ?? '-') ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-7">
                    <div class="row g-2">
                        <div class="col-3"><div class="summary-stat"><div class="val"><?= $totalSessions ?></div><div class="lbl">Sesi</div></div></div>
                        <div class="col-3"><div class="summary-stat"><div class="val"><?= $totalDuration ?></div><div class="lbl">Total Mnt</div></div></div>
                        <div class="col-3"><div class="summary-stat"><div class="val"><?= number_format($totalCalories) ?></div><div class="lbl">Total Kalori</div></div></div>
                        <div class="col-3"><div class="summary-stat"><div class="val"><?= $sessionsPerWeek ?>/m</div><div class="lbl">Frekuensi</div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Body Stats -->
        <?php if ($memberInfo && $latestBmi): ?>
        <div class="row mb-3">
            <div class="col-md-4 mb-2">
                <div class="stat-badge stat-bmi">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-lbl">BMI (WHO)</div>
                            <div class="stat-val"><?= $bmiInfo[0] ?></div>
                        </div>
                        <i class="bi bi-speedometer stat-icon"></i>
                    </div>
                    <span class="stat-cat"><?= $bmiInfo[1] ?></span>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="stat-badge stat-fat">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-lbl">Body Fat (ACE)</div>
                            <div class="stat-val"><?= $fatInfo[0] ?>%</div>
                        </div>
                        <i class="bi bi-body-text stat-icon"></i>
                    </div>
                    <span class="stat-cat"><?= $fatInfo[1] ?></span>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="stat-badge stat-rpe">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-lbl">RPE Rata-rata</div>
                            <div class="stat-val"><?= $avgRpeVal ?>/10</div>
                        </div>
                        <i class="bi bi-fire stat-icon"></i>
                    </div>
                    <span class="stat-cat"><?= is_numeric($avgRpeVal) ? ($avgRpeVal <= 2 ? 'Sangat Ringan' : ($avgRpeVal <= 4 ? 'Ringan' : ($avgRpeVal <= 6 ? 'Sedang' : ($avgRpeVal <= 8 ? 'Berat' : 'Sangat Berat')))) : '-' ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detail Table -->
        <div class="card mb-3">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table"></i> Riwayat Latihan</h6>
                <span class="badge bg-primary"><?= $totalSessions ?> sesi</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($filteredWorkouts)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                        <p class="mt-2 text-muted">Tidak ada data latihan untuk periode ini</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="30">#</th>
                                    <th>Tanggal</th>
                                    <th>Jenis Latihan</th>
                                    <th class="text-center">Durasi</th>
                                    <th class="text-center">Kalori</th>
                                    <th class="text-center">RPE</th>
                                    <th>Intensitas</th>
                                    <th class="text-center">BB/TB</th>
                                    <th class="text-center">Lemak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredWorkouts as $idx => $w):
                                    $mName = 'Unknown';
                                    foreach ($members as $m) {
                                        if ($m['id'] == $w['member_id']) { $mName = $m['name']; break; }
                                    }
                                    $intensityClass = '';
                                    $intensityText = '';
                                    switch ($w['intensity'] ?? '') {
                                        case 'low': $intensityClass = 'bg-info'; $intensityText = 'Ringan'; break;
                                        case 'medium': $intensityClass = 'bg-warning text-dark'; $intensityText = 'Sedang'; break;
                                        case 'high': $intensityClass = 'bg-danger'; $intensityText = 'Berat'; break;
                                    }
                                ?>
                                    <tr>
                                        <td class="text-muted"><?= $idx + 1 ?></td>
                                        <td><?= date('d/m', strtotime($w['date'])) ?></td>
                                        <td><span class="badge workout-<?= strtolower(str_replace(' ', '', $w['workout_type'])) ?>" style="font-size:0.65rem"><?= $w['workout_type'] ?></span></td>
                                        <td class="text-center"><?= $w['duration'] ?>m</td>
                                        <td class="text-center"><?= $w['calories'] ?? '-' ?></td>
                                        <td class="text-center fw-bold"><?= $w['rpe'] ?? '-' ?></td>
                                        <td><span class="badge <?= $intensityClass ?>" style="font-size:0.65rem"><?= $intensityText ?></span></td>
                                        <td class="text-center" style="font-size:0.75rem"><?= $w['weight'] ?? '-' ?>/<?= $w['height'] ?? '-' ?></td>
                                        <td class="text-center"><?= $w['body_fat'] ?? '-' ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="fw-bold">TOTAL / RATA-RATA</td>
                                    <td class="text-center fw-bold"><?= $totalDuration ?>m</td>
                                    <td class="text-center fw-bold"><?= number_format($totalCalories) ?></td>
                                    <td class="text-center fw-bold"><?= $avgRpeVal ?></td>
                                    <td colspan="3" style="font-size:0.7rem">Avg: <?= $avgDuration ?>m/sesi | <?= $avgCalories ?> kkal/sesi | <?= $sessionsPerWeek ?> sesi/minggu</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Hasil Latihan -->
        <?php if (!empty($filteredWorkouts)): ?>
        <div class="card mb-3">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard-data"></i> Detail Hasil Latihan</h6>
            </div>
            <div class="card-body">
                <?php foreach ($filteredWorkouts as $w):
                    $mName = 'Unknown';
                    $mGender = 'male';
                    foreach ($members as $m) {
                        if ($m['id'] == $w['member_id']) { $mName = $m['name']; $mGender = $m['gender'] ?? 'male'; break; }
                    }
                    $bmiVal = $w['bmi'] ?? null;
                    $bmiCat = ''; $bmiColor = '';
                    if ($bmiVal) {
                        if ($bmiVal < 18.5) { $bmiCat = 'Kurus'; $bmiColor = 'text-info'; }
                        elseif ($bmiVal < 23) { $bmiCat = 'Normal'; $bmiColor = 'text-success'; }
                        elseif ($bmiVal < 25) { $bmiCat = 'Pre-Overweight'; $bmiColor = 'text-warning'; }
                        elseif ($bmiVal < 30) { $bmiCat = 'Overweight'; $bmiColor = 'text-warning'; }
                        else { $bmiCat = 'Obesitas'; $bmiColor = 'text-danger'; }
                    }
                    $fatVal = $w['body_fat'] ?? null;
                    $fatCat = ''; $fatColor = '';
                    if ($fatVal) {
                        if ($mGender === 'male') {
                            if ($fatVal < 6) { $fatCat = 'Essential Fat'; $fatColor = 'text-info'; }
                            elseif ($fatVal < 14) { $fatCat = 'Atletis'; $fatColor = 'text-info'; }
                            elseif ($fatVal < 18) { $fatCat = 'Fitness'; $fatColor = 'text-success'; }
                            elseif ($fatVal < 25) { $fatCat = 'Rata-rata'; $fatColor = 'text-warning'; }
                            else { $fatCat = 'Obesitas'; $fatColor = 'text-danger'; }
                        } else {
                            if ($fatVal < 14) { $fatCat = 'Essential Fat'; $fatColor = 'text-info'; }
                            elseif ($fatVal < 21) { $fatCat = 'Atletis'; $fatColor = 'text-info'; }
                            elseif ($fatVal < 25) { $fatCat = 'Fitness'; $fatColor = 'text-success'; }
                            elseif ($fatVal < 32) { $fatCat = 'Rata-rata'; $fatColor = 'text-warning'; }
                            else { $fatCat = 'Obesitas'; $fatColor = 'text-danger'; }
                        }
                    }
                    $intensityText = '';
                    $intensityClass = '';
                    switch ($w['intensity'] ?? '') {
                        case 'low': $intensityText = 'Ringan'; $intensityClass = 'bg-info'; break;
                        case 'medium': $intensityText = 'Sedang'; $intensityClass = 'bg-warning text-dark'; break;
                        case 'high': $intensityText = 'Berat'; $intensityClass = 'bg-danger'; break;
                    }
                    $met = ($w['weight'] > 0 && $w['duration'] > 0) ? round($w['calories'] / ($w['weight'] * ($w['duration'] / 60)), 1) : '-';
                ?>
                <div class="result-card mb-3 p-3" style="border: 1px solid #e0e0e0; border-radius: 10px;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($mName) ?></h6>
                            <small class="text-muted"><?= $w['date'] ?> | Usia <?= $w['age'] ?? '-' ?> thn | BB <?= $w['weight'] ?? '-' ?>kg | TB <?= $w['height'] ?? '-' ?>cm</small>
                        </div>
                        <span class="badge <?= $intensityClass ?>"><?= $intensityText ?> (RPE <?= $w['rpe'] ?? '-' ?>)</span>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-2"><div class="text-center p-2 bg-light rounded"><small class="text-muted d-block">Jenis</small><strong><?= $w['workout_type'] ?></strong></div></div>
                        <div class="col-md-2"><div class="text-center p-2 bg-light rounded"><small class="text-muted d-block">Durasi</small><strong><?= $w['duration'] ?> mnt</strong></div></div>
                        <div class="col-md-2"><div class="text-center p-2 bg-light rounded"><small class="text-muted d-block">Kalori</small><strong><?= $w['calories'] ?? '-' ?> kcal</strong></div></div>
                        <div class="col-md-2"><div class="text-center p-2 bg-light rounded"><small class="text-muted d-block">BMI</small><strong class="<?= $bmiColor ?>"><?= $bmiVal ?: '-' ?> <small>(<?= $bmiCat ?>)</small></strong></div></div>
                        <div class="col-md-2"><div class="text-center p-2 bg-light rounded"><small class="text-muted d-block">Body Fat</small><strong class="<?= $fatColor ?>"><?= $fatVal ?: '-' ?>% <small>(<?= $fatCat ?>)</small></strong></div></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Evaluasi & Target -->
        <?php if ($memberInfo && $totalSessions > 0): ?>
        <div class="row mb-3">
            <div class="col-md-6 mb-2">
                <div class="card eval-card eval-result h-100">
                    <div class="card-body eval-section">
                        <h6 class="fw-bold text-success"><i class="bi bi-check-circle"></i> Hasil Periode Ini</h6>
                        <ul class="list-unstyled mb-0">
                            <li><i class="bi bi-dot text-success"></i> <?= $totalSessions ?> sesi latihan, total <?= $totalDuration ?> menit</li>
                            <li><i class="bi bi-dot text-success"></i> Total pembakaran: <?= number_format($totalCalories) ?> kalori</li>
                            <li><i class="bi bi-dot text-success"></i> Frekuensi: <?= $sessionsPerWeek ?> sesi/minggu (<?= $weekSpan ?> minggu)</li>
                            <li><i class="bi bi-dot text-success"></i> Rata-rata: <?= $avgDuration ?> menit, <?= $avgCalories ?> kkal/sesi</li>
                            <?php if ($latestBmi): ?>
                                <li><i class="bi bi-dot text-success"></i> BMI terakhir: <?= $bmiInfo[0] ?> — <?= $bmiInfo[1] ?></li>
                            <?php endif; ?>
                            <?php if ($latestFat): ?>
                                <li><i class="bi bi-dot text-success"></i> Body fat terakhir: <?= $fatInfo[0] ?>% — <?= $fatInfo[1] ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-2">
                <div class="card eval-card eval-target h-100">
                    <div class="card-body eval-section">
                        <h6 class="fw-bold text-warning"><i class="bi bi-flag"></i> Target Sesi Berikutnya</h6>
                        <ul class="list-unstyled mb-0">
                            <?php
                            $targets = [];
                            if ($latestFat && $fatInfo[1] === 'Obesitas') $targets[] = "Kardio 150-300 menit/minggu (ACSM)";
                            elseif ($latestFat && $fatInfo[1] === 'Rata-rata') $targets[] = "Tingkatkan kardio 3-4x/minggu, 30+ menit";
                            if ($avgDuration < 30) $targets[] = "Durasi minimal 30 menit/sesi (ACSM)";
                            if ($sessionsPerWeek < 2) $targets[] = "Minimal 2-3 sesi/minggu untuk progres";
                            if ($latestBmi && $bmiInfo[1] === 'Overweight') $targets[] = "Deficit 500 kkal/hari + kardio rutin";
                            elseif ($latestBmi && $bmiInfo[1] === 'Kurus') $targets[] = "Surplus kalori + strength training";
                            if (is_numeric($avgRpeVal) && $avgRpeVal < 5) $targets[] = "Naikkan intensitas ke RPE 5-7";
                            elseif (is_numeric($avgRpeVal) && $avgRpeVal >= 8) $targets[] = "Turunkan RPE ke 6-7 untuk recovery";
                            $targets[] = "Variasikan latihan: Strength + Cardio + Flexibility";
                            $targets[] = "Pantau BB & body fat setiap 2 minggu";
                            $targets = array_slice($targets, 0, 5);
                            foreach ($targets as $t):
                            ?>
                                <li><i class="bi bi-dot text-warning"></i> <?= $t ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Referensi -->
        <div class="card mb-3">
            <div class="card-body py-2" style="font-size:0.75rem; color:#666">
                <strong>Referensi:</strong>
                WHO BMI Classification (2000) | ACE Body Fat Categories | ACSM Guidelines for Exercise Testing (11th Ed) |
                Compendium of Physical Activities (Ainsworth 2011) | Borg CR-10 RPE Scale | Deurenberg Formula (1998)
            </div>
        </div>
    </div>

    <div class="print-footer">
        <div class="d-flex align-items-center justify-content-center gap-3 mb-1">
            <img src="/logo_ssfc-removebg-preview.png" alt="Logo SSFC" style="height: 60px;">
            <img src="/active_human_logo-removebg-preview.png" alt="Active Human" style="height: 60px; background: white; border-radius: 8px; padding: 4px;">
        </div>
        FitTrack — Monitoring Fitness App | Laporan dicetak pada <?= date('d M Y H:i') ?>
    </div>

    <footer class="bg-dark text-white py-3 mt-3 no-print">
        <div class="container text-center">
            <p class="mb-0">&copy; 2026 FitTrack - Monitoring Fitness App</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
