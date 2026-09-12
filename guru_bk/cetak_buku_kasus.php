<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap, nip FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// Inisialisasi variabel filter
$filter_kelas = isset($_GET['kelas_id']) ? $_GET['kelas_id'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $semester_label = "Ganjil";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $semester_label = "Genap";
}

// Build WHERE clause
$where_kelas = "";
if (!empty($filter_kelas)) {
    $kelas_clean = mysqli_real_escape_string($koneksi, $filter_kelas);
    $where_kelas = "AND s.kelas_id = '$kelas_clean'";
}

// Query untuk daftar kasus siswa
$query_kasus = mysqli_query($koneksi, "
    SELECT cp.id, cp.tanggal, cp.keterangan,
           s.nama_lengkap as nama_siswa, k.nama_kelas,
           jp.nama_pelanggaran,
           kon.solusi as tindak_lanjut,
           kon.masalah as catatan_konseling
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN konseling kon ON cp.id = kon.catatan_pelanggaran_id
    WHERE cp.tanggal BETWEEN '$start_date' AND '$end_date'
    $where_kelas
    ORDER BY cp.tanggal ASC, cp.id ASC
");

// Hitung tahun pelajaran
$tahun_pelajaran = ($semester == '1') ? "$tahun/" . ($tahun + 1) : ($tahun - 1) . "/$tahun";

// Format tanggal cetak
$bulan_map = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tanggal_cetak = date('d') . ' ' . $bulan_map[(int)date('m')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Catatan Kasus Siswa - <?php echo $semester_label; ?> <?php echo $tahun_pelajaran; ?></title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body, table, tr, td, th, p, div { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 12pt; line-height: 1.5; }
        body { line-height: 1.5; padding: 12mm 25mm; margin: 0; }
        
        /* Kop Surat */
        .kop-surat { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            margin-bottom: 5px; 
        }
        .kop-surat-border {
            border-top: 1px solid #000;
            border-bottom: 3.5px solid #000;
            padding-bottom: 2px;
            margin-bottom: 15px;
        }
        .kop-logo-container { width: 105px; height: 110px; display: flex; align-items: center; justify-content: center; }
        .kop-logo { max-width: 105px; max-height: 110px; object-fit: contain; }
        .logo-placeholder { 
            width: 90px; height: 100px; border: 1px dashed #ccc; background: #fff; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 10px; color: #ccc; text-align: center;
        }
        .kop-text { text-align: center; flex: 1; padding: 0 15px; font-family: 'Times New Roman', Times, serif; }
        .kop-text h3 { margin: 0; font-size: 16pt; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; font-family: 'Times New Roman', Times, serif; color: #000; line-height: 1.25; }
        .kop-text h2 { margin: 3px 0; font-size: 22pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; font-family: 'Times New Roman', Times, serif; color: #000; line-height: 1.25; }
        .kop-text p { margin: 3px 0; font-size: 11.5pt; font-style: normal; color: #222; font-family: 'Times New Roman', Times, serif; }
        
        /* Judul Sub-header */
        .title { text-align: center; margin: 10px 0 15px 0; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        .title p { margin: 2px 0; font-size: 12pt; font-family: 'Times New Roman', Times, serif; line-height: 1.5; }
        
        /* Tabel Kasus */
        table.kasus { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        table.kasus th, table.kasus td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
            font-size: 12pt;
            line-height: 1.5;
            font-family: 'Times New Roman', Times, serif;
        }
        table.kasus th {
            background-color: #ffffff;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 12pt;
            line-height: 1.5;
        }
        table.kasus td.center { text-align: center; }
        
        /* Tanda Tangan */
        .signature-area {
            margin-top: 25px;
            text-align: right;
            padding-right: 25px;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .signature-area p { margin: 2px 0; font-size: 12pt; font-family: 'Times New Roman', Times, serif; line-height: 1.5; }
        .signature-area .name { font-weight: bold; text-decoration: underline; margin-top: 45px; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        .signature-area .nip { font-size: 12pt; font-family: 'Times New Roman', Times, serif; line-height: 1.5; }
        
        /* Print */
        @media print {
            .no-print { display: none !important; }
            @page { size: A4 landscape; margin: 0; }
            body { padding: 12mm 25mm; }
        }
        
        /* Action Bar */
        .action-bar {
            background: #f3f4f6; padding: 10px; border-radius: 8px; 
            margin-bottom: 20px; display: flex; gap: 10px; align-items: center;
            font-family: 'Inter', sans-serif;
        }
        .btn {
            padding: 6px 15px; border-radius: 4px; cursor: pointer; 
            text-decoration: none; font-family: sans-serif; font-size: 12px; border: none;
        }
        .btn-print { background: #2563eb; color: white; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back { background: #e5e7eb; color: #374151; }
        .btn-back:hover { background: #d1d5db; }
    </style>
</head>
<body>
    <!-- Action Bar (tidak dicetak) -->
    <div class="action-bar no-print">
        <button class="btn btn-print" onclick="window.print()">
            🖨️ Cetak Dokumen
        </button>
        <a href="buku_kasus.php?kelas_id=<?php echo urlencode($filter_kelas); ?>&semester=<?php echo $semester; ?>&tahun=<?php echo $tahun; ?>" class="btn btn-back">
            ← Kembali
        </a>
    </div>

    <!-- Kop Surat -->
    <div class="kop-surat">
        <div class="kop-logo-container">
            <?php if(file_exists(__DIR__ . '/images/Logo_Resmi_Provinsi_Jambi.png')): ?>
                <img src="images/Logo_Resmi_Provinsi_Jambi.png?v=2" class="kop-logo" alt="Logo Provinsi Jambi">
            <?php else: ?>
                <div class="logo-placeholder">Logo<br>Provinsi</div>
            <?php endif; ?>
        </div>
        <div class="kop-text">
            <h3>PEMERINTAH PROPINSI JAMBI</h3>
            <h3>DINAS PENDIDIKAN</h3>
            <h2>SMA NEGERI 7 BUNGO</h2>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 6px; font-size: 11.5pt; font-family: 'Times New Roman', Times, serif;">
                <span style="font-style: italic;">Jl. Desa lubuk Landai, Kec. Tanah Sepenggal Lintas.</span>
                <span style="font-weight: bold; font-style: normal;">NPSN: 10500692</span>
            </div>
        </div>
        <div class="kop-logo-container">
            <?php if(file_exists(__DIR__ . '/images/logo_sma.png')): ?>
                <img src="images/logo_sma.png?v=2" class="kop-logo" alt="Logo SMAN 7">
            <?php else: ?>
                <div class="logo-placeholder">Logo<br>SMA</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="kop-surat-border"></div>

    <!-- Judul Dokumen Sub-header -->
    <div class="title">
        <p style="font-size: 12pt; line-height: 1.5; margin: 0; font-weight: normal;">TAHUN PELAJARAN <?php echo $tahun_pelajaran; ?></p>
        <p style="font-size: 12pt; line-height: 1.5; margin: 2px 0;"><em>Semester: <?php echo $semester_label; ?></em></p>
    </div>

    <!-- Tabel Kasus -->
    <table class="kasus">
        <thead>
            <tr>
                <th style="width: 35px;">No</th>
                <th style="width: 130px;">Hari, tanggal peristiwa</th>
                <th style="width: 50px;">Kelas</th>
                <th style="width: 130px;">Nama Siswa</th>
                <th style="width: 150px;">Bentuk pelanggaran</th>
                <th style="width: 140px;">Tindak lanjut</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $has_data = false;
            while($row = mysqli_fetch_assoc($query_kasus)): 
                $has_data = true;
                // Format hari dan tanggal
                $hari_en = date('l', strtotime($row['tanggal']));
                $hari_map = [
                    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
                ];
                $hari = $hari_map[$hari_en] ?? $hari_en;
                
                $tgl = date('d', strtotime($row['tanggal']));
                $bln = $bulan_map[(int)date('m', strtotime($row['tanggal']))];
                $thn = date('Y', strtotime($row['tanggal']));
                
                // Tindak lanjut & Catatan
                $tindak_lanjut = $row['tindak_lanjut'] ?? '';
                if (empty(trim($tindak_lanjut))) {
                    $tindak_lanjut = 'Belum ditindak lanjuti';
                    $catatan = '-';
                } else {
                    $catatan = !empty($row['catatan_konseling']) ? $row['catatan_konseling'] : ($row['keterangan'] ?? '-');
                }
            ?>
            <tr>
                <td class="center"><?php echo $no++; ?></td>
                <td class="center">
                    <strong><?php echo $hari; ?>,</strong><br>
                    <?php echo "$tgl $bln $thn"; ?>
                </td>
                <td class="center"><strong><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></strong></td>
                <td><strong><?php echo htmlspecialchars($row['nama_siswa']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></td>
                <td><?php echo htmlspecialchars($tindak_lanjut); ?></td>
                <td><?php echo htmlspecialchars($catatan); ?></td>
            </tr>
            <?php 
            endwhile; 
            if(!$has_data): ?>
                <tr>
                    <td colspan="7" class="center" style="padding: 20px;">
                        <em>Tidak ada catatan kasus pada periode ini.</em>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Tanda Tangan -->
    <div class="signature-area" style="page-break-inside: avoid; break-inside: avoid;">
        <p>Lubuk Landai, <?php echo $tanggal_cetak; ?></p>
        <p>Guru Bimbingan Konseling,</p>
        <p class="name"><?php echo htmlspecialchars($guru['nama_lengkap'] ?? 'Guru BK'); ?></p>
        <p class="nip">NIP. <?php echo htmlspecialchars($guru['nip'] ?? '-'); ?></p>
    </div>
</body>
</html>
