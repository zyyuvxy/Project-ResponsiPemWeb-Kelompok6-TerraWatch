<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $unreadCount = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
}
?>
<div class="sidebar" id="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../assets/img/terrawatch-logo-light.png" alt="TerraWatch" style="height: 36px; max-width: 180px; object-fit: contain;">
        </div>
        <div class="sidebar-sdg-badge">
            <i class="fas fa-globe-americas"></i> SDG 15: Life on Land
        </div>
        <div class="sidebar-portal-label">Portal Warga</div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dasbor</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['buat_laporan', 'create_report']) ? 'active' : '' ?>">
                <a href="buat_laporan.php">
                    <i class="fas fa-plus-circle"></i>
                    <span>Buat Laporan</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['laporan_saya', 'my_reports', 'detail_laporan', 'edit_laporan']) ? 'active' : '' ?>">
                <a href="laporan_saya.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan Saya</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['laporan_publik', 'public_reports', 'detail_publik']) ? 'active' : '' ?>">
                <a href="laporan_publik.php">
                    <i class="fas fa-globe"></i>
                    <span>Laporan Publik</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['artikel', 'articles', 'detail_artikel']) ? 'active' : '' ?>">
                <a href="artikel.php">
                    <i class="fas fa-book"></i>
                    <span>Artikel Edukasi</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['notifikasi', 'notifications']) ? 'active' : '' ?>">
                <a href="notifikasi.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifikasi</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['profil', 'profile']) ? 'active' : '' ?>">
                <a href="profil.php">
                    <i class="fas fa-user"></i>
                    <span>Profil Saya</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Separator -->
    <div class="sidebar-separator"></div>

    <!-- Bottom Links -->
    <div class="sidebar-bottom">
        <a href="../auth/logout.php" class="sidebar-logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </div>
</div>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background-color: #34483B;
        color: #ffffff;
        display: flex;
        flex-direction: column;
        z-index: 1000;
        overflow-y: auto;
    }
    .sidebar-header {
        padding: 24px 20px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }
    .logo-leaf {
        font-size: 24px;
    }
    .logo-name {
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .sidebar-sdg-badge {
        background: rgba(255,255,255,0.12);
        color: #B5C2AA;
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-block;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .sidebar-portal-label {
        font-size: 11px;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-top: 4px;
    }
    .sidebar-nav {
        flex: 1;
        padding: 12px 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu li {
        margin: 2px 12px;
    }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        color: rgba(255,255,255,0.78);
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.1);
        color: #ffffff;
    }
    .sidebar-menu li.active a {
        background: #789078;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .sidebar-menu li a i {
        width: 18px;
        text-align: center;
        font-size: 14px;
    }
    .notif-badge {
        background: #C17B58;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 10px;
        margin-left: auto;
    }
    .sidebar-separator {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 4px 20px;
    }
    .sidebar-bottom {
        padding: 8px 12px;
    }
    .sidebar-help-link,
    .sidebar-logout-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        font-size: 13px;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .sidebar-help-link:hover,
    .sidebar-logout-link:hover {
        background: rgba(255,255,255,0.1);
        color: #ffffff;
    }
    .sidebar-logout-link {
        color: #D6CBB5;
    }
    .sidebar-logout-link:hover {
        color: #C17B58;
    }
</style>
