<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('../admin/dashboard.php');
    } else {
        redirect('../user/dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Server-side validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok.';
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar. Silakan gunakan email lain.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, 'user')");
            $result = $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashed_password
            ]);

            if ($result) {
                $user_id = $pdo->lastInsertId();

                logActivity($pdo, $user_id, 'Registrasi akun baru');

                $success = 'Registrasi berhasil! Silakan masuk dengan akun baru Anda.';
            } else {
                $error = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - TerraWatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #34483B 0%, #34483B 40%, #789078 80%, #B5C2AA 100%);
            background-size: cover;
            position: relative;
            padding: 20px 0;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.03)" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,218.7C672,235,768,245,864,234.7C960,224,1056,192,1152,181.3C1248,171,1344,181,1392,186.7L1440,192L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            pointer-events: none;
        }
        .register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px 35px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 25px;
        }
        .logo-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .logo-brand .logo-leaf {
            font-size: 34px;
            line-height: 1;
        }
        .logo-brand .logo-name {
            font-size: 28px;
            font-weight: 700;
            color: #34483B;
            letter-spacing: -0.5px;
        }
        .tagline {
            font-size: 12px;
            color: #789078;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 4px;
            font-weight: 600;
        }
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .sdg-badge {
            display: inline-block;
            background: linear-gradient(135deg, #789078, #34483B);
            color: white;
            font-size: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-top: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .section-title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 6px;
            display: block;
        }
        .input-group-custom {
            position: relative;
        }
        .input-group-custom i.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #98A2B3;
            font-size: 14px;
            z-index: 2;
        }
        .input-group-custom input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #D6CBB5;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #F6F6F0;
        }
        .input-group-custom input:focus {
            outline: none;
            border-color: #789078;
            box-shadow: 0 0 0 3px rgba(120, 144, 120, 0.15);
            background: #fff;
        }
        .input-group-custom input.is-invalid {
            border-color: #C17B58;
        }
        .invalid-feedback {
            font-size: 12px;
            color: #C17B58;
            margin-top: 4px;
        }
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #34483B, #789078);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #34483B, #789078);
            box-shadow: 0 4px 15px rgba(52, 72, 59, 0.4);
            transform: translateY(-1px);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #667085;
        }
        .login-link a {
            color: #34483B;
            font-weight: 600;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #98A2B3;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="logo-section">
            <div class="logo-brand">
                <span class="logo-leaf">🌿</span>
                <span class="logo-name">TerraWatch</span>
            </div>
            <div class="tagline">Observe, Report, Protect</div>
            <div class="sdg-badge mt-2">
                <i class="fas fa-globe-americas me-1"></i> SDG 15: Life on Land
            </div>
        </div>

        <div class="section-title">Buat Akun Baru</div>

        <form method="POST" action="" id="registerForm" novalidate>
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <div class="input-group-custom">
                    <i class="fas fa-user field-icon"></i>
                    <input type="text" id="name" name="name" placeholder="Masukkan nama lengkap" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group-custom">
                    <i class="fas fa-envelope field-icon"></i>
                    <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
                    <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-shield-alt field-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
                    <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </span>
                </div>
                <div class="invalid-feedback" id="passwordMismatch" style="display:none;">
                    Password tidak cocok.
                </div>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus me-1"></i> Daftar
            </button>
        </form>

        <div class="login-link">
            Sudah punya akun? <a href="login.php">Masuk</a>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Client-side validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const mismatchDiv = document.getElementById('passwordMismatch');

            if (password.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Password Terlalu Pendek',
                    text: 'Password harus minimal 6 karakter.',
                    confirmButtonColor: '#34483B'
                });
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                mismatchDiv.style.display = 'block';
                document.getElementById('confirm_password').classList.add('is-invalid');
                Swal.fire({
                    icon: 'warning',
                    title: 'Password Tidak Cocok',
                    text: 'Password dan konfirmasi password harus sama.',
                    confirmButtonColor: '#34483B'
                });
                return;
            }

            mismatchDiv.style.display = 'none';
            document.getElementById('confirm_password').classList.remove('is-invalid');
        });

        <?php if (!empty($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Mendaftar',
            text: '<?= addslashes($error) ?>',
            confirmButtonColor: '#34483B'
        });
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Registrasi Berhasil!',
            text: '<?= addslashes($success) ?>',
            confirmButtonColor: '#34483B',
            confirmButtonText: 'Masuk Sekarang',
            timer: 2500,
            showConfirmButton: true
        }).then(() => {
            window.location.href = 'login.php';
        });
        <?php endif; ?>
    </script>
</body>
</html>
