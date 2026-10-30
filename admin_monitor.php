<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch(PDO::FETCH_ASSOC);
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Les Komputer Mandiri';
$logo_lembaga = $pengaturan ? $pengaturan['logo_path'] : 'uploads/default_logo.png';

// 1. Deteksi Kapasitas & Statistik Database
try {
    $query_size = $pdo->prepare("
        SELECT 
            table_schema AS `database_name`, 
            SUM(data_length + index_length) AS `size_in_bytes`,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS `size_in_mb` 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        GROUP BY table_schema
    ");
    $query_size->execute();
    $db_info = $query_size->fetch(PDO::FETCH_ASSOC);

    $query_tables = $pdo->prepare("
        SELECT 
            table_name, 
            table_rows, 
            ROUND((data_length + index_length) / 1024, 2) AS size_kb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    $query_tables->execute();
    $tables_info = $query_tables->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// 2. Deteksi Malware & Kode Berbahaya
$scanned_files = [];
$is_scanned = isset($_GET['scan']) && $_GET['scan'] == 'true';

if ($is_scanned) {
    $dangerous_patterns = ['eval(', 'shell_exec(', 'system(', 'passthru(', 'assert('];
    $dir = __DIR__;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filepath = $file->getRealPath();
            $filename = $file->getFilename();
            
            if ($filename === 'admin_monitor.php') continue;

            $content = file_get_contents($filepath);
            $malware_indicators = [];

            foreach ($dangerous_patterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    if ($pattern === 'eval(' && (stripos($content, '$_POST') !== false || stripos($content, '$_GET') !== false)) {
                        $malware_indicators[] = "KRITIS: Ditemukan fungsi <code>eval()</code> dengan inputan user!";
                    } else {
                        $malware_indicators[] = "Peringatan: Menggunakan fungsi sensitif <code>$pattern</code>.";
                    }
                }
            }

            if (!empty($malware_indicators)) {
                $scanned_files[] = [
                    'filename' => $filename,
                    'path' => str_replace($dir, '', $filepath),
                    'issues' => [],
                    'malware' => $malware_indicators
                ];
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
    <title>Monitoring Sistem & Keamanan | Admin Panel</title>
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

        h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-blue);
        }
    </style>
</head>
<body>

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
        <a href="admin_admin.php"><i class="bi bi-shield-lock me-2"></i> Manajemen Admin</a>
        <a href="admin_monitor.php" class="active"><i class="bi bi-cpu me-2"></i> Monitoring Sistem</a>
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
            <a href="admin_admin.php"><i class="bi bi-shield-lock me-2 fs-5"></i> Manajemen Admin</a>
            <a href="admin_monitor.php" class="active"><i class="bi bi-cpu me-2 fs-5"></i> Monitoring Sistem</a>
            <a href="index.php" target="_blank"><i class="bi bi-house me-2 fs-5"></i> Lihat Website</a>
            <a href="logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout</a>
        </div>

        <!-- Konten Utama -->
        <div class="col-12 col-md-9 p-3 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold mb-1">Monitoring Sistem & Database</h2>
                    <p class="text-muted small">Pantau kapasitas database, ukuran tabel, serta jalankan pemindaian keamanan kode.</p>
                </div>
                <a href="admin_monitor.php?scan=true" class="btn btn-danger btn-3d fw-bold rounded-pill px-4 shadow-sm">
                    <i class="bi bi-shield-shaded me-2"></i> Pindai Malware & Error
                </a>
            </div>
            
            <!-- KAPASITAS DATABASE & TABEL -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card card-3d p-4 text-center h-100">
                        <div class="card-body">
                            <i class="bi bi-hdd-stack-fill display-4 mb-3" style="color: var(--bright-blue);"></i>
                            <h6 class="text-muted fw-bold small">Total Kapasitas Database</h6>
                            <h3 class="fw-bold text-dark mt-2">
                                <?php echo (isset($db_info['size_in_mb']) && $db_info['size_in_mb'] !== '') ? $db_info['size_in_mb'] . " MB" : "0 MB"; ?>
                            </h3>
                            <p class="small text-muted mb-0">Database: <code><?php echo (isset($db_info['database_name'])) ? htmlspecialchars($db_info['database_name']) : 'Unknown'; ?></code></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card card-3d p-4 h-100">
                        <h5 class="fw-bold mb-3" style="color: var(--primary-blue);"><i class="bi bi-table me-2"></i>Kapasitas & Jumlah Data per Tabel</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Tabel</th>
                                        <th>Jumlah Baris</th>
                                        <th>Ukuran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(isset($tables_info)): foreach($tables_info as $tbl): ?>
                                    <tr>
                                        <td class="fw-bold"><i class="bi bi-database me-1 text-secondary"></i> <?php echo htmlspecialchars($tbl['table_name']); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo number_format($tbl['table_rows']); ?> baris</span></td>
                                        <td><?php echo $tbl['size_kb']; ?> KB</td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pop-up Hasil Pemindaian Malware -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-labelledby="scanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header text-white" style="background-color: var(--dark-bg); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                <h5 class="modal-title fw-bold" id="scanModalLabel"><i class="bi bi-shield-fill-exclamation me-2 text-danger"></i>Hasil Pemindaian Keamanan Sistem</h5>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small">Laporan hasil pemindaian file direktori aplikasi terhadap ancaman malware, fungsi berbahaya, ataupun syntax error.</p>
                
                <?php if (empty($scanned_files)): ?>
                    <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Aman!</strong> Tidak ditemukan adanya indikasi malware atau fungsi berbahaya mencurigakan pada file PHP Anda.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nama File</th>
                                    <th>Lokasi</th>
                                    <th>Temuan Masalah / Ancaman</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scanned_files as $file): ?>
                                <tr>
                                    <td class="fw-bold text-danger"><i class="bi bi-file-earmark-code me-1"></i> <?php echo htmlspecialchars($file['filename']); ?></td>
                                    <td><code><?php echo htmlspecialchars($file['path']); ?></code></td>
                                    <td>
                                        <?php foreach ($file['malware'] as $mal): ?>
                                            <span class="badge bg-warning text-dark d-block text-start mb-1 p-2"><?php echo $mal; ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary btn-3d rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($is_scanned): ?>
<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        var myModal = new bootstrap.Modal(document.getElementById('scanModal'));
        myModal.show();
    });
</script>
<?php endif; ?>
</body>
</html>