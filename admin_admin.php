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

// 1. Logika Tambah Admin
if (isset($_POST['tambah_admin'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $cek = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->rowCount() > 0) {
            $_SESSION['notif'] = "Username sudah digunakan, silakan pilih yang lain!";
            $_SESSION['notif_tipe'] = "danger";
        } else {
            $stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
            $exec = $stmt->execute([$username, $password]);
            if ($exec) {
                $_SESSION['notif'] = "Admin baru berhasil ditambahkan!";
                $_SESSION['notif_tipe'] = "success";
            } else {
                $_SESSION['notif'] = "Gagal menambahkan admin.";
                $_SESSION['notif_tipe'] = "danger";
            }
        }
    } else {
        $_SESSION['notif'] = "Username dan Password tidak boleh kosong!";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_admin.php");
    exit;
}

// 2. Logika Update/Edit Admin
if (isset($_POST['update_admin'])) {
    $id = $_POST['id_admin'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username)) {
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE admin SET username = ?, password = ? WHERE id_admin = ?");
            $exec = $stmt->execute([$username, $password, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin SET username = ? WHERE id_admin = ?");
            $exec = $stmt->execute([$username, $id]);
        }
        
        if($exec) {
            if(isset($_SESSION['username']) && $_SESSION['username'] == $username) {
                $_SESSION['username'] = $username;
            }
            $_SESSION['notif'] = "Data admin berhasil diperbarui!";
            $_SESSION['notif_tipe'] = "success";
        } else {
            $_SESSION['notif'] = "Gagal memperbarui data admin.";
            $_SESSION['notif_tipe'] = "danger";
        }
    } else {
        $_SESSION['notif'] = "Username tidak boleh kosong!";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_admin.php");
    exit;
}

// 3. Logika Hapus Admin
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $hitung = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($hitung > 1) {
        $stmt = $pdo->prepare("DELETE FROM admin WHERE id_admin = ?");
        $exec = $stmt->execute([$id_hapus]);
        if($exec) {
            $_SESSION['notif'] = "Akun admin berhasil dihapus!";
            $_SESSION['notif_tipe'] = "warning";
        } else {
            $_SESSION['notif'] = "Gagal menghapus akun admin.";
            $_SESSION['notif_tipe'] = "danger";
        }
    } else {
        $_SESSION['notif'] = "Tidak dapat menghapus, sistem minimal harus menyisakan 1 akun admin!";
        $_SESSION['notif_tipe'] = "danger";
    }
    header("Location: admin_admin.php");
    exit;
}

// Ambil session notifikasi jika ada
if (isset($_SESSION['notif'])) {
    $pesan = $_SESSION['notif'];
    $pesan_tipe = $_SESSION['notif_tipe'];
    unset($_SESSION['notif']);
    unset($_SESSION['notif_tipe']);
}

// Konfigurasi Pencarian & Pagination
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : '';
$limit = isset($_GET['limit']) ? $_GET['limit'] : '10';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = ($page > 1) ? $page : 1;

$where_sql = "";
$params = [];
if ($cari !== '') {
    $where_sql = " WHERE username LIKE ? ";
    $params = ["%$cari%"];
}

// Hitung Total Data
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM admin" . $where_sql);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();

// Tentukan Limit Query
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

// Ambil Data Sesuai Pencarian & Limit
$stmt_data = $pdo->prepare("SELECT * FROM admin" . $where_sql . " ORDER BY id_admin ASC" . $limit_sql);
$stmt_data->execute($params);
$admins = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Admin | Admin Panel</title>
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
            background-color: #f1f5f9; 
            color: #333;
        }

        /* Sidebar Tema Gelap & Elegan Disamakan */
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
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .btn-3d:hover {
            transform: translateY(-1px);
        }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1080; }

        h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-blue);
        }

        @media print {
            .admin-sidebar, .btn, form, .toast-container, .card-header, .row.mt-4 { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
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

<!-- Navbar Mobile -->
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
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body admin-sidebar min-vh-100 p-3">
        <a href="admin_daftar.php"><i class="bi bi-people me-2"></i> Data Pendaftar</a>
        <a href="admin_program.php"><i class="bi bi-journal-code me-2"></i> Kelola Program</a>
        <a href="admin_pengaturan.php"><i class="bi bi-gear me-2"></i> Pengaturan Lembaga</a>
        <a href="admin_admin.php" class="active"><i class="bi bi-shield-lock me-2"></i> Manajemen Admin</a>
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
            <a href="admin_program.php"><i class="bi bi-journal-code me-2 fs-5"></i> Kelola Program</a>
            <a href="admin_pengaturan.php"><i class="bi bi-gear me-2 fs-5"></i> Pengaturan Lembaga</a>
            <a href="admin_admin.php" class="active"><i class="bi bi-shield-lock me-2 fs-5"></i> Manajemen Admin</a>
            <a href="admin_monitor.php"><i class="bi bi-cpu me-2 fs-5"></i> Monitoring Sistem</a>
            <a href="index.php" target="_blank"><i class="bi bi-house me-2 fs-5"></i> Lihat Website</a>
            <a href="logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout</a>
        </div>

        <!-- Konten Utama -->
        <div class="col-12 col-md-9 p-3 p-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Manajemen Akun Admin</h2>
                    <p class="text-muted small">Kelola data administrator sistem untuk keamanan dan akses kontrol.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-3d btn-sm"><i class="bi bi-printer me-1"></i> Cetak Daftar</button>
                    <button type="button" class="btn btn-primary btn-3d btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal" style="background-color: var(--bright-blue); border-color: var(--bright-blue);">
                        <i class="bi bi-person-plus-fill me-1"></i> Tambah Admin Baru
                    </button>
                </div>
            </div>

            <!-- Card Keterangan Akun yang Sedang Masuk -->
            <div class="card card-3d mb-4 border-start border-primary border-4">
                <div class="card-body d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 p-4">
                    <div>
                        <h6 class="text-muted mb-1 small"><i class="bi bi-info-circle-fill text-primary me-1"></i> Anda Sedang Masuk Sebagai:</h6>
                        <h4 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-person-badge text-primary me-2"></i><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator'; ?>
                        </h4>
                    </div>
                    <div>
                        <span class="badge bg-success p-2 px-3 shadow-sm"><i class="bi bi-shield-check me-1"></i> Active Session</span>
                    </div>
                </div>
            </div>

            <!-- Card Tabel & Filter -->
            <div class="card card-3d p-3 p-md-4 mb-4">
                <h5 class="mb-3 fw-bold" style="color: var(--primary-blue);">Daftar Akun Administrator</h5>

                <!-- Filter Bar -->
                <form method="GET" class="row g-3 mb-3 justify-content-between">
                    <div class="col-12 col-md-4 d-flex align-items-center gap-2">
                        <label class="text-muted text-nowrap small">Tampilkan</label>
                        <select name="limit" class="form-select form-select-sm shadow-sm" onchange="this.form.submit()">
                            <option value="10" <?php if($limit == '10') echo 'selected'; ?>>10</option>
                            <option value="20" <?php if($limit == '20') echo 'selected'; ?>>20</option>
                            <option value="all" <?php if($limit == 'all') echo 'selected'; ?>>All</option>
                        </select>
                        <span class="text-muted text-nowrap small">data</span>
                        <input type="hidden" name="cari" value="<?php echo htmlspecialchars($cari); ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm shadow-sm rounded-2 overflow-hidden">
                            <input type="text" name="cari" class="form-control border-0" placeholder="Cari username..." value="<?php echo htmlspecialchars($cari); ?>">
                            <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
                            <button class="btn btn-outline-secondary border-0 bg-light" type="submit"><i class="bi bi-search"></i></button>
                            <?php if($cari !== ''): ?>
                                <a href="admin_admin.php" class="btn btn-outline-danger border-0" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = (($page - 1) * ($limit === 'all' ? count($admins) : (int)$limit)) + 1;
                            if(count($admins) > 0):
                                foreach($admins as $row): 
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td class="fw-bold"><i class="bi bi-person-circle text-primary me-2"></i> <?php echo htmlspecialchars($row['username']); ?></td>
                                <td><code><?php echo htmlspecialchars($row['password']); ?></code></td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-info text-white btn-3d" onclick="cetakAkun('<?php echo htmlspecialchars($row['username']); ?>', '<?php echo htmlspecialchars($row['password']); ?>')" title="Cetak Akun Ini">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning text-dark btn-3d" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id_admin']; ?>" title="Edit Admin">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="admin_admin.php?hapus=<?php echo $row['id_admin']; ?>" class="btn btn-sm btn-danger btn-3d" onclick="return confirm('Yakin ingin menghapus akun admin ini?')" title="Hapus Admin">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>

                            <!-- Modal Edit Admin -->
                            <div class="modal fade" id="editModal<?php echo $row['id_admin']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                        <form method="POST">
                                            <div class="modal-header bg-warning text-dark border-0">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> Edit Akun: <?php echo htmlspecialchars($row['username']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="id_admin" value="<?php echo $row['id_admin']; ?>">
                                                <input type="hidden" name="update_admin" value="1">

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small">Username</label>
                                                    <input type="text" name="username" class="form-control shadow-sm" value="<?php echo htmlspecialchars($row['username']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small">Password</label>
                                                    <input type="text" name="password" class="form-control shadow-sm" value="<?php echo htmlspecialchars($row['password']); ?>" placeholder="Masukkan password baru">
                                                    <div class="form-text text-muted" style="font-size: 11px;">Biarkan kosong jika tidak ingin mengubah password.</div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-warning fw-bold btn-3d">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Tidak ada data admin ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($limit !== 'all' && $total_page > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-end mb-0">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm" href="admin_admin.php?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>">Sebelumnya</a>
                        </li>
                        <?php for($i = 1; $i <= $total_page; $i++): ?>
                            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                <a class="page-link shadow-sm" href="admin_admin.php?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if($page >= $total_page) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm" href="admin_admin.php?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&cari=<?php echo urlencode($cari); ?>">Berikutnya</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>

            <!-- Card Statistik Bawah -->
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="card card-3d p-3 h-100">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white p-3 rounded-3 me-3 shadow">
                                <i class="bi bi-shield-lock-fill fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Total Administrator Aktif</span>
                                <h4 class="fw-bold mb-0"><?php echo $total_data; ?> Akun</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card card-3d p-3 h-100 border-start border-warning border-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning text-dark p-3 rounded-3 me-3 shadow">
                                <i class="bi bi-lightbulb-fill fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted small">Catatan Keamanan</span>
                                <p class="mb-0 small text-secondary">Pastikan password dicatat atau dibagikan hanya kepada pihak yang berwenang demi keamanan sistem.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Admin -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST">
                <div class="modal-header text-white border-0" style="background-color: var(--primary-blue);">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill"></i> Tambah Admin Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="tambah_admin" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Username</label>
                        <input type="text" name="username" class="form-control shadow-sm" placeholder="Masukkan username baru" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password</label>
                        <input type="text" name="password" class="form-control shadow-sm" placeholder="Masukkan password baru" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary btn-3d" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold btn-3d" style="background-color: var(--bright-blue); border-color: var(--bright-blue);">Simpan Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        let toastEl = document.getElementById('liveToast');
        if (toastEl) { new bootstrap.Toast(toastEl, { delay: 3500 }).show(); }
    });

    function cetakAkun(username, password) {
        let printWindow = window.open('', '', 'height=400,width=600');
        printWindow.document.write('<html><head><title>Cetak Akun Admin</title>');
        printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
        printWindow.document.write('</head><body class="p-5">');
        printWindow.document.write('<div class="card border p-4 shadow-sm">');
        printWindow.document.write('<h3 class="text-center mb-3">Kredensial Akun Administrator</h3><hr>');
        printWindow.document.write('<p><strong>Username:</strong> ' + username + '</p>');
        printWindow.document.write('<p><strong>Password:</strong> <code>' + password + '</code></p>');
        printWindow.document.write('<p class="text-muted small mt-4">Simpan informasi ini dengan aman dan jangan berikan kepada pihak yang tidak berwenang.</p>');
        printWindow.document.write('</div>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 500);
    }
</script>
</body>
</html>