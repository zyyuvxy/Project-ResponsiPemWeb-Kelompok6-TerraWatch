<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/functions.php';

$isUserLoggedIn = isLoggedIn();
$role = $_SESSION['role'] ?? 'user';

// Fetch public stats & featured reports for homepage
$totalReports = 0;
$totalCompleted = 0;
$totalUsers = 0;
$resolutionRate = 0;
$featuredReports = [];
$recentArticles = [];

if (isset($pdo) && $pdo !== null) {
    try {
        $totalReports = (int)$pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        $totalCompleted = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Completed'")->fetchColumn();
        $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
        $resolutionRate = $totalReports > 0 ? round(($totalCompleted / $totalReports) * 100, 1) : 0;

        $stmtFeatured = $pdo->query("
            SELECT r.*, c.category_name,
                   (SELECT image_path FROM report_images WHERE report_id = r.report_id ORDER BY image_id ASC LIMIT 1) as thumbnail
            FROM reports r
            LEFT JOIN categories c ON r.category_id = c.category_id
            WHERE r.status IN ('Verified', 'In Progress', 'Completed')
            ORDER BY r.created_at DESC
            LIMIT 3
        ");
        $featuredReports = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC);

        $stmtArticles = $pdo->query("
            SELECT * FROM articles 
            WHERE status = 'Published' 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $recentArticles = $stmtArticles->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        // Fallback default values
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TerraWatch - Observe, Report, Protect | SDGs 15 Life on Land</title>
    <link rel="icon" href="<?= APP_URL ?>/assets/img/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .landing-nav {
            background: rgba(52, 72, 59, 0.95);
            backdrop-filter: blur(10px);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .hero-section {
            background: linear-gradient(145deg, #34483B 0%, #34483B 45%, #789078 100%);
            color: #ffffff;
            padding: 90px 0 100px;
            position: relative;
            overflow: hidden;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 100px;
            background: #F6F6F0;
            transform: skewY(-2deg);
        }
        .feature-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 32px 24px;
            border: 1px solid #D6CBB5;
            box-shadow: 0 6px 18px rgba(0,0,0,0.04);
            height: 100%;
            transition: all 0.25s ease;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 30px rgba(52, 72, 59,0.12);
            border-color: #B5C2AA;
        }
        .feature-icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: #F6F6F0;
            color: #34483B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 20px;
        }
        .counter-box {
            background: #ffffff;
            border-radius: 14px;
            padding: 24px;
            border: 1px solid #D6CBB5;
            text-align: center;
        }
    </style>
</head>
<body style="background:#F6F6F0;">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark landing-nav shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link active" href="index.php">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="user/laporan_publik.php">Laporan Publik</a></li>
                <li class="nav-item"><a class="nav-link" href="user/artikel.php">Artikel Edukasi</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($isUserLoggedIn): ?>
                    <a href="<?= $role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php' ?>" class="btn fw-bold px-3" style="background:#C17B58; color:#ffffff; border-color:#C17B58;">
                        <i class="fas fa-th-large me-1"></i> Buka Dasbor
                    </a>
                    <a href="auth/logout.php" class="btn btn-outline-light btn-sm px-3">Keluar</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-outline-light px-3">Masuk</a>
                    <a href="auth/register.php" class="btn btn-light fw-bold text-pine px-3" style="color:#34483B;">
                        <i class="fas fa-user-plus me-1"></i> Daftar Pelapor
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero-section text-center text-lg-start">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="badge px-3 py-2 rounded-pill bg-light text-pine mb-3 fw-bold" style="color:#34483B;">
                    <i class="fas fa-globe-americas me-1"></i> SDGs 15 – Life on Land
                </div>
                <h1 class="display-4 fw-bold mb-3" style="line-height:1.2;">
                    Observe, Report, Protect Ekosistem Darat Indonesia
                </h1>
                <p class="lead text-white-50 mb-4" style="line-height:1.6;">
                    Platform partisipatif berbasis web untuk melaporkan pembalakan liar, kebakaran lahan, dan pencemaran tanah. Bersama verifikator kehutanan, lindungi keanekaragaman hayati nusantara.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <a href="user/buat_laporan.php" class="btn btn-lg fw-bold px-4 py-3 shadow-sm" style="background:#C17B58; color:#ffffff; border-color:#C17B58;">
                        <i class="fas fa-bullhorn me-2"></i> Laporkan Insiden Sekarang
                    </a>
                    <a href="user/laporan_publik.php" class="btn btn-outline-light btn-lg px-4 py-3">
                        <i class="fas fa-search me-2"></i> Jelajahi Laporan Terbuka
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center">
                <div class="card p-4 border-0 shadow-lg rounded-4 text-dark text-start" style="background:rgba(255,255,255,0.95); backdrop-filter:blur(10px);">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <span class="fw-bold text-pine"><i class="fas fa-leaf me-2"></i>Komitmen Konservasi</span>
                        <span class="badge px-3 py-1 fw-bold" style="background:rgba(120,144,120,0.18); color:#34483B;">SDG 15</span>
                    </div>
                    <p class="small text-muted mb-3">Platform Pelaporan Kerusakan Lingkungan Darat Berbasis Warga untuk Mendukung SDG 15 (Life on Land).</p>
                    <div class="p-3 bg-light rounded-3 mb-3 border" style="border-color:#D6CBB5 !important;">
                        <div class="small fw-bold text-dark mb-1">Status Penanganan Laporan Masuk</div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= $resolutionRate ?>%; background:#789078;"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Selesai Ditangani</span>
                            <span class="fw-bold text-pine"><?= $totalCompleted ?> dari <?= $totalReports ?> Laporan (<?= $resolutionRate ?>%)</span>
                        </div>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-check-circle me-1" style="color:#789078;"></i> Partisipasi aktif masyarakat demi kelestarian alam nusantara.
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Main Features -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="text-pine fw-bold text-uppercase small">Alur Kerja Sistem</span>
            <h2 class="fw-bold text-pine mt-1">Bagaimana TerraWatch Bekerja?</h2>
            <p class="text-muted">Proses transparansi pelaporan lingkungan dari penemuan insiden hingga penanganan tuntas.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="feature-icon-circle mx-auto">
                        <i class="fas fa-camera"></i>
                    </div>
                    <h5 class="fw-bold text-pine mb-2">1. Laporkan dengan Bukti</h5>
                    <p class="text-muted small mb-0">Masyarakat mengunggah foto bukti, titik koordinat GPS, dan deskripsi kejadian melalui form laporan terstruktur.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="feature-icon-circle mx-auto">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h5 class="fw-bold text-pine mb-2">2. Verifikasi Administrator</h5>
                    <p class="text-muted small mb-0">Administrator memeriksa bukti lapangan, menyetujui, meminta revisi, atau menindaklanjuti proses penanganan insiden.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <div class="feature-icon-circle mx-auto">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5 class="fw-bold text-pine mb-2">3. Pantau &amp; Edukasi</h5>
                    <p class="text-muted small mb-0">Pelapor menerima notifikasi pembaruan status berkala dan publik memperoleh transparansi data pelestarian alam.</p>
                </div>
            </div>
        </div>

        <!-- Counter Row -->
        <div class="row g-3 mb-5">
            <div class="col-md-4">
                <div class="counter-box">
                    <h2 class="fw-bold text-pine mb-1"><?= $totalReports ?></h2>
                    <span class="text-muted small">Total Laporan Kerusakan</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-box">
                    <h2 class="fw-bold text-pine mb-1"><?= $totalCompleted ?></h2>
                    <span class="text-muted small">Laporan Selesai Ditangani</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="counter-box">
                    <h2 class="fw-bold text-pine mb-1"><?= $totalUsers ?></h2>
                    <span class="text-muted small">Pelapor Warga Terdaftar</span>
                </div>
            </div>
        </div>

        <!-- Featured Public Reports -->
        <?php if (!empty($featuredReports)): ?>
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-pine mb-0"><i class="fas fa-shield-alt me-2"></i>Laporan Publik Terbaru</h4>
                    <small class="text-muted">Insiden terestrial yang telah diverifikasi</small>
                </div>
                <a href="user/laporan_publik.php" class="btn btn-outline-success btn-sm fw-bold" style="color:#34483B;border-color:#34483B;">
                    Lihat Semua Laporan <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($featuredReports as $fr): 
                    $thumb = getImageUrl($fr['thumbnail'] ?? '');
                ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                        <img src="<?= $thumb ?>" class="card-img-top" style="height:190px;object-fit:cover;" alt="Bukti" onerror="this.src='https://placehold.co/600x400/2D5016/ffffff?text=TerraWatch'">
                        <div class="card-body p-3 d-flex flex-direction-column">
                            <span class="badge bg-light text-pine border mb-2" style="color:#34483B;"><?= htmlspecialchars($fr['category_name'] ?? 'Umum') ?></span>
                            <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars(mb_strimwidth($fr['title'], 0, 55, '...')) ?></h6>
                            <p class="text-muted small mb-3 flex-grow-1"><?= htmlspecialchars(mb_strimwidth($fr['location'], 0, 45, '...')) ?></p>
                            <a href="user/detail_publik.php?id=<?= $fr['report_id'] ?>" class="btn btn-sm btn-outline-success w-100 fw-bold" style="color:#34483B;border-color:#34483B;">Lihat Tindak Lanjut</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Featured Articles -->
        <?php if (!empty($recentArticles)): ?>
        <div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-pine mb-0"><i class="fas fa-book me-2"></i>Edukasi &amp; Literasi Konservasi</h4>
                    <small class="text-muted">Artikel resmi untuk meningkatkan kesadaran perlindungan alam</small>
                </div>
                <a href="user/artikel.php" class="btn btn-outline-success btn-sm fw-bold" style="color:#34483B;border-color:#34483B;">
                    Semua Artikel <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($recentArticles as $ra): 
                    $artThumb = getImageUrl($ra['image'] ?? '');
                ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                        <img src="<?= $artThumb ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="Artikel" onerror="this.src='https://placehold.co/600x400/2D5016/ffffff?text=Edukasi'">
                        <div class="card-body p-3">
                            <small class="text-muted d-block mb-1"><i class="far fa-calendar-alt me-1"></i><?= formatDate($ra['created_at']) ?></small>
                            <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars(mb_strimwidth($ra['title'], 0, 60, '...')) ?></h6>
                            <p class="text-muted small mb-3"><?= htmlspecialchars(mb_strimwidth(strip_tags($ra['content']), 0, 90, '...')) ?></p>
                            <a href="user/detail_artikel.php?id=<?= $ra['article_id'] ?>" class="text-pine fw-bold small text-decoration-none" style="color:#34483B;">
                                Baca Selengkapnya <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- Footer -->
<footer class="text-white py-5" style="background:#34483B;">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="mb-3"><img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;"></div>
                <p class="small text-white-50 mb-3" style="line-height:1.6;">
                    Platform Web Pelaporan &amp; Pemantauan Kerusakan Lingkungan Darat untuk mendukung ketercapaian Sustainable Development Goals (SDGs) Target 15 – Life on Land.
                </p>
                <span class="badge bg-light text-pine fw-bold" style="color:#34483B;">Observe &bull; Report &bull; Protect</span>
            </div>
            <div class="col-lg-3 col-6">
                <h6 class="fw-bold text-white mb-3">Tautan Langsung</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><a href="user/buat_laporan.php" class="text-white-50 text-decoration-none">Buat Laporan</a></li>
                    <li class="mb-2"><a href="user/laporan_publik.php" class="text-white-50 text-decoration-none">Laporan Publik</a></li>
                    <li class="mb-2"><a href="user/artikel.php" class="text-white-50 text-decoration-none">Artikel Edukasi</a></li>
                    <li class="mb-2"><a href="auth/login.php" class="text-white-50 text-decoration-none">Portal Petugas &amp; Admin</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-6">
                <h6 class="fw-bold text-white mb-3">Tautan Edukasi &amp; Referensi</h6>
                <ul class="list-unstyled small text-white-50">
                    <li class="mb-2"><a href="https://www.menlhk.go.id" target="_blank" class="text-white-50 text-decoration-none"><i class="fas fa-external-link-alt me-1"></i> Referensi: Situs Resmi KLHK</a></li>
                    <li class="mb-2"><a href="user/artikel.php" class="text-white-50 text-decoration-none"><i class="fas fa-book-open me-1"></i> Artikel &amp; Panduan Konservasi</a></li>
                    <li class="mb-2"><i class="fas fa-info-circle me-1"></i> Aksi Nyata SDG 15 – Life on Land</li>
                </ul>
            </div>
        </div>
        <hr style="border-color:rgba(255,255,255,0.15);">
        <div class="text-center small text-white-50">
            <div>&copy; <?= date('Y') ?> TerraWatch - Proyek Responsi Pemrograman Web I. Mendukung SDG 15: Life on Land.</div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
