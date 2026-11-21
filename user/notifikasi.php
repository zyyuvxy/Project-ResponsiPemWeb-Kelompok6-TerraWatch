<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireUser();

$userId = $_SESSION['user_id'];

// Handle Mark All as Read
if (isset($_GET['action']) && $_GET['action'] === 'mark_all') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {}
    redirect('notifikasi.php');
}

// Handle Single Read
if (isset($_GET['read_id'])) {
    $notifId = (int)$_GET['read_id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);

        // Redirect to report if linked
        if (isset($_GET['report_id']) && (int)$_GET['report_id'] > 0) {
            redirect('detail_laporan.php?id=' . (int)$_GET['report_id']);
        }
    } catch (PDOException $e) {}
    redirect('notifikasi.php');
}

// Fetch all notifications
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

$pageTitle = 'Notifikasi Saya';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .notif-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #D6CBB5;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .notif-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid #D6CBB5;
            transition: background 0.15s ease;
            text-decoration: none;
            color: inherit;
        }
        .notif-row:hover {
            background: #F6F6F0;
            color: inherit;
        }
        .notif-row.unread {
            background: rgba(181, 194, 170, 0.2);
            border-left: 4px solid #789078;
        }
        .notif-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #F6F6F0;
            color: #34483B;
            font-size: 1.1rem;
        }
        .notif-row.unread .notif-icon-box {
            background: #34483B;
            color: #ffffff;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">
    <div class="content-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-pine mb-1"><i class="fas fa-bell me-2"></i>Pemberitahuan Sistem</h3>
            <p class="text-muted mb-0">Informasi status perkembangan laporan dan aktivitas akun Anda.</p>
        </div>
        <?php if ($unreadCount > 0): ?>
            <a href="notifikasi.php?action=mark_all" class="btn btn-outline-success btn-sm fw-bold" style="color:#34483B;border-color:#34483B;">
                <i class="fas fa-check-double me-1"></i> Tandai Semua Telah Dibaca
            </a>
        <?php endif; ?>
    </div>

    <div class="notif-card">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <h6 class="fw-bold text-muted">Belum Ada Notifikasi</h6>
                <p class="text-muted small">Anda akan menerima pembaruan di sini ketika laporan Anda diverifikasi oleh admin.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): 
                $isUnread = ((int)$n['is_read'] === 0);
                $link = 'notifikasi.php?read_id=' . $n['notification_id'] . (!empty($n['report_id']) ? '&report_id=' . $n['report_id'] : '');
            ?>
            <a href="<?= $link ?>" class="notif-row <?= $isUnread ? 'unread' : '' ?>">
                <div class="notif-icon-box">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-pine" style="<?= $isUnread ? 'font-size:0.95rem;' : 'font-size:0.9rem;color:#495057;' ?>">
                            <?= htmlspecialchars($n['message']) ?>
                        </span>
                        <?php if ($isUnread): ?>
                            <span class="badge rounded-pill px-2 py-1 ms-2" style="font-size:0.7rem;background:#789078 !important;color:#fff;">Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center gap-3 small text-muted">
                        <span><i class="far fa-clock me-1"></i><?= timeAgo($n['created_at']) ?></span>
                        <?php if (!empty($n['report_id'])): ?>
                            <span class="text-pine"><i class="fas fa-external-link-alt me-1"></i>Buka Detail Laporan</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
