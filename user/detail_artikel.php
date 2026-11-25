<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

$isUserLoggedIn = isLoggedIn();
$articleId = (int)($_GET['id'] ?? 0);

if ($articleId <= 0) {
    redirect('artikel.php');
}

try {
    // Fetch article
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as author_name 
        FROM articles a 
        LEFT JOIN users u ON a.admin_id = u.user_id 
        WHERE a.article_id = ? AND a.status = 'Published'
    ");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        redirect('artikel.php');
    }

    // Fetch related articles
    $stmtRelated = $pdo->prepare("
        SELECT * FROM articles 
        WHERE article_id != ? AND status = 'Published' 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmtRelated->execute([$articleId]);
    $relatedArticles = $stmtRelated->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    redirect('artikel.php');
}

$pageTitle = $article['title'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - TerraWatch Edukasi</title>
    <link rel="icon" href="<?= APP_URL ?>/assets/img/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .article-content-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 35px;
            border: 1px solid #D6CBB5;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 30px;
        }
        .article-main-img {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .article-body-text {
            font-size: 1.05rem;
            line-height: 1.8;
            color: #34483B;
        }
        .related-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #D6CBB5;
            margin-bottom: 25px;
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
            <a class="navbar-brand fw-bold d-flex align-items-center" href="artikel.php">
                <img src="<?= APP_URL ?>/assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; object-fit: contain;">
                <span class="ms-2">Edukasi</span>
            </a>
            <a href="../auth/login.php" class="btn btn-outline-light btn-sm">Masuk Portal</a>
        </div>
    </nav>
    <main class="container py-4">
<?php endif; ?>

    <div class="mb-3">
        <a href="artikel.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Semua Artikel
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <article class="article-content-card">
                <div class="d-flex align-items-center gap-3 mb-3 text-muted small">
                    <span class="badge px-3 py-2 fw-bold" style="background:rgba(120,144,120,0.18); color:#34483B;">Edukasi Konservasi</span>
                    <span><i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($article['author_name'] ?? 'Admin TerraWatch') ?></span>
                    <span><i class="fas fa-calendar-alt me-1"></i><?= formatDate($article['created_at']) ?></span>
                </div>

                <h2 class="fw-bold text-pine mb-3" style="line-height:1.3; color:#34483B;">
                    <?= htmlspecialchars($article['title']) ?>
                </h2>

                <?php if (!empty($article['image'])): ?>
                    <img src="<?= htmlspecialchars(getImageUrl($article['image'])) ?>" 
                         class="article-main-img" 
                         alt="<?= htmlspecialchars($article['title']) ?>"
                         onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/default-forest.jpg'">
                <?php endif; ?>

                <div class="article-body-text">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Mendukung Target SDG 15: Life on Land</span>
                    <a href="buat_laporan.php" class="btn btn-sm btn-success" style="background:#34483B;border:none;">
                        <i class="fas fa-plus-circle me-1"></i> Laporkan Insiden di Wilayah Anda
                    </a>
                </div>
            </article>
        </div>

        <div class="col-lg-4">
            <div class="related-card shadow-sm">
                <h6 class="fw-bold text-pine mb-3"><i class="fas fa-newspaper me-2"></i>Artikel Lainnya</h6>
                <?php if (empty($relatedArticles)): ?>
                    <p class="text-muted small">Belum ada artikel lainnya.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($relatedArticles as $rel): ?>
                            <a href="detail_artikel.php?id=<?= $rel['article_id'] ?>" class="list-group-item list-group-item-action px-0 py-2 border-bottom">
                                <h6 class="mb-1 text-pine fw-bold" style="font-size:0.92rem;"><?= htmlspecialchars(mb_strimwidth($rel['title'], 0, 55, '...')) ?></h6>
                                <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= formatDate($rel['created_at']) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Conservation Widget -->
            <div class="card p-3 border-0 rounded-4 text-white" style="background:linear-gradient(135deg, #34483B, #789078);">
                <h6 class="fw-bold"><i class="fas fa-leaf me-1"></i> Lindungi Hutan Kita</h6>
                <p class="small text-white-50 mb-3">Setiap informasi yang Anda laporkan menjadi data berharga bagi penegakan hukum dan restorasi ekosistem.</p>
                <a href="laporan_publik.php" class="btn btn-light btn-sm text-pine fw-bold" style="color:#34483B;">Pantau Laporan Publik</a>
            </div>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
