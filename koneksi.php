<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db_name = "db_les_pro";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`"); 
    $pdo->exec("USE `$db_name`");
    
    // Tabel Admin
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id_admin INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE,
        password VARCHAR(255)
    )");

    // Buat akun admin default (User: admin, Pass: admin123)
    $cek_admin = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
    if ($cek_admin == 0) {
        $pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin (username, password) VALUES ('admin', '$pass_hash')");
    }

    // Tabel Siswa / Pendaftar
    $pdo->exec("CREATE TABLE IF NOT EXISTS siswa (
        id_siswa INT AUTO_INCREMENT PRIMARY KEY, 
        nisn VARCHAR(20) DEFAULT '-', 
        nama_lengkap VARCHAR(100), 
        jenis_kelamin VARCHAR(20),
        usia INT,
        no_wa VARCHAR(20),
        kelas VARCHAR(50), 
        alamat TEXT,
        tgl_lahir DATE DEFAULT '2000-01-01', 
        foto_path VARCHAR(255) DEFAULT 'uploads/default.jpg'
    )");

    // Tabel Program Unggulan
    $pdo->exec("CREATE TABLE IF NOT EXISTS program (
        id_program INT AUTO_INCREMENT PRIMARY KEY,
        nama_program VARCHAR(100),
        kategori VARCHAR(50),
        deskripsi TEXT,
        gambar VARCHAR(255)
    )");

    if (!is_dir('uploads')) mkdir('uploads', 0777, true);

    // Seeding program default
    $cek_prog = $pdo->query("SELECT COUNT(*) FROM program")->fetchColumn();
    if ($cek_prog == 0) {
        $pdo->exec("INSERT INTO program (nama_program, kategori, deskripsi, gambar) VALUES 
            ('Admin Office Pro', 'OFFICE', 'Kuasai pelaporan data, Excel Advance, dan administrasi modern.', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80'),
            ('Graphic Design Master', 'CREATIVE', 'Ekspresikan kreativitasmu dengan Adobe Photoshop & Illustrator.', 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=600&q=80'),
            ('Web Development', 'TECH', 'Bangun website responsif dari nol hingga online (HTML, CSS, JS).', 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600&q=80')
        ");
    }
} catch (Exception $e) { 
    die("DB Error: " . $e->getMessage()); 
}

function v($arr, $key) { 
    return isset($arr[$key]) ? htmlspecialchars($arr[$key]) : '-'; 
}
?>