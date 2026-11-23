<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Auth check
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Pengguna';

// Fetch stats
try {
    // Total reports
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalReports = $stmt->fetchColumn();

    // Pending
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'Pending'");
    $stmt->execute([$userId]);
    $pendingReports = $stmt->fetchColumn();

    // In Progress (Verified + In Progress)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status IN ('Verified','In Progress')");
    $stmt->execute([$userId]);
    $inProgressReports = $stmt->fetchColumn();

    // Completed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'Completed'");
    $stmt->execute([$userId]);
    $completedReports = $stmt->fetchColumn();

    // Laporan Terverifikasi (Verified + In Progress + Completed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status IN ('Verified', 'In Progress', 'Completed')");
    $stmt->execute([$userId]);
    $verifiedReports = (int)$stmt->fetchColumn();

    // Coordinates for Leaflet Map
    $stmtMap = $pdo->prepare("SELECT report_id, title, latitude, longitude, status FROM reports WHERE user_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL");
    $stmtMap->execute([$userId]);
    $userMapReports = $stmtMap->fetchAll(PDO::FETCH_ASSOC);

    // Resolution rate
    $resolutionRate = $totalReports > 0 ? round(($completedReports / $totalReports) * 100, 1) : 0;

    // Recent reports (3)
    $stmt = $pdo->prepare("
        SELECT r.*, c.category_name
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.category_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total reports for pagination text
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalReportsCount = $stmt->fetchColumn();

    // Notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unread notification count
    $unreadCount = getUnreadNotificationCount($pdo, $userId);

    // User TERRA ID
    $terraId = 'TERRA-ID-' . str_pad($userId, 5, '0', STR_PAD_LEFT);

} catch (PDOException $e) {
    $totalReports = $pendingReports = $inProgressReports = $completedReports = $verifiedReports = 0;
    $resolutionRate = 0;
    $recentReports = [];
    $userMapReports = [];
    $notifications = [];
    $unreadCount = 0;
    $terraId = 'TERRA-ID-00000';
}

$pageTitle = 'Dashboard';
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
    @media print {
        .sidebar, .topbar, .rtc-actions, .quick-action-panel, .notifications-panel, .tips-card, .btn-new-report, .map-section, .table-pagination {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 15px !important;
            width: 100% !important;
        }
        .welcome-banner {
            background: #F6F6F0 !important;
            color: #34483B !important;
            border: 1px solid #D6CBB5 !important;
            box-shadow: none !important;
        }
        .welcome-banner .wb-quote, .welcome-banner h2 {
            color: #34483B !important;
        }
        .report-table-card {
            box-shadow: none !important;
            border: 1px solid #D6CBB5 !important;
            width: 100% !important;
        }
    }
    .report-table th:first-child,
    .report-table td:first-child,
    .report-id {
        white-space: nowrap !important;
    }
    .report-table th,
    .report-table td {
        padding: 10px 12px !important;
    }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">

    <!-- A. Welcome Banner -->
    <div class="welcome-banner">
        <div class="wb-left">
            <div class="wb-badge">
                <i class="fas fa-leaf"></i>
                KONTRIBUTOR KONSERVASI TERESTRIAL &nbsp;|&nbsp; ID: <?= $terraId ?>
            </div>
            <h2>Selamat Datang Kembali, <?= htmlspecialchars($userName) ?>!</h2>
            <p class="wb-quote">Setiap laporan yang Anda kirimkan berkontribusi langsung pada perlindungan hutan dan habitat satwa liar Indonesia (SDG 15).</p>
        </div>
        <div class="wb-right">
            <div class="impact-circle">
                <svg class="circle-progress" viewBox="0 0 130 130">
                    <circle cx="65" cy="65" r="58" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="6"/>
                    <circle cx="65" cy="65" r="58" fill="none" stroke="#B5C2AA" stroke-width="6"
                            stroke-dasharray="364.4" stroke-dashoffset="<?= $totalReports > 0 ? (364.4 - (364.4 * ($verifiedReports / $totalReports))) : 364.4 ?>"
                            stroke-linecap="round" transform="rotate(-90 65 65)"/>
                </svg>
                <span class="percentage"><?= $verifiedReports ?></span>
                <span class="pct-label">Laporan</span>
            </div>
            <div class="impact-label">Laporan Terverifikasi</div>
        </div>
    </div>

    <!-- B. Statistics Cards -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-header">
                <span class="sc-label">Total Laporan Saya</span>
                <div class="sc-icon green"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="sc-value"><?= $totalReports ?></div>
            <div class="sc-sub">Total Terdata</div>
            <div class="sc-footer">
                <span class="sc-tag green-tag"><i class="fas fa-check-circle"></i> Seluruh Laporan</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="sc-header">
                <span class="sc-label">Menunggu Verifikasi</span>
                <div class="sc-icon orange"><i class="fas fa-clock"></i></div>
            </div>
            <div class="sc-value"><?= $pendingReports ?></div>
            <div class="sc-sub">Laporan Baru</div>
            <div class="sc-footer">
                <span class="sc-tag orange-tag"><i class="fas fa-hourglass-half"></i> Menunggu Tindakan Admin</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="sc-header">
                <span class="sc-label">Sedang Diproses</span>
                <div class="sc-icon blue"><i class="fas fa-cogs"></i></div>
            </div>
            <div class="sc-value"><?= $inProgressReports ?></div>
            <div class="sc-sub">Ditindaklanjuti</div>
            <div class="sc-footer">
                <span class="sc-tag blue-tag"><i class="fas fa-tasks"></i> Tindak Lanjut Berjalan</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="sc-header">
                <span class="sc-label">Laporan Selesai</span>
                <div class="sc-icon teal"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="sc-value"><?= $completedReports ?></div>
            <div class="sc-sub">Telah Ditangani</div>
            <div class="sc-footer">
                <span class="sc-tag green-tag"><i class="fas fa-chart-line"></i> Resolusi <?= $resolutionRate ?>%</span>
            </div>
        </div>
    </div>

    <!-- C & D. Report Table + Quick Action Sidebar -->
    <div class="dashboard-row">

        <!-- C. Report Table -->
        <div class="report-table-card">
            <div class="rtc-header">
                <div class="rtc-title">
                    <h5><i class="fas fa-table me-2"></i>Tabel Laporan Terbaru Saya</h5>
                    <p>Riwayat pelaporan insiden terestrial yang Anda kirimkan</p>
                </div>
                <div class="rtc-actions">
                    <button class="btn-filter" onclick="window.location.href='laporan_saya.php'"><i class="fas fa-filter"></i> Filter Status</button>
                    <button class="btn-export" onclick="window.print()"><i class="fas fa-print me-1"></i> Cetak</button>
                </div>
            </div>

            <table class="report-table">
                <thead>
                    <tr>
                        <th>ID Laporan</th>
                        <th>Judul Laporan</th>
                        <th>Kategori</th>
                        <th>Tgl Kejadian</th>
                        <th>Tgl Lapor</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentReports)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada laporan. Mulai laporkan insiden!</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recentReports as $report):
                        $reportId = generateReportId($report['report_id'] ?? 0, $report['created_at'] ?? null);
                        $categoryClass = getCategoryBadgeClass($report['category_name'] ?? '');
                        $statusClass = getStatusBadgeClass($report['status']);
                        $statusLower = strtolower(str_replace(' ', '-', $report['status']));
                    ?>
                    <tr>
                        <td><span class="report-id"><?= $reportId ?></span></td>
                        <td class="report-title-cell">
                            <span class="rt-title"><?= htmlspecialchars($report['title']) ?></span>
                            <span class="rt-location"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars(mb_strimwidth($report['location'] ?? '-', 0, 40, '...')) ?></span>
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
                            <div class="dropdown">
                                <button class="kebab-menu" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="detail_laporan.php?id=<?= $report['report_id'] ?>"><i class="fas fa-eye me-2"></i>Lihat Detail</a></li>
                                    <?php if (in_array($report['status'], ['Pending', 'Revision'])): ?>
                                    <li><a class="dropdown-item" href="edit_laporan.php?id=<?= $report['report_id'] ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?= $report['report_id'] ?>)"><i class="fas fa-trash me-2"></i>Hapus</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="table-pagination">
                <span>Menampilkan <?= count($recentReports) ?> dari <?= $totalReportsCount ?> total laporan</span>
                <div class="pag-buttons">
                    <a href="laporan_saya.php" class="pag-btn" title="Lihat semua"><i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Right Sidebar: D + E -->
        <div>
            <!-- D. Quick Action Panel -->
            <div class="quick-action-panel">
                <div class="qap-title"><i class="fas fa-bolt me-2" style="color:var(--status-pending)"></i>Aksi Cepat: Laporkan Insiden</div>
                <div class="qap-subtitle">Kirim laporan cepat insiden terestrial</div>

                <div class="qap-tabs">
                    <div class="qap-tab active">Form Singkat</div>
                    <div class="qap-tab">Panduan</div>
                </div>

                <form action="buat_laporan.php" method="GET">
                    <div class="qap-field">
                        <label for="lokasi-singkat">Lokasi Kejadian Singkat</label>
                        <input type="text" id="lokasi-singkat" name="lokasi" placeholder="Contoh: Hutan Lindung Gunung Leuser, Aceh" required>
                    </div>

                    <button type="submit" class="btn-maroon">
                        <i class="fas fa-arrow-right me-2"></i>Lanjutkan Pengisian Detail
                    </button>
                </form>
            </div>

            <!-- E. Notifications Panel -->
            <div class="notifications-panel">
                <div class="np-header">
                    <h6><i class="fas fa-bell"></i> Notifikasi Terakhir</h6>
                    <a href="notifikasi.php">Semua</a>
                </div>

                <?php if (empty($notifications)): ?>
                <p class="text-muted" style="font-size:0.82rem;">Belum ada notifikasi.</p>
                <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                <div class="notif-item">
                    <div class="ni-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="ni-content">
                        <div class="ni-msg"><?= htmlspecialchars($notif['message']) ?></div>
                        <div class="ni-time"><?= timeAgo($notif['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- F & G. Map Section + Tips -->
    <div class="map-section">

        <!-- F. Map -->
        <div class="map-container">
            <div class="mc-header">
                <h5><i class="fas fa-map-marked-alt"></i> Peta Lokasi Laporan Saya</h5>
                <span class="mc-radius"><i class="fas fa-map-pin me-1"></i><?= count($userMapReports) ?> Titik Koordinat</span>
            </div>
            <div class="map-wrapper">
                <div id="leaflet-map"></div>
                <div class="map-overlay-card">
                    <div class="moc-title">Peta Lokasi Laporan Saya</div>
                    <div class="moc-value"><?= count($userMapReports) ?> Titik Terdata</div>
                    <button class="btn-green-outline" onclick="window.location.href='laporan_publik.php'">
                        <i class="fas fa-globe-asia me-1"></i> Buka Peta Publik
                    </button>
                </div>
            </div>
        </div>

        <!-- G. Conservation Tips -->
        <div class="tips-card">
            <div class="d-flex align-items-center gap-2 mb-2">
                <img src="<?= APP_URL ?>/assets/img/terrawatch-logo.png" alt="TerraWatch" style="height: 28px; object-fit: contain;">
            </div>
            <div class="tc-label">TIPS KONSERVASI HARI INI</div>
            <h5>Identifikasi Bukti Perambahan Liar Berbasis Geotag</h5>
            <p>Gunakan fitur geotag pada kamera ponsel Anda untuk mendokumentasikan lokasi persis insiden. Data GPS yang tertanam dalam foto mempercepat verifikasi dan respons tim lapangan terhadap ancaman terhadap ekosistem terestrial.</p>
            <span class="sdg-badge"><i class="fas fa-globe-americas me-1"></i>SDG 15 Target 15.2</span>
            <a href="artikel.php" class="tc-link">Baca Panduan Lengkap <i class="fas fa-arrow-right"></i></a>
        </div>

    </div>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Leaflet Map
document.addEventListener('DOMContentLoaded', function() {
    try {
        var map = L.map('leaflet-map').setView([-2.5, 118.0], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        var userMapReports = <?= json_encode($userMapReports) ?>;
        if (userMapReports && userMapReports.length > 0) {
            var bounds = [];
            userMapReports.forEach(function(r) {
                var lat = parseFloat(r.latitude);
                var lng = parseFloat(r.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    var m = L.marker([lat, lng]).addTo(map);
                    m.bindPopup('<strong>' + (r.title || 'Laporan') + '</strong><br>Status: ' + (r.status || '') + '<br><a href="detail_laporan.php?id=' + r.report_id + '">Lihat Detail</a>');
                    bounds.push([lat, lng]);
                }
            });
            if (bounds.length > 0) {
                map.fitBounds(bounds, { maxZoom: 13, padding: [30, 30] });
            }
        }
    } catch(e) {
        document.getElementById('leaflet-map').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6C757D;font-size:0.9rem;"><i class="fas fa-map me-2"></i>Peta tidak tersedia saat offline</div>';
    }
});

// Delete confirmation
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
        if (confirm('Apakah Anda yakin ingin menghapus laporan ini?')) {
            window.location.href = 'hapus_laporan.php?id=' + id;
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php

/**
 * Helper: get category badge CSS class
 */
function getCategoryBadgeClass($categoryName) {
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
