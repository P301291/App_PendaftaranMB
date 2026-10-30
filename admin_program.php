<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

$pesan = "";
$pesan_tipe = "";

// Ambil data pengaturan lembaga untuk ditampilkan secara dinamis di sidebar/header
$pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch(PDO::FETCH_ASSOC);
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Les Komputer Mandiri';
$logo_lembaga = $pengaturan ? $pengaturan['logo_path'] : 'uploads/default_logo.png';

// Fungsi bantu upload file program yang aman & anti-gagal
function upload_file_program($file_input, $old_path = "") {
    if (!is_dir('uploads')) {
        @mkdir('uploads', 0755, true);
    }

    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) {
        return $old_path;
    }

    $filename = $file_input['name'];
    $tmp_name = $file_input['tmp_name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (in_array($ext, $allowed)) {
        $new_filename = 'uploads/program_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($tmp_name, $new_filename)) {
            if (!empty($old_path) && file_exists($old_path)) {
                @unlink($old_path);
            }
            return $new_filename;
        }
    }
    return $old_path;
}

// Tambah Program
if (isset($_POST['simpan_program'])) {
    $nama_p = $_POST['nama_program'];
    $kat = $_POST['kategori'];
    $desc = $_POST['deskripsi'];
    
    $gambar_path = 'uploads/default_program.png';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $gambar_path = upload_file_program($_FILES['gambar'], $gambar_path);
    }
    
    $stmt = $pdo->prepare("INSERT INTO program (nama_program, kategori, deskripsi, gambar) VALUES (?,?,?,?)");
    $exec = $stmt->execute([$nama_p, $kat, $desc, $gambar_path]);
    
    if($exec) {
        $_SESSION['notif'] = "Program berhasil ditambahkan!";
        $_SESSION['notif_tipe'] = "success";
    } else {
        $_SESSION['notif'] = "Gagal menambahkan program.";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_program.php");
    exit;
}

// Edit / Update Program
if (isset($_POST['update_program'])) {
    $id_prog = $_POST['id_program'];
    $nama_p = $_POST['nama_program'];
    $kat = $_POST['kategori'];
    $desc = $_POST['deskripsi'];
    
    $stmt_lama = $pdo->prepare("SELECT gambar FROM program WHERE id_program = ?");
    $stmt_lama->execute([$id_prog]);
    $data_lama = $stmt_lama->fetch(PDO::FETCH_ASSOC);
    $gambar_path = isset($data_lama['gambar']) ? $data_lama['gambar'] : '';

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $gambar_path = upload_file_program($_FILES['gambar'], $gambar_path);
    }
    
    $stmt = $pdo->prepare("UPDATE program SET nama_program = ?, kategori = ?, deskripsi = ?, gambar = ? WHERE id_program = ?");
    $exec = $stmt->execute([$nama_p, $kat, $desc, $gambar_path, $id_prog]);
    
    if($exec) {
        $_SESSION['notif'] = "Program berhasil diperbarui!";
        $_SESSION['notif_tipe'] = "success";
    } else {
        $_SESSION['notif'] = "Gagal memperbarui program.";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_program.php");
    exit;
}

// Hapus Program Satuan
if (isset($_GET['hapus_program'])) {
    $stmt_lama = $pdo->prepare("SELECT gambar FROM program WHERE id_program = ?");
    $stmt_lama->execute([$_GET['hapus_program']]);
    $row_g = $stmt_lama->fetch();
    if ($row_g && !empty($row_g['gambar']) && file_exists($row_g['gambar'])) {
        @unlink($row_g['gambar']);
    }

    $stmt = $pdo->prepare("DELETE FROM program WHERE id_program = ?");
    $exec = $stmt->execute([$_GET['hapus_program']]);
    
    if($exec) {
        $_SESSION['notif'] = "Program berhasil dihapus!";
        $_SESSION['notif_tipe'] = "warning";
    } else {
        $_SESSION['notif'] = "Gagal menghapus program.";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_program.php");
    exit;
}

// Ambil session notifikasi jika ada
if (isset($_SESSION['notif'])) {
    $pesan = $_SESSION['notif'];
    $pesan_tipe = $_SESSION['notif_tipe'];
    unset($_SESSION['notif']);
    unset($_SESSION['notif_tipe']);
}

// Konfigurasi Pencarian, Filter Kategori & Pagination
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$filter_kat = isset($_GET['filter_kat']) ? trim($_GET['filter_kat']) : '';
$limit = isset($_GET['limit']) ? $_GET['limit'] : '10';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = ($page > 1) ? $page : 1;

$where_clauses = [];
$params = [];

if ($cari !== '') {
    $where_clauses[] = "(nama_program LIKE ? OR kategori LIKE ? OR deskripsi LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

if ($filter_kat !== '') {
    $where_clauses[] = "kategori = ?";
    $params[] = $filter_kat;
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Ambil daftar kategori unik untuk dropdown filter
$list_kategori = $pdo->query("SELECT DISTINCT kategori FROM program ORDER BY kategori ASC")->fetchAll(PDO::FETCH_COLUMN);

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM program" . $where_sql);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();

if ($limit === 'all') {
    $limit_sql = "";
    $total_page = 1;
} else {
    $limit_val = (int)$limit;
    $limit_val = ($limit_val > 0) ? $limit_val : 10;
    $total_page = ceil($total_data / $limit_val);
    $total_page = ($total_page > 0) ? $total_page : 1;
    if ($page > $total_page) $page = $total_page;
    $start = ($page - 1) * $limit_val;
    $limit_sql = " LIMIT $start, $limit_val";
}

$stmt_data = $pdo->prepare("SELECT * FROM program" . $where_sql . " ORDER BY id_program DESC" . $limit_sql);
$stmt_data->execute($params);
$list_prog = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Program | Panel Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background: #f1f5f9; 
            color: #333;
        }

        /* Sidebar Tema Gelap & Elegan (Disamakan) */
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
        
        h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-blue);
        }
    </style>
</head>
<body>

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

<!-- Navbar Mobile (Disamakan) -->
<nav class="navbar navbar-dark d-md-none px-3 py-2 shadow-sm" style="background: var(--dark-bg)!important;">
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
            <h5 class="offcanvas-title fw-bold fs-6 text-truncate mb-0 text-white"><?php echo htmlspecialchars($nama_lembaga); ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body admin-sidebar min-vh-100 p-3">
        <a href="admin_daftar.php"><i class="bi bi-people me-2"></i> Data Pendaftar</a>
        <a href="admin_program.php" class="active"><i class="bi bi-journal-code me-2"></i> Kelola Program</a>
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
            <a href="admin_daftar.php"><i class="bi bi-people me-2 fs-5"></i> Data Pendaftar</a>
            <a href="admin_program.php" class="active"><i class="bi bi-journal-code me-2 fs-5"></i> Kelola Program</a>
            <a href="admin_pengaturan.php"><i class="bi bi-gear me-2 fs-5"></i> Pengaturan Lembaga</a>
            <a href="admin_admin.php"><i class="bi bi-shield-lock me-2 fs-5"></i> Manajemen Admin</a>
            <a href="admin_monitor.php"><i class="bi bi-cpu me-2 fs-5"></i> Monitoring Sistem</a>
            <a href="index.php" target="_blank"><i class="bi bi-house me-2 fs-5"></i> Lihat Website</a>
            <a href="logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout</a>
        </div>

        <div class="col-12 col-md-9 p-3 p-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold mb-1">Kelola Program</h2>
                    <p class="text-muted small mb-0">Tambah, ubah, dan kelola program kursus yang tampil di halaman depan.</p>
                </div>
                <!-- Tombol Trigger Modal Tambah Program -->
                <button type="button" class="btn btn-primary btn-3d px-4 py-2 text-nowrap" style="background-color: var(--bright-blue); border-color: var(--bright-blue);" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Program Baru
                </button>
            </div>

            <!-- Modal Tambah Program Baru -->
            <div class="modal fade" id="tambahModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header text-white border-0" style="background-color: var(--primary-blue);">
                            <h5 class="modal-title fw-bold text-white"><i class="bi bi-journal-plus me-2"></i> Tambah Program Baru</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body p-4">
                                <input type="hidden" name="simpan_program" value="1">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Nama Program</label>
                                        <input type="text" name="nama_program" class="form-control shadow-sm" placeholder="Contoh: Desain Grafis" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Kategori</label>
                                        <input type="text" name="kategori" class="form-control shadow-sm" placeholder="Contoh: TECH" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Upload Gambar / Foto Program</label>
                                        <input type="file" name="gambar" class="form-control shadow-sm" accept="image/*" required>
                                        <div class="form-text">Format yang didukung: JPG, JPEG, PNG, WEBP</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Deskripsi Singkat</label>
                                        <textarea name="deskripsi" class="form-control shadow-sm" rows="3" placeholder="Deskripsi program..." required></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary fw-bold btn-3d" style="background-color: var(--bright-blue); border-color: var(--bright-blue);"><i class="bi bi-save me-1"></i> Simpan Program</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Akhir Modal Tambah -->

            <!-- Bagian Tabel & Filter -->
            <div class="card card-3d p-3 p-md-4">
                <form method="GET" class="row g-3 mb-3 justify-content-between align-items-center">
                    <div class="col-12 col-md-4 d-flex align-items-center gap-2">
                        <label class="col-form-label small fw-bold text-muted">Tampilkan</label>
                        <select name="limit" class="form-select form-select-sm shadow-sm" style="width: 75px;" onchange="this.form.submit()">
                            <option value="10" <?php if($limit == '10') echo 'selected'; ?>>10</option>
                            <option value="20" <?php if($limit == '20') echo 'selected'; ?>>20</option>
                            <option value="all" <?php if($limit == 'all') echo 'selected'; ?>>All</option>
                        </select>
                        <span class="col-form-label small fw-bold text-muted">data</span>
                        <input type="hidden" name="cari" value="<?php echo htmlspecialchars($cari); ?>">
                        <input type="hidden" name="filter_kat" value="<?php echo htmlspecialchars($filter_kat); ?>">
                    </div>

                    <div class="col-12 col-md-5 d-flex gap-2">
                        <!-- Filter Kategori -->
                        <select name="filter_kat" class="form-select form-select-sm shadow-sm" onchange="this.form.submit()">
                            <option value="">Semua Kategori</option>
                            <?php foreach($list_kategori as $kat_opt): ?>
                                <option value="<?php echo htmlspecialchars($kat_opt); ?>" <?php if($filter_kat == $kat_opt) echo 'selected'; ?>><?php echo htmlspecialchars($kat_opt); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Kolom Pencarian -->
                        <div class="input-group input-group-sm">
                            <input type="text" name="cari" class="form-control shadow-sm" placeholder="Cari program..." value="<?php echo htmlspecialchars($cari); ?>">
                            <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
                            <button class="btn btn-primary btn-3d" type="submit" style="background-color: var(--bright-blue); border-color: var(--bright-blue);"><i class="bi bi-search"></i></button>
                            <?php if($cari !== '' || $filter_kat !== ''): ?>
                                <a href="admin_program.php" class="btn btn-outline-secondary btn-3d"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Foto</th>
                                <th>Program</th>
                                <th>Kategori</th>
                                <th>Deskripsi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($list_prog) > 0): ?>
                                <?php foreach($list_prog as $lp): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($lp['gambar']); ?>" class="rounded border shadow-sm" width="45" height="45" style="object-fit: cover;">
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($lp['nama_program']); ?></td>
                                    <td><span class="badge bg-info text-dark shadow-sm"><?php echo htmlspecialchars($lp['kategori']); ?></span></td>
                                    <td><?php echo htmlspecialchars($lp['deskripsi']); ?></td>
                                    <td class="text-center text-nowrap">
                                        <!-- Tombol Trigger Modal Edit -->
                                        <button type="button" class="btn btn-sm btn-warning text-dark btn-3d" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $lp['id_program']; ?>" title="Edit Program">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <!-- Tombol Hapus -->
                                        <a href="admin_program.php?hapus_program=<?php echo $lp['id_program']; ?>" class="btn btn-sm btn-danger btn-3d" onclick="return confirm('Hapus program ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>

                                <!-- Modal Edit Data -->
                                <div class="modal fade" id="editModal<?php echo $lp['id_program']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                            <div class="modal-header text-white border-0" style="background-color: var(--primary-blue);">
                                                <h5 class="modal-title fw-bold text-white">Edit Program: <?php echo htmlspecialchars($lp['nama_program']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="update_program" value="1">
                                                    <input type="hidden" name="id_program" value="<?php echo $lp['id_program']; ?>">
                                                    
                                                    <div class="text-center mb-3">
                                                        <img src="<?php echo htmlspecialchars($lp['gambar']); ?>" class="rounded border shadow-sm mb-2" width="80" height="80" style="object-fit: cover;"><br>
                                                        <span class="small text-muted">Gambar Program Saat Ini</span>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Nama Program</label>
                                                            <input type="text" name="nama_program" class="form-control shadow-sm" value="<?php echo htmlspecialchars($lp['nama_program']); ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Kategori</label>
                                                            <input type="text" name="kategori" class="form-control shadow-sm" value="<?php echo htmlspecialchars($lp['kategori']); ?>" required>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold">Ganti Gambar Program (Opsional)</label>
                                                            <input type="file" name="gambar" class="form-control shadow-sm" accept="image/*">
                                                            <div class="form-text">Biarkan kosong jika tidak ingin mengubah gambar.</div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold">Deskripsi</label>
                                                            <textarea name="deskripsi" class="form-control shadow-sm" rows="3" required><?php echo htmlspecialchars($lp['deskripsi']); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary fw-bold btn-3d" style="background-color: var(--bright-blue); border-color: var(--bright-blue);">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- Akhir Modal Edit -->

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Tidak ada data program ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Navigasi Pagination -->
                <?php if ($limit !== 'all' && $total_page > 1): ?>
                <nav class="mt-3 overflow-auto">
                    <ul class="pagination pagination-sm justify-content-end mb-0">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link text-nowrap" href="admin_program.php?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>&filter_kat=<?php echo urlencode($filter_kat); ?>">Sebelumnya</a>
                        </li>
                        <?php for($i = 1; $i <= $total_page; $i++): ?>
                            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                <a class="page-link" href="admin_program.php?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>&filter_kat=<?php echo urlencode($filter_kat); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if($page >= $total_page) echo 'disabled'; ?>">
                            <a class="page-link text-nowrap" href="admin_program.php?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>&filter_kat=<?php echo urlencode($filter_kat); ?>">Berikutnya</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        let toastEl = document.getElementById('liveToast');
        if (toastEl) {
            let toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();
        }
    });
</script>
</body>
</html>