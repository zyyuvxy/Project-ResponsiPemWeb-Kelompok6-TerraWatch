<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search) {
    $where[] = "(al.activity LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}
if ($filterUser) {
    $where[] = "al.user_id = ?";
    $params[] = $filterUser;
}
if ($dateFrom) {
    $where[] = "al.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "al.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id $whereClause");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch logs
$sql = "SELECT al.*, u.name as user_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        $whereClause 
        ORDER BY al.created_at DESC 
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Users for filter dropdown
$stmtUsers = $pdo->query("SELECT user_id, name FROM users ORDER BY name");
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);

$pageTitle = 'Log Aktivitas';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-history me-2"></i>Log Aktivitas</h4>
                <p class="text-muted mb-0">Total: <?= $totalRecords ?> log</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Cari aktivitas..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Pengguna</label>
                        <select name="user_id" class="form-select">
                            <option value="0">Semua Pengguna</option>
                            <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['user_id'] ?>" <?= $filterUser == $u['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn text-white" style="background-color:#34483B;">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <?php if ($search || $filterUser || $dateFrom || $dateTo): ?>
                        <a href="log_aktivitas.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Aktivitas</th>
                                <th>IP Address</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-history fa-2x mb-2 d-block"></i>Tidak ada log ditemukan</td></tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log['log_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($log['activity']) ?></td>
                                <td><code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                                <td>
                                    <span title="<?= formatDateTime($log['created_at']) ?>">
                                        <?= timeAgo($log['created_at']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                <small class="text-muted">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRecords) ?> dari <?= $totalRecords ?> log</small>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
