<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

// Helper function aman untuk memanggil data array
if (!function_exists('v')) {
    function v($arr, $key, $default = '') {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }
}

// Ambil data pengaturan lembaga dari database
$pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1")->fetch(PDO::FETCH_ASSOC);
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Les Komputer Mandiri';
$alamat_lembaga = $pengaturan ? $pengaturan['alamat_lembaga'] : 'Pusat Pelatihan Komputer Terpadu';

// Ambil data siswa (berdasarkan ID tertentu atau semua)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE id_siswa = ?");
    $stmt->execute([$_GET['id']]);
    $siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $query = $pdo->query("SELECT * FROM siswa");
    $siswa_list = $query->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu Pelajar - <?php echo htmlspecialchars($nama_lembaga); ?></title>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
        }
        @page { size: A4; margin: 5mm; }
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #f0f0f0; }
        .container-print { display: grid; grid-template-columns: repeat(2, 85.6mm); gap: 5mm; padding: 5mm; justify-content: center; }
        .kartu {
            width: 85.6mm; height: 54mm;
            background: linear-gradient(135deg, #0a1e36 0%, #153254 100%);
            color: white; position: relative; overflow: hidden;
            border-radius: 3mm; box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            box-sizing: border-box; padding: 3mm;
        }
        .gold-side { position: absolute; left: 0; top: 0; width: 10mm; height: 100%; background: #d4af37; display: flex; align-items: center; justify-content: center; }
        .text-vertical { writing-mode: vertical-rl; transform: rotate(180deg); font-weight: bold; font-size: 15px; color: #0a1e36; letter-spacing: 1px; text-transform: uppercase; }
        .gold-wave { position: absolute; right: 0; top: 0; width: 30%; height: 100%; background: #d4af37; clip-path: polygon(30% 0, 100% 0, 100% 100%, 0% 100%); opacity: 0.9; }
        .header-k { position: relative; z-index: 2; display: flex; align-items: center; justify-content: center; gap: 4px; border-bottom: 1px solid #d4af37; padding-bottom: 3px; margin-bottom: 4px; padding-left: 10mm; }
        .text-header { text-align: center; line-height: 1.1; }
        .school-name { font-size: 12px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .content-area { position: relative; z-index: 2; padding-left: 10mm; }
        .foto { width: 20mm; height: 22mm; border: 2px solid #d4af37; border-radius: 50%; float: left; margin-right: 3mm; background: #fff; overflow: hidden; }
        .data { font-size: 10px; line-height: 1.4; color: #fff; }
        .nama { font-weight: bold; color: rgb(151, 225, 248); font-size: 10px; text-transform: uppercase; margin-bottom: 1px; }
        .footer-ttd { position: absolute; z-index: 5; bottom: 2mm; right: 2mm; width: 31mm; font-size: 7.5px; line-height: 1.1; color: white; text-align: center; }
        .data-row { display: flex; align-items: center; }
        .label { width: 28px; } 
        .colon { width: 8px; }
        .kartu-belakang { padding: 4mm 5mm; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .back-title { text-align: center; font-weight: bold; font-size: 11px; border-bottom: 1px solid #d4af37; padding-bottom: 3px; margin-bottom: 6px; color: #d4af37; letter-spacing: 0.5px; }
        .rules-list { font-size: 7.5px; line-height: 1.4; padding-left: 12px; margin: 0; }
        .rules-list li { margin-bottom: 4px; }
        .school-address { font-size: 7.5px; text-align: center; color: #dcdcdc; border-top: 1px dashed rgba(212, 175, 55, 0.4); padding-top: 4px; margin-top: auto; }
    </style>
</head>
<body>
<div class="container-print">
    <?php if (count($siswa_list) > 0): ?>
        <?php foreach ($siswa_list as $s): ?>
            <!-- KARTU BAGIAN DEPAN -->
            <div class="kartu">
                <div class="gold-side"><div class="text-vertical">KARTU PELAJAR</div></div>
                <div class="gold-wave"></div>
                <div class="header-k">
                    <div class="text-header">
                        <div class="school-name"><?php echo htmlspecialchars($nama_lembaga); ?></div>
                    </div>
                </div>
                <div class="content-area">
                    <div class="foto"><img src="<?php echo htmlspecialchars(v($s, 'foto_path', 'uploads/default.jpg')); ?>" width="100%" height="100%" style="object-fit:cover;"></div>
                    <div class="data">
                        <div class="nama"><?php echo htmlspecialchars(v($s, 'nama_lengkap')); ?></div>
                        <div class="data-row"><div class="label">ID</div><div class="colon">:</div><div>MB-<?php echo htmlspecialchars(v($s, 'id_siswa')); ?></div></div>
                        <div class="data-row"><div class="label">Kelas</div><div class="colon">:</div><div><?php echo htmlspecialchars(v($s, 'kelas')); ?></div></div>
                        <div class="data-row"><div class="label">WA</div><div class="colon">:</div><div><?php echo htmlspecialchars(v($s, 'no_wa')); ?></div></div>
                    </div>
                </div>
                <div class="footer-ttd">Admin Center<br><b><?php echo htmlspecialchars($nama_lembaga); ?></b></div>
            </div>

            <!-- KARTU BAGIAN BELAKANG -->
            <div class="kartu kartu-belakang">
                <div>
                    <div class="back-title">TATA TERTIB KURSUS</div>
                    <ol class="rules-list">
                        <li>Kartu member wajib dibawa saat jam pelajaran berlangsung.</li>
                        <li>Menjaga fasilitas laboratorium / sarana belajar dengan baik.</li>
                        <li>Keterlambatan konfirmasi atau kehadiran mengikuti ketentuan lembaga.</li>
                        <li>Semoga sukses menguasai keahlian bersama <?php echo htmlspecialchars($nama_lembaga); ?>!</li>
                    </ol>
                </div>
                <div class="school-address"><b><?php echo htmlspecialchars($alamat_lembaga); ?></b></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; grid-column: span 2; padding: 20px;">Tidak ada data siswa untuk dicetak.</p>
    <?php endif; ?>
</div>

<div style="text-align: center; margin: 20px;" class="no-print">
    <button onclick="window.print()" style="padding:12px 30px; cursor:pointer; background:#153254; color:white; border:none; border-radius:8px; font-weight:bold;">PRINT KARTU</button>
    <a href="admin_daftar.php" style="margin-left: 10px; padding:12px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:8px; display:inline-block; font-weight:bold;">Kembali</a>
</div>
</body>
</html>