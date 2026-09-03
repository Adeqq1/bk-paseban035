<?php
/**
 * ====================================================================================
 * MODUL REKAPITULASI STATUS DISIPLIN SISWA - PANEL WALI KELAS (BK SMA 07 Bungo SMAN 7 BUNGO)
 * ====================================================================================
 * Halaman ini menampilkan rekapitulasi poin akumulasi kedisiplinan serta tingkat
 * status disiplin (Baik, Perlu Perhatian, Sangat Kritis) bagi seluruh siswa perwalian.
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN (SECURITY CHECK): Memastikan pengguna berstatus 'wali_kelas'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'wali_kelas') {
    header("Location: ../index.php");
    exit();
}

// 4. MENGAMBIL DATA GURU & KELAS PERWALIAN
$user_id = $_SESSION['id'];

// Ambil data guru dari database
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$user_id'");
$guru       = mysqli_fetch_assoc($query_guru);
$guru_id    = $guru['id'] ?? 0;

// Format nama guru dan penulisan gelar resmi
$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? ''));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// Ambil data kelas perwalian
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas       = mysqli_fetch_assoc($query_kelas);

// 5. FILTER SEMESTER & TAHUN AJARAN (UNTUK RESET POIN SEMESTER)
$semester        = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun           = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';

if ($semester == '1') {
    $start_date     = "$tahun-07-01";
    $end_date       = "$tahun-12-31";
    $label_semester = "Semester Ganjil " . $tahun;
} else {
    $start_date     = "$tahun-01-01";
    $end_date       = "$tahun-06-30";
    $label_semester = "Semester Genap " . $tahun;
}

// 6. AMBIL DAFTAR SISWA KELAS INI UNTUK DROPDOWN FILTER
$list_siswa = [];
if ($kelas) {
    $kelas_id = $kelas['id'];
    $q_siswa  = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM siswa WHERE kelas_id = '$kelas_id' AND status = 'aktif' ORDER BY nama_lengkap ASC");
    while ($sw = mysqli_fetch_assoc($q_siswa)) {
        $list_siswa[] = $sw;
    }
}

// 7. QUERY REKAPITULASI POIN KEDISIPLINAN SISWA PERWALIAN
if ($kelas) {
    $kelas_id       = $kelas['id'];
    $where_siswa    = $filter_siswa_id !== '' ? "AND s.id = '$filter_siswa_id'" : "";
    $query_disiplin = mysqli_query($koneksi, "
        SELECT s.id, s.nisn, s.nama_lengkap, s.jenis_kelamin, 
               COALESCE(SUM(jp.poin), 0) as total_poin,
               COUNT(cp.id) as total_laporan
        FROM siswa s
        LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
        LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        WHERE s.kelas_id = '$kelas_id' AND s.status = 'aktif' $where_siswa
        GROUP BY s.id, s.nisn, s.nama_lengkap, s.jenis_kelamin
        ORDER BY total_poin DESC, total_laporan DESC, s.nama_lengkap ASC
    ");
} else {
    $query_disiplin = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Disiplin Siswa | BK SMA 07 Bungo</title>
    
    <!-- File CSS Utama & CDN Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR NAVIGASI PANEL WALI KELAS -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Wali Kelas Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="siswa_perwalian.php"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
            <li><a href="status_laporan.php"><i class="fas fa-tasks"></i> Status Laporan</a></li>
            <li><a href="status_disiplin.php" class="active"><i class="fas fa-user-shield"></i> Status Disiplin</a></li>
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($nama_guru ?? $guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'Wali Kelas', 'W'); ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($nama_guru); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Wali Kelas <?php echo htmlspecialchars($kelas['nama_kelas'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <!-- AREA KONTEN UTAMA -->
    <div class="main-content">
        <!-- Header Banner Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Status Disiplin Siswa</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Rekapitulasi akumulasi poin kedisiplinan siswa kelas <?php echo htmlspecialchars($kelas['nama_kelas'] ?? '-'); ?>.</p>
                </div>
            </div>
        </div>

        <!-- FORM FILTER DATA STATUS DISIPLIN -->
        <div class="data-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                
                <!-- Filter 1: Nama Siswa Perwalian -->
                <div style="flex: 2 1 240px; min-width: 180px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Nama Siswa</label>
                    <select name="siswa_id" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="">-- Semua Siswa --</option>
                        <?php foreach ($list_siswa as $sw): ?>
                            <option value="<?php echo $sw['id']; ?>" <?php echo $filter_siswa_id == $sw['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sw['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter 2: Semester -->
                <div style="flex: 1 1 175px; min-width: 160px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Semester</label>
                    <select name="semester" class="form-control" style="width: 100%; padding: 0.6rem 2rem 0.6rem 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>

                <!-- Filter 3: Tahun -->
                <div style="flex: 0 0 95px; min-width: 85px;">
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

                <!-- Tombol Submit & Reset Filter -->
                <div style="display: flex; gap: 8px; height: 42px;">
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.25rem; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($filter_siswa_id) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                    <a href="status_disiplin.php" class="btn btn-secondary" style="padding: 0 1.25rem; text-decoration: none; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px; background: #e2e8f0; color: #475569;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TABEL REKAPITULASI STATUS KEDISIPLINAN SISWA -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-user-shield"></i> Rekapitulasi Poin Kedisiplinan (<?php echo htmlspecialchars($label_semester); ?>)</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 75px; text-align: center;">No</th>
                            <th>Nama Siswa</th>
                            <th style="text-align: center;">Jumlah Insiden</th>
                            <th style="text-align: center;">Total Akumulasi Poin</th>
                            <th style="text-align: center;">Status Disiplin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_disiplin && mysqli_num_rows($query_disiplin) > 0):
                            while($row = mysqli_fetch_assoc($query_disiplin)):
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no++; ?></td>
                            <td style="vertical-align: middle;">
                                <div style="color: #334155; font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 3px;">NISN: <?php echo htmlspecialchars($row['nisn']); ?></div>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-secondary" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;font-weight:600;">
                                    <?php echo $row['total_laporan']; ?> Kali
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($row['total_poin'] >= 50): ?>
                                    <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                        <?php echo $row['total_poin']; ?> Poin
                                    </span>
                                <?php elseif ($row['total_poin'] > 0): ?>
                                    <span class="badge badge-warning" style="font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                        <?php echo $row['total_poin']; ?> Poin
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="font-weight: 600; padding: 4px 10px; border-radius: 6px;">
                                        0 Poin
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <!-- Tingkatan Badge Status Kedisiplinan -->
                                <?php if ($row['total_poin'] >= 50): ?>
                                    <span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Sangat Kritis</span>
                                <?php elseif ($row['total_poin'] >= 20): ?>
                                    <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Perlu Perhatian</span>
                                <?php elseif ($row['total_poin'] > 0): ?>
                                    <span class="badge badge-info" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;"><i class="fas fa-info-circle"></i> Kurang Disiplin</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Baik (Disiplin)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada data kedisiplinan siswa pada periode semester ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT: TOGGLE SIDEBAR MOBILE -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("mobile-toggle");
            const sidebar   = document.querySelector(".sidebar");
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
