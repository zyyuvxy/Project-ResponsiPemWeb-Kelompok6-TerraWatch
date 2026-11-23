<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Auth check
requireUser();

$userId = $_SESSION['user_id'];
$reportId = (int)($_GET['id'] ?? 0);
$errors = [];
$success = false;

// Fetch report and verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE report_id = ? AND user_id = ?");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        redirect('laporan_saya.php');
    }

    // Only allow editing if status is Pending or Revision
    if (!in_array($report['status'], ['Pending', 'Revision'])) {
        $_SESSION['flash_error'] = 'Laporan dengan status ' . $report['status'] . ' tidak dapat diubah.';
        redirect('detail_laporan.php?id=' . $reportId);
    }

    // Fetch existing images
    $stmtImg = $pdo->prepare("SELECT * FROM report_images WHERE report_id = ?");
    $stmtImg->execute([$reportId]);
    $existingImages = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories
    $stmtCat = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    redirect('laporan_saya.php');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isPostMaxSizeExceeded()) {
        $errors[] = 'Ukuran berkas yang diunggah melebihi batas konfigurasi server (post_max_size). Silakan kurangi ukuran atau jumlah foto.';
    }

    $title = sanitize($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $incidentDate = sanitize($_POST['incident_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $description = sanitize($_POST['description'] ?? '');

    if (empty($title)) {
        $errors[] = 'Judul laporan wajib diisi.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Kategori kerusakan lingkungan wajib dipilih.';
    }
    if (empty($incidentDate)) {
        $errors[] = 'Tanggal kejadian wajib diisi.';
    }
    if (empty($location)) {
        $errors[] = 'Lokasi kejadian wajib diisi.';
    }
    if (empty($description) || strlen($description) < 30) {
        $errors[] = 'Deskripsi kejadian minimal 30 karakter.';
    }

    // Validasi foto lama dan foto baru
    $deleteImageIds = array_map('intval', $_POST['delete_images'] ?? []);
    $existingImageIds = array_column($existingImages, 'image_id');
    $deleteImageIds = array_values(array_intersect($deleteImageIds, $existingImageIds));
    $remainingExistingCount = count($existingImages) - count($deleteImageIds);

    // Filter dan susun foto baru yang diunggah
    $selectedNewFiles = [];
    $totalNewBytes = 0;
    if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
        foreach ($_FILES['photos']['name'] as $idx => $name) {
            if (empty($name)) continue;
            $errCode = $_FILES['photos']['error'][$idx];
            if ($errCode === UPLOAD_ERR_NO_FILE) continue;

            if ($errCode !== UPLOAD_ERR_OK) {
                $errors[] = "Gagal mengunggah foto \"{$name}\" (Kode error: {$errCode}).";
                continue;
            }

            $fileInfo = [
                'name'     => $name,
                'type'     => $_FILES['photos']['type'][$idx] ?? '',
                'tmp_name' => $_FILES['photos']['tmp_name'][$idx],
                'error'    => $errCode,
                'size'     => $_FILES['photos']['size'][$idx],
            ];

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $errors[] = "Format berkas \"{$name}\" tidak didukung (harus JPG, JPEG, atau PNG).";
                continue;
            }

            if ($fileInfo['size'] > MAX_FILE_SIZE) {
                $errors[] = "Ukuran berkas \"{$name}\" melebihi batas 2MB.";
                continue;
            }

            if (empty($fileInfo['tmp_name']) || !file_exists($fileInfo['tmp_name'])) {
                $errors[] = "Berkas fisik \"{$name}\" tidak ditemukan di server.";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($fileInfo['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || @getimagesize($fileInfo['tmp_name']) === false) {
                $errors[] = "Berkas \"{$name}\" bukan berkas gambar asli yang valid.";
                continue;
            }

            $totalNewBytes += $fileInfo['size'];
            $selectedNewFiles[] = $fileInfo;
        }
    }

    if ($totalNewBytes >= 8 * 1024 * 1024) {
        $errors[] = 'Total ukuran foto baru tidak boleh melebihi 8 MB.';
    }

    $finalPhotoCount = $remainingExistingCount + count($selectedNewFiles);
    if ($finalPhotoCount < 1) {
        $errors[] = 'Laporan harus memiliki minimal 1 foto bukti.';
    } elseif ($finalPhotoCount > 5) {
        $errors[] = 'Total foto laporan tidak boleh lebih dari 5.';
    }

    if (empty($errors)) {
        $uploadedPaths = [];
        try {
            $pdo->beginTransaction();

            // Hapus foto yang dipilih untuk dihapus
            if (!empty($deleteImageIds)) {
                $placeholders = implode(',', array_fill(0, count($deleteImageIds), '?'));
                $stmtDel = $pdo->prepare("SELECT image_path FROM report_images WHERE image_id IN ($placeholders) AND report_id = ?");
                $params = array_merge($deleteImageIds, [$reportId]);
                $stmtDel->execute($params);
                $delFiles = $stmtDel->fetchAll(PDO::FETCH_COLUMN);

                foreach ($delFiles as $df) {
                    $filePath = UPLOAD_DIR . ltrim(str_replace('uploads/', '', $df), '/');
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                $stmtDelDb = $pdo->prepare("DELETE FROM report_images WHERE image_id IN ($placeholders) AND report_id = ?");
                $stmtDelDb->execute($params);
            }

            // Simpan foto-foto baru dengan uploadImage()
            if (!empty($selectedNewFiles)) {
                $stmtImg = $pdo->prepare("INSERT INTO report_images (report_id, image_path, uploaded_at) VALUES (?, ?, NOW())");
                foreach ($selectedNewFiles as $photo) {
                    $savedPath = uploadImage($photo, 'reports');
                    if ($savedPath === false) {
                        throw new Exception("Gagal memproses dan menyimpan berkas foto \"{$photo['name']}\".");
                    }
                    $uploadedPaths[] = $savedPath;
                    $stmtImg->execute([$reportId, $savedPath]);
                }
            }

            // Reset status back to Pending if it was Revision
            $newStatus = ($report['status'] === 'Revision') ? 'Pending' : $report['status'];

            $stmtUp = $pdo->prepare("
                UPDATE reports 
                SET title = ?, category_id = ?, incident_date = ?, location = ?, latitude = ?, longitude = ?, description = ?, status = ?, updated_at = NOW()
                WHERE report_id = ? AND user_id = ?
            ");
            $stmtUp->execute([
                $title,
                $categoryId,
                $incidentDate,
                $location,
                $latitude,
                $longitude,
                $description,
                $newStatus,
                $reportId,
                $userId
            ]);

            // Jika laporan berstatus Revision dan dikirim ulang oleh pelapor
            if ($report['status'] === 'Revision') {
                $stmtUpdateHistory = $pdo->prepare("INSERT INTO report_updates (report_id, changed_by, old_status, new_status, note, created_at) VALUES (?, ?, 'Revision', 'Pending', 'Pelapor mengirim perbaikan laporan', NOW())");
                $stmtUpdateHistory->execute([$reportId, $userId]);

                $userName = $_SESSION['user_name'] ?? 'Pelapor';
                notifyAdmins($pdo, "{$userName} mengirim perbaikan laporan \"{$title}\".", $reportId);
            }

            logActivity($pdo, $userId, "Memperbarui laporan: {$title} (ID: TW-" . date('Y') . "-" . str_pad($reportId, 3, '0', STR_PAD_LEFT) . ")");

            $pdo->commit();
            $success = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            // Rollback: bersihkan berkas yang sudah terlanjur dipindahkan
            foreach ($uploadedPaths as $p) {
                $fullPath = UPLOAD_DIR . ltrim($p, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            $errors[] = 'Gagal memperbarui laporan: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Laporan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - TerraWatch</title>
    <link rel="icon" href="../assets/img/favicon-32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #D6CBB5;
            padding: 26px;
            margin-bottom: 25px;
        }
        .form-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #34483B;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 2px solid #F6F6F0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .existing-img-card {
            position: relative;
            display: inline-block;
            margin: 6px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #D6CBB5;
        }
        .existing-img-card img {
            width: 110px;
            height: 90px;
            object-fit: cover;
            display: block;
        }
        .img-delete-checkbox {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(193, 123, 88, 0.9);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        #edit-picker-map {
            height: 230px;
            border-radius: 8px;
            border: 1px solid #D6CBB5;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">
    <div class="content-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-pine mb-1">
                <i class="fas fa-edit me-2 text-pine"></i>Perbarui Laporan <?= generateReportId($report['report_id'], $report['created_at'] ?? null) ?>
            </h3>
            <p class="text-muted mb-0">Perbaiki atau lengkapi data laporan insiden Anda.</p>
        </div>
        <a href="detail_laporan.php?id=<?= $reportId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Batal
        </a>
    </div>

    <?php if ($report['status'] === 'Revision'): ?>
        <div class="alert alert-warning shadow-sm mb-4">
            <h6 class="fw-bold mb-1"><i class="fas fa-exclamation-circle me-2"></i>Catatan Revisi dari Administrator:</h6>
            <p class="mb-0"><?= htmlspecialchars($report['rejection_reason'] ?? 'Silakan lengkapi data atau perjelas foto bukti.') ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="edit_laporan.php?id=<?= $reportId ?>" method="POST" enctype="multipart/form-data" id="editReportForm">
        <div class="row">
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="form-section-title"><i class="fas fa-info-circle"></i> Detail Insiden</div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Judul Laporan *</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? $report['title']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Kategori Kerusakan *</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($report['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tanggal Kejadian *</label>
                            <input type="date" name="incident_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['incident_date'] ?? $report['incident_date']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Deskripsi Lengkap *</label>
                        <textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($_POST['description'] ?? $report['description']) ?></textarea>
                    </div>
                </div>

                <!-- Photos Card -->
                <div class="form-card">
                    <div class="form-section-title"><i class="fas fa-images"></i> Foto Bukti Lapangan (Wajib 1 - 5 Foto)</div>
                    
                    <?php if (!empty($existingImages)): ?>
                        <label class="form-label fw-bold small text-pine">Foto Tersimpan Saat Ini:</label>
                        <p class="text-muted small mb-2">Centang kotak pada foto untuk menandai foto yang ingin dihapus.</p>
                        <div class="d-flex flex-wrap mb-3 gap-2" id="existingImagesList">
                            <?php foreach ($existingImages as $img): ?>
                                <div class="existing-img-card position-relative" style="width:120px; border-radius:8px; overflow:hidden; border:1.5px solid #D6CBB5;">
                                    <img src="<?= htmlspecialchars(getImageUrl($img['image_path'])) ?>" alt="Bukti" style="width:100%; height:95px; object-fit:cover; display:block;">
                                    <div class="p-1 bg-light text-center border-top">
                                        <label class="small mb-0 text-danger fw-semibold cursor-pointer" style="font-size:0.8rem;">
                                            <input type="checkbox" name="delete_images[]" value="<?= (int)$img['image_id'] ?>" class="delete-img-checkbox me-1"> Hapus
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Unggah Foto Tambahan / Baru:</label>
                        <div class="upload-box p-3 text-center border rounded-3" style="border:2px dashed #B5C2AA; background:#F6F6F0; cursor:pointer;" onclick="document.getElementById('newPhotosInput').click()">
                            <i class="fas fa-cloud-upload-alt fs-3 text-pine mb-1"></i>
                            <div class="fw-semibold small text-pine">Klik untuk memilih foto tambahan</div>
                            <div class="text-muted" style="font-size:0.75rem;">Format JPG, JPEG, PNG (Maks. 2MB per file, total foto 1 - 5)</div>
                            <input type="file" id="newPhotosInput" name="photos[]" multiple accept=".jpg,.jpeg,.png,image/jpeg,image/png" style="display:none">
                        </div>
                        <div id="newPreviewArea" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Location -->
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> Lokasi &amp; Koordinat</div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat / Patokan *</label>
                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($_POST['location'] ?? $report['location']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Latitude</label>
                            <input type="text" name="latitude" id="lat" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['latitude'] ?? ($report['latitude'] ?? '')) ?>">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Longitude</label>
                            <input type="text" name="longitude" id="lng" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['longitude'] ?? ($report['longitude'] ?? '')) ?>">
                        </div>
                    </div>

                    <div id="edit-picker-map"></div>
                </div>

                <div class="form-card bg-light text-center">
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold" style="background:#34483B;border:none;">
                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                    </button>
                    <a href="detail_laporan.php?id=<?= $reportId ?>" class="btn btn-link text-muted mt-2">Batal</a>
                </div>
            </div>
        </div>
    </form>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var curLat = <?= !empty($report['latitude']) ? (float)$report['latitude'] : -2.5 ?>;
var curLng = <?= !empty($report['longitude']) ? (float)$report['longitude'] : 118.0 ?>;
var hasCoord = <?= (!empty($report['latitude']) && !empty($report['longitude'])) ? 'true' : 'false' ?>;

var editMap = L.map('edit-picker-map').setView([curLat, curLng], hasCoord ? 12 : 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editMap);

var marker = hasCoord ? L.marker([curLat, curLng]).addTo(editMap) : null;

editMap.on('click', function(e) {
    document.getElementById('lat').value = e.latlng.lat.toFixed(6);
    document.getElementById('lng').value = e.latlng.lng.toFixed(6);
    if (marker) {
        marker.setLatLng(e.latlng);
    } else {
        marker = L.marker(e.latlng).addTo(editMap);
    }
});

var existingTotal = <?= count($existingImages) ?>;
var newPhotoInput = document.getElementById('newPhotosInput');
var newPreviewArea = document.getElementById('newPreviewArea');
var editDt = new DataTransfer();

newPhotoInput.addEventListener('change', function(e) {
    var files = Array.from(newPhotoInput.files);
    if (!files.length) return;

    var deletedCount = document.querySelectorAll('.delete-img-checkbox:checked').length;
    var currentKept = existingTotal - deletedCount;
    var projectedTotal = currentKept + editDt.items.length + files.length;

    if (projectedTotal > 5) {
        Swal.fire({
            icon: 'warning',
            title: 'Batas Maksimum Terlampaui',
            text: 'Total foto tersimpan tidak boleh melebihi 5. (Foto aktif: ' + (currentKept + editDt.items.length) + ', foto baru: ' + files.length + ')',
            confirmButtonColor: '#34483B'
        });
        newPhotoInput.files = editDt.files;
        return;
    }

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var ext = file.name.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png'].indexOf(ext) === -1) {
            Swal.fire({
                icon: 'error',
                title: 'Format Tidak Didukung',
                text: 'Berkas "' + file.name + '" bukan format JPG, JPEG, atau PNG.',
                confirmButtonColor: '#34483B'
            });
            continue;
        }

        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'Ukuran Melebihi Batas',
                text: 'Berkas "' + file.name + '" melebihi 2MB.',
                confirmButtonColor: '#34483B'
            });
            continue;
        }

        editDt.items.add(file);
    }

    newPhotoInput.files = editDt.files;
    renderNewPreviews();
});

function removeNewPhoto(index) {
    var updatedDt = new DataTransfer();
    for (var i = 0; i < editDt.files.length; i++) {
        if (i !== index) {
            updatedDt.items.add(editDt.files[i]);
        }
    }
    editDt = updatedDt;
    newPhotoInput.files = editDt.files;
    renderNewPreviews();
}

function renderNewPreviews() {
    newPreviewArea.innerHTML = '';
    var files = editDt.files;

    for (var i = 0; i < files.length; i++) {
        (function(idx) {
            var file = files[idx];
            var wrapper = document.createElement('div');
            wrapper.className = 'position-relative';
            wrapper.style.width = '100px';
            wrapper.style.height = '100px';
            wrapper.style.borderRadius = '8px';
            wrapper.style.overflow = 'hidden';
            wrapper.style.border = '1px solid #D6CBB5';

            var img = document.createElement('img');
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            img.src = URL.createObjectURL(file);

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-danger btn-sm position-absolute';
            delBtn.style.top = '3px';
            delBtn.style.right = '3px';
            delBtn.style.padding = '1px 6px';
            delBtn.style.fontSize = '11px';
            delBtn.style.lineHeight = '1';
            delBtn.style.borderRadius = '50%';
            delBtn.innerHTML = '&times;';
            delBtn.title = 'Batal unggah foto ini';
            delBtn.onclick = function(e) {
                e.stopPropagation();
                removeNewPhoto(idx);
            };

            wrapper.appendChild(img);
            wrapper.appendChild(delBtn);
            newPreviewArea.appendChild(wrapper);
        })(i);
    }
}

// Validation on submit
document.getElementById('editReportForm').addEventListener('submit', function(e) {
    var deletedCount = document.querySelectorAll('.delete-img-checkbox:checked').length;
    var kept = existingTotal - deletedCount;
    var totalPhotos = kept + editDt.files.length;

    if (totalPhotos < 1) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Foto Bukti Minimal',
            text: 'Laporan harus memiliki minimal 1 foto bukti. Jangan hapus semua foto atau unggah foto pengganti terlebih dahulu.',
            confirmButtonColor: '#34483B'
        });
        return false;
    }

    if (totalPhotos > 5) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Batas Maksimum Terlampaui',
            text: 'Total foto tidak boleh lebih dari 5. Saat ini total foto menjadi ' + totalPhotos + '.',
            confirmButtonColor: '#34483B'
        });
        return false;
    }

    var totalBytes = 0;
    for (var i = 0; i < editDt.files.length; i++) {
        var f = editDt.files[i];
        if (f.size > 2 * 1024 * 1024) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Ukuran Melebihi Batas',
                text: 'Foto baru "' + f.name + '" melebihi 2MB.',
                confirmButtonColor: '#34483B'
            });
            return false;
        }
        totalBytes += f.size;
    }

    if (totalBytes >= 8 * 1024 * 1024) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Total Ukuran Melebihi Batas',
            text: 'Total ukuran foto baru melebihi 8MB.',
            confirmButtonColor: '#34483B'
        });
        return false;
    }
});

<?php if ($success): ?>
Swal.fire({
    icon: 'success',
    title: 'Perubahan Tersimpan!',
    text: 'Laporan Anda berhasil diperbarui.',
    confirmButtonColor: '#34483B'
}).then(function() {
    window.location.href = 'detail_laporan.php?id=<?= $reportId ?>';
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
