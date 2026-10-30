<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// 1. KONFIGURASI DATABASE (Disamakan dengan panel admin: db_les_pro)
$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db_name = "db_les_pro";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto-sync tabel pengaturan lembaga jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
        id_pengaturan INT AUTO_INCREMENT PRIMARY KEY,
        nama_lembaga VARCHAR(150) DEFAULT 'Mari Belajar',
        logo_path VARCHAR(255) DEFAULT 'uploads/default_logo.png'
    )");

    // Auto-sync tabel admin (disesuaikan dengan panel admin)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id_admin INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE,
        password VARCHAR(255)
    )");

    $check_set = $pdo->query("SELECT COUNT(*) FROM pengaturan")->fetchColumn();
    if ($check_set == 0) {
        $pdo->exec("INSERT INTO pengaturan (nama_lembaga, logo_path) VALUES ('Mari Belajar', 'uploads/default_logo.png')");
    }
} catch (PDOException $e) { 
    die("Koneksi Database Gagal! Pastikan database '$db_name' sudah dibuat."); 
}

// Ambil Data Pengaturan Lembaga dari tabel pengaturan
$setting_app = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch();
$logo_app = (!empty($setting_app['logo_path']) && file_exists($setting_app['logo_path'])) ? $setting_app['logo_path'] : '';
$wa_admin = '6285776821436'; // Default WhatsApp Admin untuk kode verifikasi

function e($string) { return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }

// 2. PROSES LOGIN & REGISTER
$error = "";
$success = "";
$login_success = false;
$active_form = 'login'; // 'login' atau 'register'

if (isset($_POST['login_btn'])) {
    $active_form = 'login';
    $username = trim($_POST['user']);
    $password = $_POST['pass'];

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // Verifikasi password (mendukung teks biasa sesuai struktur panel admin)
    if ($admin && ($password == $admin['password'] || password_verify($password, $admin['password']))) {
        // Sinkronisasi session agar sesuai dengan pengecekan di seluruh file admin panel
        $_SESSION['login'] = true;
        $_SESSION['id_admin'] = $admin['id_admin'];
        $_SESSION['username'] = $admin['username'];
        $login_success = true;
    } else {
        $error = "Username atau Password salah! Periksa kembali inputan Anda.";
    }
}

if (isset($_POST['register_btn'])) {
    $active_form = 'register';
    $nama_lengkap = trim($_POST['reg_nama']); 
    $username     = trim($_POST['reg_user']);
    $password     = $_POST['reg_pass'];
    $kode_verif   = trim($_POST['reg_kode']);

    $valid_kode = "DIGIDIGISTAR3012"; 

    if (empty($username) || empty($password) || empty($kode_verif)) {
        $error = "Semua kolom pendaftaran wajib diisi!";
    } elseif ($kode_verif !== $valid_kode) {
        $error = "Kode verifikasi WhatsApp dari admin salah/tidak valid!";
    } else {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetchColumn() > 0) {
            $error = "Username sudah tergunakan, pilih username lain!";
        } else {
            // Masukkan ke tabel admin utama sistem
            $ins = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
            if ($ins->execute([$username, $password])) {
                $success = "Akun berhasil didaftarkan! Silakan masuk.";
                $active_form = 'login';
            } else {
                $error = "Gagal mendaftarkan akun.";
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
    <title>Portal Akses | <?= e($setting_app['nama_lembaga']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #0f2d7d;
            --bright-blue: #2351d1;
            --accent-yellow: #fcc100;
            --dark-bg: #0a1941;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top right, #1e4bd1, #0a1941);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow-x: hidden;
            color: #333;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            margin: 20px;
            transition: all 0.4s ease;
        }

        @media (max-width: 850px) {
            .login-container {
                flex-direction: column;
                max-width: 480px;
            }
        }

        .info-panel {
            flex: 1.1;
            background: linear-gradient(135deg, #0a1941 0%, #1e293b 100%);
            padding: 50px;
            color: #f8fafc;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-panel h3 {
            margin: 0 0 16px 0;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--accent-yellow);
            font-family: 'Montserrat', sans-serif;
        }

        .info-panel p {
            margin: 0 0 24px 0;
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.7;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
        }

        .info-list li {
            font-size: 13px;
            color: #e2e8f0;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            line-height: 1.5;
        }

        .info-list li i {
            color: var(--accent-yellow);
            font-size: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .wa-box {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 16px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .wa-box h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            color: #4ade80;
        }

        .wa-box p {
            margin: 0;
            font-size: 11px;
            color: #94a3b8;
        }

        .btn-wa {
            background: #25d366;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: 0.3s;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }

        .btn-wa:hover {
            background: #20ba5a;
            transform: translateY(-2px);
        }

        .form-panel {
            flex: 1;
            padding: 45px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            position: relative;
        }

        .form-section {
            display: none;
        }

        .form-section.active-section {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--accent-yellow);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            font-weight: 800;
            color: var(--primary-blue);
            letter-spacing: -0.5px;
            font-size: 20px;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i:not(.toggle-password) {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 19px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 19px;
            cursor: pointer;
            transition: 0.2s;
        }

        .toggle-password:hover {
            color: var(--bright-blue);
        }

        input {
            width: 100%;
            padding: 12px 42px 12px 45px;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            transition: 0.3s;
            box-sizing: border-box;
            background: #f8fafc;
        }

        input:focus {
            outline: none;
            border-color: var(--bright-blue);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(35, 81, 209, 0.15);
        }

        /* Tombol Mengikuti Konsep Halaman Index (.btn-glow) */
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(45deg, #fcc100, #ff9900);
            color: #000;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 6px 15px rgba(252, 193, 0, 0.4);
            text-transform: uppercase;
            margin-top: 5px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(252, 193, 0, 0.6);
        }

        .alert {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 18px;
            border: 1px solid #fecaca;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .success-alert {
            background: #dcfce7;
            color: #16a34a;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 18px;
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .switch-form {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
        }

        .switch-form a {
            color: var(--bright-blue);
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .switch-form a:hover {
            text-decoration: underline;
        }

        #loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(10, 25, 65, 0.96);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            z-index: 9999; opacity: 0; visibility: hidden; transition: 0.4s ease;
        }
        #loading-overlay.active { opacity: 1; visibility: visible; }
        .spinner {
            width: 60px; height: 60px; border: 5px solid rgba(255, 255, 255, 0.1);
            border-top: 5px solid var(--accent-yellow); border-radius: 50%;
            animation: spin 0.8s linear infinite; margin-bottom: 20px;
        }
        .loading-text { color: #ffffff; font-weight: 700; font-size: 18px; }
        .loading-subtext { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div id="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Autentikasi Berhasil...</div>
    <div class="loading-subtext">Menyiapkan halaman utama panel, mohon tunggu</div>
</div>

<div class="login-container" id="loginContainer">
    
    <div class="info-panel">
        <h3><i class='bx bx-info-circle'></i> Informasi Akses</h3>
        <p>Akses masuk manajemen sistem panel admin. Pendaftaran akun baru memerlukan izin dan verifikasi kode khusus dari administrator.</p>
        <ul class="info-list">
            <li>
                <i class='bx bx-check-shield'></i>
                <span>Sistem database terintegrasi dan aman.</span>
            </li>
            <li>
                <i class='bx bx-key'></i>
                <span>Ingin membuat akun baru? Klik tombol WhatsApp di bawah untuk meminta **Kode Verifikasi**.</span>
            </li>
        </ul>

        <div class="wa-box">
            <div>
                <h4>Butuh Kode Verifikasi?</h4>
                <p>Minta akses pendaftaran akun via WhatsApp Admin.</p>
            </div>
            <a href="https://wa.me/<?= $wa_admin ?>?text=Halo%20Admin,%20saya%20ingin%20meminta%20kode%20verifikasi%20untuk%20pendaftaran%20akun%20baru%20di%20Panel%20Admin." target="_blank" class="btn-wa">
                <i class='bx bxl-whatsapp' style="font-size: 18px;"></i> Hubungi WA
            </a>
        </div>
    </div>

    <div class="form-panel">
        
        <div id="sectionLogin" class="form-section <?= ($active_form == 'login') ? 'active-section' : '' ?>">
            <div class="header">
                <?php if (!empty($logo_app)): ?>
                    <img src="<?= htmlspecialchars($logo_app) ?>" alt="Logo">
                <?php else: ?>
                    <i class='bx bxs-graduation' style="font-size: 60px; color: var(--primary-blue);"></i>
                <?php endif; ?>
                <h2><?= e($setting_app['nama_lembaga']) ?></h2>
                <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Silakan Masuk ke Panel Admin</p>
            </div>

            <?php if ($error && $active_form == 'login'): ?>
                <div class="alert"><i class='bx bx-error-circle' style="font-size: 18px;"></i> <span><?= e($error) ?></span></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-alert"><i class='bx bx-check-circle' style="font-size: 18px;"></i> <span><?= e($success) ?></span></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class='bx bx-user'></i>
                        <input type="text" name="user" placeholder="Masukkan username" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class='bx bx-lock-alt'></i>
                        <input type="password" name="pass" id="passLogin" placeholder="••••••••" required>
                        <i class='bx bx-hide toggle-password' onclick="togglePass('passLogin', this)"></i>
                    </div>
                </div>

                <button type="submit" name="login_btn" class="btn-submit">Masuk Sekarang</button>
            </form>

            <div class="switch-form">
                Belum punya akun? <a onclick="switchSection('register')">Daftar Akun Baru</a>
            </div>
        </div>

        <div id="sectionRegister" class="form-section <?= ($active_form == 'register') ? 'active-section' : '' ?>">
            <div class="header">
                <i class='bx bx-user-plus' style="font-size: 55px; color: var(--primary-blue); margin-bottom: 5px;"></i>
                <h2>Pendaftaran Akun</h2>
                <p style="color: #64748b; font-size: 13px; margin-top: 4px;">Isi data diri & masukkan kode verifikasi WA</p>
            </div>

            <?php if ($error && $active_form == 'register'): ?>
                <div class="alert"><i class='bx bx-error-circle' style="font-size: 18px;"></i> <span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Nama Lengkap</label>
                    <div class="input-wrapper">
                        <i class='bx bx-id-card'></i>
                        <input type="text" name="reg_nama" placeholder="Nama lengkap Anda" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Username Baru</label>
                    <div class="input-wrapper">
                        <i class='bx bx-user'></i>
                        <input type="text" name="reg_user" placeholder="Buat username" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label>Password Baru</label>
                    <div class="input-wrapper">
                        <i class='bx bx-lock-alt'></i>
                        <input type="password" name="reg_pass" id="passReg" placeholder="••••••••" required>
                        <i class='bx bx-hide toggle-password' onclick="togglePass('passReg', this)"></i>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="color: #16a34a;">Kode Verifikasi WhatsApp Admin</label>
                    <div class="input-wrapper">
                        <i class='bx bx-key' style="color: #16a34a;"></i>
                        <input type="text" name="reg_kode" placeholder="Masukkan kode dari WA Admin" required style="border-color: #bbf7d0;">
                    </div>
                </div>

                <button type="submit" name="register_btn" class="btn-submit">Daftar Akun Sekarang</button>
            </form>

            <div class="switch-form">
                Sudah punya akun? <a onclick="switchSection('login')">Masuk di sini</a>
            </div>
        </div>

    </div>

</div>

<script>
    function togglePass(fieldId, icon) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('bx-hide');
            icon.classList.add('bx-show');
        } else {
            field.type = 'password';
            icon.classList.remove('bx-show');
            icon.classList.add('bx-hide');
        }
    }

    function switchSection(target) {
        const secLogin = document.getElementById('sectionLogin');
        const secRegister = document.getElementById('sectionRegister');

        if(target === 'register') {
            secLogin.classList.remove('active-section');
            secRegister.classList.add('active-section');
        } else {
            secRegister.classList.remove('active-section');
            secLogin.classList.add('active-section');
        }
    }

    <?php if ($login_success): ?>
    window.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('loading-overlay');
        const container = document.getElementById('loginContainer');
        
        container.style.transform = 'scale(0.95)';
        container.style.opacity = '0.4';
        overlay.classList.add('active');

        setTimeout(() => {
            window.location.href = 'admin_daftar.php'; 
        }, 2000);
    });
    <?php endif; ?>
</script>

</body>
</html>