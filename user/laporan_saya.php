<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Pengguna';

// Filters
$search = sanitize($_GET['search'] ?? $_GET['q'] ?? '');
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filterDateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_at';
$sortDir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Allowed sort columns
$allowedSorts = ['report_id', 'title', 'incident_date', 'created_at', 'status'];
if (!in_array($sortBy, $allowedSorts)) $sortBy = 'created_at';

try {
    // Build query
    $where = "WHERE r.user_id = ?";
    $params = [$userId];

    if ($search !== '') {
        $where .= " AND (r.title LIKE ? OR r.location LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterStatus !== '') {
        $where .= " AND r.status = ?";
        $params[] = $filterStatus;
    }
    if ($filterCategory > 0) {
        $where .= " AND r.category_id = ?";
        $params[] = $filterCategory;
    }
    if ($filterDateFrom !== '') {
        $where .= " AND r.incident_date >= ?";
        $params[] = $filterDateFrom;
    }
    if ($filterDateTo !== '') {
        $where .= " AND r.incident_date <= ?";
        $params[] = $filterDateTo;
    }

    // Count total
    $countSql = "SELECT COUNT(*) FROM reports r $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $perPage));

    // Fetch reports
    $sql = "SELECT r.*, c.category_name
            FROM reports r
            LEFT JOIN categories c ON r.category_id = c.category_id
            $where
            ORDER BY r.$sortBy $sortDir
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categories for filter
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY category_name");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $reports = [];
    $categories = [];
    $totalRecords = 0;
    $totalPages = 1;
}

$pageTitle = 'Laporan Saya';

// Build query string helper
function buildQs($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    return http_build_query($params);
}

function sortLink($col, $label) {
    global $sortBy, $sortDir;
    $newDir = ($sortBy === $col && $sortDir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($sortBy === $col) {
        $icon = $sortDir === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    } else {
        $icon = '<i class="fas fa-sort ms-1 text-muted"></i>';
    }
    return '<a href="?'. buildQs(['sort' => $col, 'dir' => $newDir]) .'" style="color:inherit;text-decoration:none">'.$label.$icon.'</a>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link rel="icon" href="../assets/img/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4><i class="fas fa-folder-open me-2" style="color:var(--accent-green)"></i>Laporan Saya</h4>
            <p>Kelola dan pantau semua laporan insiden terestrial yang telah Anda kirimkan</p>
        </div>
        <a href="buat_laporan.php" class="btn-primary-green"><i class="fas fa-plus"></i> Buat Laporan Baru</a>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Cari judul atau lokasi laporan..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <select name="status">
            <option value="">Semua Status</option>
            <?php foreach (['Pending','Revision','Verified','In Progress','Completed','Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="category">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category_id'] ?>" <?= $filterCategory == $cat['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" title="Dari tanggal">
        <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" title="Sampai tanggal">

        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Cari</button>
        <a href="laporan_saya.php" class="btn-filter"><i class="fas fa-times"></i> Reset</a>
    </form>

    <!-- Reports Table -->
    <div class="full-table-card">
        <table class="report-table">
            <thead>
                <tr>
                    <th><?= sortLink('report_id', 'ID Laporan') ?></th>
                    <th><?= sortLink('title', 'Judul Laporan') ?></th>
                    <th>Kategori</th>
                    <th><?= sortLink('incident_date', 'Tgl Kejadian') ?></th>
                    <th><?= sortLink('created_at', 'Tgl Lapor') ?></th>
                    <th><?= sortLink('status', 'Status') ?></th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox me-2" style="font-size:1.5rem"></i><br>
                        Tidak ada laporan ditemukan.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($reports as $report):
                    $reportId = generateReportId($report['report_id'], $report['created_at'] ?? null);
                    $categoryClass = getCategoryBadgeClassLS($report['category_name'] ?? '');
                    $statusLower = strtolower(str_replace(' ', '-', $report['status']));
                ?>
                <tr>
                    <td><span class="report-id"><?= $reportId ?></span></td>
                    <td class="report-title-cell">
                        <span class="rt-title"><?= htmlspecialchars($report['title']) ?></span>
                        <span class="rt-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars(mb_strimwidth($report['location'] ?? '-', 0, 50, '...')) ?></span>
                    </td>
                    <td><span class="category-badge <?= $categoryClass ?>"><?= htmlspecialchars($report['category_name'] ?? '-') ?></span></td>
                    <td><?= formatDate($report['incident_date'] ?? '') ?></td>
                    <td><?= formatDate($report['created_at'] ?? '') ?></td>
                    <td>
                        <span class="status-badge <?= $statusLower ?>">
                            <span class="dot"></span>
                            <?= htmlspecialchars(statusLabel($report['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="detail_laporan.php?id=<?= $report['report_id'] ?>" class="action-btn view" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                            <?php if (in_array($report['status'], ['Pending', 'Revision'])): ?>
                            <a href="edit_laporan.php?id=<?= $report['report_id'] ?>" class="action-btn edit" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                            <?php if ($report['status'] === 'Pending'): ?>
                            <button class="action-btn delete" title="Hapus" onclick="confirmDelete(<?= $report['report_id'] ?>)"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="table-pagination">
            <span>Menampilkan <?= count($reports) ?> dari <?= $totalRecords ?> total laporan (Halaman <?= $page ?> dari <?= $totalPages ?>)</span>
            <div class="pag-buttons">
                <?php if ($page > 1): ?>
                <a href="?<?= buildQs(['page' => $page - 1]) ?>" class="pag-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <a href="?<?= buildQs(['page' => $i]) ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?<?= buildQs(['page' => $page + 1]) ?>" class="pag-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="table-pagination">
            <span>Menampilkan <?= count($reports) ?> dari <?= $totalRecords ?> total laporan</span>
            <div></div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmDelete(id) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Laporan?',
            text: 'Laporan yang dihapus tidak dapat dipulihkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#C17B58',
            cancelButtonColor: '#34483B',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hapus_laporan.php?id=' + id;
            }
        });
    } else {
        if (confirm('Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.')) {
            window.location.href = 'hapus_laporan.php?id=' + id;
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php
function getCategoryBadgeClassLS($categoryName) {
    $map = [
        'Penebangan Liar' => 'deforestasi',
        'Deforestasi' => 'deforestasi',
        'Kebakaran Hutan/Lahan' => 'kebakaran',
        'Kebakaran Hutan' => 'kebakaran',
        'Pembuangan Sampah' => 'sampah',
        'Kerusakan Habitat' => 'habitat',
        'Degradasi Lahan' => 'degradasi',
        'Pencemaran Darat' => 'pencemaran',
        'Lahan Gambut' => 'gambut',
    ];
    return $map[$categoryName] ?? 'deforestasi';
}
?>
