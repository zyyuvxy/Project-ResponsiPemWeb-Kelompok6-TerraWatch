<?php
/**
 * Unified Global Footer - TerraWatch
 * Clean, centered, and responsive layout matching minimal natural palette.
 */
$hasSidebar = isset($_SESSION['user_id']);
?>
    <!-- Unified Application Footer -->
    <footer class="main-footer app-unified-footer <?= $hasSidebar ? 'has-sidebar' : 'no-sidebar' ?>">
        <div class="container-fluid px-3 px-md-4">
            <div class="footer-center-box text-center">
                <div class="footer-brand-row mb-2">
                    <span class="footer-brand-title">
                        <span class="logo-leaf me-1">🌿</span>TerraWatch
                    </span>
                    <span class="footer-separator mx-2 d-none d-sm-inline">|</span>
                    <span class="footer-copyright-text d-block d-sm-inline mt-1 mt-sm-0">
                        &copy; <?= date('Y') ?> TerraWatch Konservasi Alam Indonesia. Mendukung Aksi Nyata SDGs 15: Life on Land.
                    </span>
                </div>
                <div class="footer-links-row d-flex flex-wrap justify-content-center align-items-center gap-2 gap-md-3">
                    <a href="#" class="footer-nav-link">Kebijakan Privasi</a>
                    <span class="footer-dot">•</span>
                    <a href="#" class="footer-nav-link">Pedoman Verifikasi Spasial</a>
                    <span class="footer-dot">•</span>
                    <a href="#" class="footer-nav-link">Transparansi Data Terestrial</a>
                    <span class="footer-dot">•</span>
                    <a href="https://www.menlhk.go.id" target="_blank" class="footer-nav-link">Referensi: Situs KLHK</a>
                    <span class="footer-dot">•</span>
                    <a href="../user/artikel.php" class="footer-nav-link">Panduan Edukasi</a>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .app-unified-footer {
            background-color: #ffffff;
            border-top: 1px solid #D6CBB5;
            padding: 22px 20px 20px;
            font-size: 0.82rem;
            color: #6C757D;
            text-align: center;
            position: relative;
            z-index: 10;
            width: auto;
            margin-top: auto;
            transition: margin-left 0.3s ease;
        }
        .app-unified-footer.has-sidebar {
            margin-left: 260px;
        }
        .app-unified-footer.no-sidebar {
            margin-left: 0;
        }
        @media (max-width: 992px) {
            .app-unified-footer,
            .app-unified-footer.has-sidebar {
                margin-left: 0 !important;
                padding: 18px 12px;
            }
        }
        .footer-center-box {
            max-width: 960px;
            margin: 0 auto;
            text-align: center;
        }
        .footer-brand-title {
            font-weight: 700;
            color: #34483B;
            font-size: 0.95rem;
            letter-spacing: -0.3px;
        }
        .footer-brand-title .logo-leaf {
            font-size: 1.05rem;
        }
        .footer-separator {
            color: #D6CBB5;
        }
        .footer-copyright-text {
            color: #6C757D;
            font-size: 0.8rem;
        }
        .footer-links-row {
            margin-top: 4px;
        }
        .footer-nav-link {
            color: #789078;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .footer-nav-link:hover {
            color: #34483B;
            text-decoration: underline;
        }
        .footer-dot {
            color: #D6CBB5;
            font-size: 0.75rem;
        }
    </style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="<?= defined('APP_URL') ? APP_URL : '' ?>/assets/js/app.js"></script>
</body>
</html>
