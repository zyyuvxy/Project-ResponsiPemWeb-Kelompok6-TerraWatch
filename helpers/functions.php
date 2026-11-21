<?php
/**
 * Helper Functions
 * TerraWatch Environmental Reporting Platform
 */

/**
 * Sanitize user input (hanya trim, tanpa stripslashes dan htmlspecialchars)
 */
function sanitize($data): string
{
    return trim((string)$data);
}

/**
 * Escape string untuk output HTML yang aman
 */
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged-in user is an admin
 */
function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Proteksi rute untuk pengguna biasa (admin tidak boleh masuk)
 */
function requireUser(): void
{
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    if (isAdmin()) {
        redirect('../admin/dashboard.php');
    }
}

/**
 * Proteksi rute untuk administrator
 */
function requireAdmin(): void
{
    if (!isLoggedIn() || !isAdmin()) {
        redirect('../auth/login.php');
    }
}

/**
 * Redirect to a URL
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Format date in Indonesian style
 */
function formatDate(string $date, string $format = 'd M Y'): string
{
    $months = [
        'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar',
        'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun',
        'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep',
        'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des',
    ];

    $formatted = date($format, strtotime($date));

    return str_replace(array_keys($months), array_values($months), $formatted);
}

/**
 * Format date with time
 */
function formatDateTime(string $date): string
{
    return formatDate($date, 'd M Y H:i');
}

/**
 * Generate a report ID like TW-2024-001
 */
function generateReportId($id = null): string
{
    $year = date('Y');
    if ($id !== null && is_numeric($id) && (int)$id > 0) {
        return sprintf('TW-%s-%03d', $year, (int)$id);
    }

    try {
        $pdo  = getConnection();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM reports WHERE YEAR(created_at) = :year"
        );
        $stmt->execute(['year' => $year]);
        $count = (int) $stmt->fetch()['total'] + 1;
        return sprintf('TW-%s-%03d', $year, $count);
    } catch (Exception $e) {
        return sprintf('TW-%s-%03d', $year, rand(100, 999));
    }
}

/**
 * Resolve image URL safely from uploads or relative paths
 */
function getImageUrl(?string $path): string
{
    if (empty($path)) {
        return 'https://placehold.co/600x400/34483B/ffffff?text=TerraWatch';
    }
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    $cleanPath = ltrim($path, '/');
    if (strpos($cleanPath, 'uploads/') === 0) {
        return '../' . $cleanPath;
    }
    return '../uploads/' . $cleanPath;
}

/**
 * Return the CSS class for a status badge
 */
function getStatusBadgeClass(string $status): string
{
    $map = [
        'Pending'     => 'badge-warning',
        'Revision'    => 'badge-info',
        'Verified'    => 'badge-primary',
        'In Progress' => 'badge-info',
        'Completed'   => 'badge-success',
        'Rejected'    => 'badge-danger',
        'Draft'       => 'badge-secondary',
        'Published'   => 'badge-success',
    ];

    return $map[$status] ?? 'badge-secondary';
}

/**
 * Return an icon class for a status
 */
function getStatusIcon(string $status): string
{
    $map = [
        'Pending'     => 'fas fa-clock',
        'Revision'    => 'fas fa-edit',
        'Verified'    => 'fas fa-check-circle',
        'In Progress' => 'fas fa-spinner',
        'Completed'   => 'fas fa-check-double',
        'Rejected'    => 'fas fa-times-circle',
        'Draft'       => 'fas fa-file-alt',
        'Published'   => 'fas fa-globe',
    ];

    return $map[$status] ?? 'fas fa-question-circle';
}

/**
 * Return a human-readable relative time string in Indonesian
 */
function timeAgo(string $datetime): string
{
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' tahun yang lalu';
    }
    if ($diff->m > 0) {
        return $diff->m . ' bulan yang lalu';
    }
    if ($diff->d >= 7) {
        $weeks = floor($diff->d / 7);
        return $weeks . ' minggu yang lalu';
    }
    if ($diff->d > 0) {
        return $diff->d . ' hari yang lalu';
    }
    if ($diff->h > 0) {
        return $diff->h . ' jam yang lalu';
    }
    if ($diff->i > 0) {
        return $diff->i . ' menit yang lalu';
    }

    return 'Baru saja';
}

/**
 * Cek apakah payload kiriman POST melebihi batas post_max_size server
 */
function isPostMaxSizeExceeded(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && empty($_POST)
        && empty($_FILES)
        && isset($_SERVER['CONTENT_LENGTH'])
        && (int)$_SERVER['CONTENT_LENGTH'] > 0;
}

/**
 * Handle image upload dengan validasi menyeluruh
 *
 * @param array $file Elemen $_FILES (satu berkas)
 * @param string $directory Sub-folder tujuan di dalam uploads/ ('reports', 'articles', 'profiles')
 * @return string|false Path relatif diawali nama folder (contoh: 'reports/ab12cd34ef56gh78.jpg') atau false jika gagal
 */
function uploadImage(array $file, string $directory)
{
    // Cek status error upload PHP
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validasi ukuran (maks 2 MB)
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // Validasi ekstensi
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return false;
    }

    // Validasi keberadaan berkas sementara
    if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return false;
    }

    // Validasi MIME type dengan finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
        return false;
    }

    // Validasi struktur gambar nyata dengan getimagesize
    $imgSize = @getimagesize($file['tmp_name']);
    if ($imgSize === false) {
        return false;
    }

    // Bersihkan nama direktori agar konsisten dan tidak menggandakan 'uploads/'
    $cleanDir = trim(str_replace(['\\', 'uploads/'], ['/', ''], $directory), '/');
    if (empty($cleanDir)) {
        $cleanDir = 'reports';
    }

    $targetDir = UPLOAD_DIR . $cleanDir . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Nama file acak menggunakan bin2hex(random_bytes(8)) + ekstensi
    $filename = bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $targetDir . $filename;

    $moved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        $moved = move_uploaded_file($file['tmp_name'], $targetPath);
    } elseif (php_sapi_name() === 'cli') {
        $moved = copy($file['tmp_name'], $targetPath);
    }

    if (!$moved) {
        return false;
    }

    // Kembalikan path relatif dengan folder (contoh: 'reports/ab12cd34ef56gh78.jpg')
    return $cleanDir . '/' . $filename;
}

/**
 * Create a notification for a user
 */
function createNotification(PDO $pdo, int $userId, string $message, ?int $reportId = null): bool
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, report_id, message) VALUES (:user_id, :report_id, :message)"
    );
    return $stmt->execute([
        'user_id'   => $userId,
        'report_id' => $reportId,
        'message'   => $message,
    ]);
}

/**
 * Log an activity
 */
function logActivity(PDO $pdo, int $userId, string $activity): bool
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity, ip_address) VALUES (:user_id, :activity, :ip_address)"
    );
    return $stmt->execute([
        'user_id'    => $userId,
        'activity'   => $activity,
        'ip_address' => $ipAddress,
    ]);
}

/**
 * Get count of unread notifications for a user
 */
function getUnreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = :user_id AND is_read = 0"
    );
    $stmt->execute(['user_id' => $userId]);
    return (int) $stmt->fetch()['cnt'];
}

const ADMIN_TRANSITIONS = [
    'Pending'     => ['Verified', 'Revision', 'Rejected'],
    'Verified'    => ['In Progress'],
    'In Progress' => ['Completed'],
    'Revision'    => [],
    'Rejected'    => [],
    'Completed'   => [],
];

const NOTE_REQUIRED = ['Revision', 'Rejected', 'Completed'];

/**
 * Konversi nilai status ke label resmi bahasa Indonesia
 */
function statusLabel(string $s): string
{
    $labels = [
        'Pending'     => 'Menunggu Verifikasi',
        'Revision'    => 'Perlu Perbaikan',
        'Verified'    => 'Terverifikasi',
        'In Progress' => 'Sedang Diproses',
        'Completed'   => 'Selesai',
        'Rejected'    => 'Ditolak',
        'Draft'       => 'Draf',
        'Published'   => 'Diterbitkan',
    ];
    return $labels[$s] ?? $s;
}

/**
 * Mengirim notifikasi ke semua admin yang aktif
 */
function notifyAdmins(PDO $pdo, string $message, ?int $reportId = null): void
{
    $stmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($admins)) {
        $insert = $pdo->prepare("INSERT INTO notifications (user_id, report_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        foreach ($admins as $adminId) {
            $insert->execute([(int)$adminId, $reportId, $message]);
        }
    }
}

/**
 * Format file size in human-readable form
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
