<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

if (isset($pdo)) {
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
}

// Otomatis buat kolom status verifikasi & is_read jika belum ada
try {
    $cek_kolom = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'status_verifikasi'");
    if (!$cek_kolom || !$cek_kolom->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE siswa ADD COLUMN status_verifikasi VARCHAR(20) DEFAULT 'Pending'");
    }
    $cek_kolom_read = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'is_read'");
    if (!$cek_kolom_read || !$cek_kolom_read->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE siswa ADD COLUMN is_read INT DEFAULT 0");
    }
} catch (PDOException $e) {}

$pesan = ""; $pesan_tipe = "";

$stmt_pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1");
$pengaturan = $stmt_pengaturan ? $stmt_pengaturan->fetch(PDO::FETCH_ASSOC) : false;
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Mari Belajar';
$logo_lembaga = $pengaturan ? $pengaturan['logo_path'] : 'uploads/default_logo.png';
$alamat_lembaga = $pengaturan ? $pengaturan['alamat_lembaga'] : '-';
$telp_lembaga = $pengaturan ? $pengaturan['telp_lembaga'] : '-';

function formatNoWA($no) {
    $no = preg_replace('/[^0-9]/', '', $no);
    if (substr($no, 0, 1) === '0') { $no = '62' . substr($no, 1); }
    return $no;
}

// Logika Klik Notifikasi Lonceng
if (isset($_GET['read_notif'])) {
    $id_read = (int)$_GET['read_notif'];
    $pdo->prepare("UPDATE siswa SET is_read = 1 WHERE id_siswa = ?")->execute([$id_read]);
    header("Location: admin_daftar.php"); exit;
}

// Ambil data notifikasinya
$stmt_notif = $pdo->query("SELECT * FROM siswa WHERE is_read = 0 ORDER BY id_siswa DESC LIMIT 10");
$notif_pendaftar = $stmt_notif ? $stmt_notif->fetchAll(PDO::FETCH_ASSOC) : [];
$jumlah_pendaftar_baru = count($notif_pendaftar);

// Logika Verifikasi
if (isset($_GET['verifikasi'])) {
    $pdo->prepare("UPDATE siswa SET status_verifikasi = 'Terverifikasi' WHERE id_siswa = ?")->execute([(int)$_GET['verifikasi']]);
    $_SESSION['notif'] = "Data pendaftar berhasil diverifikasi!"; $_SESSION['notif_tipe'] = "success";
    header("Location: admin_daftar.php"); exit;
}

// Logika Batal Verifikasi
if (isset($_GET['batal_verifikasi'])) {
    $pdo->prepare("UPDATE siswa SET status_verifikasi = 'Pending' WHERE id_siswa = ?")->execute([(int)$_GET['batal_verifikasi']]);
    $_SESSION['notif'] = "Status verifikasi berhasil dibatalkan!"; $_SESSION['notif_tipe'] = "warning";
    header("Location: admin_daftar.php"); exit;
}

// Logika Hapus
if (isset($_GET['hapus'])) {
    $stmt = $pdo->prepare("SELECT foto_path FROM siswa WHERE id_siswa = ?");
    $stmt->execute([$_GET['hapus']]);
    $row_f = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row_f && file_exists($row_f['foto_path']) && $row_f['foto_path'] != 'uploads/default.jpg') { @unlink($row_f['foto_path']); }
    $pdo->prepare("DELETE FROM siswa WHERE id_siswa = ?")->execute([$_GET['hapus']]);
    $_SESSION['notif'] = "Data pendaftar berhasil dihapus!"; $_SESSION['notif_tipe'] = "warning";
    header("Location: admin_daftar.php"); exit;
}

// Logika Update Siswa
if (isset($_POST['update_siswa'])) {
    $id = $_POST['id_siswa'];
    $nama = $_POST['nama_lengkap']; $jk = $_POST['jenis_kelamin'];
    $usia = $_POST['usia']; $no_wa = $_POST['no_wa'];
    $kelas = $_POST['kelas']; $alamat = $_POST['alamat'];
    
    $stmt_lama = $pdo->prepare("SELECT foto_path FROM siswa WHERE id_siswa = ?");
    $stmt_lama->execute([$id]);
    $foto_path = $stmt_lama->fetch(PDO::FETCH_ASSOC)['foto_path'];

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $destination = 'uploads/siswa_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                if ($foto_path != 'uploads/default.jpg' && file_exists($foto_path)) { @unlink($foto_path); }
                $foto_path = $destination;
            }
        }
    }
    $pdo->prepare("UPDATE siswa SET nama_lengkap = ?, jenis_kelamin = ?, usia = ?, no_wa = ?, kelas = ?, alamat = ?, foto_path = ? WHERE id_siswa = ?")->execute([$nama, $jk, $usia, $no_wa, $kelas, $alamat, $foto_path, $id]);
    $_SESSION['notif'] = "Data pendaftar berhasil diperbarui!"; $_SESSION['notif_tipe'] = "success";
    header("Location: admin_daftar.php"); exit;
}

if (isset($_SESSION['notif'])) {
    $pesan = $_SESSION['notif']; $pesan_tipe = $_SESSION['notif_tipe'];
    unset($_SESSION['notif']); unset($_SESSION['notif_tipe']);
}

// Statistik
$total_pendaftar = $pdo->query("SELECT COUNT(*) FROM siswa WHERE is_read = 1")->fetchColumn();
$total_terverifikasi = $pdo->query("SELECT COUNT(*) FROM siswa WHERE is_read = 1 AND status_verifikasi = 'Terverifikasi'")->fetchColumn();
$total_pending = $pdo->query("SELECT COUNT(*) FROM siswa WHERE is_read = 1 AND (status_verifikasi != 'Terverifikasi' OR status_verifikasi IS NULL)")->fetchColumn();

// Pencarian & Pagination
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";

if ($cari != "") {
    $stmt_jml = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE is_read = 1 AND (nama_lengkap LIKE ? OR kelas LIKE ? OR no_wa LIKE ?)");
    $stmt_jml->execute(["%$cari%", "%$cari%", "%$cari%"]);
    $total_data = $stmt_jml->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE is_read = 1 AND (nama_lengkap LIKE ? OR kelas LIKE ? OR no_wa LIKE ?) LIMIT $start, $limit");
    $stmt->execute(["%$cari%", "%$cari%", "%$cari%"]);
    $pendaftar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $total_data = $total_pendaftar;
    $stmt_p = $pdo->query("SELECT * FROM siswa WHERE is_read = 1 ORDER BY id_siswa DESC LIMIT $start, $limit");
    $pendaftar = $stmt_p ? $stmt_p->fetchAll(PDO::FETCH_ASSOC) : [];
}
$total_halaman = ceil($total_data / $limit);

// Ambil daftar kelas untuk modal laporan
$daftar_kelas_lap = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE is_read = 1")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pendaftar & Laporan | Panel Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-blue: #0f2d7d;
            --bright-blue: #2351d1;
            --accent-yellow: #fcc100;
            --dark-bg: #0a1941;
        }

        body { 
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9; 
            color: #333;
        }

        /* Sidebar Tema Gelap & Elegan */
        .admin-sidebar { 
            background: linear-gradient(135deg, #0a1941 0%, #1e293b 100%); 
            color: white; 
            min-height: 100vh; 
            padding: 25px 20px; 
            box-shadow: 5px 0 15px rgba(0,0,0,0.1); 
        }
        
        .admin-sidebar h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
        }

        .admin-sidebar a { 
            color: #94a3b8; 
            display: flex;
            align-items: center;
            padding: 12px 18px; 
            text-decoration: none; 
            border-radius: 12px; 
            margin-bottom: 8px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            font-size: 13.5px;
        }

        .admin-sidebar a:hover, .admin-sidebar a.active { 
            background: linear-gradient(135deg, var(--bright-blue) 0%, var(--primary-blue) 100%); 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(35, 81, 209, 0.4); 
        }

        .card-3d { 
            border: none; 
            border-radius: 20px; 
            background: #ffffff; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03); 
        }

        .btn-3d { 
            border-radius: 10px; 
            font-weight: 600; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
            transition: 0.2s;
        }
        .btn-3d:hover {
            transform: translateY(-1px);
        }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1080; }
        
        h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-blue);
        }
    </style>
</head>
<body>

<?php if ($pesan !== ""): ?>
<div class="toast-container">
    <div id="liveToast" class="toast align-items-center text-white bg-<?php echo ($pesan_tipe == 'warning') ? 'warning text-dark' : $pesan_tipe; ?> border-0 shadow-lg show" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-info-circle-fill me-2"></i><?php echo $pesan; ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Navbar Mobile -->
<nav class="navbar navbar-dark bg-dark d-md-none px-3 py-2 shadow-sm" style="background: var(--dark-bg)!important;">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars($logo_lembaga); ?>" class="rounded-circle border border-warning" width="32" height="32" style="object-fit:cover;">
            <span class="navbar-brand fw-bold fs-6 text-truncate mb-0" style="max-width: 200px; font-family:'Montserrat',sans-serif;"><?php echo htmlspecialchars($nama_lembaga); ?></span>
        </div>
        <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
    </div>
</nav>

<!-- Offcanvas Sidebar Mobile -->
<div class="offcanvas offcanvas-start text-white" tabindex="-1" id="mobileSidebar" style="background: var(--dark-bg);">
    <div class="offcanvas-header border-bottom border-secondary">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars($logo_lembaga); ?>" class="rounded-circle border border-warning shadow-sm" width="35" height="35" style="object-fit: cover;">
            <h5 class="offcanvas-title fw-bold fs-6 text-truncate mb-0"><?php echo htmlspecialchars($nama_lembaga); ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body admin-sidebar min-vh-100">
        <a href="admin_daftar.php" class="active"><i class="bi bi-people me-2"></i> Data Pendaftar</a>
        <a href="admin_program.php"><i class="bi bi-journal-code me-2"></i> Kelola Program</a>
        <a href="admin_pengaturan.php"><i class="bi bi-gear me-2"></i> Pengaturan Lembaga</a>
        <a href="admin_admin.php"><i class="bi bi-shield-lock me-2"></i> Manajemen Admin</a>
        <a href="admin_monitor.php"><i class="bi bi-cpu me-2"></i> Monitoring Sistem</a>
        <a href="index.php" target="_blank"><i class="bi bi-house me-2"></i> Lihat Website</a>
        <a href="logout.php" class="text-danger mt-4"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Desktop -->
        <div class="col-md-3 admin-sidebar p-4 d-none d-md-block">
            <div class="text-center mb-4">
                <img src="<?php echo htmlspecialchars($logo_lembaga); ?>" class="rounded-circle border border-warning shadow-sm mb-2" width="70" height="70" style="object-fit: cover;">
                <h5 class="fw-bold text-truncate mb-0 text-white"><?php echo htmlspecialchars($nama_lembaga); ?></h5>
                <small class="text-warning" style="font-size: 11px; letter-spacing: 0.5px;">Panel Administrasi</small>
            </div>
            <hr class="border-secondary opacity-50">
            <a href="admin_daftar.php" class="active"><i class="bi bi-people me-2 fs-5"></i> Data Pendaftar</a>
            <a href="admin_program.php"><i class="bi bi-journal-code me-2 fs-5"></i> Kelola Program</a>
            <a href="admin_pengaturan.php"><i class="bi bi-gear me-2 fs-5"></i> Pengaturan Lembaga</a>
            <a href="admin_admin.php"><i class="bi bi-shield-lock me-2 fs-5"></i> Manajemen Admin</a>
            <a href="admin_monitor.php"><i class="bi bi-cpu me-2 fs-5"></i> Monitoring Sistem</a>
            <a href="index.php" target="_blank"><i class="bi bi-house me-2 fs-5"></i> Lihat Website</a>
            <a href="logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout</a>
        </div>

        <!-- Konten Utama -->
        <div class="col-12 col-md-9 p-3 p-md-5">
            
            <!-- Header Atas & Notifikasi Lonceng -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Data Pendaftar</h2>
                    <p class="text-muted small mb-0">Kelola dan verifikasi data calon peserta didik baru.</p>
                </div>
                
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <!-- Dropdown Notifikasi Lonceng -->
                    <div class="dropdown">
                        <button class="btn btn-light position-relative border shadow-sm btn-3d px-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Pendaftar Terbaru">
                            <i class="bi bi-bell-fill fs-5 text-warning"></i>
                            <?php if ($jumlah_pendaftar_baru > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $jumlah_pendaftar_baru; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-2 rounded-4" style="width: 300px;">
                            <li class="dropdown-header fw-bold text-dark border-bottom pb-2">
                                <i class="bi bi-bell me-1"></i> Pendaftar Baru (Belum Dibuka)
                            </li>
                            <?php if ($jumlah_pendaftar_baru > 0): ?>
                                <?php foreach ($notif_pendaftar as $np): ?>
                                    <li>
                                        <a class="dropdown-item py-2 rounded-3 text-wrap d-flex align-items-center gap-2 mt-1 bg-light fw-bold" href="admin_daftar.php?read_notif=<?php echo $np['id_siswa']; ?>">
                                            <img src="<?php echo htmlspecialchars($np['foto_path']); ?>" class="rounded-circle border" width="30" height="30" style="object-fit:cover;">
                                            <div>
                                                <div class="small text-dark"><?php echo htmlspecialchars($np['nama_lengkap']); ?></div>
                                                <div class="text-muted" style="font-size: 11px;">Program: <?php echo htmlspecialchars($np['kelas']); ?></div>
                                            </div>
                                            <span class="badge bg-danger ms-auto" style="font-size: 8px;">Buka</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted text-center small py-3">Tidak ada pendaftar baru</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-3d" data-bs-toggle="modal" data-bs-target="#modalLaporan">
                            <i class="bi bi-file-earmark-text"></i> Cetak Laporan
                        </button>
                        <a href="cetak_kartu.php" target="_blank" class="btn btn-primary btn-3d" style="background-color: var(--bright-blue); border-color: var(--bright-blue);"><i class="bi bi-printer"></i> Cetak Semua Kartu</a>
                    </div>
                </div>
            </div>

            <!-- Card Statistik -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card card-3d p-3 border-start border-primary border-4">
                        <div class="d-flex align-items-center">
                            <div class="fs-1 text-primary me-3"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <span class="text-muted small fw-semibold">Total Pendaftar</span>
                                <h3 class="fw-bold mb-0"><?php echo $total_pendaftar; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card card-3d p-3 border-start border-success border-4">
                        <div class="d-flex align-items-center">
                            <div class="fs-1 text-success me-3"><i class="bi bi-patch-check-fill"></i></div>
                            <div>
                                <span class="text-muted small fw-semibold">Terverifikasi</span>
                                <h3 class="fw-bold mb-0"><?php echo $total_terverifikasi; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-3d p-3 border-start border-warning border-4">
                        <div class="d-flex align-items-center">
                            <div class="fs-1 text-warning me-3"><i class="bi bi-clock-history"></i></div>
                            <div>
                                <span class="text-muted small fw-semibold">Belum Verifikasi</span>
                                <h3 class="fw-bold mb-0"><?php echo $total_pending; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Tabel & Filter Pencarian -->
            <div class="card card-3d p-3 p-md-4">
                <form method="GET" class="row g-3 mb-3 align-items-center justify-content-between">
                    <div class="col-auto d-flex align-items-center gap-2">
                        <label class="col-form-label small fw-bold text-muted">Tampilkan</label>
                        <select name="limit" class="form-select form-select-sm shadow-sm" style="width: 75px;" onchange="this.form.submit()">
                            <option value="5" <?php if($limit == 5) echo 'selected'; ?>>5</option>
                            <option value="10" <?php if($limit == 10) echo 'selected'; ?>>10</option>
                            <option value="25" <?php if($limit == 25) echo 'selected'; ?>>25</option>
                            <option value="50" <?php if($limit == 50) echo 'selected'; ?>>50</option>
                        </select>
                        <span class="col-form-label small fw-bold text-muted">data</span>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="text" name="cari" class="form-control shadow-sm" placeholder="Cari nama, kelas, no WA..." value="<?php echo htmlspecialchars($cari); ?>">
                            <button class="btn btn-primary btn-3d" type="submit" style="background-color: var(--bright-blue); border-color: var(--bright-blue);"><i class="bi bi-search"></i></button>
                            <?php if($cari != ""): ?>
                                <a href="admin_daftar.php" class="btn btn-outline-secondary btn-3d"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>WhatsApp</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($pendaftar) > 0): ?>
                                <?php foreach($pendaftar as $row): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($row['foto_path']); ?>" class="rounded-circle border shadow-sm" width="40" height="40" style="object-fit:cover;">
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['jenis_kelamin']); ?>, <?php echo htmlspecialchars($row['usia']); ?> Thn</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['no_wa']); ?></td>
                                    <td><span class="badge bg-info text-dark shadow-sm"><?php echo htmlspecialchars($row['kelas']); ?></span></td>
                                    <td>
                                        <?php if(isset($row['status_verifikasi']) && $row['status_verifikasi'] == 'Terverifikasi'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-all"></i> Terverifikasi</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <?php if(!isset($row['status_verifikasi']) || $row['status_verifikasi'] != 'Terverifikasi'): ?>
                                            <a href="admin_daftar.php?verifikasi=<?php echo $row['id_siswa']; ?>" class="btn btn-sm btn-success btn-3d" title="Verifikasi Data"><i class="bi bi-check-lg"></i></a>
                                        <?php else: ?>
                                            <a href="admin_daftar.php?batal_verifikasi=<?php echo $row['id_siswa']; ?>" class="btn btn-sm btn-secondary btn-3d" title="Batalkan Verifikasi"><i class="bi bi-x-circle"></i></a>
                                            
                                            <?php 
                                                $wa_number = formatNoWA($row['no_wa']);
                                                $pesan_wa = "Assalamu'alaikum Wr. Wb.,\n\nYth. " . $row['nama_lengkap'] . ",\n\nPendaftaran Anda di " . $nama_lembaga . " untuk kelas " . $row['kelas'] . " telah TERVERIFIKASI.\n\nTerima kasih!";
                                                $link_wa = "https://wa.me/" . $wa_number . "?text=" . urlencode($pesan_wa);
                                            ?>
                                            <a href="<?php echo $link_wa; ?>" target="_blank" class="btn btn-sm btn-success btn-3d" title="Kirim WA"><i class="bi bi-whatsapp"></i></a>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-warning text-dark btn-3d btn-edit" 
                                            data-id="<?php echo $row['id_siswa']; ?>"
                                            data-nama="<?php echo htmlspecialchars($row['nama_lengkap']); ?>"
                                            data-jk="<?php echo htmlspecialchars($row['jenis_kelamin']); ?>"
                                            data-usia="<?php echo htmlspecialchars($row['usia']); ?>"
                                            data-nowa="<?php echo htmlspecialchars($row['no_wa']); ?>"
                                            data-kelas="<?php echo htmlspecialchars($row['kelas']); ?>"
                                            data-alamat="<?php echo htmlspecialchars($row['alamat']); ?>"
                                            data-foto="<?php echo htmlspecialchars($row['foto_path']); ?>"
                                            title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <a href="cetak_kartu.php?id=<?php echo $row['id_siswa']; ?>" target="_blank" class="btn btn-sm btn-primary btn-3d" style="background-color: var(--bright-blue); border-color: var(--bright-blue);" title="Cetak Kartu"><i class="bi bi-printer"></i></a>
                                        <a href="admin_daftar.php?hapus=<?php echo $row['id_siswa']; ?>" class="btn btn-sm btn-danger btn-3d" onclick="return confirm('Yakin hapus data?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada data pendaftar yang tersedia.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
                    <div class="small text-muted">
                        Menampilkan halaman <strong><?php echo $page; ?></strong> dari total <strong><?php echo max(1, $total_halaman); ?></strong> halaman (<?php echo $total_data; ?> data)
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>">Sebelumnya</a>
                        </li>
                        
                        <?php 
                        $loop_halaman = ($total_halaman > 0) ? $total_halaman : 1;
                        for($i = 1; $i <= $loop_halaman; $i++): 
                        ?>
                            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php if($page >= $loop_halaman || $total_halaman == 0) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FILTER & CETAK LAPORAN -->
<div class="modal fade" id="modalLaporan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="cetak_laporan.php" method="GET" target="_blank">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text"></i> Filter Cetak Laporan PDF</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Verifikasi</label>
                        <select name="lap_status" class="form-select shadow-sm">
                            <option value="">Semua Status</option>
                            <option value="Terverifikasi">Terverifikasi</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Program Kelas</label>
                        <select name="lap_kelas" class="form-select shadow-sm">
                            <option value="">Semua Program</option>
                            <?php foreach($daftar_kelas_lap as $kls): ?>
                                <option value="<?php echo $kls; ?>"><?php echo $kls; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold btn-3d"><i class="bi bi-printer"></i> Lanjutkan Cetak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT DATA -->
<div class="modal fade" id="globalEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header text-white border-0" style="background-color: var(--primary-blue);">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> Edit Data Pendaftar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_siswa" id="edit_id_siswa">
                    <input type="hidden" name="update_siswa" value="1">

                    <div class="text-center mb-3">
                        <img id="edit_foto_preview" src="uploads/default.jpg" class="rounded-circle border shadow mb-2" width="80" height="80" style="object-fit: cover;"><br>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Ganti Foto Profil</label>
                        <input type="file" name="foto" class="form-control shadow-sm" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" class="form-control shadow-sm" required>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Jenis Kelamin</label>
                            <select name="jenis_kelamin" id="edit_jk" class="form-select shadow-sm" required>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Usia</label>
                            <input type="number" name="usia" id="edit_usia" class="form-control shadow-sm" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Nomor WhatsApp</label>
                            <input type="text" name="no_wa" id="edit_nowa" class="form-control shadow-sm" required>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Kelas / Program</label>
                            <input type="text" name="kelas" id="edit_kelas" class="form-control shadow-sm" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat</label>
                        <textarea name="alamat" id="edit_alamat" class="form-control shadow-sm" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold btn-3d w-100" style="background-color: var(--bright-blue); border-color: var(--bright-blue);">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let toastEl = document.getElementById('liveToast');
        if (toastEl) {
            let toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();
        }

        const editButtons = document.querySelectorAll('.btn-edit');
        const globalModal = new bootstrap.Modal(document.getElementById('globalEditModal'));

        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_id_siswa').value = this.getAttribute('data-id');
                document.getElementById('edit_nama').value = this.getAttribute('data-nama');
                document.getElementById('edit_jk').value = this.getAttribute('data-jk');
                document.getElementById('edit_usia').value = this.getAttribute('data-usia');
                document.getElementById('edit_nowa').value = this.getAttribute('data-nowa');
                document.getElementById('edit_kelas').value = this.getAttribute('data-kelas');
                document.getElementById('edit_alamat').value = this.getAttribute('data-alamat');
                document.getElementById('edit_foto_preview').src = this.getAttribute('data-foto');

                globalModal.show();
            });
        });
    });
</script>
</body>
</html>