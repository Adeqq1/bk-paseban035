<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// =========================================================================
// 1. CEK OTENTIKASI SISTEM & HAK AKSES USER (ROLE SISWA)
// Mengamankan halaman agar hanya pengguna berstatus 'siswa' yang dapat mengakses portal ini.
// =========================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php"); // Jika bukan siswa, alihkan ke login utama
    exit();
}

$user_id = $_SESSION['id'];

// =========================================================================
// 2. PENARIKAN DATA SISWA & ID UTAMA
// Mengambil data profil siswa dan ID internal untuk penarikan riwayat.
// =========================================================================
$query_siswa = mysqli_query($koneksi, "SELECT id, nama_lengkap, foto FROM siswa WHERE user_id = '$user_id'");
$siswa = mysqli_fetch_assoc($query_siswa);
$siswa_id = $siswa['id'];

// =========================================================================
// 3. FILTER SEMESTER & TAHUN AJARAN
// Menentukan rentang tanggal semester (Ganjil: Jul-Des, Genap: Jan-Jun) berdasarkan filter URL.
// =========================================================================
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $label_semester = "Ganjil (Juli - Desember)";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $label_semester = "Genap (Januari - Juni)";
}

// =========================================================================
// 4. QUERY PENAMPILAN RIWAYAT CATATAN PELANGGARAN SISWA
// Mengambil seluruh catatan pelanggaran siswa sesuai dengan filter rentang semester & tahun.
// =========================================================================
$query_pelanggaran = mysqli_query($koneksi, "
    SELECT cp.tanggal, jp.nama_pelanggaran, jp.poin, cp.keterangan, cp.pelapor_asli, g.nama_lengkap as nama_wali
    FROM catatan_pelanggaran cp
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN guru g ON cp.guru_id = g.id
    WHERE cp.siswa_id = '$siswa_id' AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
    ORDER BY cp.tanggal ASC
");

// =========================================================================
// 5. QUERY PENAMPILAN RIWAYAT BIMBINGAN & KONSELING SISWA
// Mengambil seluruh riwayat konseling (Mandiri maupun Tindak Lanjut) sesuai filter tanggal.
// =========================================================================
$query_bimbingan = mysqli_query($koneksi, "
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Riwayat | BK SMA 07 Bungo</title>
    <!-- File CSS Utama Admin & CDN Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Gaya judul seksi riwayat */
        .section-title { 
            background: #ffffff; 
            padding: 14px 20px; 
            border-radius: 10px; 
            margin-top: 2rem; 
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #2563eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- =================================================================== -->
    <!-- NAVIGATION SIDEBAR UTAMA (PANEL SISWA)                              -->
    <!-- =================================================================== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Siswa Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="riwayat.php" class="active"><i class="fas fa-history"></i> Riwayat & Arsip</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Footer Sidebar Profil Siswa Aktif -->
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($siswa['nama_lengkap'] ?? 'Siswa', 'S'); ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars(ucwords(strtolower($siswa['nama_lengkap']))); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Siswa SMAN 7</div>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- KONTEN UTAMA ARSIP RIWAYAT SISWA                                    -->
    <!-- =================================================================== -->
    <div class="main-content">
        <!-- Banner Header Riwayat -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-history" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 700; color: white;">Riwayat & Arsip Saya</h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Pantau rekam jejak bimbingan konseling dan catatan poin siswa.</p>
                </div>
            </div>
        </div>

        <!-- Kartu Filter Periode Semester & Tahun Ajaran -->
        <div class="data-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <!-- Filter Semester -->
                <div style="flex: 2 1 260px; min-width: 240px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Semester</label>
                    <select name="semester" class="form-control" style="width: 100%; padding: 0.6rem 2.2rem 0.6rem 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Semester Ganjil (Juli - Desember)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Semester Genap (Januari - Juni)</option>
                    </select>
                </div>

                <!-- Filter Tahun -->
                <div style="flex: 0 0 100px; min-width: 90px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Tahun</label>
                    <select name="tahun" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <?php 
                        $thn_skrg = date('Y');
                        for($t = $thn_skrg; $t >= $thn_skrg - 3; $t--): 
                        ?>
                            <option value="<?php echo $t; ?>" <?php echo $tahun == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Tombol Submit Filter -->
                <div style="display: flex; gap: 8px; height: 42px;">
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.25rem; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                    <a href="riwayat.php" class="btn btn-secondary" style="padding: 0 1.25rem; text-decoration: none; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px; background: #e2e8f0; color: #475569;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- =================================================================== -->
        <!-- SEKSI 1: RIWAYAT CATATAN PELANGGARAN SISWA                         -->
        <!-- =================================================================== -->
        <div class="section-title" style="border-left-color: #ef4444;">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0; color: #1e293b;">
                <i class="fas fa-exclamation-triangle" style="color: #dc2626; margin-right: 8px;"></i> Riwayat Catatan Pelanggaran
            </h3>
            <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px;">
                Periode <?php echo $label_semester . ' ' . $tahun; ?>
            </span>
        </div>

        <div class="data-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 55px; text-align: center;">NO</th>
                            <th>TANGGAL</th>
                            <th>NAMA PELANGGARAN</th>
                            <th style="text-align: center;">POIN</th>
                            <th>PELAPOR ASLI</th>
                            <th>WALI KELAS</th>
                            <th>KETERANGAN / DETAIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no_p = 1;
                        if (mysqli_num_rows($query_pelanggaran) > 0):
                            while ($row = mysqli_fetch_assoc($query_pelanggaran)):
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no_p++; ?></td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></small></td>
                            <td><span style="color: #334155; font-weight: 400;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></span></td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                    +<?php echo $row['poin']; ?> Poin
                                </span>
                            </td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo !empty($row['pelapor_asli']) ? htmlspecialchars($row['pelapor_asli']) : '—'; ?></small></td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo htmlspecialchars($row['nama_wali'] ?? '-'); ?></small></td>
                            <td style="max-width: 240px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;" title="<?php echo !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '—'; ?>">
                                    <?php echo !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '—'; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">Tidak ada catatan pelanggaran pada periode semester ini. Selamat!</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- SEKSI 2: RIWAYAT BIMBINGAN & KONSELING SISWA                        -->
        <!-- =================================================================== -->
        <div class="section-title" style="border-left-color: #2563eb; margin-top: 3rem;">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0; color: #1e293b;">
                <i class="fas fa-comments" style="color: #2563eb; margin-right: 8px;"></i> Riwayat Sesi Bimbingan & Konseling
            </h3>
            <span class="badge" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px;">
                Periode <?php echo $label_semester . ' ' . $tahun; ?>
            </span>
        </div>

        <div class="data-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 55px; text-align: center;">NO</th>
                            <th>TANGGAL SESI</th>
                            <th>JENIS KONSELING</th>
                            <th>POKOK MASALAH / TOPIK</th>
                            <th>HASIL & SOLUSI KONSELING</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no_b = 1;
                        if (mysqli_num_rows($query_bimbingan) > 0):
                            while ($row = mysqli_fetch_assoc($query_bimbingan)):
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no_b++; ?></td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></small></td>
                            <td>
                                <span class="badge badge-info" style="background: #e0f2fe; color: #0369a1; font-weight: 500;"><?php echo htmlspecialchars($row['jenis_konseling'] ?? 'Bimbingan Mandiri'); ?></span>
                            </td>
                            <td style="max-width: 220px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;" title="<?php echo htmlspecialchars($row['masalah']); ?>">
                                    <?php echo htmlspecialchars($row['masalah']); ?>
                                </div>
                            </td>
                            <td style="max-width: 240px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;" title="<?php echo !empty($row['solusi']) ? htmlspecialchars($row['solusi']) : 'Sedang dalam proses penanganan'; ?>">
                                    <?php echo !empty($row['solusi']) ? htmlspecialchars($row['solusi']) : 'Sedang dalam proses penanganan'; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada riwayat sesi bimbingan konseling pada periode semester ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
