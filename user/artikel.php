<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

$isUserLoggedIn = isLoggedIn();
$search = sanitize($_GET['q'] ?? '');

$whereSql = "status = 'Published'";
$params = [];

if (!empty($search)) {
    $whereSql .= " AND (title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as author_name 
        FROM articles a 
        LEFT JOIN users u ON a.admin_id = u.user_id 
        WHERE $whereSql 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $articles = [];
}

$pageTitle = 'Artikel Edukasi Lingkungan';
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
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .article-card {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #D6CBB5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .article-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(52, 72, 59,0.12);
            border-color: #B5C2AA;
        }
        .article-thumb {
            height: 200px;
            width: 100%;
            object-fit: cover;
            background: #F6F6F0;
        }
        .article-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .hero-banner-edu {
            background: linear-gradient(135deg, #34483B 0%, #789078 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 35px;
            margin-bottom: 30px;
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
            <a class="navbar-brand fw-bold d-flex align-items-center" href="../index.php">
                <img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;">
                <span class="ms-2">Edukasi</span>
            </a>
            <a href="../auth/login.php" class="btn btn-outline-light btn-sm">Masuk Portal</a>
        </div>
    </nav>
    <main class="container py-4">
<?php endif; ?>

    <!-- Edu Hero Banner -->
    <div class="hero-banner-edu shadow-sm">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-light text-pine px-3 py-2 mb-2 fw-bold" style="color:#34483B;">
                    <i class="fas fa-seedling me-1"></i> PUSAT EDUKASI &amp; LITERASI TERESTRIAL
                </span>
                <h3 class="fw-bold mb-2">Panduan Konservasi &amp; Pengetahuan Ekosistem Darat</h3>
                <p class="mb-0 text-white-50">Tingkatkan wawasan Anda seputar pencegahan deforestasi, perlindungan satwa liar, dan target SDGs 15 Life on Land.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <form method="GET" action="artikel.php" class="d-flex">
                    <input type="text" name="q" class="form-control me-2" placeholder="Cari artikel..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn fw-bold" style="background:#C17B58;color:#fff;border-color:#C17B58;" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Articles Grid -->
    <?php if (empty($articles)): ?>
        <div class="card p-5 text-center border-0 shadow-sm my-4">
            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
            <h5 class="fw-bold text-muted">Belum Ada Artikel yang Dipublikasikan</h5>
            <p class="text-muted small">Kembali lagi nanti untuk membaca panduan konservasi terbaru.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($articles as $art): 
                $imgUrl = !empty($art['image']) ? getImageUrl($art['image']) : 'https://placehold.co/600x400/34483B/ffffff?text=Konservasi+TerraWatch';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="article-card">
                    <img src="<?= $imgUrl ?>" class="article-thumb" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.src='https://placehold.co/600x400/34483B/ffffff?text=TerraWatch'">
                    <div class="article-body">
                        <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
                            <span><i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($art['author_name'] ?? 'Admin') ?></span>
                            <span><i class="fas fa-calendar-alt me-1"></i><?= formatDate($art['created_at']) ?></span>
                        </div>
                        <h5 class="fw-bold mb-2 text-dark" style="line-height:1.4; min-height:50px;">
                            <a href="detail_artikel.php?id=<?= $art['article_id'] ?>" class="text-decoration-none text-dark hover-green">
                                <?= htmlspecialchars(mb_strimwidth($art['title'], 0, 65, '...')) ?>
                            </a>
                        </h5>
                        <p class="text-muted small mb-4 flex-grow-1" style="line-height:1.5;">
                            <?= htmlspecialchars(mb_strimwidth(strip_tags($art['content']), 0, 120, '...')) ?>
                        </p>
                        <a href="detail_artikel.php?id=<?= $art['article_id'] ?>" class="btn btn-outline-success w-100 fw-bold" style="color:#34483B;border-color:#34483B;">
                            Baca Artikel Lengkap <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
