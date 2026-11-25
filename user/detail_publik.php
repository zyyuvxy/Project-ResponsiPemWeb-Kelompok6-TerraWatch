<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

$isUserLoggedIn = isLoggedIn();
$reportId = (int)($_GET['id'] ?? 0);

if ($reportId <= 0) {
    redirect('laporan_publik.php');
}

try {
    // Only fetch if status is public
    $stmt = $pdo->prepare("
        SELECT r.*, c.category_name, u.name as reporter_name
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.category_id
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE r.report_id = ? AND r.status IN ('Verified', 'In Progress', 'Completed')
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        redirect('laporan_publik.php');
    }

    // Fetch images
    $stmtImg = $pdo->prepare("SELECT * FROM report_images WHERE report_id = ?");
    $stmtImg->execute([$reportId]);
    $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    // Fetch status history
    $stmtHistory = $pdo->prepare("
        SELECT ru.*, a.name as changer_name, a.role as changer_role
        FROM report_updates ru
        LEFT JOIN users a ON ru.changed_by = a.user_id
        WHERE ru.report_id = ?
        ORDER BY ru.created_at ASC, ru.update_id ASC
    ");
    $stmtHistory->execute([$reportId]);
    $updates = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    redirect('laporan_publik.php');
}

$pageTitle = 'Detail Laporan Publik ' . generateReportId($report['report_id'], $report['created_at'] ?? null);
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
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .detail-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 28px;
            border: 1px solid #D6CBB5;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .gallery-thumb {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .gallery-thumb:hover {
            transform: scale(1.03);
        }
        .timeline-item {
            position: relative;
            padding-left: 28px;
            padding-bottom: 20px;
            border-left: 2px solid #D6CBB5;
        }
        .timeline-item:last-child {
            border-left: 2px solid transparent;
            padding-bottom: 0;
        }
        .timeline-dot {
            position: absolute;
            left: -8px;
            top: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #34483B;
            border: 2px solid #ffffff;
        }
        #public-map {
            height: 260px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<?php if ($isUserLoggedIn): ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <main class="main-content">
<?php else: ?>
    <nav class="navbar navbar-dark bg-pine py-3 shadow-sm" style="background:#34483B;">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="laporan_publik.php">
                <img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;">
                <span class="ms-2">Laporan Publik</span>
            </a>
            <a href="../auth/login.php" class="btn btn-outline-light btn-sm">Masuk Portal</a>
        </div>
    </nav>
    <main class="container py-4">
<?php endif; ?>

    <div class="mb-3">
        <a href="laporan_publik.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Katalog Publik
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-secondary mb-2"><?= generateReportId($report['report_id'], $report['created_at'] ?? null) ?></span>
                        <h3 class="fw-bold text-pine mb-1"><?= htmlspecialchars($report['title']) ?></h3>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($report['location']) ?>
                        </p>
                    </div>
                    <?php $statusLower = strtolower(str_replace(' ', '-', $report['status'])); ?>
                    <span class="status-badge <?= $statusLower ?> fs-6 px-3 py-2">
                        <span class="dot"></span><?= htmlspecialchars(statusLabel($report['status'])) ?>
                    </span>
                </div>

                <hr class="my-3">

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Kategori Kerusakan</small>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($report['category_name'] ?? 'Umum') ?></span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Tanggal Kejadian Dilaporkan</small>
                        <span class="fw-bold text-dark"><?= formatDate($report['incident_date']) ?></span>
                    </div>
                </div>

                <h6 class="fw-bold text-pine mb-2">Deskripsi Kejadian:</h6>
                <div class="p-3 bg-light rounded-3 mb-4 text-dark" style="line-height:1.6;">
                    <?= nl2br(htmlspecialchars($report['description'])) ?>
                </div>

                <!-- Foto Bukti -->
                <h6 class="fw-bold text-pine mb-2">Dokumentasi Bukti Lapangan:</h6>
                <?php if (empty($images)): ?>
                    <p class="text-muted small">Tidak ada foto lampiran.</p>
                <?php else: ?>
                    <div class="row g-2 mb-3">
                        <?php foreach ($images as $img): 
                            $imgSrc = getImageUrl($img['image_path']);
                        ?>
                            <div class="col-4 col-md-3">
                                <a href="<?= htmlspecialchars($imgSrc) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="gallery-thumb" alt="Foto Bukti" onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted"><i class="fas fa-search-plus me-1"></i> Klik gambar untuk memperbesar resolusi asli.</small>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Map Card -->
            <?php if (!empty($report['latitude']) && !empty($report['longitude'])): ?>
            <div class="detail-card">
                <h6 class="fw-bold text-pine mb-2"><i class="fas fa-map-marked-alt me-1"></i> Titik Geospasial</h6>
                <div id="public-map" class="mb-2"></div>
                <div class="small text-muted">
                    Koordinat: <?= htmlspecialchars($report['latitude']) ?>, <?= htmlspecialchars($report['longitude']) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Timeline -->
            <div class="detail-card">
                <h6 class="fw-bold text-pine mb-3"><i class="fas fa-history me-1"></i> Riwayat Penanganan</h6>
                <div class="timeline">
                    <div class="timeline-item">
                        <span class="timeline-dot"></span>
                        <div class="fw-bold small">Laporan Dibuat</div>
                        <div class="text-muted small"><?= formatDateTime($report['created_at']) ?></div>
                    </div>
                    <?php foreach ($updates as $up): ?>
                    <div class="timeline-item">
                        <span class="timeline-dot" style="background:#789078;"></span>
                        <div class="fw-bold small text-pine">Status: <?= htmlspecialchars(statusLabel($up['new_status'])) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;">oleh <?= ($up['changed_by'] == $report['user_id'] ? 'Pelapor' : htmlspecialchars($up['changer_name'] ?? 'Admin')) ?></div>
                        <?php if (!empty($up['note'])): ?>
                            <div class="small text-secondary my-1">"<?= htmlspecialchars($up['note']) ?>"</div>
                        <?php endif; ?>
                        <div class="text-muted small"><?= formatDateTime($up['created_at']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if (!empty($report['latitude']) && !empty($report['longitude'])): ?>
<script>
var map = L.map('public-map').setView([<?= (float)$report['latitude'] ?>, <?= (float)$report['longitude'] ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.marker([<?= (float)$report['latitude'] ?>, <?= (float)$report['longitude'] ?>]).addTo(map)
    .bindPopup('<b><?= htmlspecialchars($report['title']) ?></b><br><?= htmlspecialchars($report['location']) ?>')
    .openPopup();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
