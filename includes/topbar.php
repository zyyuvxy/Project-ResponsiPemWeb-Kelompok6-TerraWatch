<?php
// Indonesian date formatting
$hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$hariIni = $hari[date('w')] . ', ' . date('d') . ' ' . $bulan[(int)date('m')] . ' ' . date('Y');

$userName = $_SESSION['user_name'] ?? 'Pengguna';
$userRole = $_SESSION['role'] ?? 'user';
$profilePhoto = $_SESSION['profile_photo'] ?? null;
$roleBadge = $userRole === 'admin' ? 'Administrator' : 'Pelapor Warga Aktif';

$unreadCountTopbar = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $unreadCountTopbar = getUnreadNotificationCount($pdo, (int)$_SESSION['user_id']);
}
?>
<div class="topbar">
    <div class="topbar-left">
        <!-- Search Bar -->
        <div class="topbar-search">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Cari laporan, lokasi hutan, satwa liar, atau koordin...">
        </div>
    </div>

    <div class="topbar-center">
        <!-- Current Date -->
        <div class="topbar-date">
            <i class="fas fa-calendar-alt me-1"></i>
            <?= $hariIni ?>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Admin Notification Bell -->
        <?php if ($userRole === 'admin'): ?>
        <a href="notifikasi.php" class="position-relative me-3 text-decoration-none d-flex align-items-center" title="Notifikasi Admin">
            <i class="fas fa-bell fs-5" style="color:#34483B;"></i>
            <?php if ($unreadCountTopbar > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                <?= $unreadCountTopbar ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- New Report Button -->
        <?php if ($userRole !== 'admin'): ?>
        <a href="buat_laporan.php" class="btn-new-report">
            <i class="fas fa-plus me-1"></i> Buat Laporan Baru
        </a>
        <?php endif; ?>

        <!-- User Profile -->
        <div class="topbar-profile">
            <div class="profile-info">
                <span class="profile-name"><?= htmlspecialchars($userName) ?></span>
                <span class="profile-role-badge"><?= $roleBadge ?></span>
            </div>
            <div class="profile-avatar">
                <?php if ($profilePhoto): ?>
                    <img src="<?= htmlspecialchars(getImageUrl($profilePhoto)) ?>" alt="Profile">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
        padding: 12px 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-left: 260px;
        position: sticky;
        top: 0;
        z-index: 999;
        gap: 16px;
    }
    .topbar-left {
        flex: 1;
        max-width: 400px;
    }
    .topbar-search {
        position: relative;
    }
    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #98A2B3;
        font-size: 13px;
    }
    .search-input {
        width: 100%;
        padding: 10px 14px 10px 40px;
        border: 1.5px solid #D6CBB5;
        border-radius: 12px;
        font-size: 13px;
        background: #F6F6F0;
        transition: all 0.2s ease;
        color: #34483B;
    }
    .search-input:focus {
        outline: none;
        border-color: #789078;
        box-shadow: 0 0 0 3px rgba(120, 144, 120, 0.15);
        background: #fff;
    }
    .search-input::placeholder {
        color: #789078;
    }
    .topbar-center {
        flex-shrink: 0;
    }
    .topbar-date {
        font-size: 13px;
        color: #789078;
        font-weight: 500;
        white-space: nowrap;
    }
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-shrink: 0;
    }
    .btn-new-report {
        display: inline-flex;
        align-items: center;
        padding: 8px 18px;
        background: #34483B;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .btn-new-report:hover {
        background: #789078;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(52, 72, 59, 0.25);
        transform: translateY(-1px);
    }
    .topbar-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }
    .profile-info {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
    .profile-name {
        font-size: 13px;
        font-weight: 600;
        color: #34483B;
    }
    .profile-role-badge {
        font-size: 10px;
        color: #34483B;
        font-weight: 600;
        background: rgba(181, 194, 170, 0.35);
        padding: 1px 8px;
        border-radius: 8px;
    }
    .profile-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid #789078;
    }
    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #34483B, #789078);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
    }
</style>
