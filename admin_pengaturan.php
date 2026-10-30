<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'koneksi.php';

if (!isset($_SESSION['login'])) { 
    header("Location: login.php"); 
    exit; 
}

$pesan = "";
$pesan_tipe = "";

// 1. OTOMATIS BUAT TABEL & KOLOM JIKA BELUM ADA
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
        id_pengaturan INT AUTO_INCREMENT PRIMARY KEY,
        nama_lembaga VARCHAR(255) NOT NULL,
        alamat_lembaga TEXT NOT NULL,
        no_wa_lembaga VARCHAR(50) NOT NULL,
        email_lembaga VARCHAR(100) DEFAULT '',
        logo_path VARCHAR(255) NOT NULL,
        hero_image VARCHAR(255) DEFAULT 'uploads/default_hero.png'
    )");

    $cek_hero =$pdo->query("SHOW COLUMNS FROM pengaturan LIKE 'hero_image'")->fetch();
    if (!$cek_hero) {$pdo->exec("ALTER TABLE pengaturan ADD COLUMN hero_image VARCHAR(255) DEFAULT 'uploads/default_hero.png' AFTER logo_path");
    }

    $cek_email =$pdo->query("SHOW COLUMNS FROM pengaturan LIKE 'email_lembaga'")->fetch();
    if (!$cek_email) {$pdo->exec("ALTER TABLE pengaturan ADD COLUMN email_lembaga VARCHAR(100) DEFAULT '' AFTER no_wa_lembaga");
    }

    $cek_data =$pdo->query("SELECT COUNT(*) FROM pengaturan")->fetchColumn();
    if ($cek_data == 0) {$pdo->exec("INSERT INTO pengaturan (id_pengaturan, nama_lembaga, alamat_lembaga, no_wa_lembaga, email_lembaga, logo_path, hero_image) 
                    VALUES (1, 'Les Komputer Mandiri', 'Jl. Contoh Alamat No. 123, Kota Anda', '081234567890', 'info@maribelajar.com', 'uploads/default_logo.png', 'uploads/default_hero.png')");
    }
} catch (PDOException $e) {
    // Tangani error database jika ada
}

// 2. FUNGSI UPLOAD YANG AMAN DAN ANTI-GAGAL
function upload_file_langsung($file_input,$prefix, $old_path,$default_path) {
    if (!is_dir('uploads')) {
        @mkdir('uploads', 0755, true);
    }

    if (!isset($file_input) ||$file_input['error'] === UPLOAD_ERR_NO_FILE) {
        return $old_path;
    }

    if ($file_input['error'] !== UPLOAD_ERR_OK) {
        return $old_path;
    }

    $filename = $file_input['name'];$tmp_name = $file_input['tmp_name'];$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (in_array($ext, $allowed)) {$new_filename = 'uploads/' . $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;

        if (move_uploaded_file($tmp_name,$new_filename)) {
            if (!empty($old_path) &&$old_path != $default_path && file_exists($old_path)) {
                @unlink($old_path);
            }
            return $new_filename;
        }
    }

    return $old_path;
}

// 3. PROSES KETIKA FORM DISUBMIT
if (isset($_POST['update_pengaturan'])) {
    $nama =$_POST['nama_lembaga'];
    $alamat =$_POST['alamat_lembaga'];
    $no_wa =$_POST['no_wa_lembaga'];
    $email =$_POST['email_lembaga'];

    $stmt_l =$pdo->query("SELECT logo_path, hero_image FROM pengaturan WHERE id_pengaturan = 1");
    $data_l =$stmt_l->fetch(PDO::FETCH_ASSOC);
    $logo_path =$data_l['logo_path'];
    $hero_image =$data_l['hero_image'];

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {$logo_path = upload_file_langsung($_FILES['logo'], 'logo',$logo_path, 'uploads/default_logo.png');
    }

    if (isset($_FILES['hero_img']) && $_FILES['hero_img']['error'] === UPLOAD_ERR_OK) {$hero_image = upload_file_langsung($_FILES['hero_img'], 'hero',$hero_image, 'uploads/default_hero.png');
    }

    $update =$pdo->prepare("UPDATE pengaturan SET nama_lembaga = ?, alamat_lembaga = ?, no_wa_lembaga = ?, email_lembaga = ?, logo_path = ?, hero_image = ? WHERE id_pengaturan = 1");
    $exec =$update->execute([$nama,$alamat, $no_wa,$email, $logo_path,$hero_image]);

    if ($exec) {$_SESSION['notif'] = "Pengaturan dan gambar berhasil diperbarui!";
        $_SESSION['notif_tipe'] = "success";
    } else {
        $_SESSION['notif'] = "Gagal memperbarui database.";
        $_SESSION['notif_tipe'] = "danger";
    }

    header("Location: admin_pengaturan.php");
    exit;
}

// 4. EKSPOR DATABASE (.SQL)
if (isset($_GET['ekspor_db'])) {
    $database_name =$pdo->query("SELECT DATABASE()")->fetchColumn();
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"backup_" . $database_name . "_" . date('Y-m-d_H-i-s') . ".sql\"");

    $tables = [];
    $stmt =$pdo->query("SHOW TABLES");
    while ($row =$stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] =$row[0];
    }

    $sql_script = "";
    foreach ($tables as$table) {
        $row =$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $sql_script .= "\n\n" . $row['Create Table'] . ";\n\n";

        $result =$pdo->query("SELECT * FROM `$table`");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {$sql_script .= "INSERT INTO `$table` VALUES(";
            $comma = "";
            foreach ($row as $value) {$value = addslashes($value);$value = str_replace("\n", "\\n", $value);$sql_script .= $comma . "'" . $value . "'";
                $comma = ",";
            }
            $sql_script .= ");\n";
        }
    }
    echo $sql_script;
    exit;
}

// 5. EKSPOR DATABASE KE JSON
if (isset($_GET['ekspor_json'])) {$tables = [];
    $stmt =$pdo->query("SHOW TABLES");
    while ($row =$stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] =$row[0];
    }

    $database_data = [];
    foreach ($tables as$table) {
        $stmt_data =$pdo->query("SELECT * FROM `$table`");
        $database_data[$table] =$stmt_data->fetchAll(PDO::FETCH_ASSOC);
    }

    $database_name =$pdo->query("SELECT DATABASE()")->fetchColumn();
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="backup_' . $database_name . '_' . date('Y-m-d_H-i-s') . '.json"');
    echo json_encode($database_data, JSON_PRETTY_PRINT);
    exit;
}

// 6. IMPOR DATABASE DARI JSON
if (isset($_POST['impor_json'])) {
    if (isset($_FILES['file_json']) &&$_FILES['file_json']['error'] === UPLOAD_ERR_OK) {
        $file_tmp =$_FILES['file_json']['tmp_name'];
        $json_content = file_get_contents($file_tmp);
        $data = json_decode($json_content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            try {
                $pdo->beginTransaction();$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                foreach ($data as $table =>$rows) {
                    $cek_tabel =$pdo->query("SHOW TABLES LIKE '$table'")->fetch();
                    if ($cek_tabel) {$pdo->exec("TRUNCATE TABLE `$table`");

                        if (!empty($rows) && is_array($rows)) {
                            foreach ($rows as $row) {$columns = array_keys($row);$placeholders = array_fill(0, count($columns), '?');$col_str = "`" . implode("`, `", $columns) . "`";
                                $val_str = implode(", ", $placeholders);
                                
                                $stmt =$pdo->prepare("INSERT INTO `$table` ($col_str) VALUES ($val_str)");
                                $stmt->execute(array_values($row));
                            }
                        }
                    }
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $pdo->commit();

                $_SESSION['notif'] = "Database JSON berhasil diimpor!";
                $_SESSION['notif_tipe'] = "success";
            } catch (Exception $e) {
                $pdo->rollBack();$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $_SESSION['notif'] = "Gagal mengimpor JSON: " . $e->getMessage();$_SESSION['notif_tipe'] = "danger";
            }
        } else {
            $_SESSION['notif'] = "File JSON tidak valid atau rusak.";
            $_SESSION['notif_tipe'] = "danger";
        }
    } else {
        $_SESSION['notif'] = "Pilih file JSON terlebih dahulu.";
        $_SESSION['notif_tipe'] = "warning";
    }

    header("Location: admin_pengaturan.php");
    exit;
}

$pengaturan =$pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch(PDO::FETCH_ASSOC);

if (isset($_SESSION['notif'])) {
    $pesan =$_SESSION['notif'];
    $pesan_tipe =$_SESSION['notif_tipe'];
    unset($_SESSION['notif']);
    unset($_SESSION['notif_tipe']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Lembaga & Database | Admin Panel</title>
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
        
        .preview-box {
            background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 15px; text-align: center;
        }

        h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            color: var(--primary-blue);
        }
    </style>
</head>
<body>

<?php if ($pesan !== ""): ?>
<div class="toast-container">
    <div id="liveToast" class="toast align-items-center text-white bg-<?php echo ($pesan_tipe == 'warning') ? 'warning text-dark' :$pesan_tipe; ?> border-0 shadow-lg show" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><i class="bi bi-info-circle-fill me-2"></i><?php echo $pesan; ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Navbar Mobile -->
<nav class="navbar navbar-dark d-md-none px-3 py-2 shadow-sm" style="background: var(--dark-bg)!important;">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars($pengaturan['logo_path']); ?>" class="rounded-circle border border-warning" width="32" height="32" style="object-fit:cover;">
            <span class="navbar-brand fw-bold fs-6 text-truncate mb-0" style="max-width: 200px; font-family:'Montserrat',sans-serif;"><?php echo htmlspecialchars($pengaturan['nama_lembaga']); ?></span>
        </div>
        <button class="btn btn-outline-light btn-sm" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"><i class="bi bi-list fs-4"></i></button>
    </div>
</nav>

<!-- Offcanvas Sidebar Mobile -->
<div class="offcanvas offcanvas-start text-white" tabindex="-1" id="mobileSidebar" style="background: var(--dark-bg);">
    <div class="offcanvas-header border-bottom border-secondary">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars($pengaturan['logo_path']); ?>" class="rounded-circle border border-warning shadow-sm" width="35" height="35" style="object-fit: cover;">
            <h5 class="offcanvas-title fw-bold fs-6 text-truncate mb-0 text-white"><?php echo htmlspecialchars($pengaturan['nama_lembaga']); ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body admin-sidebar min-vh-100 p-3">
        <a href="admin_daftar.php"><i class="bi bi-people me-2"></i> Data Pendaftar</a>
        <a href="admin_program.php"><i class="bi bi-journal-code me-2"></i> Kelola Program</a>
        <a href="admin_pengaturan.php" class="active"><i class="bi bi-gear me-2"></i> Pengaturan Lembaga</a>
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
                <img src="<?php echo htmlspecialchars($pengaturan['logo_path']); ?>" class="rounded-circle border border-warning shadow-sm mb-2" width="70" height="70" style="object-fit: cover;">
                <h5 class="fw-bold text-truncate mb-0 text-white"><?php echo htmlspecialchars($pengaturan['nama_lembaga']); ?></h5>
                <small class="text-warning" style="font-size: 11px; letter-spacing: 0.5px;">Panel Administrasi</small>
            </div>
            <hr class="border-secondary opacity-50">
            <a href="admin_daftar.php"><i class="bi bi-people me-2 fs-5"></i> Data Pendaftar</a>
            <a href="admin_program.php"><i class="bi bi-journal-code me-2 fs-5"></i> Kelola Program</a>
            <a href="admin_pengaturan.php" class="active"><i class="bi bi-gear me-2 fs-5"></i> Pengaturan Lembaga</a>
            <a href="admin_admin.php"><i class="bi bi-shield-lock me-2 fs-5"></i> Manajemen Admin</a>
            <a href="admin_monitor.php"><i class="bi bi-cpu me-2 fs-5"></i> Monitoring Sistem</a>
            <a href="index.php" target="_blank"><i class="bi bi-house me-2 fs-5"></i> Lihat Website</a>
            <a href="logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-left me-2 fs-5"></i> Logout</a>
        </div>

        <!-- Konten Utama -->
        <div class="col-12 col-md-9 p-3 p-md-5">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Pengaturan Lembaga & Sistem</h2>
                <p class="text-muted small">Kelola informasi profil, kontak resmi, media visual lembaga, serta backup database.</p>
            </div>

            <div class="card card-3d p-4 mb-4">
                <form method="POST" enctype="multipart/form-data">
                    <h5 class="fw-bold mb-3" style="color: var(--primary-blue);"><i class="bi bi-building me-2"></i> Profil & Tampilan Lembaga</h5>
                    <hr class="mb-4">

                    <div class="row g-4">
                        <!-- Kolom Kiri: Informasi Teks Lembaga -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Nama Lembaga</label>
                                <input type="text" name="nama_lembaga" class="form-control shadow-sm" value="<?php echo htmlspecialchars($pengaturan['nama_lembaga']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">No. WhatsApp Resmi Lembaga</label>
                                <input type="text" name="no_wa_lembaga" class="form-control shadow-sm" value="<?php echo htmlspecialchars($pengaturan['no_wa_lembaga']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Email Resmi Lembaga</label>
                                <input type="email" name="email_lembaga" class="form-control shadow-sm" value="<?php echo htmlspecialchars($pengaturan['email_lembaga']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Alamat Lengkap Lembaga</label>
                                <textarea name="alamat_lembaga" class="form-control shadow-sm" rows="4" required><?php echo htmlspecialchars($pengaturan['alamat_lembaga']); ?></textarea>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Pengaturan Media / Gambar (Logo & Banner) -->
                        <div class="col-md-5 border-start-md ps-md-4">
                            <!-- Preview & Upload Logo -->
                            <div class="mb-4">
                                <label class="form-label fw-bold small">Logo Lembaga</label>
                                <div class="preview-box mb-2">
                                    <img src="<?php echo htmlspecialchars($pengaturan['logo_path']); ?>" class="rounded-circle border shadow-sm mb-2" width="70" height="70" style="object-fit: cover;"><br>
                                    <span class="small text-muted d-block">Pratinjau Logo Saat Ini</span>
                                </div>
                                <input type="file" name="logo" class="form-control form-control-sm shadow-sm" accept="image/*">
                                <div class="form-text" style="font-size: 11px;">Format: PNG, JPG, JPEG, WEBP</div>
                            </div>

                            <!-- Preview & Upload Hero Image -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Banner Utama (Hero Image)</label>
                                <div class="preview-box mb-2">
                                    <img src="<?php echo !empty($pengaturan['hero_image']) ? htmlspecialchars($pengaturan['hero_image']) : 'uploads/default_hero.png'; ?>" class="rounded border shadow-sm mb-2" width="120" height="70" style="object-fit: cover;"><br>
                                    <span class="small text-muted d-block">Pratinjau Banner Saat Ini</span>
                                </div>
                                <input type="file" name="hero_img" class="form-control form-control-sm shadow-sm" accept="image/*">
                                <div class="form-text" style="font-size: 11px;">Format: PNG, JPG, JPEG, WEBP</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" name="update_pengaturan" class="btn btn-primary btn-3d px-4 py-2" style="background-color: var(--bright-blue); border-color: var(--bright-blue);"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>

            <!-- Card Manajemen Database (SQL & JSON) -->
            <div class="card card-3d p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color: var(--primary-blue);"><i class="bi bi-database me-2"></i> Manajemen Database</h5>
                    <button class="btn btn-outline-secondary btn-sm btn-3d" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEkspor" aria-expanded="false">
                        <i class="bi bi-eye"></i> Sembunyikan / Tampilkan Panel
                    </button>
                </div>
                <hr class="mb-3">
                <div class="collapse show" id="collapseEkspor">
                    <!-- Opsi Ekspor SQL -->
                    <div class="alert alert-light border p-3 rounded-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Unduh Cadangan Database (.sql)</h6>
                            <p class="small text-muted mb-0">Cadangan format SQL standar untuk pemulihan melalui phpMyAdmin.</p>
                        </div>
                        <a href="admin_pengaturan.php?ekspor_db=1" class="btn btn-success btn-3d text-nowrap"><i class="bi bi-download me-1"></i> Ekspor SQL</a>
                    </div>

                    <!-- Opsi Ekspor & Impor JSON -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border p-3 rounded-4 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-1"><i class="bi bi-filetype-json text-primary me-1"></i> Ekspor ke JSON</h6>
                                    <p class="small text-muted mb-3">Unduh seluruh isi tabel database dalam format file terstruktur JSON.</p>
                                </div>
                                <a href="admin_pengaturan.php?ekspor_json=1" class="btn btn-primary btn-3d w-100" style="background-color: var(--bright-blue);"><i class="bi bi-download me-1"></i> Ekspor JSON</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border p-3 rounded-4 bg-light h-100">
                                <h6 class="fw-bold mb-1"><i class="bi bi-upload text-success me-1"></i> Impor dari JSON</h6>
                                <p class="small text-muted mb-2">Pulihkan data sistem menggunakan file cadangan berformat JSON.</p>
                                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Peringatan: Impor data akan menimpa/mengganti data lama di database. Lanjutkan?');">
                                    <input type="file" name="file_json" class="form-control form-control-sm mb-2 shadow-sm" accept=".json" required>
                                    <button type="submit" name="impor_json" class="btn btn-warning btn-3d w-100 fw-semibold text-dark"><i class="bi bi-arrow-repeat me-1"></i> Impor JSON</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        let toastEl = document.getElementById('liveToast');
        if (toastEl) { new bootstrap.Toast(toastEl, { delay: 3500 }).show(); }
    });
</script>
</body>
</html>