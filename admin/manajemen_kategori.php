<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAdmin();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['category_name']);
        $desc = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon']);

        if (empty($name)) {
            $_SESSION['error'] = 'Nama kategori wajib diisi.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (category_name, description, icon) VALUES (?, ?, ?)");
            $stmt->execute([$name, $desc, $icon]);
            logActivity($pdo, $_SESSION['user_id'], "Menambahkan kategori: $name");
            $_SESSION['success'] = 'Kategori berhasil ditambahkan.';
        }
        redirect('manajemen_kategori.php');
    }

    if ($action === 'edit') {
        $id = (int)$_POST['category_id'];
        $name = sanitize($_POST['category_name']);
        $desc = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon']);

        if (empty($name)) {
            $_SESSION['error'] = 'Nama kategori wajib diisi.';
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ?, icon = ? WHERE category_id = ?");
            $stmt->execute([$name, $desc, $icon, $id]);
            logActivity($pdo, $_SESSION['user_id'], "Mengedit kategori ID #$id: $name");
            $_SESSION['success'] = 'Kategori berhasil diperbarui.';
        }
        redirect('manajemen_kategori.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['category_id'];

        // Check if has reports
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE category_id = ?");
        $stmtCheck->execute([$id]);
        $reportCount = $stmtCheck->fetchColumn();

        if ($reportCount > 0) {
            $_SESSION['error'] = "Kategori tidak dapat dihapus karena masih memiliki $reportCount laporan terkait.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, $_SESSION['user_id'], "Menghapus kategori ID #$id");
            $_SESSION['success'] = 'Kategori berhasil dihapus.';
        }
        redirect('manajemen_kategori.php');
    }
}

// Fetch categories with report count
$stmt = $pdo->query("SELECT c.*, COUNT(r.report_id) as report_count FROM categories c LEFT JOIN reports r ON c.category_id = r.category_id GROUP BY c.category_id ORDER BY c.category_id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manajemen Kategori';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="content-wrapper p-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'success',title:'Berhasil',text:'<?= $_SESSION['success'] ?>',timer:2000,showConfirmButton:false}));</script>
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'error',title:'Gagal',text:'<?= $_SESSION['error'] ?>'}));</script>
        <?php unset($_SESSION['error']); endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="fas fa-tags me-2"></i>Manajemen Kategori</h4>
                <p class="text-muted mb-0">Kelola kategori laporan lingkungan</p>
            </div>
            <button class="btn text-white" style="background-color:#34483B;" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-1"></i>Tambah Kategori
            </button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #F6F6F0;">
                            <tr>
                                <th>ID</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Ikon</th>
                                <th class="text-center">Jumlah Laporan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada kategori</td></tr>
                            <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['category_id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($cat['category_name']) ?></td>
                                <td><span class="text-muted"><?= htmlspecialchars($cat['description'] ?? '-') ?></span></td>
                                <td>
                                    <?php if ($cat['icon']): ?>
                                    <i class="fas fa-<?= htmlspecialchars($cat['icon']) ?>" style="color:#34483B;"></i>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge" style="background-color:#789078;"><?= $cat['report_count'] ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editCategory(<?= json_encode($cat) ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?= $cat['category_id'] ?>, '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>', <?= $cat['report_count'] ?>)" title="Hapus">
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

<!-- Add Category Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header" style="background-color:#34483B;">
                    <h5 class="modal-title text-white"><i class="fas fa-plus me-2"></i>Tambah Kategori</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ikon (Font Awesome, tanpa 'fa-')</label>
                        <input type="text" name="icon" class="form-control" placeholder="Contoh: tree, fire, trash">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white" style="background-color:#34483B;">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="editCategoryId">
                <div class="modal-header" style="background-color:#789078;">
                    <h5 class="modal-title text-white"><i class="fas fa-edit me-2"></i>Edit Kategori</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ikon (Font Awesome, tanpa 'fa-')</label>
                        <input type="text" name="icon" id="editIcon" class="form-control" placeholder="Contoh: tree, fire, trash">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn text-white" style="background-color:#789078;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="category_id" id="deleteCategoryId">
</form>

<script>
function editCategory(cat) {
    document.getElementById('editCategoryId').value = cat.category_id;
    document.getElementById('editCategoryName').value = cat.category_name;
    document.getElementById('editDescription').value = cat.description || '';
    document.getElementById('editIcon').value = cat.icon || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteCategory(id, name, count) {
    if (count > 0) {
        Swal.fire({icon:'error', title:'Tidak Dapat Dihapus', text:'Kategori "' + name + '" masih memiliki ' + count + ' laporan terkait.'});
        return;
    }
    Swal.fire({
        title: 'Hapus Kategori?',
        text: 'Kategori "' + name + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#C17B58',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
