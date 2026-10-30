<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';

// Ambil data pengaturan lembaga secara dinamis dari database (id_pengaturan = 1)
$pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch(PDO::FETCH_ASSOC);
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Mari Belajar';
$logo_lembaga = $pengaturan ? $pengaturan['logo_path'] : 'uploads/default_logo.png';
$alamat_lembaga = $pengaturan ? $pengaturan['alamat_lembaga'] : 'Alamat belum diatur';
$no_wa_lembaga = $pengaturan ? $pengaturan['no_wa_lembaga'] : '-';
$email_lembaga = ($pengaturan && isset($pengaturan['email_lembaga'])) ? $pengaturan['email_lembaga'] : 'info@lembaga.com';
$hero_image = ($pengaturan && !empty($pengaturan['hero_image'])) ? $pengaturan['hero_image'] : 'uploads/default_hero.png';

// Pisahkan nama lembaga untuk efek styling visual
$parts = explode(' ', trim($nama_lembaga));
$brand_first = isset($parts[0]) ? $parts[0] : 'MARI';
$brand_rest = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : 'BELAJAR';

$pesan = "";
$pesan_tipe = "";

// Handle Pendaftaran Siswa (Menggunakan Session Notifikasi selaras dengan backend)
if (isset($_POST['aksi_daftar'])) {
    $nama = $_POST['nama'];
    $jk = $_POST['jk'];
    $usia = $_POST['usia'];
    $telp = $_POST['telp'];
    $kelas = $_POST['kelas'];
    $alamat = $_POST['alamat'];
    
    $stmt = $pdo->prepare("INSERT INTO siswa (nama_lengkap, jenis_kelamin, usia, no_wa, kelas, alamat, nisn, tgl_lahir, foto_path) VALUES (?,?,?,?,?,?,?,?,?)");
    $exec = $stmt->execute([$nama, $jk, $usia, $telp, $kelas, $alamat, 'NISN-' . rand(1000,9999), '2005-01-01', 'uploads/default.jpg']);
    
    if ($exec) {
        $_SESSION['notif'] = "Pendaftaran berhasil dikirim! Data Anda telah tersimpan di sistem.";
        $_SESSION['notif_tipe'] = "success";
    } else {
        $_SESSION['notif'] = "Gagal mengirim pendaftaran. Silakan coba lagi.";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: index.php#daftar");
    exit;
}

// Ambil session notifikasi jika ada
if (isset($_SESSION['notif'])) {
    $pesan = $_SESSION['notif'];
    $pesan_tipe = $_SESSION['notif_tipe'];
    unset($_SESSION['notif']);
    unset($_SESSION['notif_tipe']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nama_lembaga); ?> | Les Komputer Terpercaya</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-blue: #0f2d7d; --bright-blue: #2351d1; --accent-yellow: #fcc100; --light-gray: #f8f9fa; }
        body { font-family: 'Poppins', sans-serif; background-color: #f1f5f9; color: #333; overflow-x: hidden; }
        .navbar { padding: 20px 0; background: #0a1941; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .navbar-brand { font-family: 'Montserrat', sans-serif; font-weight: 800; color: white !important; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .navbar-brand span { color: var(--accent-yellow); }
        
        /* Hero Section yang Responsif untuk HP & Desktop */
        .hero { background: radial-gradient(circle at top right, #1e4bd1, #0a1941); padding: 80px 0 50px 0; color: white; min-height: 70vh; display: flex; align-items: center; }
        .hero h1 { font-family: 'Montserrat', sans-serif; font-weight: 800; font-size: 2.3rem; line-height: 1.2; margin-bottom: 20px; }
        .hero h1 span { color: var(--accent-yellow); }

        @media (min-width: 992px) {
            .hero { padding: 120px 0 60px 0; min-height: 80vh; }
            .hero h1 { font-size: 3.5rem; }
        }
        
        .program-section { padding: 80px 0; }
        
        .card-3d {
            border: none;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card-3d:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.12), 0 10px 15px -5px rgba(0, 0, 0, 0.08);
        }

        .program-card { border: none; border-radius: 20px; overflow: hidden; background: #fff; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; height: 100%; }
        .program-card:hover { transform: translateY(-5px); box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.12); }
        
        .img-wrapper { height: 200px; overflow: hidden; position: relative; }
        .img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .program-badge { position: absolute; top: 15px; left: 15px; background: var(--accent-yellow); color: #000; padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; z-index: 2; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .stats-section { padding: 60px 0; background: #f1f5f9; }
        .stat-card { background: white; border-radius: 20px; padding: 30px 20px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card i { font-size: 2.5rem; color: var(--bright-blue); display: block; margin-bottom: 15px; }
        
        .reg-section { padding: 80px 0; }
        .reg-card { border: none; border-radius: 25px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); background: #fff; padding: 40px; }
        
        .form-label { font-weight: 600; color: var(--primary-blue); }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #cbd5e1; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(35, 81, 209, 0.15); border-color: var(--bright-blue); }

        .btn-glow { background: linear-gradient(45deg, #fcc100, #ff9900); color: #000 !important; font-weight: 800; padding: 12px 30px; border-radius: 50px; border: none; box-shadow: 0 6px 15px rgba(252, 193, 0, 0.4); text-transform: uppercase; text-decoration: none; display: inline-block; transition: all 0.2s ease; }
        .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(252, 193, 0, 0.6); }
        .btn-glow:active { transform: translateY(1px); }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1080; }

        /* Floating WhatsApp Button */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50px;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            z-index: 1040;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .whatsapp-float:hover {
            background-color: #20ba5a;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
        }
    </style>
</head>
<body>

    <!-- Bootstrap Toast Notifikasi (Selaras dengan Backend) -->
    <?php if ($pesan !== ""): ?>
    <div class="toast-container">
        <div id="liveToast" class="toast align-items-center text-white bg-<?php echo ($pesan_tipe == 'warning') ? 'warning text-dark' : $pesan_tipe; ?> border-0 shadow-lg show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-semibold">
                    <i class="bi <?php echo ($pesan_tipe == 'success') ? 'bi-check-circle-fill' : (($pesan_tipe == 'warning') ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'); ?> me-2"></i>
                    <?php echo $pesan; ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg sticky-top navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="<?php echo htmlspecialchars($logo_lembaga); ?>" alt="Logo" width="40" height="40" class="rounded-circle border border-warning shadow-sm" style="object-fit: cover;">
                <div><?php echo htmlspecialchars($nama_lembaga); ?></div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="index.php">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="#program">Program</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3" href="#daftar">Daftar</a>
                    </li>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <?php if (isset($_SESSION['login'])): ?>
                            <div class="dropdown">
                                <button class="btn btn-warning fw-bold dropdown-toggle text-dark px-4 py-2 rounded-pill shadow-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle"></i> Admin Panel
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-4">
                                    <li><a class="dropdown-item py-2" href="admin_daftar.php"><i class="bi bi-people me-2"></i> Data Pendaftar</a></li>
                                    <li><a class="dropdown-item py-2" href="admin_program.php"><i class="bi bi-journal-code me-2"></i> Kelola Program</a></li>
                                    <li><a class="dropdown-item py-2" href="admin_pengaturan.php"><i class="bi bi-gear me-2"></i> Pengaturan Lembaga</a></li>
                                    <li><a class="dropdown-item py-2" href="admin_admin.php"><i class="bi bi-shield-lock me-2"></i> Manajemen Admin</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a class="btn btn-warning fw-bold text-dark px-4 py-2 rounded-pill shadow-sm" href="login.php">
                                <i class="bi bi-person-circle"></i> Admin Panel
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header Hero Section -->
    <header class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 pb-4 pb-lg-5 text-center text-lg-start">
                    <h1>Mulai Perjalanan <span>Belajarmu</span> Bersama <?php echo htmlspecialchars($nama_lembaga); ?>!</h1>
                    <p class="lead opacity-75 mb-4">Kembangkan keterampilan digitalmu bersama instruktur berpengalaman. Kurikulum berbasis industri.</p>
                    <a href="#daftar" class="btn btn-glow">Daftar Sekarang</a>
                </div>
                <div class="col-lg-5 text-center mt-4 mt-lg-0">
                    <img src="<?php echo htmlspecialchars($hero_image); ?>" alt="Hero Illustration" class="img-fluid drop-shadow" style="max-height: 680px; object-fit: contain; filter: drop-shadow(0 15px 25px rgba(0,0,0,0.3));">
                </div>
            </div>
        </div>
    </header>

    <section class="stats-section">
        <div class="container">
            <div class="text-center mb-5"><h4 class="fw-bold text-primary">MENGAPA MEMILIH KAMI?</h4></div>
            <div class="row g-4 text-center">
                <div class="col-md-4"><div class="stat-card"><i class="bi bi-patch-check-fill"></i><h5 class="fw-bold">Sertifikat</h5><p class="text-muted small mb-0">Dapatkan sertifikat keahlian.</p></div></div>
                <div class="col-md-4"><div class="stat-card"><i class="bi bi-clock-history"></i><h5 class="fw-bold">Sesuai Kebutuhan Kerja</h5><p class="text-muted small mb-0">Materi dirancang langsung untuk dunia kerja.</p></div></div>
                <div class="col-md-4"><div class="stat-card"><i class="bi bi-people-fill"></i><h5 class="fw-bold">Interaktif & Menyenangkan</h5><p class="text-muted small mb-0">Pembelajaran fleksibel dengan mentor.</p></div></div>
            </div>
        </div>
    </section>

    <section class="program-section" id="program">
        <div class="container">
            <div class="text-center mb-5"><h2 class="display-5 fw-bold">Program Unggulan</h2><div class="mx-auto" style="width: 60px; height: 5px; background: var(--accent-yellow); border-radius: 10px;"></div></div>
            <div class="row g-4 justify-content-center">
                <?php 
                $programs = $pdo->query("SELECT * FROM program")->fetchAll(PDO::FETCH_ASSOC);
                foreach($programs as $prog): 
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="program-card">
                        <div class="img-wrapper"><img src="<?php echo htmlspecialchars($prog['gambar']); ?>" alt="Program"><div class="program-badge"><?php echo htmlspecialchars($prog['kategori']); ?></div></div>
                        <div class="p-4"><h5 class="fw-bold"><?php echo htmlspecialchars($prog['nama_program']); ?></h5><p class="small text-muted"><?php echo htmlspecialchars($prog['deskripsi']); ?></p></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="reg-section bg-light" id="daftar">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="reg-card">
                        <div class="text-center mb-4"><h2 class="fw-bold text-primary">Form Pendaftaran Kursus</h2><p class="text-muted">Isi data diri di bawah untuk langsung mendaftar ke sistem database.</p></div>
                        <form method="POST">
                            <input type="hidden" name="aksi_daftar" value="1">
                            <div class="row g-3">
                                <div class="col-md-12"><label class="form-label">Nama Lengkap</label><input type="text" name="nama" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label">Jenis Kelamin</label><select name="jk" class="form-select" required><option value="">-- Pilih --</option><option value="Laki-laki">Laki-laki</option><option value="Perempuan">Perempuan</option></select></div>
                                <div class="col-md-6"><label class="form-label">Usia</label><input type="number" name="usia" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label">Nomor WhatsApp</label><input type="tel" name="telp" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label">Pilih Program</label><select name="kelas" class="form-select" required><option value="">-- Pilih Kelas --</option><?php foreach($programs as $p): ?><option value="<?php echo htmlspecialchars($p['nama_program']); ?>"><?php echo htmlspecialchars($p['nama_program']); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-12"><label class="form-label">Alamat Lengkap</label><textarea name="alamat" class="form-control" rows="3" required></textarea></div>
                                <div class="col-md-12 mt-4"><button type="submit" class="btn btn-glow w-100 py-3">KIRIM PENDAFTARAN</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer dengan Informasi Sinkron dari Pengaturan -->
    <footer class="py-5 bg-dark text-white border-top">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <h5 class="fw-bold text-warning mb-2"><?php echo htmlspecialchars($nama_lembaga); ?></h5>
                    <p class="small text-white-50 mb-1"><i class="bi bi-geo-alt-fill me-2"></i><?php echo nl2br(htmlspecialchars($alamat_lembaga)); ?></p>
                    <p class="small text-white-50 mb-1"><i class="bi bi-whatsapp me-2"></i><?php echo htmlspecialchars($no_wa_lembaga); ?></p>
                    <p class="small text-white-50 mb-0"><i class="bi bi-envelope-fill me-2"></i><?php echo htmlspecialchars($email_lembaga); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-white-50 mb-0 small">&copy; 2026 <?php echo htmlspecialchars($nama_lembaga); ?>. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Tombol Mengambang WhatsApp Sinkron Pengaturan -->
    <?php
    $clean_wa = preg_replace('/[^0-9]/', '', $no_wa_lembaga);
    if (substr($clean_wa, 0, 1) == '0') {
        $clean_wa = '62' . substr($clean_wa, 1);
    }
    $wa_message = urlencode("Halo admin " . $nama_lembaga . ", saya ingin bertanya seputar program kursus yang tersedia.");
    ?>
    <a href="https://wa.me/<?php echo $clean_wa; ?>?text=<?php echo $wa_message; ?>" class="whatsapp-float" target="_blank" title="Chat WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            let toastEl = document.getElementById('liveToast');
            if (toastEl) {
                let toast = new bootstrap.Toast(toastEl, { delay: 4000 });
                toast.show();
            }
        });
    </script>
</body>
</html>