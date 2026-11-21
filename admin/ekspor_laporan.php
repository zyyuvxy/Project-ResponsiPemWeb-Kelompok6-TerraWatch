<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Accept filter params (same as manajemen_laporan)
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(r.report_id LIKE ? OR r.title LIKE ? OR u.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}
if ($filterStatus) {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}
if ($filterCategory) {
    $where[] = "r.category_id = ?";
    $params[] = $filterCategory;
}
if ($dateFrom) {
    $where[] = "r.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "r.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT r.report_id, r.title, u.name as reporter_name, u.email as reporter_email, 
               c.category_name, r.incident_date, r.location, r.latitude, r.longitude, 
               r.description, r.status, r.rejection_reason, r.created_at, r.updated_at
        FROM reports r 
        JOIN users u ON r.user_id = u.user_id 
        JOIN categories c ON r.category_id = c.category_id 
        $whereClause 
        ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSV
$filename = 'laporan_terrawatch_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
fputcsv($output, [
    'ID Laporan',
    'Judul',
    'Pelapor',
    'Email Pelapor',
    'Kategori',
    'Tanggal Kejadian',
    'Lokasi',
    'Latitude',
    'Longitude',
    'Deskripsi',
    'Status',
    'Alasan Penolakan',
    'Tanggal Dibuat',
    'Terakhir Update'
]);

// Data rows
foreach ($reports as $r) {
    fputcsv($output, [
        $r['report_id'],
        $r['title'],
        $r['reporter_name'],
        $r['reporter_email'],
        $r['category_name'],
        $r['incident_date'],
        $r['location'],
        $r['latitude'],
        $r['longitude'],
        $r['description'],
        $r['status'],
        $r['rejection_reason'],
        $r['created_at'],
        $r['updated_at']
    ]);
}

fclose($output);

// Log activity
logActivity($pdo, $_SESSION['user_id'], "Mengekspor " . count($reports) . " laporan ke CSV");
exit;
