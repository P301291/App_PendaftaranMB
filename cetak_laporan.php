<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();
include 'koneksi.php';
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }

$stmt_pengaturan = $pdo->query("SELECT * FROM pengaturan WHERE id_pengaturan = 1");
$pengaturan = $stmt_pengaturan ? $stmt_pengaturan->fetch(PDO::FETCH_ASSOC) : false;
$nama_lembaga = $pengaturan ? $pengaturan['nama_lembaga'] : 'Les Komputer Mandiri';
$logo_lembaga = $pengaturan ? $pengaturan['logo_path'] : 'uploads/default_logo.png';
$alamat_lembaga = $pengaturan ? $pengaturan['alamat_lembaga'] : 'Alamat Lembaga Belum Diatur';
// Disinkronkan dengan kolom no_wa_lembaga dari pengaturan database[cite: 2]
$telp_lembaga = $pengaturan ? $pengaturan['no_wa_lembaga'] : '-';

$lap_status = isset($_GET['lap_status']) ? $_GET['lap_status'] : '';
$lap_kelas = isset($_GET['lap_kelas']) ? $_GET['lap_kelas'] : '';

$q_lap = "SELECT * FROM siswa WHERE is_read = 1";
$p_lap = [];

if ($lap_status != "") {
    if ($lap_status == 'Terverifikasi') {
        $q_lap .= " AND status_verifikasi = 'Terverifikasi'";
    } else {
        $q_lap .= " AND (status_verifikasi != 'Terverifikasi' OR status_verifikasi IS NULL)";
    }
}
if ($lap_kelas != "") {
    $q_lap .= " AND kelas = ?";
    $p_lap[] = $lap_kelas;
}
$q_lap .= " ORDER BY id_siswa DESC";
$s_lap = $pdo->prepare($q_lap);
$s_lap->execute($p_lap);
$data_cetak_laporan = $s_lap->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Pendaftar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #ffffff; color: #000; font-family: Arial, sans-serif; }
        .print-container { padding: 20px; max-width: 1000px; margin: 0 auto; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="print-container">
    <!-- Tombol Aksi Manual -->
    <div class="text-end mb-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-sm fw-bold">Cetak Ulang / Simpan PDF</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Tutup Halaman</button>
    </div>

    <!-- Kop Surat (No. WhatsApp Lembaga sekarang mengambil data dari pengaturan) -->
    <div class="text-center position-relative pb-3 mb-4 border-bottom border-dark border-3">
        <img src="<?php echo htmlspecialchars($logo_lembaga); ?>" style="width: 70px; height: 70px; object-fit: cover; position: absolute; left: 10px; top: 0;" class="rounded-circle">
        <h4 class="fw-bold mb-1 text-uppercase">Laporan Rekapitulasi Pendaftar</h4>
        <h3 class="fw-bold mb-1 text-uppercase"><?php echo htmlspecialchars($nama_lembaga); ?></h3>
        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($alamat_lembaga); ?> | WhatsApp: <?php echo htmlspecialchars($telp_lembaga); ?></p>
    </div>

    <div class="row mb-3 small">
        <div class="col-6">
            <div>Waktu Cetak: <?php echo date('d-m-Y H:i'); ?> WIB</div>
            <div>Total Data: <strong><?php echo count($data_cetak_laporan); ?> Siswa</strong></div>
        </div>
        <div class="col-6 text-end">
            <div>Filter Status: <strong><?php echo $lap_status !== "" ? $lap_status : 'Semua'; ?></strong></div>
            <div>Filter Program: <strong><?php echo $lap_kelas !== "" ? $lap_kelas : 'Semua'; ?></strong></div>
        </div>
    </div>

    <table class="table table-bordered table-striped align-middle small">
        <thead class="table-dark text-center">
            <tr>
                <th style="width: 5%;">No</th>
                <th>Nama Lengkap</th>
                <th>JK</th>
                <th>Usia</th>
                <th>No. WhatsApp</th>
                <th>Program / Kelas</th>
                <th>Status Verifikasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($data_cetak_laporan) > 0): ?>
                <?php $no = 1; foreach($data_cetak_laporan as $cr): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($cr['nama_lengkap']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($cr['jenis_kelamin']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($cr['usia']); ?> Thn</td>
                    <td><?php echo htmlspecialchars($cr['no_wa']); ?></td>
                    <td><?php echo htmlspecialchars($cr['kelas']); ?></td>
                    <td class="text-center">
                        <?php echo (isset($cr['status_verifikasi']) && $cr['status_verifikasi'] == 'Terverifikasi') ? 'Terverifikasi' : 'Pending'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center py-3 text-muted">Tidak ada data pendaftar yang sesuai dengan filter.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5">
        <div class="col-8"></div>
        <div class="col-4 text-center small">
            <p class="mb-1">Ditetapkan di: <?php echo htmlspecialchars($nama_lembaga); ?></p>
            <p class="mb-5">Pada Tanggal: <?php echo date('d F Y'); ?></p>
            <br><br>
            <p class="fw-bold border-bottom border-dark pb-0 d-inline-block px-4 mb-0">Admin / Panitia Pendaftaran</p>
        </div>
    </div>
</div>

</body>
</html>