<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Public reports can be seen by logged-in users and guests, but if user is logged in they get full nav
$isUserLoggedIn = isLoggedIn();
$userId = $_SESSION['user_id'] ?? null;

// Filter and search parameters
$search = sanitize($_GET['q'] ?? '');
$categoryId = (int)($_GET['kategori'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

// Allowed public statuses (PRD Section 6.1: Laporan yang telah disetujui / Verified, In Progress, Completed)
$whereConditions = ["r.status IN ('Verified', 'In Progress', 'Completed')"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(r.title LIKE ? OR r.location LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryId > 0) {
    $whereConditions[] = "r.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($statusFilter) && in_array($statusFilter, ['Verified', 'In Progress', 'Completed'])) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $whereConditions);

try {
    // Total count for pagination
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $whereSql");
    $stmtCount->execute($params);
    $totalReports = $stmtCount->fetchColumn();
    $totalPages = ceil($totalReports / $limit);

    // Fetch reports
    $sql = "
        SELECT r.*, c.category_name, u.name as reporter_name,
               (SELECT image_path FROM report_images WHERE report_id = r.report_id ORDER BY image_id ASC LIMIT 1) as thumbnail
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.category_id
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE $whereSql
        ORDER BY r.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categories for filter
    $categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $reports = [];
    $totalReports = 0;
    $totalPages = 1;
    $categories = [];
}

$pageTitle = 'Laporan Publik';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link rel="icon" href="<?= APP_URL ?>/assets/img/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .public-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #D6CBB5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .public-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 22px rgba(52, 72, 59,0.12);
            border-color: #B5C2AA;
        }
        .public-card-img {
            width: 100%;
            aspect-ratio: 3/2;
            object-fit: cover;
            background: #F6F6F0;
        }
        .public-card-body {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .filter-panel {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            border: 1px solid #D6CBB5;
            margin-bottom: 25px;
        }
        .stat-ribbon {
            background: #34483B;
            color: #ffffff;
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body>

<?php if ($isUserLoggedIn): ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <main class="main-content">
<?php else: ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-pine py-3 shadow-sm" style="background:#34483B;">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="../index.php">
                <img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;">
            </a>
            <div class="ms-auto">
                <a href="../auth/login.php" class="btn btn-outline-light me-2">Masuk</a>
                <a href="../auth/register.php" class="btn btn-light fw-bold text-pine" style="color:#34483B;">Daftar Pelapor</a>
            </div>
        </div>
    </nav>
    <main class="container py-4">
<?php endif; ?>

    <!-- Ribbon Header -->
    <div class="stat-ribbon">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt me-2"></i>Transparansi Laporan Terestrial Terverifikasi</h4>
            <p class="mb-0 text-white-50 small">Dokumentasi terbuka tindakan pelestarian alam dan monitoring degradasi lahan (SDG 15).</p>
        </div>
        <div class="text-end d-none d-md-block">
            <span class="badge bg-light text-pine fs-6 px-3 py-2" style="color:#34483B;"><?= $totalReports ?> Laporan Publik</span>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-panel">
        <form method="GET" action="laporan_publik.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari insiden, lokasi, hutan..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="kategori" class="form-select">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $categoryId == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status Terbuka</option>
                    <option value="Verified" <?= $statusFilter === 'Verified' ? 'selected' : '' ?>>Terverifikasi</option>
                    <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>Sedang Ditangani</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Selesai Ditangani</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-success flex-grow-1" style="background:#34483B;border:none;">Filter</button>
                <?php if (!empty($search) || $categoryId > 0 || !empty($statusFilter)): ?>
                    <a href="laporan_publik.php" class="btn btn-outline-secondary" title="Reset"><i class="fas fa-redo"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Report Grid -->
    <?php if (empty($reports)): ?>
        <div class="card p-5 text-center shadow-sm border-0 my-4">
            <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
            <h5 class="fw-bold text-muted">Belum Ada Laporan Publik yang Sesuai</h5>
            <p class="text-muted small">Coba ubah kata kunci pencarian atau bersihkan filter di atas.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($reports as $r): 
                $thumb = getImageUrl($r['thumbnail'] ?? '');
                $statusLower = strtolower(str_replace(' ', '-', $r['status']));
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="public-card h-100">
                    <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($r['title']) ?>" class="public-card-img" onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                    <div class="public-card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['category_name'] ?? 'Umum') ?></span>
                            <span class="status-badge <?= $statusLower ?>">
                                <span class="dot"></span><?= htmlspecialchars($r['status']) ?>
                            </span>
                        </div>
                        <h6 class="fw-bold mb-2 text-dark line-clamp-2" style="min-height:2.6rem; line-height:1.4;">
                            <?= htmlspecialchars($r['title']) ?>
                        </h6>
                        <p class="text-muted small mb-2 line-clamp-1">
                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                            <?= htmlspecialchars($r['location']) ?>
                        </p>
                        <p class="text-muted small mb-3 line-clamp-2" style="line-height:1.4;">
                            <?= htmlspecialchars(strip_tags($r['description'])) ?>
                        </p>
                        <div class="border-top pt-2 d-flex justify-content-between align-items-center mt-auto">
                            <span class="small text-muted"><i class="fas fa-calendar-alt me-1"></i><?= formatDate($r['incident_date']) ?></span>
                            <a href="detail_publik.php?id=<?= $r['report_id'] ?>" class="btn btn-sm btn-outline-success mt-auto" style="color:#34483B;border-color:#34483B;">
                                Detail <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="d-flex justify-content-center">
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
