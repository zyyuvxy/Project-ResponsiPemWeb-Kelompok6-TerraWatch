<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadNotifCountAdmin = 0;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $unreadNotifCountAdmin = getUnreadNotificationCount($pdo, (int)$_SESSION['user_id']);
}
?>
<div class="sidebar" id="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span class="logo-leaf">🌿</span>
            <span class="logo-name">TerraWatch</span>
        </div>
        <div class="sidebar-sdg-badge">
            <i class="fas fa-globe-americas"></i> SDG 15: Life on Land
        </div>
        <div class="sidebar-portal-label">Panel Administrator</div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="<?= in_array($currentPage, ['dashboard']) ? 'active' : '' ?>">
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['manajemen_laporan', 'detail_laporan']) ? 'active' : '' ?>">
                <a href="manajemen_laporan.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Manajemen Laporan</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['notifikasi']) ? 'active' : '' ?>">
                <a href="notifikasi.php">
                    <i class="fas fa-bell"></i>
                    <span>Notifikasi</span>
                    <?php if ($unreadNotifCountAdmin > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-auto"><?= $unreadNotifCountAdmin ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['manajemen_kategori']) ? 'active' : '' ?>">
                <a href="manajemen_kategori.php">
                    <i class="fas fa-tags"></i>
                    <span>Manajemen Kategori</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['manajemen_artikel', 'tambah_artikel']) ? 'active' : '' ?>">
                <a href="manajemen_artikel.php">
                    <i class="fas fa-newspaper"></i>
                    <span>Manajemen Artikel</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['manajemen_pengguna']) ? 'active' : '' ?>">
                <a href="manajemen_pengguna.php">
                    <i class="fas fa-users"></i>
                    <span>Manajemen Pengguna</span>
                </a>
            </li>
            <li class="<?= in_array($currentPage, ['log_aktivitas']) ? 'active' : '' ?>">
                <a href="log_aktivitas.php">
                    <i class="fas fa-history"></i>
                    <span>Log Aktivitas</span>
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
    .sidebar-separator {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 4px 20px;
    }
    .sidebar-bottom {
        padding: 8px 12px;
    }
    .sidebar-logout-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        color: #D6CBB5;
        text-decoration: none;
        font-size: 13px;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .sidebar-logout-link:hover {
        background: rgba(255,255,255,0.1);
        color: #C17B58;
    }
</style>
