<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireUser();

$userId = $_SESSION['user_id'];
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reportId <= 0) {
    redirect('laporan_saya.php');
}

try {
    // Fetch report
    $stmt = $pdo->prepare("
        SELECT r.*, c.category_name
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.category_id
        WHERE r.report_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        redirect('laporan_saya.php');
    }

    // Fetch images
    $stmt = $pdo->prepare("SELECT * FROM report_images WHERE report_id = ? ORDER BY uploaded_at ASC");
    $stmt->execute([$reportId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch update history
    $stmt = $pdo->prepare("
        SELECT ru.*, u.name as changer_name, u.role as changer_role
        FROM report_updates ru
        LEFT JOIN users u ON ru.changed_by = u.user_id
        WHERE ru.report_id = ?
        ORDER BY ru.created_at ASC
    ");
    $stmt->execute([$reportId]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build timeline from updates
    $timeline = [];
    if (!empty($updates)) {
        foreach ($updates as $upd) {
            $timeline[] = [
                'status' => statusLabel($upd['new_status']),
                'date'   => $upd['created_at'],
                'note'   => $upd['note'] ?? '',
                'active' => true
            ];
        }
    } else {
        $timeline[] = [
            'status' => 'Laporan Dikirim',
            'date'   => $report['created_at'],
            'note'   => 'Laporan dikirim',
            'active' => true
        ];
    }
    // If not completed, add upcoming steps
    $statusOrder = ['Pending', 'Verified', 'In Progress', 'Completed'];
    $currentIdx = array_search($report['status'], $statusOrder);
    if ($currentIdx !== false && $currentIdx < count($statusOrder) - 1) {
        for ($i = $currentIdx + 1; $i < count($statusOrder); $i++) {
            $timeline[] = [
                'status' => $statusOrder[$i],
                'date' => null,
                'note' => 'Menunggu proses',
                'active' => false
            ];
        }
    }

    $displayReportId = generateReportId($report['report_id']);

} catch (PDOException $e) {
    redirect('laporan_saya.php');
}

$pageTitle = 'Detail Laporan';

function getCategoryBadgeClassDL($categoryName) {
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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4><i class="fas fa-file-alt me-2" style="color:var(--accent-green)"></i>Detail Laporan</h4>
            <p>Informasi lengkap laporan insiden terestrial</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (in_array($report['status'], ['Pending', 'Revision'])): ?>
            <a href="edit_laporan.php?id=<?= $reportId ?>" class="btn-secondary-outline"><i class="fas fa-edit"></i> Edit Laporan</a>
            <?php endif; ?>
            <a href="laporan_saya.php" class="btn-secondary-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left Column: Report Info -->
        <div class="col-lg-8">

            <!-- Report Info Card -->
            <div class="detail-card">
                <div class="dc-header">
                    <div>
                        <div class="dc-id"><?= $displayReportId ?></div>
                        <h5><?= htmlspecialchars($report['title']) ?></h5>
                    </div>
                    <span class="status-badge <?= strtolower(str_replace(' ', '-', $report['status'])) ?>">
                        <span class="dot"></span>
                        <?= htmlspecialchars(statusLabel($report['status'])) ?>
                    </span>
                </div>

                <div class="detail-info-grid">
                    <div class="di-item">
                        <label><i class="fas fa-layer-group me-1"></i>Kategori</label>
                        <span class="category-badge <?= getCategoryBadgeClassDL($report['category_name'] ?? '') ?>"><?= htmlspecialchars($report['category_name'] ?? '-') ?></span>
                    </div>
                    <div class="di-item">
                        <label><i class="fas fa-calendar-day me-1"></i>Tanggal Kejadian</label>
                        <span><?= formatDate($report['incident_date'] ?? '') ?></span>
                    </div>
                    <div class="di-item">
                        <label><i class="fas fa-map-marker-alt me-1"></i>Lokasi</label>
                        <span><?= htmlspecialchars($report['location'] ?? '-') ?></span>
                    </div>
                    <div class="di-item">
                        <label><i class="fas fa-calendar-check me-1"></i>Tanggal Lapor</label>
                        <span><?= formatDateTime($report['created_at'] ?? '') ?></span>
                    </div>
                    <?php if ($report['latitude'] && $report['longitude']): ?>
                    <div class="di-item">
                        <label><i class="fas fa-crosshairs me-1"></i>Koordinat</label>
                        <span><?= $report['latitude'] ?>, <?= $report['longitude'] ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($report['updated_at']): ?>
                    <div class="di-item">
                        <label><i class="fas fa-sync me-1"></i>Terakhir Diperbarui</label>
                        <span><?= formatDateTime($report['updated_at']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($report['description']): ?>
                <div class="mt-4 pt-3" style="border-top:1px solid var(--border-color)">
                    <label style="display:block;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted);font-weight:600;margin-bottom:8px">
                        <i class="fas fa-align-left me-1"></i>Deskripsi
                    </label>
                    <p style="font-size:0.88rem;line-height:1.7;color:var(--text-dark);margin:0"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($report['status'] === 'Rejected' && $report['rejection_reason']): ?>
                <div class="mt-4 p-3" style="background:rgba(193, 123, 88, 0.08);border-radius:10px;border-left:3px solid var(--status-rejected)">
                    <label style="display:block;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.8px;color:var(--status-rejected);font-weight:600;margin-bottom:4px">
                        <i class="fas fa-exclamation-triangle me-1"></i>Alasan Penolakan
                    </label>
                    <p style="font-size:0.85rem;color:var(--text-dark);margin:0"><?= nl2br(htmlspecialchars($report['rejection_reason'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Photo Gallery -->
            <?php if (!empty($images)): ?>
            <div class="detail-card">
                <h6 class="fw-700 mb-3"><i class="fas fa-images me-2" style="color:var(--accent-green)"></i>Dokumentasi Foto (<?= count($images) ?>)</h6>
                <div class="photo-gallery">
                    <?php foreach ($images as $img): 
                        $imgPath = getImageUrl($img['image_path']);
                    ?>
                    <div class="gallery-item" onclick="openLightbox('<?= htmlspecialchars($imgPath) ?>')">
                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="Foto Laporan" loading="lazy" onerror="this.src='https://placehold.co/600x400/2D5016/ffffff?text=Bukti+Laporan'">
                        <div class="gallery-overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Map -->
            <?php if ($report['latitude'] && $report['longitude']): ?>
            <div class="detail-card">
                <h6 class="fw-700 mb-3"><i class="fas fa-map-marked-alt me-2" style="color:var(--accent-green)"></i>Lokasi pada Peta</h6>
                <div class="detail-map" id="detail-map"></div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right Column: Timeline & Updates -->
        <div class="col-lg-4">

            <!-- Status Timeline -->
            <div class="detail-card">
                <h6 class="fw-700 mb-3"><i class="fas fa-stream me-2" style="color:var(--accent-green)"></i>Status Timeline</h6>
                <div class="status-timeline">
                    <?php foreach ($timeline as $tl): ?>
                    <div class="timeline-item">
                        <div class="tl-dot <?= $tl['active'] ? 'active' : '' ?>"></div>
                        <div class="tl-content">
                            <div class="tl-status"><?= htmlspecialchars($tl['status']) ?></div>
                            <?php if ($tl['date']): ?>
                            <div class="tl-date"><i class="far fa-clock me-1"></i><?= formatDateTime($tl['date']) ?></div>
                            <?php endif; ?>
                            <?php if ($tl['note']): ?>
                            <div class="tl-note"><?= htmlspecialchars($tl['note']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Update History -->
            <?php if (!empty($updates)): ?>
            <div class="detail-card">
                <h6 class="fw-700 mb-3"><i class="fas fa-history me-2" style="color:var(--accent-green)"></i>Riwayat Pembaruan</h6>
                <?php foreach ($updates as $upd): ?>
                <div class="update-history-item">
                    <div class="uh-header">
                        <span class="uh-admin">
                            <i class="fas fa-<?= ($upd['changed_by'] == $userId ? 'user' : 'user-shield') ?> me-1"></i>
                            <?= ($upd['changed_by'] == $userId ? 'Pelapor' : htmlspecialchars($upd['changer_name'] ?? 'Admin')) ?>
                        </span>
                        <span class="uh-date"><?= formatDateTime($upd['created_at']) ?></span>
                    </div>
                    <div class="uh-change">
                        <?php if (!empty($upd['old_status'])): ?>
                        <span class="status-badge <?= strtolower(str_replace(' ', '-', $upd['old_status'])) ?>" style="font-size:0.65rem;padding:2px 8px">
                            <span class="dot"></span><?= htmlspecialchars(statusLabel($upd['old_status'])) ?>
                        </span>
                        <i class="fas fa-arrow-right" style="font-size:0.6rem;color:var(--text-muted)"></i>
                        <?php endif; ?>
                        <span class="status-badge <?= strtolower(str_replace(' ', '-', $upd['new_status'])) ?>" style="font-size:0.65rem;padding:2px 8px">
                            <span class="dot"></span><?= htmlspecialchars(statusLabel($upd['new_status'])) ?>
                        </span>
                    </div>
                    <?php if ($upd['note']): ?>
                    <div class="uh-note"><i class="fas fa-comment-dots me-1 text-muted"></i><?= htmlspecialchars($upd['note']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="detail-card">
                <h6 class="fw-700 mb-3"><i class="fas fa-cog me-2" style="color:var(--accent-green)"></i>Aksi</h6>
                <div class="d-grid gap-2">
                    <?php if (in_array($report['status'], ['Pending', 'Revision'])): ?>
                    <a href="edit_laporan.php?id=<?= $reportId ?>" class="btn-primary-green justify-content-center"><i class="fas fa-edit"></i> Edit Laporan</a>
                    <?php endif; ?>
                    <?php if ($report['status'] === 'Pending'): ?>
                    <button class="btn-secondary-outline justify-content-center" onclick="confirmDelete(<?= $reportId ?>)" style="color:var(--status-rejected);border-color:var(--status-rejected)">
                        <i class="fas fa-trash"></i> Hapus Laporan
                    </button>
                    <?php endif; ?>
                    <a href="laporan_saya.php" class="btn-secondary-outline justify-content-center"><i class="fas fa-list"></i> Semua Laporan</a>
                    <a href="dashboard.php" class="btn-secondary-outline justify-content-center"><i class="fas fa-home"></i> Dashboard</a>
                </div>
            </div>

        </div>
    </div>

</main>

<!-- Lightbox Overlay -->
<div class="lightbox-overlay" id="lightbox">
    <button class="lb-close" onclick="closeLightbox()">&times;</button>
    <img src="" alt="Foto" id="lightbox-img">
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Lightbox
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

// Map
<?php if ($report['latitude'] && $report['longitude']): ?>
document.addEventListener('DOMContentLoaded', function() {
    try {
        var lat = <?= (float)$report['latitude'] ?>;
        var lng = <?= (float)$report['longitude'] ?>;
        var map = L.map('detail-map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);
        L.marker([lat, lng]).addTo(map)
            .bindPopup('<strong><?= addslashes(htmlspecialchars($report['title'])) ?></strong><br><?= addslashes(htmlspecialchars($report['location'] ?? '')) ?>')
            .openPopup();
    } catch(e) {
        document.getElementById('detail-map').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6C757D;font-size:0.9rem;"><i class="fas fa-map me-2"></i>Peta tidak tersedia</div>';
    }
});
<?php endif; ?>

function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.')) {
        window.location.href = 'delete_laporan.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
