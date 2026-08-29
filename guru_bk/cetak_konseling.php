<?php
// Memulai session dan memuat file koneksi database
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Memastikan pengguna yang login adalah Guru BK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;


// Memastikan parameter ID konseling dikirimkan di URL
if (!isset($_GET['id'])) {
    header("Location: konseling.php");
    exit();
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$user_id = $_SESSION['id'];

// Mengambil data lengkap konseling, data siswa, data guru BK, wali kelas, serta detail pelanggaran jika ada
$query = mysqli_query($koneksi, "
    SELECT kon.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas,
           g.nama_lengkap as nama_guru, g.nip as nip_guru,
           wk.nama_lengkap as nama_walikelas, wk.nip as nip_walikelas,
           jp.kategori, jp.nama_pelanggaran, jp.poin as poin_pelanggaran, cp.keterangan
    FROM konseling kon
    JOIN siswa s ON kon.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN guru g ON (kon.guru_id = g.id OR kon.guru_id = g.user_id)
    LEFT JOIN guru wk ON k.wali_kelas_id = wk.id
    LEFT JOIN catatan_pelanggaran cp ON kon.catatan_pelanggaran_id = cp.id
    LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    WHERE kon.id = '$id'
");
$p = mysqli_fetch_assoc($query);

// Jika data konseling tidak ditemukan, tampilkan pesan error
if (!$p) {
    die("Data konseling tidak ditemukan.");
}

// Menentukan pihak penandatangan surat RPL berdasarkan bobot angka poin pelanggaran yang dilaporkan:
// - Poin >= 50: Melibatkan Orang Tua / Wali (Tingkat Berat / SP)
// - Poin 25 - 49 (misal 30 poin): Melibatkan Wali Kelas, Guru BK, dan Siswa (Tingkat Sedang)
// - Poin < 25: Cukup Guru BK dan Siswa (Tingkat Ringan)
if ($p['jenis_konseling'] == 'Tindak Lanjut' && isset($p['poin_pelanggaran'])) {
    $poin_kasus = (int)$p['poin_pelanggaran'];
    if ($poin_kasus >= 50) {
        $p['kategori'] = 'Berat';
    } elseif ($poin_kasus >= 25) {
        $p['kategori'] = 'Sedang';
    } elseif ($poin_kasus > 0) {
        $p['kategori'] = 'Ringan';
    }
}

// Menentukan Jenis Layanan: jika Tindak Lanjut Pelanggaran dipetakan menjadi 'Konferensi Kasus', 
// sedangkan jika Bimbingan Mandiri dipetakan menjadi 'Konseling Individu'
$jenis_layanan = $p['jenis_konseling'] == 'Tindak Lanjut' ? 'Konferensi Kasus' : 'Konseling Individu';
$kategori_masalah = '';

// Jika jenis konseling adalah Mandiri, ambil kategori masalah dari riwayat jadwal pengajuan bimbingan siswa
if ($p['jenis_konseling'] == 'Mandiri') {
    $q_kat = mysqli_query($koneksi, "
        SELECT kategori_masalah 
        FROM jadwal_bimbingan 
        WHERE siswa_id='{$p['siswa_id']}' 
          AND guru_id='{$p['guru_id']}' 
          AND status='Selesai' 
          AND topik='" . mysqli_real_escape_string($koneksi, $p['topik_permasalahan']) . "' 
        ORDER BY id DESC LIMIT 1
    ");
    $kat = mysqli_fetch_assoc($q_kat);
    if ($kat) {
        $kategori_masalah = $kat['kategori_masalah'];
    } else {
        // Fallback: Jika tidak ketemu topik spesifik, ambil pengajuan terakhir yang berstatus Selesai
        $q_kat = mysqli_query($koneksi, "
            SELECT kategori_masalah 
            FROM jadwal_bimbingan 
            WHERE siswa_id='{$p['siswa_id']}' 
              AND guru_id='{$p['guru_id']}' 
              AND status='Selesai' 
            ORDER BY id DESC LIMIT 1
        ");
        $kat = mysqli_fetch_assoc($q_kat);
        if ($kat) {
            $kategori_masalah = $kat['kategori_masalah'];
        }
    }
    // Merapikan tampilan kategori masalah dengan mengganti tanda "/" atau " / " menjadi kata " dan " agar lebih profesional
    if (!empty($kategori_masalah)) {
        $kategori_masalah = str_replace(array(' / ', '/'), ' dan ', $kategori_masalah);
    }
}

// Menentukan Bidang Bimbingan
if (!empty($p['bidang_bimbingan'])) {
    $bidang_bimbingan = $p['bidang_bimbingan'];
} else {
    $bidang_bimbingan = 'Pribadi';
    if ($p['jenis_konseling'] == 'Mandiri' && !empty($kategori_masalah)) {
        if (stripos($kategori_masalah, 'Belajar') !== false || stripos($kategori_masalah, 'Akademik') !== false) {
            $bidang_bimbingan = 'Belajar';
        } elseif (stripos($kategori_masalah, 'Sosial') !== false || stripos($kategori_masalah, 'Pertemanan') !== false) {
            $bidang_bimbingan = 'Sosial';
        } elseif (stripos($kategori_masalah, 'Karir') !== false || stripos($kategori_masalah, 'Masa Depan') !== false) {
            $bidang_bimbingan = 'Karir';
        } else {
            $bidang_bimbingan = 'Pribadi';
        }
    }
}

// Menetapkan nilai default fungsi dan tujuan layanan untuk cetak RPL
$fungsi_layanan = 'Pengentasan';
$tujuan_layanan = 'Perubahan perilaku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rencana Pelaksanaan Layanan - <?php echo $p['nama_siswa']; ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body, table, tr, td, p, div { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 12pt; }
        body { line-height: 1.5; padding: 40px 60px; }
        
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
        
        .title { text-align: center; font-size: 20px; margin-bottom: 20px; letter-spacing: 1px; }
        
        table.layout { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        table.layout td { vertical-align: top; padding: 3px 0; }
        table.layout td:first-child { width: 220px; }
        table.layout td:nth-child(2) { width: 20px; }
        
        .signature-table { width: 100%; margin-top: 30px; border-collapse: collapse; text-align: center; }
        .signature-table td { vertical-align: top; padding: 10px; }
        .signature-space { height: 55px; }
        
        @media print {
        #mobile-toggle { display: none !important; }
            .no-print { display: none !important; }
            body { padding: 20px 40px; }
            thead { display: table-row-group; }
        }
        
        .action-bar {
            background: #f3f4f6; padding: 10px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center;
        }
        .btn {
            padding: 6px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; font-family: sans-serif; font-size: 12px; border: none;
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
        <button onclick="window.print()" class="btn btn-print">Cetak Sekarang</button>
        <!-- Tombol kembali ke halaman tabel data -->
        <a href="konseling.php" class="btn btn-back">Kembali</a>
        <?php if($p['jenis_konseling'] == 'Tindak Lanjut' && $p['kategori'] == 'Berat'): ?>
        <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
            <label style="font-size: 12px; font-family: sans-serif; color: #333; font-weight: bold;">Ketik Nama Ortu/Wali:</label>
            <!-- Kotak input teks. Atribut onkeyup mengubah nama ortu/wali pada surat secara real-time -->
            <input type="text" 
                   placeholder="Bpk. Budi..." 
                   style="padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; width: 200px; font-size: 13px;" 
                   onkeyup="document.getElementById('nama_ortu_cetak').innerText = this.value ? this.value : '(...........................................)';">
        </div>
        <?php endif; ?>
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

    <div class="title">Rencana Pelaksanaan Layanan</div>

    <table class="layout">
        <?php if($p['jenis_konseling'] == 'Tindak Lanjut'): ?>
        <tr>
            <td>Topik Permasalahan</td>
            <td>:</td>
            <td><?php 
                $topik = !empty($p['topik_permasalahan']) ? $p['topik_permasalahan'] : $p['nama_pelanggaran'];
                echo $topik ? htmlspecialchars($topik) : '-'; 
            ?></td>
        </tr>
        <?php else: ?>

        <tr>
            <td>Topik Permasalahan</td>
            <td>:</td>
            <td><?php echo $p['topik_permasalahan'] ? htmlspecialchars($p['topik_permasalahan']) : nl2br(htmlspecialchars($p['masalah'])); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>Bidang Bimbingan</td>
            <td>:</td>
            <td><?php echo $bidang_bimbingan; ?></td>
        </tr>
        <tr>
            <td>Jenis Layanan</td>
            <td>:</td>
            <td><?php echo $jenis_layanan; ?></td>
        </tr>
        <tr>
            <td>Fungsi Layanan</td>
            <td>:</td>
            <td><?php echo $fungsi_layanan; ?></td>
        </tr>
        <tr>
            <td>Tujuan Layanan</td>
            <td>:</td>
            <td><?php echo $tujuan_layanan; ?></td>
        </tr>
        <tr>
            <td colspan="3" style="height: 20px;"></td>
        </tr>
        <tr>
            <td>Sasaran Layanan</td>
            <td>:</td>
            <td><?php echo $p['nama_siswa']; ?>, <?php echo $p['nama_kelas']; ?></td>
        </tr>

        <tr>
            <td colspan="3" style="height: 20px;"></td>
        </tr>
        <tr>
            <td>Gambaran Ringkasan<br>Masalah</td>
            <td>:</td>
            <td style="text-align: justify; text-align-last: left; line-height: 1.6; word-break: normal; overflow-wrap: break-word;">
                <?php if (!empty($p['masalah'])): ?>
                    <?php 
                        $teks_masalah = $p['masalah'];
                        // Bersihkan teks otomatis dari sistem lama agar tidak muncul ganda
                        if (strpos($teks_masalah, 'Jenis Pelanggaran:') !== false && strpos($teks_masalah, 'Keterangan:') !== false) {
                            $teks_masalah = preg_replace('/Jenis Pelanggaran:.*?\nKeterangan: /s', '', $teks_masalah);
                        }
                        echo nl2br(htmlspecialchars(trim($teks_masalah))); 
                    ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
        </tr>

    </table>

    <?php 
        // Format tanggal indonesia untuk tanda tangan
        $bulan = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
        $tgl = date('d', strtotime($p['tanggal']));
        $bln = $bulan[(int)date('m', strtotime($p['tanggal']))];
        $thn = date('Y', strtotime($p['tanggal']));
        $tgl_lengkap = $tgl . ' ' . $bln . ' ' . $thn;
    ?>
    <?php if($p['jenis_konseling'] == 'Tindak Lanjut' && $p['kategori'] == 'Berat'): ?>
    <table class="signature-table" style="width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top; padding-bottom: 30px;">
                Mengetahui,<br>
                Orang Tua / Wali
                <div class="signature-space" style="height: 55px;"></div>
                <span id="nama_ortu_cetak">(...........................................)</span>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: top; padding-bottom: 30px; padding-left: 50px;">
                Lubuk Landai, <?php echo $tgl_lengkap; ?><br>
                Guru Bimbingan dan Konseling,
                <div class="signature-space" style="height: 55px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['nama_guru']); ?></span><br>
                NIP. <?php echo $p['nip_guru'] ? $p['nip_guru'] : '-'; ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center; vertical-align: top;">
                Mengetahui,<br>
                Konseli
                <div class="signature-space" style="height: 55px;"></div>
                <span><?php echo ucwords(strtolower($p['nama_siswa'])); ?></span>
            </td>
        </tr>
    </table>
    <?php elseif($p['jenis_konseling'] == 'Tindak Lanjut' && $p['kategori'] == 'Sedang'): ?>
    <!-- SEDANG: 3 Tanda Tangan (Konseli + Guru BK + Wali Kelas) -->
    <table class="signature-table" style="width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top; padding-bottom: 30px;">
                Mengetahui,<br>
                Konseli
                <div class="signature-space" style="height: 55px;"></div>
                <span><?php echo ucwords(strtolower($p['nama_siswa'])); ?></span>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: top; padding-bottom: 30px; padding-left: 50px;">
                Lubuk Landai, <?php echo $tgl_lengkap; ?><br>
                Guru Bimbingan dan Konseling,
                <div class="signature-space" style="height: 55px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['nama_guru']); ?></span><br>
                NIP. <?php echo $p['nip_guru'] ? $p['nip_guru'] : '-'; ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center; vertical-align: top;">
                <div style="display: inline-block; text-align: left;">
                    Mengetahui,<br>
                    Wali Kelas
                    <div class="signature-space" style="height: 55px;"></div>
                    <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo !empty($p['nama_walikelas']) ? htmlspecialchars($p['nama_walikelas']) : '(...........................................)'; ?></span><br>
                    NIP. <?php echo $p['nip_walikelas'] ? $p['nip_walikelas'] : '-'; ?>
                </div>
            </td>
        </tr>
    </table>
    <?php elseif($p['jenis_konseling'] == 'Tindak Lanjut' && $p['kategori'] == 'Ringan'): ?>
    <!-- RINGAN: 2 Tanda Tangan (Konseli + Guru BK) -->
    <table class="signature-table" style="width: 100%; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top;">
                Mengetahui,<br>
                Konseli
                <div class="signature-space" style="height: 55px;"></div>
                <span><?php echo ucwords(strtolower($p['nama_siswa'])); ?></span>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: top; padding-left: 50px;">
                Lubuk Landai, <?php echo $tgl_lengkap; ?><br>
                Guru Bimbingan dan Konseling,
                <div class="signature-space" style="height: 55px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['nama_guru']); ?></span><br>
                NIP. <?php echo $p['nip_guru'] ? $p['nip_guru'] : '-'; ?>
            </td>
        </tr>
    </table>
    <?php else: ?>
    <!-- BIMBINGAN MANDIRI: 2 Tanda Tangan (Konseli + Guru BK) -->
    <table class="signature-table" style="width: 100%; margin-top: 35px; border-collapse: collapse; page-break-inside: avoid; break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top;">
                Mengetahui,<br>
                Konseli
                <div class="signature-space" style="height: 55px;"></div>
                <span><?php echo ucwords(strtolower($p['nama_siswa'])); ?></span>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: top; padding-left: 50px;">
                Lubuk Landai, <?php echo $tgl_lengkap; ?><br>
                Guru Bimbingan dan Konseling,
                <div class="signature-space" style="height: 55px;"></div>
                <span style="border-bottom: 1.5px solid #000; font-weight: bold; display: inline-block; padding-bottom: 1px; line-height: 1.2;"><?php echo htmlspecialchars($p['nama_guru']); ?></span><br>
                NIP. <?php echo $p['nip_guru'] ? $p['nip_guru'] : '-'; ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>
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
