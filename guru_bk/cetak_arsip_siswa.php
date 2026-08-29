<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

// Ambil data guru yang login
$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT nama_lengkap, nip FROM guru WHERE user_id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$nama_guru = $guru ? $guru['nama_lengkap'] : '';
$nip_guru = $guru ? $guru['nip'] : '';

$siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '1';
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

if (!$siswa_id) {
    echo "Pilih siswa terlebih dahulu.";
    exit();
}

// Detail siswa
$query_siswa = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = '$siswa_id'");
$siswa = mysqli_fetch_assoc($query_siswa);

// Filter Tanggal
if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $label_semester = "Ganjil (Juli - Desember)";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $label_semester = "Genap (Januari - Juni)";
}

// Ambil riwayat Pelanggaran
$res_pelanggaran = mysqli_query($koneksi, "
    SELECT cp.tanggal, jp.nama_pelanggaran, jp.poin, cp.keterangan
    FROM catatan_pelanggaran cp
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    WHERE cp.siswa_id = '$siswa_id' AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
    ORDER BY cp.tanggal ASC
");

// Ambil riwayat Bimbingan
$res_bimbingan = mysqli_query($koneksi, "
    SELECT k.tanggal, k.masalah, k.solusi, k.jenis_konseling
    FROM konseling k
    LEFT JOIN guru g ON k.guru_id = g.id
    WHERE k.siswa_id = '$siswa_id' AND k.tanggal BETWEEN '$start_date' AND '$end_date'
    ORDER BY k.tanggal ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan_BK_<?php echo $siswa['nama_lengkap']; ?></title>
    <style>
        /* Pengaturan dasar halaman untuk cetak kertas A4 */
        @page { size: A4; margin: 0; }
        body, table, tr, td, p, div { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 12pt; }
        body { line-height: 1.5; padding: 15px 40px; }
        
        /* Tata letak Kop Surat (Header) */
        .kop-surat { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            margin-bottom: 5px; 
        }
        
        /* Garis pembatas ganda standar surat dinas */
        .kop-surat-border {
            border-top: 1px solid #000;
            border-bottom: 3.5px solid #000;
            padding-bottom: 2px;
            margin-bottom: 15px;
        }
        
        /* Wadah dan ukuran logo */
        .kop-logo-container { width: 90px; height: 95px; display: flex; align-items: center; justify-content: center; }
        .kop-logo { max-width: 90px; max-height: 95px; }
        
        /* Kotak putih jika logo belum diupload */
        .logo-placeholder { 
            width: 80px; 
            height: 90px; 
            border: 1px dashed #ccc; 
            background: #fff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 10px; 
            color: #ccc;
            text-align: center;
        }
        
        /* Pengaturan teks di tengah Kop Surat */
        .kop-text { text-align: center; flex: 1; padding: 0 10px; }
        .kop-text h3 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .kop-text h2 { margin: 2px 0; font-size: 18pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .kop-text p { margin: 3px 0; font-size: 11px; font-style: normal; color: #222; }
        
        /* Area informasi siswa (Nama, NIS, Kelas) */
        .info-siswa { margin-bottom: 20px; }
        table.info-table { width: 100%; border-collapse: collapse; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        table.info-table td { padding: 4px 6px; border: none; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        
        /* Judul Laporan utama */
        .laporan-title { text-align: center; text-decoration: underline; margin: 20px 0; text-transform: uppercase; }
        
        /* Tabel data riwayat pelanggaran dan bimbingan */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        table.data thead { display: table-row-group; }
        table.data th { border: 1px solid #000; padding: 8px 10px; text-align: left; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; background-color: #f2f2f2; font-weight: bold; }
        table.data td { border: 1px solid #000; padding: 8px 10px; text-align: left; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; }
        .col-center { text-align: center !important; }
        .col-left { text-align: left !important; }
        .col-justify { text-align: justify !important; text-justify: inter-word; word-wrap: break-word; word-break: break-word; }
        
        /* Area Tanda Tangan Guru BK */
        .footer { margin-top: 50px; display: flex; justify-content: flex-end; }
        .tanda-tangan { text-align: center; width: 250px; }
        
        /* Sembunyikan elemen navigasi saat diprint */
        @media print {
        #mobile-toggle { display: none !important; }
            .no-print { display: none !important; }
            body { padding: 20px 40px; }
            thead { display: table-row-group; }
        }
        
        /* Bar navigasi bantuan (Tombol Cetak/Kembali) */
        .action-bar {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-family: sans-serif;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-back { background: #6b7280; color: #fff; }
    
        .btn-submit {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 0.75rem 1.75rem !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            text-decoration: none !important;
            outline: none !important;
        }
        .btn-submit:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.38) !important;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
            color: #ffffff !important;
        }
        .btn-cancel {
            background: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1px solid #fecaca !important;
            padding: 0.75rem 1.75rem !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.08) !important;
            outline: none !important;
        }
        .btn-cancel:hover {
            background: #fee2e2 !important;
            color: #b91c1c !important;
            border-color: #fca5a5 !important;
            text-decoration: none !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.18) !important;
        }

    </style>
</head>
<body>
    
    <!-- Area Tombol Aksi (Tidak akan ikut tercetak di kertas) -->
    <div class="action-bar no-print">
        <!-- Tombol memicu dialog print pada browser -->
        <button onclick="window.print()" class="btn btn-print">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                </path>
            </svg>
            Cetak Sekarang
        </button>
        <!-- Tombol kembali ke halaman tabel data -->
        <a href="arsip_siswa.php?siswa_id=<?php echo $siswa_id; ?>" class="btn btn-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Kembali
        </a>
    </div>

    <div class="kop-surat">
        <div class="kop-logo-container">
            <img src="images/Logo_Resmi_Provinsi_Jambi.png?v=2" class="kop-logo" alt="Logo Provinsi Jambi">
        </div>
        
        <div class="kop-text">
            <h3>PEMERINTAH PROPINSI JAMBI</h3>
            <h3>DINAS PENDIDIKAN</h3>
            <h2>SMA NEGERI 7 BUNGO</h2>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 4px; font-size: 10pt; font-family: 'Times New Roman', Times, serif;">
                <span style="font-style: italic;">Jl. Desa lubuk Landai, Kec. Tanah Sepenggal Lintas.</span>
                <span style="font-weight: bold; font-style: normal;">NPSN: 10500692</span>
            </div>
        </div>
        
        <div class="kop-logo-container">
            <img src="images/logo_sma.png?v=2" class="kop-logo" alt="Logo SMAN 7">
        </div>
    </div>
    <div class="kop-surat-border"></div>

    <div class="laporan-title">
        <h3>LAPORAN KEDISIPLINAN & BIMBINGAN SISWA</h3>
    </div>

    <div class="info-siswa">
        <table class="info-table">
            <tr>
                <td width="150">Nama Lengkap</td>
                <td width="10">:</td>
                <td><strong><?php echo $siswa['nama_lengkap']; ?></strong></td>
            </tr>
            <tr>
                <td>NISN</td>
                <td>:</td>
                <td><?php echo $siswa['nisn']; ?></td>
            </tr>
            <tr>
                <td>Kelas</td>
                <td>:</td>
                <td><?php echo $siswa['nama_kelas']; ?></td>
            </tr>
            <tr>
                <td>Periode Laporan</td>
                <td>:</td>
                <td>Semester <?php echo $label_semester; ?> - Tahun <?php echo $tahun; ?></td>
            </tr>
        </table>
    </div>

    <h4 style="margin-bottom: 5px; border-bottom: 1px solid #ddd;">I. Riwayat Pelanggaran & Kedisiplinan</h4>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 15%; text-align: center;">Tanggal</th>
                <th style="width: 30%;">Kasus Pelanggaran</th>
                <th style="width: 45%;">Keterangan</th>
                <th style="width: 5%; text-align: center;">Poin</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; $total_p = 0;
            if(mysqli_num_rows($res_pelanggaran) > 0) {
                while($p = mysqli_fetch_assoc($res_pelanggaran)): 
                    $total_p += $p['poin'];
            ?>
                <tr>
                    <td class="col-center"><?php echo $no++; ?></td>
                    <td class="col-center"><?php echo tgl_indo($p['tanggal']); ?></td>
                    <td class="col-justify"><?php echo $p['nama_pelanggaran']; ?></td>
                    <td class="col-justify"><?php echo $p['keterangan']; ?></td>
                    <td class="col-center"><?php echo $p['poin']; ?></td>
                </tr>
            <?php endwhile; ?>
                <tr>
                    <td colspan="4" style="text-align: left;">TOTAL POIN PELANGGARAN</td>
                    <td style="text-align: center; background: #eee;"><?php echo $total_p; ?></td>
                </tr>
            <?php } else { ?>
                <tr><td colspan="5" style="text-align: center;">Tidak ada catatan pelanggaran.</td></tr>
            <?php } ?>
        </tbody>
    </table>

    <h4 style="margin-bottom: 5px; border-bottom: 1px solid #ddd;">II. Riwayat Bimbingan & Konseling</h4>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 15%; text-align: center;">Tanggal</th>
                <th style="width: 20%; text-align: center;">Jenis Layanan</th>
                <th style="width: 35%;">Masalah / Topik</th>
                <th style="width: 25%;">Hasil / Solusi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if(mysqli_num_rows($res_bimbingan) > 0) {
                while($b = mysqli_fetch_assoc($res_bimbingan)): 
            ?>
                <tr>
                    <td class="col-center"><?php echo $no++; ?></td>
                    <td class="col-center"><?php echo tgl_indo($b['tanggal']); ?></td>
                    <td class="col-center"><?php echo $b['jenis_konseling'] == 'Tindak Lanjut' ? 'Konferensi Kasus' : 'Konseling Individu'; ?></td>
                    <td class="col-justify"><?php echo nl2br(htmlspecialchars($b['masalah'])); ?></td>
                    <td class="col-justify"><?php echo nl2br(htmlspecialchars($b['solusi'])); ?></td>
                </tr>
            <?php endwhile; } else { ?>
                <tr><td colspan="5" style="text-align: center;">Tidak ada catatan bimbingan.</td></tr>
            <?php } ?>
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 30px; page-break-inside: avoid; border-collapse: collapse;">
        <tr>
            <td style="width: 60%; border: none;"></td>
            <td style="width: 40%; text-align: left; padding-left: 20px; vertical-align: top; border: none; font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5;">
                Lubuk Landai, <?php echo tgl_indo(date('Y-m-d')); ?><br>
                Guru Pembimbing (BK),<br>
                <div style="height: 65px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo $nama_guru ? htmlspecialchars($nama_guru) : '...................................................'; ?></span><br>
                NIP. <?php echo $nip_guru ? htmlspecialchars($nip_guru) : '...................................................'; ?>
            </td>
        </tr>
    </table>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("mobile-toggle");
            const sidebar = document.querySelector(".sidebar");
            if (toggleBtn && sidebar) {
                let overlay = document.getElementById("sidebar-overlay");
                if (!overlay) {
                    overlay = document.createElement("div");
                    overlay.className = "sidebar-overlay";
                    overlay.id = "sidebar-overlay";
                    document.body.appendChild(overlay);
                    overlay.addEventListener("click", function() {
                        sidebar.classList.remove("active");
                        document.body.classList.remove("sidebar-closed");
                        overlay.classList.remove("active");
                    });
                }

                toggleBtn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    if (window.innerWidth <= 992) {
                        sidebar.classList.toggle("active");
                        if (overlay) overlay.classList.toggle("active", sidebar.classList.contains("active"));
                    } else {
                        document.body.classList.toggle("sidebar-closed");
                    }
                });
                
                document.addEventListener("click", function(e) {
                    if (window.innerWidth <= 992 && sidebar.classList.contains("active") && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                        sidebar.classList.remove("active");
                        if (overlay) overlay.classList.remove("active");
                    }
                });
            }
        });
    </script>
</body>
</html>


