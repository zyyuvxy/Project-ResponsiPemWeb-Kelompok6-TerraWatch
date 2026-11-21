<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Filter & search params
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'date_desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(r.report_id LIKE ? OR r.title LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}
if ($filterStatus) {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = "r.category_id = ?";
    $params[] = $filterCategory;
}
if ($dateFrom) {
    $where[] = "r.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "r.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

switch ($sortBy) {
    case 'date_asc': $orderBy = 'r.created_at ASC'; break;
    case 'status_asc': $orderBy = 'r.status ASC, r.created_at DESC'; break;
    case 'status_desc': $orderBy = 'r.status DESC, r.created_at DESC'; break;
    default: $orderBy = 'r.created_at DESC'; break;
}

// Count total
$countSql = "SELECT COUNT(*) FROM reports r JOIN users u ON r.user_id = u.user_id $whereClause";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch data
$sql = "SELECT r.*, u.name as reporter_name, c.category_name 
        FROM reports r 
        JOIN users u ON r.user_id = u.user_id 
        JOIN categories c ON r.category_id = c.category_id 
        $whereClause 
        ORDER BY $orderBy 
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categories for filter
$stmtCats = $pdo->query("SELECT * FROM categories ORDER BY category_name");
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

// Build query string for pagination
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);

$pageTitle = 'Manajemen Laporan';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-clipboard-list me-2"></i>Manajemen Laporan</h4>
                <p class="text-muted mb-0">Total: <?= $totalRecords ?> laporan</p>
            </div>
            <a href="ekspor_laporan.php?<?= $queryString ?>" class="btn text-white" style="background-color:#34483B;">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </a>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="ID, judul, atau nama pelapor..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <?php foreach (['Pending','Revision','Verified','In Progress','Completed','Rejected'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="0">Semua Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $filterCategory == $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
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
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn text-white w-100" style="background-color:#789078;">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </form>
                <div class="mt-2 d-flex justify-content-between">
                    <div>
                        <label class="form-label small mb-0 me-2">Urutkan:</label>
                        <div class="btn-group btn-group-sm">
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'date_desc'])) ?>" class="btn <?= $sortBy === 'date_desc' ? 'text-white' : 'btn-outline-secondary' ?>" <?= $sortBy === 'date_desc' ? 'style="background-color:#34483B;"' : '' ?>>Terbaru</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'date_asc'])) ?>" class="btn <?= $sortBy === 'date_asc' ? 'text-white' : 'btn-outline-secondary' ?>" <?= $sortBy === 'date_asc' ? 'style="background-color:#34483B;"' : '' ?>>Terlama</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'status_asc'])) ?>" class="btn <?= $sortBy === 'status_asc' ? 'text-white' : 'btn-outline-secondary' ?>" <?= $sortBy === 'status_asc' ? 'style="background-color:#34483B;"' : '' ?>>Status A-Z</a>
                        </div>
                    </div>
                    <?php if ($search || $filterStatus || $filterCategory || $dateFrom || $dateTo): ?>
                    <a href="manajemen_laporan.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Reset Filter</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Pelapor</th>
                                <th>Kategori</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Tidak ada laporan ditemukan</td></tr>
                            <?php else: ?>
                            <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><span class="fw-bold">#<?= $r['report_id'] ?></span></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($r['title']) ?>">
                                        <?= htmlspecialchars($r['title']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                                <td><span class="badge" style="background-color:#789078;"><?= htmlspecialchars($r['category_name']) ?></span></td>
                                <td><?= formatDate($r['created_at']) ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($r['status']) ?>"><?= e(statusLabel($r['status'])) ?></span></td>
                                <td class="text-center">
                                    <a href="detail_laporan.php?id=<?= $r['report_id'] ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($r['status'] === 'Pending'): ?>
                                    <a href="detail_laporan.php?id=<?= $r['report_id'] ?>&action=verify" class="btn btn-sm btn-outline-success" title="Verifikasi">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="detail_laporan.php?id=<?= $r['report_id'] ?>&action=update" class="btn btn-sm btn-outline-warning" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </a>
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
                <small class="text-muted">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRecords) ?> dari <?= $totalRecords ?> laporan</small>
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
