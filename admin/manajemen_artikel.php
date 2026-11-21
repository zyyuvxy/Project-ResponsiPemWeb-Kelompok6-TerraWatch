<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Handle delete
if (isset($_POST['delete_article'])) {
    $articleId = (int)$_POST['article_id'];
    
    // Get image path before delete
    $stmtImg = $pdo->prepare("SELECT image FROM articles WHERE article_id = ?");
    $stmtImg->execute([$articleId]);
    $article = $stmtImg->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM articles WHERE article_id = ?");
    $stmt->execute([$articleId]);
    
    // Delete image file
    if ($article && !empty($article['image'])) {
        $oldImg = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $article['image']), '/');
        if (file_exists($oldImg)) {
            @unlink($oldImg);
        }
    }
    
    logActivity($pdo, $_SESSION['user_id'], "Menghapus artikel ID #$articleId");
    $_SESSION['success'] = 'Artikel berhasil dihapus.';
    redirect('manajemen_artikel.php');
}

// Handle toggle publish
if (isset($_POST['toggle_publish'])) {
    $articleId = (int)$_POST['article_id'];
    $stmtCurrent = $pdo->prepare("SELECT status, title FROM articles WHERE article_id = ?");
    $stmtCurrent->execute([$articleId]);
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
    
    $newStatus = $current['status'] === 'Published' ? 'Draft' : 'Published';
    $stmt = $pdo->prepare("UPDATE articles SET status = ?, updated_at = NOW() WHERE article_id = ?");
    $stmt->execute([$newStatus, $articleId]);
    
    logActivity($pdo, $_SESSION['user_id'], "Mengubah status artikel \"{$current['title']}\" menjadi $newStatus");
    $_SESSION['success'] = "Artikel berhasil diubah ke status $newStatus.";
    redirect('manajemen_artikel.php');
}

// Fetch articles
$stmt = $pdo->query("SELECT a.*, u.name as author_name FROM articles a LEFT JOIN users u ON a.admin_id = u.user_id ORDER BY a.created_at DESC");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manajemen Artikel';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <?php if (isset($_SESSION['success'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'success',title:'Berhasil',text:'<?= $_SESSION['success'] ?>',timer:2000,showConfirmButton:false}));</script>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'error',title:'Gagal',text:'<?= $_SESSION['error'] ?>'}));</script>
        <?php unset($_SESSION['error']); endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-newspaper me-2"></i>Manajemen Artikel</h4>
                <p class="text-muted mb-0">Kelola artikel dan berita lingkungan</p>
            </div>
            <a href="tambah_artikel.php" class="btn text-white" style="background-color:#34483B;">
                <i class="fas fa-plus me-1"></i>Tambah Artikel
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>Judul</th>
                                <th>Penulis</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($articles)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-newspaper fa-2x mb-2 d-block"></i>Belum ada artikel</td></tr>
                            <?php else: ?>
                            <?php foreach ($articles as $a): ?>
                            <tr>
                                <td><?= $a['article_id'] ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 300px;">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($a['author_name'] ?? 'Admin') ?></td>
                                <td>
                                    <?php if ($a['status'] === 'Published'): ?>
                                    <span class="badge" style="background:#789078;color:#fff;"><i class="fas fa-check me-1"></i>Published</span>
                                    <?php else: ?>
                                    <span class="badge" style="background:#D6CBB5;color:#34483B;"><i class="fas fa-pencil-alt me-1"></i>Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($a['created_at']) ?></td>
                                <td class="text-center">
                                    <a href="tambah_artikel.php?id=<?= $a['article_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="article_id" value="<?= $a['article_id'] ?>">
                                        <button type="submit" name="toggle_publish" class="btn btn-sm <?= $a['status'] === 'Published' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $a['status'] === 'Published' ? 'Set Draft' : 'Publish' ?>">
                                            <i class="fas fa-<?= $a['status'] === 'Published' ? 'eye-slash' : 'eye' ?>"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteArticle(<?= $a['article_id'] ?>, '<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteArticleForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_article" value="1">
    <input type="hidden" name="article_id" id="deleteArticleId">
</form>

<script>
function deleteArticle(id, title) {
    Swal.fire({
        title: 'Hapus Artikel?',
        text: 'Artikel "' + title + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#C17B58',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteArticleId').value = id;
            document.getElementById('deleteArticleForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
