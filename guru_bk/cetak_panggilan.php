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
    header("Location: daftar_panggilan.php");
    exit();
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil detail panggilan
$query = mysqli_query($koneksi, "
    SELECT p.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas,
           g.nama_lengkap as nama_guru, g.nip as nip_guru,
           wk.nama_lengkap as nama_walikelas, wk.nip as nip_walikelas
    FROM panggilan_orang_tua p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN guru wk ON k.wali_kelas_id = wk.id
    JOIN guru g ON p.guru_id = g.id
    WHERE p.id = '$id'
");
$p = mysqli_fetch_assoc($query);

if (!$p) {
    die("Data tidak ditemukan.");
}

$timestamp_buat = strtotime($p['created_at']);
$tgl_surat = tgl_indo(date('Y-m-d', $timestamp_buat));
$bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"][date("n", $timestamp_buat)];
$tahun_surat = date("Y", $timestamp_buat);

// KONFIGURASI LOGO (Tempel link logo Anda di sini)
$link_logo_kiri = ""; // Contoh: "https://link-gambar.com/logo.png"
$link_logo_kanan = "";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Panggilan - <?php echo $p['nama_siswa']; ?></title>
    <style>
        /* Mengatur ukuran kertas saat dicetak (A4) */
        @page { size: A4; margin: 0; }
        
        /* Pengaturan dasar font dan spasi */
        /* Pengaturan dasar font dan spasi */
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
        
        /* Judul Surat */
        .title { text-align: center; font-weight: bold; text-decoration: underline; margin: 15px 0 10px 0; font-size: 16px; letter-spacing: 1px; }
        
        /* Tabel identitas nomor dan hal */
        .meta-table { width: auto; margin-bottom: 15px; }
        .meta-table td { vertical-align: top; padding: 2px 0; }
        
        .recipient { margin-bottom: 15px; line-height: 1.5; }
        
        .content { margin-top: 10px; }
        .content p { margin: 5px 0; }
        
        /* Pengaturan tabel isi (jadwal/identitas siswa) */
        .jadwal-table { margin-left: 40px; margin-bottom: 10px; }
        .jadwal-table td { padding: 3px 5px; }
        
        /* Menyembunyikan elemen tertentu saat diprint */
        @media print {
        #mobile-toggle { display: none !important; }
            .no-print { display: none !important; }
            body { padding: 20px 40px; }
        }
        
        /* Bar navigasi bantuan di layar (tombol cetak/kembali) */
        .action-bar {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 6px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: sans-serif;
            font-size: 12px;
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
    
    <div class="action-bar no-print">
        <button onclick="window.print()" class="btn btn-print">Cetak Sekarang</button>
        <a href="daftar_panggilan.php" class="btn btn-back">Kembali</a>
        
        <!-- Area Input untuk Mengedit Format Nomor Surat (berada di pojok kanan) -->
        <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 12px; font-family: sans-serif; color: #333; font-weight: bold;">Edit Format Nomor Surat:</label>
            <!-- Kotak input teks. Atribut onkeyup bertugas mengubah teks nomor surat pada kertas secara real-time saat pengguna mengetik -->
            <input type="text" 
                   value="<?php echo "421/ " . (!empty($p['nomor_urut']) ? htmlspecialchars($p['nomor_urut']) : '...') . " /SMAN.7- Bungo/" . $bulan_romawi . "/" . $tahun_surat; ?>" 
                   style="padding: 6px 12px; border-radius: 4px; border: 1px solid #cbd5e1; width: 350px; font-size: 13px; font-weight: 500;" 
                   onkeyup="document.getElementById('nomor_surat_cetak').innerText = this.value;">
        </div>
    </div>

    <div class="kop-surat">
        <div class="kop-logo-container">
            <?php if($link_logo_kiri): ?>
                <img src="<?php echo $link_logo_kiri; ?>" class="kop-logo">
            <?php else: ?>
                <img src="images/Logo_Resmi_Provinsi_Jambi.png?v=2" class="kop-logo" alt="Logo Provinsi Jambi">
            <?php endif; ?>
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
            <?php if($link_logo_kanan): ?>
                <img src="<?php echo $link_logo_kanan; ?>" class="kop-logo">
            <?php else: ?>
                <img src="images/logo_sma.png?v=2" class="kop-logo" alt="Logo SMAN 7">
            <?php endif; ?>
        </div>
    </div>
    <div class="kop-surat-border"></div>

    <div class="title">SURAT PANGGILAN ORANG TUA</div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
        <table class="meta-table" style="margin-bottom: 0;">
            <tr>
                <td style="width: 80px;">Nomor</td>
                <td style="width: 15px;">:</td>
                <td id="nomor_surat_cetak">421/ <?php echo !empty($p['nomor_urut']) ? htmlspecialchars($p['nomor_urut']) : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'; ?> /SMAN.7- Bungo/<?php echo $bulan_romawi; ?>/<?php echo $tahun_surat; ?></td>
            </tr>
            <tr>
                <td>Lampiran</td>
                <td>:</td>
                <td>-</td>
            </tr>
            <tr>
                <td>Perihal</td>
                <td>:</td>
                <td>Panggilan Orang Tua</td>
            </tr>
        </table>
        <div>
            Lubuk Landai, <?php echo $tgl_surat; ?>
        </div>
    </div>

    <div class="recipient" style="margin-left: 95px;">
        Kepada Yth<br>
        Bapak / Ibu / Sdr Orang Tua<br>
        Dari : <?php echo htmlspecialchars($p['nama_siswa']); ?><br>
        Di _<br>
        <span style="margin-left: 40px;">Tempat</span>
    </div>

    <div class="content">
        <p>Dengan Hormat,</p>
        <p style="text-indent: 40px; text-align: justify; line-height: 1.5;">Dengan ini kami mengharapkan kehadiran bapak / ibu / saudara / orang tua wali murid SMA Negeri 7 Bungo pada :</p>
        <table class="jadwal-table">
            <?php 
                if (!empty($p['tanggal_panggilan'])) {
                    $hari_inggris = date('l', strtotime($p['tanggal_panggilan']));
                    $hari_indo = [
                        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                    ];
                    $hari = $hari_indo[$hari_inggris];
                    $tgl_panggilan = tgl_indo($p['tanggal_panggilan']);
                } else {
                    $hari = "";
                    $tgl_panggilan = "";
                }

                if (!empty($p['jam_panggilan'])) {
                    $jam_panggilan = date('H:i', strtotime($p['jam_panggilan'])) . " WIB";
                } else {
                    $jam_panggilan = "";
                }
            ?>
            <tr>
                <td width="100">Hari</td>
                <td>: <?php echo !empty($hari) ? $hari : ''; ?></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: <?php echo !empty($tgl_panggilan) ? $tgl_panggilan : ''; ?></td>
            </tr>
            <tr>
                <td>Jam</td>
                <td>: <?php echo !empty($jam_panggilan) ? $jam_panggilan : ''; ?></td>
            </tr>
            <tr>
                <td>Tempat</td>
                <td>: <?php echo !empty($p['tempat']) ? htmlspecialchars($p['tempat']) : 'Ruang BK SMAN 7 Bungo'; ?></td>
            </tr>
        </table>
        
        <p style="margin-top: 15px;">Untuk membicarakan masalah anak kita yaitu :</p>
        <table style="margin-left: 20px; margin-top: 5px;">
            <tr>
                <td width="18" style="padding: 3px 0px; vertical-align: top; text-align: right;">1.</td>
                <td width="100" style="padding: 3px 5px;">Nama</td>
                <td style="padding: 3px 5px;">: ..........................................................................................................</td>
            </tr>
            <tr>
                <td style="padding: 3px 0px; vertical-align: top; text-align: right;">2.</td>
                <td style="padding: 3px 5px;">Kelas</td>
                <td style="padding: 3px 5px;">: ..........................................................................................................</td>
            </tr>
            <tr>
                <td style="padding: 3px 0px; vertical-align: top; text-align: right;">3.</td>
                <td style="padding: 3px 5px;">Masalah</td>
                <td style="padding: 3px 5px;">: ..........................................................................................................</td>
            </tr>
            <tr>
                <td style="padding: 3px 0px; vertical-align: top; text-align: right;">4.</td>
                <td colspan="2" style="padding: 3px 5px;">Hal - hal lain yang dirasa perlu</td>
            </tr>
        </table>

        <p style="text-indent: 40px; margin-top: 10px; text-align: justify; line-height: 1.5;">Demikian kami sangat mengharapkan kehadiran bapak / ibu / saudara orang tua / wali murid tepat waktunya, atas perhatiannya kami ucapkan terima kasih.</p>
    </div>

    <table style="width: 100%; margin-top: 15px; page-break-inside: avoid;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: left; vertical-align: top;">
                Lubuk Landai, <?php echo $tgl_surat; ?><br>
                Wali Kelas,<br>
                <div style="height: 60px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo !empty($p['nama_walikelas']) ? htmlspecialchars($p['nama_walikelas']) : '(...........................................)'; ?></span><br>
                NIP. <?php echo !empty($p['nip_walikelas']) ? htmlspecialchars($p['nip_walikelas']) : '-'; ?>
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


