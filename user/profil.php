<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

requireUser();

$userId = $_SESSION['user_id'];
$errors = [];
$successMsg = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirect('../auth/logout.php');
    }

    // Count user reports
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
    $stmtCount->execute([$userId]);
    $totalReports = $stmtCount->fetchColumn();

} catch (PDOException $e) {
    redirect('../auth/login.php');
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Profile Info & Photo
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($name)) {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        // Handle Photo Upload
        $profilePhoto = $user['profile_photo'];
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $res = uploadImage($_FILES['photo'], 'profiles');
            if ($res !== false) {
                // Delete old photo if exists
                if (!empty($user['profile_photo'])) {
                    $oldPath = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $user['profile_photo']), '/');
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $profilePhoto = $res;
                $_SESSION['profile_photo'] = $profilePhoto;
            } else {
                $errors[] = 'Gagal mengunggah foto profil. Pastikan berkas berupa JPG/PNG yang valid dan ukuran maks. 2MB.';
            }
        }

        if (empty($errors)) {
            try {
                $stmtUp = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, phone = ?, address = ?, profile_photo = ? 
                    WHERE user_id = ?
                ");
                $stmtUp->execute([$name, $phone, $address, $profilePhoto, $userId]);

                $_SESSION['user_name'] = $name;
                $user['name'] = $name;
                $user['phone'] = $phone;
                $user['address'] = $address;
                $user['profile_photo'] = $profilePhoto;

                logActivity($pdo, $userId, 'Memperbarui profil data akun');
                $successMsg = 'Profil berhasil diperbarui!';
            } catch (PDOException $e) {
                $errors[] = 'Gagal menyimpan profil: ' . $e->getMessage();
            }
        }
    }

    // Change Password
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = 'Semua kolom password wajib diisi.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Kata sandi saat ini tidak cocok.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Kata sandi baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            try {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmtPass = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmtPass->execute([$newHash, $userId]);

                logActivity($pdo, $userId, 'Mengubah kata sandi akun');
                $successMsg = 'Kata sandi berhasil diperbarui!';
            } catch (PDOException $e) {
                $errors[] = 'Gagal memperbarui kata sandi: ' . $e->getMessage();
            }
        }
    }
}

$terraId = 'TERRA-ID-' . str_pad($userId, 5, '0', STR_PAD_LEFT);
$pageTitle = 'Profil Pengguna';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .profile-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 30px;
            border: 1px solid #D6CBB5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #F6F6F0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .avatar-placeholder-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #F6F6F0;
            color: #34483B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 4px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            margin: 0 auto;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">
    <div class="content-header mb-4">
        <h3 class="fw-bold text-pine mb-1"><i class="fas fa-user-circle me-2"></i>Pengaturan Profil Saya</h3>
        <p class="text-muted mb-0">Kelola informasi identitas akun pelapor terestrial dan keamanan kata sandi Anda.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left: Summary Info -->
        <div class="col-lg-4">
            <div class="profile-card text-center">
                <div class="mb-3">
                    <?php 
                    $hasPhoto = !empty($user['profile_photo']);
                    $photoDiskPath = $hasPhoto ? UPLOAD_DIR . ltrim(str_replace('uploads/', '', $user['profile_photo']), '/') : '';
                    ?>
                    <?php if ($hasPhoto && file_exists($photoDiskPath)): ?>
                        <img src="<?= htmlspecialchars(getImageUrl($user['profile_photo'])) ?>" class="profile-avatar-large" alt="Foto">
                    <?php else: ?>
                        <div class="avatar-placeholder-large">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <h5 class="fw-bold text-pine mb-1"><?= htmlspecialchars($user['name']) ?></h5>
                <span class="badge px-3 py-1 mb-2 fw-bold" style="background:rgba(120,144,120,0.18); color:#34483B;">Pelapor Warga Aktif</span>
                <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>

                <div class="p-3 bg-light rounded-3 text-start mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">ID Pelapor:</span>
                        <span class="small fw-bold text-pine"><?= $terraId ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">Total Laporan:</span>
                        <span class="small fw-bold text-dark"><?= $totalReports ?> Insiden</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="small text-muted">Bergabung:</span>
                        <span class="small text-muted"><?= formatDate($user['created_at']) ?></span>
                    </div>
                </div>

                <div class="badge bg-light text-pine border p-2 w-100" style="color:#34483B;">
                    <i class="fas fa-leaf me-1"></i> Kontributor Konservasi SDG 15
                </div>
            </div>
        </div>

        <!-- Right: Forms -->
        <div class="col-lg-8">
            <!-- Edit Data Form -->
            <div class="profile-card">
                <h5 class="fw-bold text-pine mb-3 pb-2 border-bottom"><i class="fas fa-id-card me-2"></i>Informasi Akun</h5>
                
                <form action="profil.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Lengkap *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Alamat Email</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly disabled>
                            <small class="text-muted">Email digunakan sebagai ID login tetap.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat Domisili</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Kabupaten / Kota domisili Anda..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Unggah Foto Profil Baru</label>
                        <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
                        <small class="text-muted">Format JPG, JPEG, PNG (Maks. 2MB)</small>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold px-4" style="background:#34483B;border:none;">
                        <i class="fas fa-save me-1"></i> Simpan Profil
                    </button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="profile-card">
                <h5 class="fw-bold text-pine mb-3 pb-2 border-bottom"><i class="fas fa-lock me-2"></i>Keamanan Kata Sandi</h5>

                <form action="profil.php" method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kata Sandi Saat Ini *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Kata Sandi Baru *</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                            <small class="text-muted">Minimal 6 karakter.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Ulangi Kata Sandi Baru *</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-success fw-bold px-4" style="color:#34483B;border-color:#34483B;">
                        <i class="fas fa-key me-1"></i> Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($successMsg)): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Sukses!',
    text: '<?= addslashes($successMsg) ?>',
    confirmButtonColor: '#34483B'
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
