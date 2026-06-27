<?php
session_start();
require_once __DIR__ . '/helpers.php';

$membersFileRead = getReadFile('members.json');
$membersFileWrite = getWriteFile('members.json');
$members = file_exists($membersFileRead) ? json_decode(file_get_contents($membersFileRead), true) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $newMember = [
                'id' => uniqid(),
                'name' => htmlspecialchars($_POST['name']),
                'age' => (int)$_POST['age'],
                'gender' => $_POST['gender'],
                'goal' => htmlspecialchars($_POST['goal']),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $members[] = $newMember;
            file_put_contents($membersFileWrite, json_encode($members, JSON_PRETTY_PRINT));
            header('Location: /members?success=added');
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            $members = array_filter($members, function($m) use ($id) {
                return $m['id'] !== $id;
            });
            file_put_contents($membersFileWrite, json_encode(array_values($members), JSON_PRETTY_PRINT));
            header('Location: /members?success=deleted');
            exit;
        } elseif ($_POST['action'] === 'toggle') {
            $id = $_POST['id'];
            foreach ($members as &$m) {
                if ($m['id'] === $id) {
                    $m['status'] = $m['status'] === 'active' ? 'inactive' : 'active';
                    break;
                }
            }
            file_put_contents($membersFileWrite, json_encode($members, JSON_PRETTY_PRINT));
            header('Location: /members?success=toggled');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - FitTrack</title>
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
                        <a class="nav-link active" href="/members"><i class="bi bi-people"></i> Members</a>
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

    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold"><i class="bi bi-people"></i> Members</h2>
                <p class="text-muted">Kelola data member fitness</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-person-plus"></i> Tambah Member
                </button>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                $messages = [
                    'added' => 'Member berhasil ditambahkan!',
                    'deleted' => 'Member berhasil dihapus!',
                    'toggled' => 'Status member berhasil diubah!'
                ];
                echo $messages[$_GET['success']] ?? 'Aksi berhasil!';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="searchInput" placeholder="Cari member..." onkeyup="searchMembers()">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter" onchange="filterMembers()">
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="genderFilter" onchange="filterMembers()">
                            <option value="">Semua Gender</option>
                            <option value="male">Laki-laki</option>
                            <option value="female">Perempuan</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($members)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-person-x fs-1 text-muted"></i>
                        <h5 class="mt-3">Belum ada member</h5>
                        <p class="text-muted">Klik "Tambah Member" untuk menambahkan member baru</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="membersTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Usia</th>
                                    <th>Gender</th>
                                    <th>Goal</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $index => $member): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="member-photo me-2">
                                                    <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                                </div>
                                                <?= htmlspecialchars($member['name']) ?>
                                            </div>
                                        </td>
                                        <td><?= $member['age'] ?></td>
                                        <td>
                                            <span class="badge <?= $member['gender'] === 'male' ? 'bg-primary' : 'bg-danger' ?>">
                                                <?= $member['gender'] === 'male' ? 'Laki-laki' : 'Perempuan' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($member['goal']) ?></td>
                                        <td>
                                            <span class="badge <?= $member['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $member['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="Detail" onclick="viewMember('<?= $member['id'] ?>')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-warning" title="Toggle Status">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus member ini?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
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

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Tambah Member Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usia *</label>
                                <input type="number" class="form-control" name="age" min="15" max="80" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender *</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Pilih Gender</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tujuan Fitness</label>
                            <textarea class="form-control" name="goal" rows="3" placeholder="Contoh: Menurunkan berat badan, Membentuk otot, dll."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
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
        function searchMembers() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#membersTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }

        function filterMembers() {
            const status = document.getElementById('statusFilter').value;
            const gender = document.getElementById('genderFilter').value;
            const rows = document.querySelectorAll('#membersTable tbody tr');
            
            rows.forEach(row => {
                const rowStatus = row.querySelector('.badge.bg-success, .badge.bg-secondary');
                const rowGender = row.querySelector('.badge.bg-primary, .badge.bg-danger');
                
                let showRow = true;
                
                if (status && rowStatus) {
                    const isStatusMatch = (status === 'active' && rowStatus.classList.contains('bg-success')) ||
                                        (status === 'inactive' && rowStatus.classList.contains('bg-secondary'));
                    if (!isStatusMatch) showRow = false;
                }
                
                if (gender && rowGender) {
                    const isGenderMatch = (gender === 'male' && rowGender.classList.contains('bg-primary')) ||
                                        (gender === 'female' && rowGender.classList.contains('bg-danger'));
                    if (!isGenderMatch) showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }

        function viewMember(id) {
            // Can be extended to show member details in a modal
            alert('Detail member ID: ' + id);
        }
    </script>
</body>
</html>
