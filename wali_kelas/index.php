<?php
/**
 * ====================================================================================
 * HALAMAN UTAMA (DASHBOARD) WALI KELAS - SISTEM INFORMASI BIMBINGAN KONSELING (BK SMA 07 Bungo)
 * SMAN 7 BUNGO
 * ====================================================================================
 * Halaman ini berfungsi sebagai pusat kontrol dan pemantauan bagi Wali Kelas.
 * Menampilkan statistik kelas perwalian, jumlah siswa aktif, siswa dengan akumulasi 
 * poin kedisiplinan kritis (>= 50 poin), serta riwayat laporan pelanggaran ke Guru BK.
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN (SECURITY CHECK):
// Memastikan pengguna telah login dan memiliki hak akses (role) sebagai 'wali_kelas'.
// Jika belum login atau bukan Wali Kelas, pengguna akan dialihkan ke halaman utama login.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'wali_kelas') {
    header("Location: ../index.php");
    exit();
}

// 4. MENGAMBIL DATA WALI KELAS DARI DATABASE:
// Ambil ID pengguna dari session login aktif
$user_id = $_SESSION['id'];

// Mengambil data lengkap guru dari tabel 'guru' berdasarkan user_id login
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru['id'] ?? 0; // ID internal guru (primary key tabel guru)

// Pemformatan nama guru dan penulisan gelar agar terstruktur dan rapi
$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? ''));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// 5. MENGAMBIL DATA KELAS PERWALIAN:
// Query untuk mencari kelas yang diampu oleh wali kelas ini (wali_kelas_id = guru_id)
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas = mysqli_fetch_assoc($query_kelas);

// Inisialisasi variabel statistik awal
$total_laporan_terkirim = 0;
$query_recent_laporan   = false;
$query_siswa            = false;
$total_kritis           = 0;

// 6. PROSES KALKULASI STATISTIK KELAS (JIKA KELAS DITEMUKAN):
if ($kelas) {
    $kelas_id = $kelas['id'];
    
    // Penentuan Semester dan Tahun Ajaran Aktif untuk Reset Periode Poin Kedisiplinan:
    // Bulan 7 - 12 = Semester 1 (Ganjil), Bulan 1 - 6 = Semester 2 (Genap)
    $current_semester = date('m') >= 7 ? '1' : '2';
    $current_tahun    = date('Y');
    
    if ($current_semester == '1') {
        $start_date = "$current_tahun-07-01";
        $end_date   = "$current_tahun-12-31";
    } else {
        $start_date = "$current_tahun-01-01";
        $end_date   = "$current_tahun-06-30";
    }

    // QUERY STATISTIK 1: Mengambil daftar siswa perwalian beserta total penjumlahan (SUM) poin pelanggaran di semester aktif.
    $query_siswa = mysqli_query($koneksi, "
        SELECT s.id, s.nisn, s.nama_lengkap, s.jenis_kelamin, COALESCE(SUM(jp.poin), 0) as total_poin
        FROM siswa s
        LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
        LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        WHERE s.kelas_id = '$kelas_id' AND s.status = 'aktif'
        GROUP BY s.id, s.nisn, s.nama_lengkap, s.jenis_kelamin
        ORDER BY total_poin DESC, s.nama_lengkap ASC
    ");

    // QUERY STATISTIK 2: Menghitung total siswa perwalian yang poin pelanggarannya telah mencapai batas kritis (>= 50 poin).
    $query_kritis = mysqli_query($koneksi, "
        SELECT COUNT(*) as total_kritis FROM (
            SELECT s.id, COALESCE(SUM(jp.poin), 0) as total_poin
            FROM siswa s
            LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
            LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
            WHERE s.kelas_id = '$kelas_id' AND s.status = 'aktif'
            GROUP BY s.id
            HAVING total_poin >= 50
        ) as subquery
    ");
    $data_kritis  = mysqli_fetch_assoc($query_kritis);
    $total_kritis = $data_kritis['total_kritis'] ?? 0;

    // QUERY STATISTIK 3: Menghitung total laporan pelanggaran yang telah dibuat/dikirim oleh Wali Kelas ini.
    $q_lapor_count          = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM catatan_pelanggaran WHERE guru_id = '$guru_id'");
    $d_lapor_count          = $q_lapor_count ? mysqli_fetch_assoc($q_lapor_count) : null;
    $total_laporan_terkirim = $d_lapor_count['total'] ?? 0;

    // QUERY STATISTIK 4: Mengambil 5 riwayat laporan pelanggaran terbaru yang dilaporkan oleh Wali Kelas ini beserta status respon Guru BK.
    $query_recent_laporan = mysqli_query($koneksi, "
        SELECT cp.*, s.nama_lengkap as nama_siswa, s.nisn, jp.nama_pelanggaran, jp.poin, jp.kategori,
               kon.status as status_bk, kon.solusi as tindakan_solusi
        FROM catatan_pelanggaran cp
        JOIN siswa s ON cp.siswa_id = s.id
        JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        LEFT JOIN konseling kon ON kon.catatan_pelanggaran_id = cp.id
        WHERE cp.guru_id = '$guru_id'
        ORDER BY cp.id DESC
        LIMIT 5
    ");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali Kelas | BK SMA 07 Bungo</title>
    
    <!-- Memuat file CSS utama untuk layout panel admin/dashboard -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    
    <!-- Memuat pustaka ikon FontAwesome versi 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Memuat font Plus Jakarta Sans dari Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- =================================================================== -->
    <!-- SIDEBAR NAVIGASI PANEL WALI KELAS                                   -->
    <!-- =================================================================== -->
    <div class="sidebar">
        <!-- Header Brand Sidebar -->
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Wali Kelas Panel</p>
        </div>
        
        <!-- Daftar Menu Navigasi Samping -->
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="siswa_perwalian.php"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
            <li><a href="status_laporan.php"><i class="fas fa-tasks"></i> Status Laporan</a></li>
            <li><a href="status_disiplin.php"><i class="fas fa-user-shield"></i> Status Disiplin</a></li>
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <!-- Footer Informasi Profil Singkat Wali Kelas -->
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <!-- Mengecek apakah pengguna memiliki foto profil yang tersimpan di sistem -->
                <?php if (!empty($_SESSION['foto']) && file_exists('../assets/uploads/profil/' . $_SESSION['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $_SESSION['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'W', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($nama_guru); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Wali Kelas <?php echo htmlspecialchars($kelas['nama_kelas'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- AREA KONTEN UTAMA DASHBOARD WALI KELAS                              -->
    <!-- =================================================================== -->
    <div class="main-content">
        
        <!-- Banner Header Selamat Datang -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 14px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.25);">
            
            <div style="display: flex; align-items: center; gap: 1.5rem; flex: 1; min-width: 280px;">
                <div style="background: rgba(255,255,255,0.1); width: 64px; height: 64px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-chalkboard-teacher" style="font-size: 1.9rem; color: #60a5fa;"></i>
                </div>
                
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.65rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Selamat Datang, <span style="color: #60a5fa;"><?php echo htmlspecialchars($nama_guru); ?></span>!</h1>
                    
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5;">
                        <?php if ($kelas): ?>
                            Selaku Wali Kelas <strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong>, Anda dapat memantau tingkat kedisiplinan siswa perwalian serta berkoordinasi dengan Guru BK.
                        <?php else: ?>
                            Selamat datang di portal Wali Kelas SMAN 7 Bungo. Silakan hubungi admin untuk dihubungkan ke kelas perwalian.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Badge Status Wali Kelas -->
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div style="background: rgba(255,255,255,0.08); padding: 0.65rem 1.1rem; border-radius: 999px; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.18);">
                    <span style="display: block; width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 10px rgba(34, 197, 94, 0.6);"></span>
                    <span style="font-size: 0.85rem; font-weight: 600; color: #f8fafc;">Wali Kelas Active</span>
                </div>
            </div>
        </div>

        <!-- TAMPILAN JIKA KELAS PERWALIAN SUDAH TERHUBUNG -->
        <?php if ($kelas): ?>
        
        <!-- GRID KARTU STATISTIK KELAS PERWALIAN -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- KARTU 1: Nama Kelas Perwalian yang Diampu -->
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 18px; border: 1px solid #e2e8f0; border-left: 5px solid #3b82f6;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; flex-shrink: 0;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <span style="display: block; color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Kelas Perwalian</span>
                    <span style="display: block; font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></span>
                    <span style="display: block; font-size: 0.78rem; color: #94a3b8; margin-top: 4px;">Kelas diampu aktif</span>
                </div>
            </div>

            <!-- KARTU 2: Total Siswa Aktif Terdaftar dalam Kelas -->
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 18px; border: 1px solid #e2e8f0; border-left: 5px solid #10b981;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; flex-shrink: 0;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <span style="display: block; color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Jumlah Siswa</span>
                    <span style="display: block; font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?php echo $query_siswa ? mysqli_num_rows($query_siswa) : 0; ?></span>
                    <span style="display: block; font-size: 0.78rem; color: #94a3b8; margin-top: 4px;">Siswa aktif terdaftar</span>
                </div>
            </div>

            <!-- KARTU 3: Total Laporan Pelanggaran yang Telah Dikirimkan -->
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 18px; border: 1px solid #e2e8f0; border-left: 5px solid #8b5cf6;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; flex-shrink: 0;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <span style="display: block; color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Laporan Terkirim</span>
                    <span style="display: block; font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1.1;"><?php echo $total_laporan_terkirim; ?></span>
                    <span style="display: block; font-size: 0.78rem; color: #94a3b8; margin-top: 4px;">Dilaporkan ke BK</span>
                </div>
            </div>

            <!-- KARTU 4: Jumlah Siswa dengan Akumulasi Poin Kritis (>= 50 Poin) -->
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 18px; border: 1px solid #e2e8f0; border-left: 5px solid #ef4444;">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; flex-shrink: 0;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span style="display: block; color: #64748b; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Siswa Poin Kritis</span>
                    <span style="display: block; font-size: 1.6rem; font-weight: 800; color: #ef4444; line-height: 1.1;"><?php echo $total_kritis; ?></span>
                    <span style="display: block; font-size: 0.78rem; color: #94a3b8; margin-top: 4px;">Siswa Poin &ge; 50</span>
                </div>
            </div>
        </div>

        <!-- TAMPILAN JIKA AKUN WALI KELAS BELUM TERHUBUNG KE KELAS PERWALIAN -->
        <?php else: ?>
            <div class="alert" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 1.25rem; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.4rem; color: #d97706;"></i> 
                <div>
                    <strong style="font-size: 1rem; display: block; margin-bottom: 2px;">Akun Wali Kelas Belum Ditugaskan</strong>
                    <span style="font-size: 0.9rem; color: #92400e;">Akun Anda (<strong><?php echo htmlspecialchars($guru['nama_lengkap'] ?? 'Guru'); ?></strong>) belum terhubung ke kelas perwalian manapun. Silakan hubungi Administrator Sistem untuk memilih kelas perwalian Anda.</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- =================================================================== -->
    <!-- SCRIPT JAVASCRIPT: TOGGLE SIDEBAR MOBILE & TABEL RESPONSIF          -->
    <!-- =================================================================== -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle Sidebar Drawer (Menampilkan / Menyembunyikan menu pada perangkat HP/Tablet)
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
                    overlay.classList.remove("active");
                });
            }

            // Event listener saat tombol 3 garis (hamburger) diklik
            toggleBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                if (window.innerWidth <= 992) {
                    sidebar.classList.toggle("active");
                    if (overlay) overlay.classList.toggle("active", sidebar.classList.contains("active"));
                } else {
                    document.body.classList.toggle("sidebar-closed");
                }
            });
            
            // Tutup sidebar jika pengguna mengeklik di luar area menu
            document.addEventListener("click", function(e) {
                if (window.innerWidth <= 992 && sidebar.classList.contains("active") && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove("active");
                    if (overlay) overlay.classList.remove("active");
                }
            });
        }

        // Injeksi otomatis atribut data-label pada sel tabel untuk tampilan responsif di layar seluler
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            table.querySelectorAll('tbody tr').forEach(function(row) {
                row.querySelectorAll('td').forEach(function(td, index) {
                    if (headers[index]) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
