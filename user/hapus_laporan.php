<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireUser();

$userId = $_SESSION['user_id'];
$reportId = (int)($_GET['id'] ?? 0);

if ($reportId <= 0) {
    redirect('laporan_saya.php');
}

try {
    // Check report existence and ownership
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE report_id = ? AND user_id = ?");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        $_SESSION['flash_error'] = 'Laporan tidak ditemukan.';
        redirect('laporan_saya.php');
    }

    // Only allow deletion if status is Pending (PRD Section 5.4 & 9)
    if ($report['status'] !== 'Pending') {
        $_SESSION['flash_error'] = 'Hanya laporan dengan status Pending yang dapat dihapus.';
        redirect('detail_laporan.php?id=' . $reportId);
    }

    // Delete image files from disk
    $stmtImg = $pdo->prepare("SELECT image_path FROM report_images WHERE report_id = ?");
    $stmtImg->execute([$reportId]);
    $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $img) {
        $filePath = UPLOAD_DIR . 'reports/' . $img;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    // Delete report from DB
    $stmtDel = $pdo->prepare("DELETE FROM reports WHERE report_id = ? AND user_id = ?");
    $stmtDel->execute([$reportId, $userId]);

    // Log activity
    logActivity($pdo, $userId, "Menghapus laporan Pending: {$report['title']}");

    $_SESSION['flash_success'] = 'Laporan berhasil dihapus.';

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Terjadi kesalahan saat menghapus laporan: ' . $e->getMessage();
}

redirect('laporan_saya.php');
exit;
