<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

$editMode = false;
$article = ['title' => '', 'content' => '', 'image' => '', 'status' => 'Draft'];

// If edit mode
if (isset($_GET['id'])) {
    $editMode = true;
    $articleId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE article_id = ?");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$article) {
        $_SESSION['error'] = 'Artikel tidak ditemukan.';
        redirect('manajemen_artikel.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $content = $_POST['content']; // allow HTML
    $status = sanitize($_POST['status']);
    $imagePath = $editMode ? $article['image'] : '';

    // Validate
    $errors = [];
    if (empty($title)) $errors[] = 'Judul wajib diisi.';
    if (empty($content)) $errors[] = 'Konten wajib diisi.';
    if (!in_array($status, ['Draft', 'Published'])) $errors[] = 'Status tidak valid.';

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImage($_FILES['image'], 'articles');
        if (is_string($result)) {
            // Delete old image if updating
            if ($editMode && !empty($article['image'])) {
                $oldImg = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $article['image']), '/');
                if (file_exists($oldImg)) {
                    @unlink($oldImg);
                }
            }
            $imagePath = $result;
        } else {
            $errors[] = 'Gagal mengunggah gambar. Pastikan berkas berupa JPG/PNG yang valid dan ukuran maks. 2MB.';
        }
    }

    if (empty($errors)) {
        try {
            if ($editMode) {
                $stmt = $pdo->prepare("UPDATE articles SET title = ?, content = ?, image = ?, status = ?, updated_at = NOW() WHERE article_id = ?");
                $stmt->execute([$title, $content, $imagePath, $status, $article['article_id']]);
                logActivity($pdo, $_SESSION['user_id'], "Mengedit artikel: $title");
                $_SESSION['success'] = 'Artikel berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO articles (admin_id, title, content, image, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $content, $imagePath, $status]);
                logActivity($pdo, $_SESSION['user_id'], "Menambahkan artikel: $title");
                $_SESSION['success'] = 'Artikel berhasil ditambahkan.';
            }
            redirect('manajemen_artikel.php');
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan artikel: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

$pageTitle = $editMode ? 'Edit Artikel' : 'Tambah Artikel';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <?php if (isset($_SESSION['error'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'error',title:'Gagal',html:'<?= $_SESSION['error'] ?>'}));</script>
        <?php unset($_SESSION['error']); endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">
                    <i class="fas fa-<?= $editMode ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editMode ? 'Edit Artikel' : 'Tambah Artikel Baru' ?>
                </h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" style="color:#34483B;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manajemen_artikel.php" style="color:#34483B;">Artikel</a></li>
                        <li class="breadcrumb-item active"><?= $editMode ? 'Edit' : 'Tambah' ?></li>
                    </ol>
                </nav>
            </div>
            <a href="manajemen_artikel.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Judul Artikel <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control form-control-lg" value="<?= htmlspecialchars($article['title']) ?>" required placeholder="Masukkan judul artikel...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Konten Artikel <span class="text-danger">*</span></label>
                                <div class="btn-group mb-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('heading')"><i class="fas fa-heading"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('list')"><i class="fas fa-list"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('link')"><i class="fas fa-link"></i></button>
                                </div>
                                <textarea name="content" id="articleContent" class="form-control" rows="15" required placeholder="Tulis konten artikel..."><?= htmlspecialchars($article['content']) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Draft" <?= $article['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="Published" <?= $article['status'] === 'Published' ? 'selected' : '' ?>>Published</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Gambar</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/jpg" id="imageInput">
                                <small class="text-muted">Format: JPG, JPEG, PNG. Maks: 2MB</small>
                                <div id="imagePreview" class="mt-2">
                                    <?php if ($editMode && !empty($article['image'])): ?>
                                    <img src="<?= htmlspecialchars(getImageUrl($article['image'])) ?>" class="img-fluid rounded" alt="Preview">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <button type="submit" class="btn text-white w-100 mb-2" style="background-color:#34483B;">
                                <i class="fas fa-save me-1"></i><?= $editMode ? 'Simpan Perubahan' : 'Simpan Artikel' ?>
                            </button>
                            <?php if ($editMode): ?>
                            <a href="manajemen_artikel.php" class="btn btn-outline-secondary w-100">Batal</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Image preview
document.getElementById('imageInput').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="img-fluid rounded" alt="Preview">';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Basic text formatting
function formatText(type) {
    const textarea = document.getElementById('articleContent');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.substring(start, end);
    let replacement = '';

    switch(type) {
        case 'bold': replacement = '<strong>' + selected + '</strong>'; break;
        case 'italic': replacement = '<em>' + selected + '</em>'; break;
        case 'underline': replacement = '<u>' + selected + '</u>'; break;
        case 'heading': replacement = '<h3>' + selected + '</h3>'; break;
        case 'list': replacement = '<ul>\n<li>' + selected + '</li>\n</ul>'; break;
        case 'link': 
            const url = prompt('Masukkan URL:');
            if (url) replacement = '<a href="' + url + '">' + (selected || url) + '</a>';
            else return;
            break;
    }

    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
