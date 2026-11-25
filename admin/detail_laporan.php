<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$reportId) {
    redirect('manajemen_laporan.php');
}

// Handle status update
// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = sanitize($_POST['new_status'] ?? '');
    $note = sanitize($_POST['note'] ?? '');

    // Ambil data laporan saat ini
    $stmtCur = $pdo->prepare("SELECT status, user_id, title FROM reports WHERE report_id = ?");
    $stmtCur->execute([$reportId]);
    $currentData = $stmtCur->fetch(PDO::FETCH_ASSOC);

    if (!$currentData) {
        $_SESSION['error'] = 'Laporan tidak ditemukan.';
        redirect('manajemen_laporan.php');
    }

    $currentStatus = $currentData['status'];
    $allowedTransitions = ADMIN_TRANSITIONS[$currentStatus] ?? [];

    // Validasi aturan transisi
    if (!in_array($newStatus, $allowedTransitions, true)) {
        $_SESSION['error'] = 'Transisi status dari ' . statusLabel($currentStatus) . ' ke ' . statusLabel($newStatus) . ' tidak diizinkan.';
        redirect("detail_laporan.php?id=$reportId");
    }

    // Validasi catatan wajib
    if (in_array($newStatus, NOTE_REQUIRED, true) && empty($note)) {
        $_SESSION['error'] = 'Catatan wajib diisi untuk status ' . statusLabel($newStatus) . '.';
        redirect("detail_laporan.php?id=$reportId");
    }

    try {
        $pdo->beginTransaction();

        // Update laporan dengan pengecekan status saat ini (cegah race condition antar admin)
        if ($newStatus === 'Rejected') {
            $stmtUpdate = $pdo->prepare("UPDATE reports SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE report_id = ? AND status = ?");
            $stmtUpdate->execute([$newStatus, $note, $reportId, $currentStatus]);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE report_id = ? AND status = ?");
            $stmtUpdate->execute([$newStatus, $reportId, $currentStatus]);
        }

        if ($stmtUpdate->rowCount() === 0) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Gagal memperbarui status: status laporan telah diubah sebelumnya.';
            redirect("detail_laporan.php?id=$reportId");
        }

        // Catat riwayat ke report_updates menggunakan changed_by
        $stmtInsert = $pdo->prepare("INSERT INTO report_updates (report_id, changed_by, old_status, new_status, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtInsert->execute([$reportId, $_SESSION['user_id'], $currentStatus, $newStatus, $note]);

        // Buat notifikasi untuk pelapor dengan label status bahasa Indonesia
        $notifMessage = "Laporan \"{$currentData['title']}\" kini berstatus " . statusLabel($newStatus) . ".";
        if (!empty($note)) {
            $notifMessage .= " Catatan: $note";
        }
        createNotification($pdo, (int)$currentData['user_id'], $notifMessage, $reportId);

        // Catat ke log aktivitas
        logActivity($pdo, $_SESSION['user_id'], "Mengubah status laporan #$reportId dari $currentStatus menjadi $newStatus");

        $pdo->commit();
        $_SESSION['success'] = 'Status laporan berhasil diubah menjadi ' . statusLabel($newStatus) . '.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Gagal memperbarui status: ' . $e->getMessage();
    }

    redirect("detail_laporan.php?id=$reportId");
}

// Fetch report
$stmt = $pdo->prepare("SELECT r.*, u.name as reporter_name, u.email as reporter_email, u.phone as reporter_phone, u.profile_photo as reporter_photo, c.category_name 
                        FROM reports r 
                        JOIN users u ON r.user_id = u.user_id 
                        JOIN categories c ON r.category_id = c.category_id 
                        WHERE r.report_id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    $_SESSION['error'] = 'Laporan tidak ditemukan.';
    redirect('manajemen_laporan.php');
}

// Fetch images
$stmtImages = $pdo->prepare("SELECT * FROM report_images WHERE report_id = ?");
$stmtImages->execute([$reportId]);
$images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

// Fetch status history (urutan kronologis dari awal ke akhir)
$stmtHistory = $pdo->prepare("SELECT ru.*, u.name as changer_name, u.role as changer_role FROM report_updates ru LEFT JOIN users u ON ru.changed_by = u.user_id WHERE ru.report_id = ? ORDER BY ru.created_at ASC, ru.update_id ASC");
$stmtHistory->execute([$reportId]);
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Detail Laporan #' . $reportId;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= e($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= e($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>Detail Laporan #<?= $reportId ?></h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" style="color:#34483B;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manajemen_laporan.php" style="color:#34483B;">Laporan</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </nav>
            </div>
            <a href="manajemen_laporan.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
        </div>

        <div class="row g-4">
            <!-- Report Details -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2" style="color:#34483B;"></i>Informasi Laporan</h6>
                        <span class="badge <?= getStatusBadgeClass($report['status']) ?> fs-6"><?= e(statusLabel($report['status'])) ?></span>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><th width="180" class="text-muted">Judul</th><td class="fw-bold"><?= htmlspecialchars($report['title']) ?></td></tr>
                            <tr><th class="text-muted">Kategori</th><td><span class="badge" style="background-color:#789078;"><?= htmlspecialchars($report['category_name']) ?></span></td></tr>
                            <tr><th class="text-muted">Tanggal Kejadian</th><td><?= formatDate($report['incident_date']) ?></td></tr>
                            <tr><th class="text-muted">Lokasi</th><td><?= htmlspecialchars($report['location']) ?></td></tr>
                            <?php if ($report['latitude'] && $report['longitude']): ?>
                            <tr><th class="text-muted">Koordinat</th><td><?= $report['latitude'] ?>, <?= $report['longitude'] ?></td></tr>
                            <?php endif; ?>
                            <tr><th class="text-muted">Tanggal Dibuat</th><td><?= formatDateTime($report['created_at']) ?></td></tr>
                            <tr><th class="text-muted">Terakhir Update</th><td><?= $report['updated_at'] ? formatDateTime($report['updated_at']) : '-' ?></td></tr>
                            <?php if ($report['rejection_reason']): ?>
                            <tr><th class="text-muted text-danger">Alasan Penolakan</th><td class="text-danger"><?= htmlspecialchars($report['rejection_reason']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                        <hr>
                        <h6 class="text-muted mb-2">Deskripsi</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
                    </div>
                </div>

                <!-- Photo Gallery -->
                <?php if (!empty($images)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-images me-2" style="color:#34483B;"></i>Foto Dokumentasi (<?= count($images) ?>)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($images as $img): 
                                $imgSrc = getImageUrl($img['image_path']);
                            ?>
                            <div class="col-md-4 col-6">
                                <a href="<?= htmlspecialchars($imgSrc) ?>" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage(this)">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="img-fluid rounded shadow-sm" style="height:200px;width:100%;object-fit:cover;cursor:zoom-in;" alt="Foto Laporan" onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Status History Timeline -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-history me-2" style="color:#34483B;"></i>Riwayat Perubahan Status</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                        <p class="text-muted text-center mb-0">Belum ada riwayat perubahan status.</p>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($history as $h): ?>
                            <div class="d-flex mb-3">
                                <div class="me-3 text-center" style="min-width: 40px;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;background-color:#34483B;">
                                        <i class="fas <?= getStatusIcon($h['new_status']) ?> text-white small"></i>
                                    </div>
                                    <?php if ($h !== end($history)): ?>
                                    <div style="width:2px;height:30px;background-color:#dee2e6;margin:0 auto;"></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold small">
                                        <?php if (!empty($h['old_status'])): ?>
                                        <span class="badge <?= getStatusBadgeClass($h['old_status']) ?> me-1"><?= e(statusLabel($h['old_status'])) ?></span>
                                        <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                        <?php endif; ?>
                                        <span class="badge <?= getStatusBadgeClass($h['new_status']) ?>"><?= e(statusLabel($h['new_status'])) ?></span>
                                    </div>
                                    <small class="text-muted">oleh <?= ($h['changed_by'] == $report['user_id'] ? 'Pelapor' : e($h['changer_name'] ?? 'Admin')) ?> • <?= formatDateTime($h['created_at']) ?></small>
                                    <?php if ($h['note']): ?>
                                    <p class="mb-0 mt-1 small bg-light p-2 rounded"><?= htmlspecialchars($h['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Reporter Info -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-user me-2" style="color:#34483B;"></i>Informasi Pelapor</h6>
                    </div>
                    <div class="card-body text-center">
                        <img src="<?= $report['reporter_photo'] ? htmlspecialchars(getImageUrl($report['reporter_photo'])) : '../assets/img/default-avatar.png' ?>" class="rounded-circle mb-3" width="80" height="80" style="object-fit:cover;" alt="Foto Pelapor">
                        <h6><?= htmlspecialchars($report['reporter_name']) ?></h6>
                        <p class="text-muted small mb-1"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($report['reporter_email']) ?></p>
                        <?php if ($report['reporter_phone']): ?>
                        <p class="text-muted small mb-0"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($report['reporter_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Update Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-edit me-2" style="color:#34483B;"></i>Tindak Lanjut &amp; Status</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($report['status'] === 'Pending'): ?>
                            <form method="POST" id="statusForm">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="new_status" id="newStatusInput" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Catatan Admin</label>
                                    <textarea name="note" id="noteField" class="form-control" rows="4" placeholder="Tuliskan catatan verifikasi, instruksi perbaikan, atau alasan penolakan..."></textarea>
                                    <small class="text-muted">Wajib diisi untuk Minta Perbaikan &amp; Tolak.</small>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <button type="button" class="btn text-white w-100" style="background-color:#34483B;" onclick="confirmStatusChange('Verified', 'Verifikasi Laporan?', 'Laporan akan diverifikasi dan siap untuk ditindaklanjuti.', false, '#34483B')">
                                        <i class="fas fa-check-circle me-1"></i>Verifikasi
                                    </button>
                                    <button type="button" class="btn text-white w-100" style="background-color:#789078;" onclick="confirmStatusChange('Revision', 'Minta Perbaikan?', 'Pelapor akan diminta merevisi laporan ini. Catatan perbaikan wajib diisi.', true, '#789078')">
                                        <i class="fas fa-edit me-1"></i>Minta Perbaikan
                                    </button>
                                    <button type="button" class="btn text-white w-100" style="background-color:#C17B58;" onclick="confirmStatusChange('Rejected', 'Tolak Laporan?', 'Laporan ini akan ditolak permanen. Catatan alasan penolakan wajib diisi.', true, '#C17B58')">
                                        <i class="fas fa-times-circle me-1"></i>Tolak
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($report['status'] === 'Verified'): ?>
                            <form method="POST" id="statusForm">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="new_status" id="newStatusInput" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Catatan Tindak Lanjut <span class="text-muted fw-normal">(Opsional)</span></label>
                                    <textarea name="note" id="noteField" class="form-control" rows="3" placeholder="Tambahkan catatan tindak lanjut lapangan jika ada..."></textarea>
                                </div>

                                <button type="button" class="btn text-white w-100" style="background-color:#34483B;" onclick="confirmStatusChange('In Progress', 'Mulai Tindak Lanjut?', 'Status laporan akan diubah menjadi Sedang Diproses.', false, '#34483B')">
                                    <i class="fas fa-spinner me-1"></i>Mulai Tindak Lanjut
                                </button>
                            </form>
                        <?php elseif ($report['status'] === 'In Progress'): ?>
                            <form method="POST" id="statusForm">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="new_status" id="newStatusInput" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Ringkasan Hasil Tindak Lanjut <span class="text-danger">*wajib</span></label>
                                    <textarea name="note" id="noteField" class="form-control" rows="4" placeholder="Tuliskan ringkasan hasil tindak lanjut penanganan laporan ini..." required></textarea>
                                </div>

                                <button type="button" class="btn text-white w-100" style="background-color:#34483B;" onclick="confirmStatusChange('Completed', 'Tandai Selesai?', 'Pastikan tindakan penanganan telah tuntas. Ringkasan hasil tindak lanjut wajib diisi.', true, '#34483B')">
                                    <i class="fas fa-check-double me-1"></i>Tandai Selesai
                                </button>
                            </form>
                        <?php elseif ($report['status'] === 'Revision'): ?>
                            <div class="alert alert-info border-0 mb-0" style="background:rgba(120,144,120,0.15);color:#34483B;">
                                <i class="fas fa-hourglass-half me-2"></i>Laporan sedang dalam proses perbaikan oleh pelapor. Form pembaruan status akan tersedia kembali setelah pelapor mengirimkan revisi.
                            </div>
                        <?php elseif ($report['status'] === 'Completed'): ?>
                            <div class="alert alert-success border-0 mb-0" style="background:rgba(52,72,59,0.1);color:#34483B;">
                                <i class="fas fa-check-circle me-2"></i>Tindak lanjut laporan ini telah <strong>Selesai</strong>. Status ini bersifat akhir.
                            </div>
                        <?php elseif ($report['status'] === 'Rejected'): ?>
                            <div class="alert alert-danger border-0 mb-0" style="background:rgba(193,123,88,0.12);color:#A65B49;">
                                <i class="fas fa-ban me-2"></i>Laporan ini telah <strong>Ditolak</strong>. Status ini bersifat akhir.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Zoom Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" class="img-fluid" alt="Zoom Image">
            </div>
        </div>
    </div>
</div>

<script>
function showImage(el) {
    document.getElementById('modalImage').src = el.href;
}

function confirmStatusChange(targetStatus, title, text, noteRequired, confirmColor) {
    const noteField = document.getElementById('noteField');
    const noteVal = noteField ? noteField.value.trim() : '';

    if (noteRequired && !noteVal) {
        Swal.fire({
            icon: 'warning',
            title: 'Catatan Diperlukan',
            text: 'Catatan wajib diisi sebelum mengubah status ini.',
            confirmButtonColor: '#34483B'
        });
        if (noteField) noteField.focus();
        return;
    }

    Swal.fire({
        title: title,
        text: text,
        icon: targetStatus === 'Rejected' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('newStatusInput').value = targetStatus;
            document.getElementById('statusForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
