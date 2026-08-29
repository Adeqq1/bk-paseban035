<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;


if (!isset($_GET['id'])) {
    header("Location: kunjungan_rumah.php");
    exit();
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$user_id = $_SESSION['id'];

$query = mysqli_query($koneksi, "
    SELECT kr.*, s.nama_lengkap as nama_siswa, s.nisn, s.jenis_kelamin, s.alamat as alamat_siswa,
           k.nama_kelas, g.nama_lengkap as nama_guru, g.nip as nip_guru
    FROM kunjungan_rumah kr
    JOIN siswa s ON kr.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN guru g ON (kr.guru_id = g.id OR kr.guru_id = g.user_id)
    WHERE kr.id = '$id'
");
$p = mysqli_fetch_assoc($query);

if (!$p) {
    die("Data Laporan Kunjungan Rumah tidak ditemukan.");
}

$timestamp_buat = strtotime($p['tanggal_pelaksanaan']);
$tgl_pelaksanaan_indo = tgl_indo(date('Y-m-d', $timestamp_buat));

$jenis_kelamin_text = ($p['jenis_kelamin'] == 'L') ? 'Laki-laki' : (($p['jenis_kelamin'] == 'P') ? 'Perempuan' : '-');
$nama_ortu_fix = !empty($p['nama_ortu']) ? $p['nama_ortu'] : '-';
$alamat_fix = !empty($p['alamat']) ? $p['alamat'] : (!empty($p['alamat_siswa']) ? $p['alamat_siswa'] : '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kunjungan Rumah - <?php echo htmlspecialchars($p['nama_siswa']); ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body, table, tr, td, p, div { 
            font-family: 'Times New Roman', Times, serif; 
            color: #000; 
            font-size: 12pt; 
            line-height: 1.5;
        }
        body { padding: 25px 45px; }

        /* Kop Surat */
        .kop-surat { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
        .kop-logo-container { width: 90px; height: 95px; display: flex; align-items: center; justify-content: center; }
        .kop-logo { max-width: 90px; max-height: 95px; }
        .kop-text { text-align: center; flex: 1; padding: 0 10px; }
        .kop-text h3 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .kop-text h2 { margin: 2px 0; font-size: 18pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        
        .kop-surat-border {
            border-top: 1px solid #000;
            border-bottom: 3.5px solid #000;
            padding-bottom: 2px;
            margin-bottom: 20px;
        }

        .document-title {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14pt;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }

        .section-title {
            font-weight: normal;
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 12pt;
        }

        .section-content {
            margin-left: 20px;
            text-align: justify;
            text-indent: 35px;
        }

        table.identitas-table {
            width: 100%;
            border-collapse: collapse;
            margin-left: 20px;
        }
        table.identitas-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .action-bar {
            background: #f3f4f6; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center;
        }
        .btn {
            padding: 6px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; font-family: sans-serif; font-size: 12px; border: none; font-weight: 600;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-back { background: #6b7280; color: #fff; }

        @media print {
        #mobile-toggle { display: none !important; }
            .no-print { display: none !important; }
            body { padding: 20px 40px; }
        }
    
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
        <button onclick="window.print()" class="btn btn-print">Cetak Sekarang</button>
        <!-- Tombol kembali ke halaman tabel data -->
        <a href="kunjungan_rumah.php" class="btn btn-back">Kembali</a>
    </div>

    <!-- KOP SURAT RESMI -->
    <div class="kop-surat">
        <div class="kop-logo-container">
            <img src="images/Logo_Resmi_Provinsi_Jambi.png?v=2" class="kop-logo" alt="Logo Provinsi Jambi">
        </div>
        <div class="kop-text">
            <h3>PEMERINTAH PROPINSI JAMBI</h3>
            <h3>DINAS PENDIDIKAN</h3>
            <h2>SMA NEGERI 7 BUNGO</h2>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 4px; font-size: 9pt; font-family: 'Times New Roman', Times, serif;">
                <span style="font-style: italic;">Jl. Desa lubuk Landai, Kec. Tanah Sepenggal Lintas.</span>
                <span style="font-weight: bold; font-style: normal;">NPSN: 10500692</span>
            </div>
        </div>
        <div class="kop-logo-container">
            <img src="images/logo_sma.png?v=2" class="kop-logo" alt="Logo SMAN 7">
        </div>
    </div>
    <div class="kop-surat-border"></div>

    <div class="document-title">
        <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;">
            LAPORAN KUNJUNGAN RUMAH (HOME VISIT)
        </span>
    </div>

    <!-- A. IDENTITAS KONSELI -->
    <div class="section-title">A. Identitas Konseli :</div>
    <table class="identitas-table">
        <tr>
            <td width="25">1.</td>
            <td width="160">Nama Konseli</td>
            <td>: <?php echo htmlspecialchars($p['nama_siswa']); ?></td>
        </tr>
        <tr>
            <td>2.</td>
            <td>Kelas</td>
            <td>: <?php echo htmlspecialchars($p['nama_kelas'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>3.</td>
            <td>Jenis Kelamin</td>
            <td>: <?php echo htmlspecialchars($jenis_kelamin_text); ?></td>
        </tr>
        <tr>
            <td>4.</td>
            <td>Alamat</td>
            <td>: <?php echo htmlspecialchars($alamat_fix); ?></td>
        </tr>
        <tr>
            <td>5.</td>
            <td>Nama Ortu / Wali</td>
            <td>: <?php echo htmlspecialchars($nama_ortu_fix); ?></td>
        </tr>
    </table>

    <!-- B. PERMASALAHAN KONSELI -->
    <div class="section-title">B. Permasalahan Konseli :</div>
    <div class="section-content">
        <?php echo nl2br(htmlspecialchars($p['permasalahan'] ?? '-')); ?>
    </div>

    <!-- C. TUJUAN HOME VISIT -->
    <div class="section-title">C. Tujuan Home Visit :</div>
    <div class="section-content" style="text-indent: 0;">
        <?php echo nl2br(htmlspecialchars($p['tujuan_home_visit'] ?? '-')); ?>
    </div>

    <!-- D. PELAKSANAAN KUNJUNGAN RUMAH -->
    <div class="section-title">D. Pelaksanaan Kunjungan Rumah :</div>
    <table class="identitas-table">
        <tr>
            <td width="25">1.</td>
            <td width="160">Tanggal Pelaksanaan</td>
            <td>: <?php echo htmlspecialchars($tgl_pelaksanaan_indo); ?></td>
        </tr>
        <tr>
            <td>2.</td>
            <td>Yang Ditemui</td>
            <td>: <?php echo htmlspecialchars($p['yang_ditemui'] ?? '-'); ?></td>
        </tr>
    </table>

    <!-- E. HASIL HOME VISIT -->
    <div class="section-title">E. Hasil Home Visit :</div>
    <div class="section-content">
        <?php echo nl2br(htmlspecialchars($p['hasil_home_visit'] ?? '-')); ?>
    </div>

    <!-- F. TANDA TANGAN -->
    <table style="width: 100%; margin-top: 20px; border-collapse: collapse; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top; padding: 15px 0 20px 0;">
                Diketahui oleh,<br>
                Orang Tua / Wali
                <div style="height: 55px;"></div>
                <span>
                    <?php echo htmlspecialchars($nama_ortu_fix); ?>
                </span>
            </td>
            <td style="width: 50%; text-align: center; vertical-align: top; padding: 15px 0 20px 0;">
                Tanah Abang, <?php echo htmlspecialchars($tgl_pelaksanaan_indo); ?><br>
                Konseli,
                <div style="height: 55px;"></div>
                <span>
                    <?php echo htmlspecialchars($p['nama_siswa']); ?>
                </span>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center; vertical-align: top; padding-top: 15px;">
                <div style="display: inline-block; text-align: left;">
                    Mengetahui,<br>
                    Guru Bimbingan dan Konseling
                    <div style="height: 55px;"></div>
                    <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;">
                        <?php echo htmlspecialchars($p['nama_guru']); ?>
                    </span><br>
                    NIP. <?php echo !empty($p['nip_guru']) ? htmlspecialchars($p['nip_guru']) : '-'; ?>
                </div>
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
