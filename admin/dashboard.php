<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Statistics
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM reports");
$totalReports = (int)$stmtTotal->fetchColumn();

$statuses = ['Pending', 'Revision', 'Verified', 'In Progress', 'Completed', 'Rejected'];
$statusCounts = [];
foreach ($statuses as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = ?");
    $stmt->execute([$s]);
    $statusCounts[$s] = (int)$stmt->fetchColumn();
}

// Reports per category (bar chart)
$stmtCat = $pdo->query("SELECT c.category_name, COUNT(r.report_id) as total FROM categories c LEFT JOIN reports r ON c.category_id = r.category_id GROUP BY c.category_id, c.category_name ORDER BY total DESC");
$catData = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
$catLabels = array_column($catData, 'category_name');
$catValues = array_column($catData, 'total');

// Reports per status (doughnut chart - 6 status)
$statusLabels = [];
$statusValues = [];
foreach ($statuses as $s) {
    $statusLabels[] = statusLabel($s);
    $statusValues[] = $statusCounts[$s];
}

// Reports per month (line chart - last 6 months)
$monthLabels = [];
$monthValues = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $monthLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$date]);
    $monthValues[] = $stmt->fetchColumn();
}

// Laporan Pending terbaru untuk blok "Perlu Tindakan"
$stmtPending = $pdo->query("SELECT r.*, u.name as reporter_name, c.category_name 
    FROM reports r 
    JOIN users u ON r.user_id = u.user_id 
    JOIN categories c ON r.category_id = c.category_id 
    WHERE r.status = 'Pending' 
    ORDER BY r.created_at ASC 
    LIMIT 5");
$pendingReports = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// Recent reports (last 5)
$stmtRecent = $pdo->query("SELECT r.*, u.name as reporter_name, c.category_name FROM reports r JOIN users u ON r.user_id = u.user_id JOIN categories c ON r.category_id = c.category_id ORDER BY r.created_at DESC LIMIT 5");
$recentReports = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

// Recent activities (last 10)
$stmtActivities = $pdo->query("SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 10");
$recentActivities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h4>
                <p class="text-muted mb-0">Ringkasan data dan statistik TerraWatch</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge fs-6 text-white" style="background:#34483B;"><i class="fas fa-file-alt me-1"></i>Total: <?= $totalReports ?> Laporan</span>
                <span class="badge bg-success fs-6"><i class="fas fa-calendar me-1"></i><?= date('d M Y') ?></span>
            </div>
        </div>

        <!-- 6 Status Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #C17B58 !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('Pending') ?></div>
                        <h3 class="mb-0" style="color: #C17B58;"><?= $statusCounts['Pending'] ?></h3>
                        <i class="fas fa-clock text-muted mt-1"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #D6CBB5 !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('Revision') ?></div>
                        <h3 class="mb-0" style="color: #8C7B5D;"><?= $statusCounts['Revision'] ?></h3>
                        <i class="fas fa-edit text-muted mt-1"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #789078 !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('Verified') ?></div>
                        <h3 class="mb-0" style="color: #789078;"><?= $statusCounts['Verified'] ?></h3>
                        <i class="fas fa-check-circle text-muted mt-1"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #B5C2AA !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('In Progress') ?></div>
                        <h3 class="mb-0" style="color: #465E4E;"><?= $statusCounts['In Progress'] ?></h3>
                        <i class="fas fa-spinner text-muted mt-1"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #34483B !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('Completed') ?></div>
                        <h3 class="mb-0" style="color: #34483B;"><?= $statusCounts['Completed'] ?></h3>
                        <i class="fas fa-check-double text-muted mt-1"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #A65B49 !important;">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1"><?= statusLabel('Rejected') ?></div>
                        <h3 class="mb-0" style="color: #A65B49;"><?= $statusCounts['Rejected'] ?></h3>
                        <i class="fas fa-times-circle text-muted mt-1"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Blok Perlu Tindakan (Laporan Pending Terbaru) -->
        <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #C17B58 !important;">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                <div>
                    <h6 class="mb-0 fw-bold" style="color:#C17B58;">
                        <i class="fas fa-exclamation-circle me-2"></i>Perlu Tindakan: Laporan Menunggu Verifikasi (<?= count($pendingReports) ?>)
                    </h6>
                    <small class="text-muted">Laporan berstatus Menunggu Verifikasi yang membutuhkan peninjauan administrator</small>
                </div>
                <a href="manajemen_laporan.php?status=Pending" class="btn btn-sm text-white" style="background-color:#C17B58;">
                    Lihat Semua Pending
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>Judul Laporan</th>
                                <th>Pelapor</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th>Waktu Masuk</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingReports)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-2x mb-2 d-block" style="color:#789078;"></i>
                                    Semua laporan telah ditinjau. Tidak ada laporan pending saat ini.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($pendingReports as $pr): ?>
                            <tr>
                                <td><span class="fw-bold">#<?= $pr['report_id'] ?></span></td>
                                <td>
                                    <div class="text-truncate fw-bold" style="max-width: 250px;" title="<?= e($pr['title']) ?>">
                                        <?= e($pr['title']) ?>
                                    </div>
                                </td>
                                <td><?= e($pr['reporter_name']) ?></td>
                                <td><span class="badge" style="background-color:#789078;"><?= e($pr['category_name']) ?></span></td>
                                <td><small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?= e($pr['location']) ?></small></td>
                                <td><small class="text-muted"><?= timeAgo($pr['created_at']) ?></small></td>
                                <td class="text-center">
                                    <a href="detail_laporan.php?id=<?= $pr['report_id'] ?>" class="btn btn-sm text-white" style="background-color:#34483B;">
                                        <i class="fas fa-search me-1"></i>Tinjau
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2" style="color:#34483B;"></i>Laporan per Kategori</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2" style="color:#34483B;"></i>Laporan per Status</h6>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <canvas id="statusChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2" style="color:#34483B;"></i>Laporan per Bulan</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="monthChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports & Activities -->
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list me-2" style="color:#34483B;"></i>Laporan Terbaru</h6>
                        <a href="manajemen_laporan.php" class="btn btn-sm text-white" style="background-color:#34483B;">Lihat Semua</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background-color: #F6F6F0;">
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Pelapor</th>
                                        <th>Kategori</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentReports)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada laporan</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($recentReports as $r): ?>
                                    <tr onclick="window.location='detail_laporan.php?id=<?= $r['report_id'] ?>'" style="cursor:pointer;">
                                        <td><span class="fw-bold">#<?= $r['report_id'] ?></span></td>
                                        <td><?= htmlspecialchars($r['title']) ?></td>
                                        <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                                        <td><span class="badge" style="background-color:#789078;"><?= htmlspecialchars($r['category_name']) ?></span></td>
                                        <td><span class="badge <?= getStatusBadgeClass($r['status']) ?>"><?= e(statusLabel($r['status'])) ?></span></td>
                                        <td><?= formatDate($r['created_at']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-history me-2" style="color:#34483B;"></i>Aktivitas Terbaru</h6>
                        <a href="log_aktivitas.php" class="btn btn-sm text-white" style="background-color:#34483B;">Lihat Semua</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($recentActivities)): ?>
                            <li class="list-group-item text-center text-muted py-3">Belum ada aktivitas</li>
                            <?php else: ?>
                            <?php foreach ($recentActivities as $act): ?>
                            <li class="list-group-item border-0 px-3 py-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong class="small"><?= htmlspecialchars($act['user_name'] ?? 'System') ?></strong>
                                        <p class="mb-0 small text-muted"><?= htmlspecialchars($act['activity']) ?></p>
                                    </div>
                                    <small class="text-muted text-nowrap ms-2"><?= timeAgo($act['created_at']) ?></small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Bar Chart - Laporan per Kategori
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{
            label: 'Jumlah Laporan',
            data: <?= json_encode(array_map('intval', $catValues)) ?>,
            backgroundColor: ['#34483B','#789078','#B5C2AA','#C17B58','#D6CBB5','#465E4E'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Doughnut Chart - Laporan per Status
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', $statusValues)) ?>,
            backgroundColor: ['#C17B58','#D6CBB5','#789078','#465E4E','#34483B','#A65B49'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8 } } }
    }
});

// Line Chart - Laporan per Bulan
new Chart(document.getElementById('monthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [{
            label: 'Laporan',
            data: <?= json_encode(array_map('intval', $monthValues)) ?>,
            borderColor: '#34483B',
            backgroundColor: 'rgba(52,72,59,0.1)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#789078',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
