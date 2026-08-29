<?php
// Memulai session PHP
session_start();
// Memuat file konfigurasi koneksi database
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Proteksi Halaman: Hanya boleh diakses oleh Guru BK yang terautentikasi
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;


// Memastikan parameter ID terdefinisi di URL
if (!isset($_GET['id'])) {
    header("Location: alih_kasus.php");
    exit();
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$user_id = $_SESSION['id'];

// Pengambilan data rujukan alih tangan kasus
$query = mysqli_query($koneksi, "
    SELECT ak.*, s.nama_lengkap as nama_siswa, s.jenis_kelamin, s.alamat as alamat_siswa, k.nama_kelas,
           g.nama_lengkap as nama_guru, g.nip as nip_guru
    FROM alih_kasus ak
    JOIN siswa s ON ak.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN guru g ON (ak.guru_id = g.id OR ak.guru_id = g.user_id)
    WHERE ak.id = '$id'
");
$p = mysqli_fetch_assoc($query);

// Validasi keberadaan data
if (!$p) {
    die("Data alih tangan kasus tidak ditemukan.");
}

// Format Nomor Surat
function format_nomor_surat($nomor_urut, $tanggal) {
    $num = !empty($nomor_urut) ? htmlspecialchars($nomor_urut) : '___';
    $time = !empty($tanggal) ? strtotime($tanggal) : time();
    $bulan_num = date('n', $time);
    $tahun = date('Y', $time);
    $romawi = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $bulan_romawi = $romawi[$bulan_num - 1] ?? 'VII';
    return '421/ ' . $num . ' /SMAN.7- Bungo/' . $bulan_romawi . '/' . $tahun;
}

// Helper Nama Hari dalam Bahasa Indonesia
function get_nama_hari($tanggal) {
    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $day_en = date('l', strtotime($tanggal));
    return $days[$day_en] ?? '-';
}

// Helper Tanggal & Bulan
function get_tgl_bulan($tanggal) {
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $time = strtotime($tanggal);
    $d = date('j', $time);
    $m = date('n', $time);
    return $d . ' ' . ($months[$m] ?? '');
}

// Helper Tahun
function get_tahun($tanggal) {
    return date('Y', strtotime($tanggal));
}

// Konversi jenis kelamin
$jenis_kelamin_siswa = $p['jenis_kelamin'] == 'L' ? 'Laki-laki' : ($p['jenis_kelamin'] == 'P' ? 'Perempuan' : '-');
$tanggal_surat = !empty($p['tanggal']) ? $p['tanggal'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Alih Tangan Kasus - <?php echo htmlspecialchars($p['nama_siswa']); ?></title>
    <style>
        /* Mengatur ukuran kertas saat dicetak (A4) */
        @page { size: A4; margin: 0; }
        
        * { box-sizing: border-box; }

        /* Pengaturan dasar font dan spasi selaras Surat Panggilan Orang Tua */
        body, table, tr, td, p, div { 
            font-family: 'Times New Roman', Times, serif; 
            color: #000; 
            font-size: 12pt; 
            line-height: 1.5; 
        }

        body { 
            background: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        /* Bar Navigasi Aksi di Luar Kertas (No-Print) */
        .top-action-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .btn-group-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edit-number-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .edit-number-right label {
            font-size: 12px;
            font-family: system-ui, -apple-system, sans-serif;
            color: #334155;
            font-weight: 600;
        }
        .input-number-custom {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            width: 350px;
            font-size: 13px;
            font-weight: 500;
            font-family: system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background: #ffffff;
            transition: border-color 0.2s;
        }
        .input-number-custom:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 6px 15px; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none; 
            font-family: system-ui, -apple-system, sans-serif; 
            font-size: 12px; 
            border: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back { background: #6b7280; color: #fff; }
        .btn-back:hover { background: #475569; }

        /* Lembar Kertas Surat (A4 Sheet Canvas) */
        .paper-sheet {
            background: #ffffff;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px 45px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
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
            margin-bottom: 10px;
        }
        .kop-logo-container { width: 90px; height: 95px; display: flex; align-items: center; justify-content: center; }
        .kop-logo { max-width: 90px; max-height: 95px; }
        .kop-text { text-align: center; flex: 1; padding: 0 10px; }
        .kop-text h3 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .kop-text h2 { margin: 2px 0; font-size: 18pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        
        .nomor-surat-left {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .title-container { 
            text-align: center; 
            margin-bottom: 12px; 
        }
        .title-main {
            font-size: 14pt; 
            font-weight: bold; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .title-sub {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .date-line {
            margin-bottom: 10px;
            font-size: 12pt;
        }

        .section-header {
            font-weight: normal;
            margin-bottom: 4px;
            font-size: 12pt;
        }

        table.layout-table { 
            width: 100%; 
            margin-bottom: 10px; 
            border-collapse: collapse; 
        }
        table.layout-table td { 
            vertical-align: top; 
            padding: 2px 0; 
            font-size: 12pt;
        }
        table.layout-table td.indent {
            width: 30px;
        }
        table.layout-table td.label { 
            width: 180px; 
        }
        table.layout-table td.colon { 
            width: 20px; 
        }
        
        .multiline-value {
            line-height: 1.4;
            text-align: justify;
        }

        .closing-text {
            margin-top: 10px;
            margin-bottom: 15px;
            line-height: 1.4;
            text-align: justify;
            text-indent: 40px;
            font-size: 12pt;
        }

        .signature-section { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 12pt;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .signature-section td { 
            vertical-align: top; 
            padding: 2px 10px; 
            font-size: 12pt;
        }
        .signature-space { 
            height: 45px; 
        }

        /* Aturan Cetak / Print */
        @media print {
        #mobile-toggle { display: none !important; }
            .no-print { display: none !important; }
            body { 
                background: #ffffff !important; 
                padding: 15px 40px !important; 
                margin: 0 !important;
            }
            .paper-sheet {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
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
    
    <!-- BARIS KONTROL LUAR KERTAS (HANYA MUNCUL DI LAYAR, TIDAK IKUT DICETAK) -->
    <!-- Area Tombol Aksi (Tidak akan ikut tercetak di kertas) -->
    <div class="top-action-bar no-print">
        <div class="btn-group-left">
            <!-- Tombol memicu dialog print pada browser -->
            <button onclick="window.print()" class="btn btn-print">Cetak Sekarang</button>
            <!-- Tombol kembali ke halaman tabel data -->
            <a href="alih_kasus.php" class="btn btn-back">Kembali</a>
        </div>
        
        <!-- Area Input untuk Mengedit Format Nomor Surat -->
        <div class="edit-number-right">
            <label for="input_nomor_surat">Edit Format Nomor Surat:</label>
            <!-- Kotak input teks. Atribut onkeyup bertugas mengubah teks nomor surat pada kertas secara real-time saat pengguna mengetik -->
            <input type="text" 
                   id="input_nomor_surat" 
                   class="input-number-custom" 
                   value="<?php echo format_nomor_surat($p['nomor_urut'], $tanggal_surat); ?>" 
                   onkeyup="document.getElementById('nomor_surat_cetak').innerText = this.value;" 
                   oninput="document.getElementById('nomor_surat_cetak').innerText = this.value;">
        </div>
    </div>

    <!-- LEMBAR FISIK KERTAS SURAT (A4 SHEET) -->
    <div class="paper-sheet">
        <!-- Kop Surat Resmi -->
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

        <!-- Nomor Surat Kiri -->
        <div class="nomor-surat-left">
            Nomor : <span id="nomor_surat_cetak"><?php echo format_nomor_surat($p['nomor_urut'], $tanggal_surat); ?></span>
        </div>

        <!-- Judul Berita Acara -->
        <div class="title-container">
            <div class="title-main">BERITA ACARA ALIH TANGAN KASUS</div>
            <div class="title-sub">BIMBINGAN DAN KONSELING</div>
        </div>

        <!-- Waktu Pelaksanaan -->
        <div class="date-line">
            Pada hari ini <span style="display:inline-block; min-width:120px;"><?php echo get_nama_hari($tanggal_surat); ?></span> tanggal: <span style="display:inline-block; min-width:140px;"><?php echo get_tgl_bulan($tanggal_surat); ?></span> tahun: <?php echo get_tahun($tanggal_surat); ?>
        </div>

        <div class="section-header">Telah dilaksanakan alih tangan kasus :</div>

        <!-- Detail Siswa -->
        <table class="layout-table">
            <tr>
                <td class="indent"></td>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td><?php echo htmlspecialchars($p['nama_siswa']); ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Jenis kelamin</td>
                <td class="colon">:</td>
                <td><?php echo htmlspecialchars($jenis_kelamin_siswa); ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Kelas</td>
                <td class="colon">:</td>
                <td><?php echo htmlspecialchars($p['nama_kelas'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Alamat</td>
                <td class="colon">:</td>
                <td><?php echo !empty($p['alamat_siswa']) ? htmlspecialchars($p['alamat_siswa']) : '-'; ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Masalah</td>
                <td class="colon">:</td>
                <td class="multiline-value"><?php echo nl2br(htmlspecialchars($p['ringkasan_masalah'])); ?></td>
            </tr>
        </table>

        <div class="section-header" style="margin-top: 15px;">Dari guru BK kepada :</div>

        <!-- Detail Penerima Kasus -->
        <table class="layout-table">
            <tr>
                <td class="indent"></td>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td><?php echo htmlspecialchars($p['penerima_kasus']); ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td><?php echo !empty($p['jabatan_penerima']) ? htmlspecialchars($p['jabatan_penerima']) : '-'; ?></td>
            </tr>
            <tr>
                <td class="indent"></td>
                <td class="label">Alamat</td>
                <td class="colon">:</td>
                <td><?php echo !empty($p['alamat_penerima']) ? htmlspecialchars($p['alamat_penerima']) : '-'; ?></td>
            </tr>
        </table>

        <!-- Paragraf Penutup -->
        <div class="closing-text">
            Demikianlah berita acara alih tangan kasus ini dibuat dengan sebenarnya tanpa ada paksaan dari pihak manapun, untuk dapat dipergunakan sebagaimana mestinya.
        </div>

        <!-- Tabel Tanda Tangan (3 Tanda Tangan Sejajar & Tanpa Garis Bawah Penerima Kasus) -->
        <table class="signature-section" style="width: 100%; border-collapse: collapse; page-break-inside: avoid; margin-top: 25px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div style="display: flex; flex-direction: column; justify-content: space-between; height: 160px; width: fit-content; margin: 0 auto; text-align: left;">
                        <div>
                            Guru Bimbingan dan Konseling,
                        </div>
                        <div>
                            <span style="display: inline-block; border-bottom: 1.5px solid #000; font-weight: bold; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['nama_guru']); ?></span><br>
                            NIP. <?php echo htmlspecialchars($p['nip_guru'] ? $p['nip_guru'] : '...................................................'); ?>
                        </div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div style="display: flex; flex-direction: column; justify-content: space-between; height: 160px; width: fit-content; margin: 0 auto; text-align: left;">
                        <div>
                            Lubuk Landai, <?php echo tgl_indo($tanggal_surat); ?><br>
                            Penerima Kasus,<br>
                            <?php echo !empty($p['jabatan_penerima']) ? htmlspecialchars($p['jabatan_penerima']) : ''; ?>
                        </div>
                        <div>
                            <span style="display: inline-block; font-weight: bold; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['penerima_kasus']); ?></span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center; vertical-align: top; padding-top: 10px;">
                    <div style="display: inline-block; text-align: left;">
                        Diketahui,<br>
                        Kepala Sekolah,
                        <div class="signature-space" style="height: 60px;"></div>
                        <span style="display: inline-block; border-bottom: 1.5px solid #000; font-weight: bold; padding-bottom: 1px; line-height: 1.2;"><?php echo !empty($p['nama_kepsek']) ? htmlspecialchars($p['nama_kepsek']) : '( ................................................... )'; ?></span><br>
                        NIP. <?php echo !empty($p['nip_kepsek']) ? htmlspecialchars($p['nip_kepsek']) : '...................................................'; ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>
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
