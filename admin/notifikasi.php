<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

$userId = (int)$_SESSION['user_id'];

// Handle Tandai Semua Dibaca
if (isset($_GET['action']) && $_GET['action'] === 'mark_all') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {}
    redirect('notifikasi.php');
}

// Handle Klik Buka Notifikasi Tunggal
if (isset($_GET['read_id'])) {
    $notifId = (int)$_GET['read_id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);

        // Jika terhubung ke laporan, langsung arahkan ke detail laporan
        if (isset($_GET['report_id']) && (int)$_GET['report_id'] > 0) {
            redirect('detail_laporan.php?id=' . (int)$_GET['report_id']);
        }
    } catch (PDOException $e) {}
    redirect('notifikasi.php');
}

// Ambil daftar notifikasi untuk admin ini
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadCount = getUnreadNotificationCount($pdo, $userId);
} catch (PDOException $e) {
    $notifications = [];
    $unreadCount = 0;
}

$pageTitle = 'Notifikasi Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-bell me-2" style="color:#34483B;"></i>Notifikasi Masuk</h4>
                <p class="text-muted mb-0">Pantau laporan baru dan perbaikan laporan yang dikirim oleh pelapor</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <a href="notifikasi.php?action=mark_all" class="btn btn-sm text-white" style="background-color:#34483B;">
                <i class="fas fa-check-double me-1"></i>Tandai Semua Dibaca
            </a>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:14px; overflow:hidden;">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3" style="color:#B5C2AA;"></i>
                    <h6 class="fw-bold">Belum Ada Notifikasi</h6>
                    <p class="small text-muted mb-0">Notifikasi akan muncul saat ada laporan baru atau kiriman perbaikan dari warga.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): 
                        $isUnread = ((int)$n['is_read'] === 0);
                        $link = 'notifikasi.php?read_id=' . $n['notification_id'] . (!empty($n['report_id']) ? '&report_id=' . $n['report_id'] : '');
                    ?>
                    <a href="<?= $link ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-start gap-3" style="<?= $isUnread ? 'background:#F6F6F0; border-left: 4px solid #34483B;' : 'border-left: 4px solid transparent;' ?>">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mt-1" style="width:38px;height:38px;min-width:38px;background:<?= $isUnread ? '#34483B' : '#B5C2AA' ?>;color:#fff;">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold" style="color:#34483B; font-size:0.95rem;">
                                    <?= htmlspecialchars($n['message']) ?>
                                </span>
                                <?php if ($isUnread): ?>
                                    <span class="badge rounded-pill ms-2" style="background:#789078; font-size:0.7rem;">Baru</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-3 small text-muted">
                                <span><i class="far fa-clock me-1"></i><?= timeAgo($n['created_at']) ?></span>
                                <?php if (!empty($n['report_id'])): ?>
                                    <span style="color:#789078;"><i class="fas fa-external-link-alt me-1"></i>Buka Detail Laporan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
