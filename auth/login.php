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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Tolak login jika akun dinonaktifkan
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $error = 'Akun Anda dinonaktifkan. Hubungi administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_photo'] = $user['profile_photo'];

                logActivity($pdo, $user['user_id'], 'Login berhasil');

                if ($user['role'] === 'admin') {
                    redirect('../admin/dashboard.php');
                } else {
                    redirect('../user/dashboard.php');
                }
            }
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - TerraWatch</title>
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
        .login-card {
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
        .form-group {
            margin-bottom: 20px;
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
        .input-group-custom i {
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
        .btn-login {
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
        .btn-login:hover {
            background: linear-gradient(135deg, #34483B, #789078);
            box-shadow: 0 4px 15px rgba(52, 72, 59, 0.4);
            transform: translateY(-1px);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #667085;
        }
        .register-link a {
            color: #34483B;
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover {
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
    <div class="login-card">
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

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group-custom">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group-custom">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-1"></i> Masuk
            </button>
        </form>

        <div class="register-link">
            Belum punya akun? <a href="register.php">Daftar Sekarang</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        <?php if (!empty($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Masuk',
            text: '<?= addslashes($error) ?>',
            confirmButtonColor: '#34483B'
        });
        <?php endif; ?>
    </script>
</body>
</html>
