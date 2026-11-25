<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    $userId = (int)$_POST['user_id'];

    // Prevent self role change
    if ($userId === (int)$_SESSION['user_id']) {
        $_SESSION['error'] = 'Anda tidak dapat mengubah role Anda sendiri.';
        redirect('manajemen_pengguna.php');
    }

    $stmtUser = $pdo->prepare("SELECT role, name FROM users WHERE user_id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $newRole = $user['role'] === 'admin' ? 'user' : 'admin';
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->execute([$newRole, $userId]);
        logActivity($pdo, $_SESSION['user_id'], "Mengubah role pengguna \"{$user['name']}\" menjadi $newRole");
        $_SESSION['success'] = "Role pengguna \"{$user['name']}\" berhasil diubah menjadi $newRole.";
    }
    redirect('manajemen_pengguna.php');
}

// Handle active status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $userId = (int)$_POST['user_id'];

    // Prevent self deactivation
    if ($userId === (int)$_SESSION['user_id']) {
        $_SESSION['error'] = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
        redirect('manajemen_pengguna.php');
    }

    $stmtUser = $pdo->prepare("SELECT role, name, is_active FROM users WHERE user_id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['role'] === 'admin') {
            $_SESSION['error'] = 'Admin tidak dapat menonaktifkan sesama admin.';
            redirect('manajemen_pengguna.php');
        }

        $newActive = ((int)$user['is_active'] === 1) ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $stmt->execute([$newActive, $userId]);
        $actionText = $newActive === 1 ? 'mengaktifkan' : 'menonaktifkan';
        logActivity($pdo, $_SESSION['user_id'], "Admin $actionText akun pengguna \"{$user['name']}\"");
        $_SESSION['success'] = "Akun \"{$user['name']}\" berhasil " . ($newActive === 1 ? 'diaktifkan' : 'dinonaktifkan') . ".";
    }
    redirect('manajemen_pengguna.php');
}

// Search & pagination
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search) {
    $where = "WHERE u.name LIKE ? OR u.email LIKE ?";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam];
}

// Count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch users with report count
$sql = "SELECT u.*, COUNT(r.report_id) as report_count 
        FROM users u 
        LEFT JOIN reports r ON u.user_id = r.user_id 
        $where 
        GROUP BY u.user_id 
        ORDER BY u.created_at DESC 
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);

$pageTitle = 'Manajemen Pengguna';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <?php if (isset($_SESSION['success'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'success',title:'Berhasil',text:'<?= $_SESSION['success'] ?>',timer:2000,showConfirmButton:false}));</script>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'error',title:'Gagal',text:'<?= $_SESSION['error'] ?>'}));</script>
        <?php unset($_SESSION['error']); endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-users me-2"></i>Manajemen Pengguna</h4>
                <p class="text-muted mb-0">Total: <?= $totalRecords ?> pengguna</p>
            </div>
        </div>

        <!-- Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama atau email pengguna..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn text-white w-100" style="background-color:#34483B;">
                            <i class="fas fa-search me-1"></i>Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Jumlah Laporan</th>
                                <th>Tanggal Daftar</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengguna ditemukan</td></tr>
                            <?php else: ?>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars(getImageUrl($u['profile_photo'])) ?>" class="rounded-circle me-2" width="35" height="35" style="object-fit:cover;" alt="" onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                                        <div>
                                            <strong><?= e($u['name']) ?></strong>
                                            <?php if ($u['phone']): ?>
                                             <br><small class="text-muted"><?= e($u['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge" style="background-color:#34483B;"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                                    <?php else: ?>
                                    <span class="badge" style="background-color:#B5C2AA;color:#34483B;"><i class="fas fa-user me-1"></i>User</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge" style="background-color:#789078;"><i class="fas fa-check-circle me-1"></i>Aktif</span>
                                    <?php else: ?>
                                    <span class="badge" style="background-color:#C17B58;"><i class="fas fa-ban me-1"></i>Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge" style="background-color:#789078;"><?= $u['report_count'] ?></span></td>
                                <td><?= formatDate($u['created_at']) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userDetailModal<?= $u['user_id'] ?>" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-warning" onclick="toggleRole(<?= $u['user_id'] ?>, '<?= e(addslashes($u['name'])) ?>', '<?= $u['role'] ?>')" title="Toggle Role">
                                        <i class="fas fa-user-cog"></i>
                                    </button>
                                    <?php if ($u['role'] !== 'admin'): ?>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="toggleStatus(<?= $u['user_id'] ?>, '<?= e(addslashes($u['name'])) ?>', 1)" title="Nonaktifkan Akun">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="toggleStatus(<?= $u['user_id'] ?>, '<?= e(addslashes($u['name'])) ?>', 0)" title="Aktifkan Akun">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- User Detail Modal -->
                            <div class="modal fade" id="userDetailModal<?= $u['user_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header" style="background-color:#34483B;">
                                            <h5 class="modal-title text-white"><i class="fas fa-user me-2"></i>Detail Pengguna</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= htmlspecialchars(getImageUrl($u['profile_photo'])) ?>" class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover;" alt="" onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                                            <h5><?= e($u['name']) ?></h5>
                                            <p class="text-muted"><?= e($u['email']) ?></p>
                                            <table class="table table-borderless text-start">
                                                <tr><th class="text-muted">Role</th><td><?= ucfirst($u['role']) ?></td></tr>
                                                <tr><th class="text-muted">Status Akun</th><td><?= (int)$u['is_active'] === 1 ? '<span class="badge" style="background-color:#789078;">Aktif</span>' : '<span class="badge" style="background-color:#C17B58;">Nonaktif</span>' ?></td></tr>
                                                <tr><th class="text-muted">Telepon</th><td><?= e($u['phone'] ?? '-') ?></td></tr>
                                                <tr><th class="text-muted">Alamat</th><td><?= e($u['address'] ?? '-') ?></td></tr>
                                                <tr><th class="text-muted">Jumlah Laporan</th><td><?= $u['report_count'] ?></td></tr>
                                                <tr><th class="text-muted">Terdaftar</th><td><?= formatDateTime($u['created_at']) ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                <small class="text-muted">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRecords) ?> dari <?= $totalRecords ?> pengguna</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $queryString ?>&page=<?= $page - 1 ?>">«</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $queryString ?>&page=<?= $i ?>" <?= $i === $page ? 'style="background-color:#34483B;border-color:#34483B;"' : '' ?>><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $queryString ?>&page=<?= $page + 1 ?>">»</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Toggle Role Form -->
<form id="toggleRoleForm" method="POST" style="display:none;">
    <input type="hidden" name="toggle_role" value="1">
    <input type="hidden" name="user_id" id="toggleUserId">
</form>

<!-- Toggle Status Form -->
<form id="toggleStatusForm" method="POST" style="display:none;">
    <input type="hidden" name="toggle_status" value="1">
    <input type="hidden" name="user_id" id="toggleStatusUserId">
</form>

<script>
function toggleRole(id, name, currentRole) {
    const newRole = currentRole === 'admin' ? 'user' : 'admin';
    Swal.fire({
        title: 'Ubah Role Pengguna?',
        html: 'Role <strong>' + name + '</strong> akan diubah dari <strong>' + currentRole + '</strong> menjadi <strong>' + newRole + '</strong>.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#34483B',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('toggleUserId').value = id;
            document.getElementById('toggleRoleForm').submit();
        }
    });
}

function toggleStatus(id, name, currentStatus) {
    const action = currentStatus === 1 ? 'menonaktifkan' : 'mengaktifkan';
    const confirmBtn = currentStatus === 1 ? 'Ya, Nonaktifkan!' : 'Ya, Aktifkan!';
    const btnColor = currentStatus === 1 ? '#C17B58' : '#789078';
    Swal.fire({
        title: (currentStatus === 1 ? 'Nonaktifkan' : 'Aktifkan') + ' Akun?',
        html: 'Apakah Anda yakin ingin ' + action + ' akun <strong>' + name + '</strong>?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: btnColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmBtn,
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('toggleStatusUserId').value = id;
            document.getElementById('toggleStatusForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
