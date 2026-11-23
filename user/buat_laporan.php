<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Auth check
requireUser();

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Pre-fill from quick action if available
$prefillLocation = $_GET['lokasi'] ?? '';

// Fetch active categories
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Cek apakah payload melebihi post_max_size server
if (isPostMaxSizeExceeded()) {
    $errors[] = 'Total ukuran foto melebihi batas server (maks 8 MB per kiriman). Kurangi jumlah atau ukuran foto.';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isPostMaxSizeExceeded()) {
    $title = sanitize($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $incidentDate = sanitize($_POST['incident_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $description = sanitize($_POST['description'] ?? '');

    // Validations (PRD Section 4.2 & Section 9)
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
    if (empty($description)) {
        $errors[] = 'Deskripsi kejadian wajib diisi.';
    } elseif (strlen($description) < 30) {
        $errors[] = 'Deskripsi kejadian terlalu singkat (minimal 30 karakter agar informasi jelas).';
    }

    // Filter dan validasi file foto yang dipilih
    $selectedFiles = [];
    if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
        for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
            $fileName = $_FILES['photos']['name'][$i];
            $fileError = $_FILES['photos']['error'][$i];
            if (empty($fileName) && $fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $selectedFiles[] = [
                'name'     => $fileName,
                'type'     => $_FILES['photos']['type'][$i] ?? '',
                'tmp_name' => $_FILES['photos']['tmp_name'][$i] ?? '',
                'error'    => $fileError,
                'size'     => $_FILES['photos']['size'][$i] ?? 0,
            ];
        }
    }

    $totalPhotos = count($selectedFiles);
    if ($totalPhotos < 1) {
        $errors[] = 'Wajib mengunggah minimal 1 foto bukti insiden (maksimal 5 foto).';
    } elseif ($totalPhotos > 5) {
        $errors[] = "Maksimal 5 foto bukti yang dapat diunggah. Anda memilih {$totalPhotos} foto.";
    } else {
        $totalSize = 0;
        foreach ($selectedFiles as $file) {
            $totalSize += $file['size'];
            $fileName = htmlspecialchars($file['name']);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $errors[] = "Berkas \"{$fileName}\" melebihi batas ukuran 2MB.";
                } else {
                    $errors[] = "Berkas \"{$fileName}\" gagal diunggah (Kode error: {$file['error']}).";
                }
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $errors[] = "Format berkas \"{$fileName}\" tidak didukung. Gunakan JPG, JPEG, atau PNG.";
                continue;
            }

            if ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = "Ukuran berkas \"{$fileName}\" melebihi batas maksimum 2MB.";
                continue;
            }

            if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
                $errors[] = "Berkas fisik \"{$fileName}\" tidak ditemukan di server.";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || @getimagesize($file['tmp_name']) === false) {
                $errors[] = "Berkas \"{$fileName}\" bukan format gambar asli yang valid.";
                continue;
            }
        }

        if ($totalSize >= 8 * 1024 * 1024) {
            $errors[] = 'Total ukuran seluruh foto tidak boleh melebihi 8 MB.';
        }
    }

    // Process if valid
    if (empty($errors)) {
        $uploadedPaths = [];
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO reports (user_id, category_id, title, incident_date, location, latitude, longitude, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $categoryId,
                $title,
                $incidentDate,
                $location,
                $latitude,
                $longitude,
                $description
            ]);

            $reportId = (int)$pdo->lastInsertId();

            // Catat riwayat status awal di report_updates
            $stmtUpdateHistory = $pdo->prepare("INSERT INTO report_updates (report_id, changed_by, old_status, new_status, note, created_at) VALUES (?, ?, NULL, 'Pending', 'Laporan dikirim', NOW())");
            $stmtUpdateHistory->execute([$reportId, $userId]);

            // Simpan foto menggunakan uploadImage()
            $stmtImg = $pdo->prepare("INSERT INTO report_images (report_id, image_path, uploaded_at) VALUES (?, ?, NOW())");
            foreach ($selectedFiles as $file) {
                $savedPath = uploadImage($file, 'reports');
                if ($savedPath === false) {
                    throw new Exception("Gagal memproses dan menyimpan berkas foto \"{$file['name']}\".");
                }
                $uploadedPaths[] = $savedPath;
                $stmtImg->execute([$reportId, $savedPath]);
            }

            // Create user notification
            createNotification(
                $pdo,
                $userId,
                "Laporan \"{$title}\" berhasil dikirim dan berstatus Menunggu Verifikasi.",
                $reportId
            );

            // Beri notifikasi ke seluruh admin aktif
            $userName = $_SESSION['user_name'] ?? 'Pelapor';
            notifyAdmins($pdo, "Laporan baru dari {$userName}: \"{$title}\".", $reportId);

            // Log activity
            logActivity($pdo, $userId, "Mengirim laporan kerusakan lingkungan: {$title} (ID: TW-" . date('Y') . "-" . str_pad($reportId, 3, '0', STR_PAD_LEFT) . ")");

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
            $errors[] = 'Gagal menyimpan laporan: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Buat Laporan Kerusakan';
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
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #D6CBB5;
            padding: 30px;
            margin-bottom: 30px;
        }
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #34483B;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #F6F6F0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #34483B;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border: 1.5px solid #D6CBB5;
            border-radius: 9px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #789078;
            box-shadow: 0 0 0 3px rgba(120, 144, 120, 0.15);
        }
        .upload-box {
            border: 2px dashed #B5C2AA;
            border-radius: 12px;
            background: #F6F6F0;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-box:hover {
            border-color: #789078;
            background: rgba(181, 194, 170, 0.25);
        }
        .upload-box i {
            font-size: 2.2rem;
            color: #789078;
            margin-bottom: 10px;
        }
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }
        .preview-img-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #D6CBB5;
        }
        .preview-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #picker-map {
            height: 250px;
            border-radius: 10px;
            border: 1px solid #D6CBB5;
            margin-top: 10px;
        }
        .map-hint {
            font-size: 0.8rem;
            color: #6C757D;
            margin-top: 5px;
        }
        .btn-submit-report {
            background: #34483B;
            color: #ffffff;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 9px;
            border: none;
            transition: all 0.2s;
        }
        .btn-submit-report:hover {
            background: #2f4035;
            color: #ffffff;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php include __DIR__ . '/../includes/topbar.php'; ?>

<main class="main-content">
    <div class="content-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold text-pine mb-1">
                    <i class="fas fa-plus-circle me-2 text-pine"></i>Formulir Pelaporan Kerusakan Lingkungan
                </h3>
                <p class="text-muted mb-0">Laporkan insiden terestrial secara rinci dan jelas untuk mendukung aksi pelestarian ekosistem darat (SDG 15).</p>
            </div>
            <a href="laporan_saya.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Laporan Saya
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <h6 class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Perhatian:</h6>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="buat_laporan.php" method="POST" enctype="multipart/form-data" id="reportForm">
        <div class="row">
            <!-- Left Column: Primary Details -->
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Informasi Utama Insiden
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Laporan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="Contoh: Penebangan Pohon Ilegal di Blok C Hutan Lindung" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                        <small class="text-muted">Buat judul ringkas dan spesifik mengenai kejadian.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Kategori Kerusakan <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" 
                                        <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="incident_date" class="form-label">Tanggal Kejadian <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="incident_date" name="incident_date" 
                                   max="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['incident_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi Kejadian <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" 
                                  placeholder="Jelaskan secara rinci apa yang terjadi, estimasi luas area, jenis flora/fauna yang terdampak, atau ciri-ciri aktivitas yang mencurigakan..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        <small class="text-muted">Minimal 30 karakter. Informasi lengkap mempermudah verifikasi verifikator kehutanan.</small>
                    </div>
                </div>

                <!-- Evidence Upload Card -->
                <div class="form-card">
                    <div class="form-section-title">
                        <i class="fas fa-camera"></i> Bukti Foto Lapangan <span class="text-danger">*</span>
                    </div>

                    <div class="upload-box" onclick="document.getElementById('photos').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h6 class="fw-bold mb-1">Klik atau pilih foto bukti kejadian</h6>
                        <p class="text-muted small mb-0">Wajib unggah 1 sampai 5 foto bukti (JPG, JPEG, PNG, maks. 2MB per file, total maks. 8MB)</p>
                        <input type="file" id="photos" name="photos[]" multiple accept=".jpg,.jpeg,.png,image/jpeg,image/png" style="display:none">
                    </div>

                    <div id="previewArea" class="preview-container"></div>
                </div>
            </div>

            <!-- Right Column: Location & Coordinates -->
            <div class="col-lg-4">
                <div class="form-card">
                    <div class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i> Lokasi Kejadian
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Alamat / Patokan Lokasi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location" 
                               placeholder="Contoh: Desa Sukamaju, Dekat Pos Jaga 2 TNKS" 
                               value="<?= htmlspecialchars($_POST['location'] ?? $prefillLocation) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-2">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="text" class="form-control form-control-sm" id="latitude" name="latitude" 
                                   placeholder="-2.123456" 
                                   value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
                        </div>
                        <div class="col-6 mb-2">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="text" class="form-control form-control-sm" id="longitude" name="longitude" 
                                   placeholder="101.123456" 
                                   value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-success w-100 mb-2" onclick="getCurrentLocation()">
                        <i class="fas fa-location-arrow me-1"></i> Gunakan GPS Perangkat Saya
                    </button>

                    <div id="picker-map"></div>
                    <div class="map-hint">
                        <i class="fas fa-info-circle me-1"></i> Klik pada peta untuk menetapkan koordinat lokasi kejadian.
                    </div>
                </div>

                <!-- Submit Action Box -->
                <div class="form-card text-center bg-light">
                    <p class="small text-muted mb-3">
                        Dengan mengirimkan laporan ini, Anda menyatakan bahwa data yang diberikan adalah benar dan dapat dipertanggungjawabkan demi kelestarian alam.
                    </p>
                    <button type="submit" class="btn btn-submit-report w-100 shadow-sm">
                        <i class="fas fa-paper-plane me-2"></i> Kirim Laporan Sekarang
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Leaflet Map Picker
var pickerMap = L.map('picker-map').setView([-2.5, 118.0], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(pickerMap);

var marker;

function setCoordinates(lat, lng) {
    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(pickerMap);
    }
}

pickerMap.on('click', function(e) {
    setCoordinates(e.latlng.lat, e.latlng.lng);
});

// Geolocation
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            pickerMap.setView([lat, lng], 13);
            setCoordinates(lat, lng);
        }, function(err) {
            Swal.fire({
                icon: 'warning',
                title: 'Akses Lokasi Ditolak',
                text: 'Silakan klik titik lokasi langsung pada peta interaktif.'
            });
        });
    }
}

// Manage Selected Files via DataTransfer
var photoInput = document.getElementById('photos');
var previewArea = document.getElementById('previewArea');
var photoDt = new DataTransfer();

photoInput.addEventListener('change', function(e) {
    var files = Array.from(photoInput.files);
    if (!files.length) return;

    if (photoDt.items.length + files.length > 5) {
        Swal.fire({
            icon: 'warning',
            title: 'Batas Maksimum Terlampaui',
            text: 'Maksimal 5 foto bukti yang diperbolehkan. Saat ini sudah ada ' + photoDt.items.length + ' foto terpilih.',
            confirmButtonColor: '#34483B'
        });
        photoInput.files = photoDt.files;
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
                text: 'Berkas "' + file.name + '" melebihi batas 2MB (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB).',
                confirmButtonColor: '#34483B'
            });
            continue;
        }

        photoDt.items.add(file);
    }

    photoInput.files = photoDt.files;
    renderPreviews();
});

function removePhoto(index) {
    var newDt = new DataTransfer();
    for (var i = 0; i < photoDt.files.length; i++) {
        if (i !== index) {
            newDt.items.add(photoDt.files[i]);
        }
    }
    photoDt = newDt;
    photoInput.files = photoDt.files;
    renderPreviews();
}

function renderPreviews() {
    previewArea.innerHTML = '';
    var files = photoDt.files;

    for (var i = 0; i < files.length; i++) {
        (function(idx) {
            var file = files[idx];
            var wrapper = document.createElement('div');
            wrapper.className = 'preview-img-wrapper position-relative';
            wrapper.style.width = '110px';
            wrapper.style.height = '110px';
            wrapper.style.borderRadius = '8px';
            wrapper.style.overflow = 'hidden';
            wrapper.style.border = '1px solid #D6CBB5';
            wrapper.style.display = 'inline-block';
            wrapper.style.margin = '6px';

            var img = document.createElement('img');
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            img.src = URL.createObjectURL(file);

            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-danger btn-sm position-absolute';
            deleteBtn.style.top = '4px';
            deleteBtn.style.right = '4px';
            deleteBtn.style.padding = '2px 7px';
            deleteBtn.style.fontSize = '12px';
            deleteBtn.style.lineHeight = '1';
            deleteBtn.style.borderRadius = '50%';
            deleteBtn.innerHTML = '&times;';
            deleteBtn.title = 'Hapus foto ini';
            deleteBtn.onclick = function(e) {
                e.stopPropagation();
                removePhoto(idx);
            };

            wrapper.appendChild(img);
            wrapper.appendChild(deleteBtn);
            previewArea.appendChild(wrapper);
        })(i);
    }
}

// Form validation before submit
document.getElementById('reportForm').addEventListener('submit', function(e) {
    var count = photoDt.files.length;
    if (count < 1) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Foto Bukti Wajib',
            text: 'Wajib mengunggah minimal 1 foto bukti lapangan (JPG, JPEG, PNG).',
            confirmButtonColor: '#34483B'
        });
        return false;
    }

    if (count > 5) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Terlalu Banyak Foto',
            text: 'Maksimal 5 foto bukti yang diperbolehkan.',
            confirmButtonColor: '#34483B'
        });
        return false;
    }

    var totalBytes = 0;
    for (var i = 0; i < count; i++) {
        var f = photoDt.files[i];
        if (f.size > 2 * 1024 * 1024) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Ukuran Melebihi Batas',
                text: 'Foto "' + f.name + '" melebihi batas 2MB.',
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
            text: 'Total ukuran seluruh foto melebihi 8MB. Harap kompresi berkas foto terlebih dahulu.',
            confirmButtonColor: '#34483B'
        });
        return false;
    }
});

<?php if ($success): ?>
Swal.fire({
    icon: 'success',
    title: 'Laporan Berhasil Dikirim!',
    text: 'Laporan Anda telah tercatat dan sedang menunggu proses verifikasi oleh administrator.',
    confirmButtonColor: '#34483B',
    confirmButtonText: 'Lihat Laporan Saya'
}).then(function() {
    window.location.href = 'laporan_saya.php';
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
