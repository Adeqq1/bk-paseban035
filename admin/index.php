<?php
// ==============================================================================
// BAGIAN 1: INISIALISASI & OTENTIKASI SISTEM
// ==============================================================================

// Memulai sesi (session) untuk mengenali data pengguna yang sedang aktif (login)
session_start();

// Memanggil file konfigurasi koneksi database agar dapat terhubung ke MySQL
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Pengecekan keamanan: Memastikan bahwa pengguna sudah login dan berposisi (role) sebagai 'admin'
// Jika belum login atau bukan admin, sistem akan menolak akses dan mengalihkan balik ke halaman utama
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
// --- FITUR DASHBOARD ADMIN DIMATIKAN SEMENTARA UNTUK TESTING DOSEN ---
// Walaupun dia punya role admin, tendang kembali ke halaman utama
// if ($_SESSION['role'] === 'admin') {
//     session_destroy();
//     echo "<script>
//             alert('Maaf, akses ke Halaman Dashboard Admin sedang dikunci oleh sistem!');
//             window.location.href = '../index.php';
//           </script>";
//     exit();
// }
// ==============================================================================
// BAGIAN 2: PENGAMBILAN DATA STATISTIKsecara REAL-TIME DARI DATABASE
// ==============================================================================

// 1. Menghitung jumlah keseluruhan siswa yang terdaftar dalam tabel 'siswa'
$query_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa");
$total_siswa = mysqli_fetch_assoc($query_siswa)['total'];

// 2. Menghitung jumlah keseluruhan guru / wali kelas yang terdaftar dalam tabel 'guru'
$query_guru = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM guru");
$total_guru = mysqli_fetch_assoc($query_guru)['total'];

// 3. Menghitung jumlah total catatan pelanggaran yang pernah dicatat ke dalam sistem
$query_pelanggaran = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM catatan_pelanggaran");
$total_pelanggaran = mysqli_fetch_assoc($query_pelanggaran)['total'];

// 4. Menghitung jumlah kelas aktif yang terdaftar di sistem dari tabel 'kelas'
$query_kelas = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kelas");
$total_kelas = mysqli_fetch_assoc($query_kelas)['total'];

// 5. Mengambil data foto pengguna yang sedang login secara real-time dari database
$user_id = $_SESSION['id'];
$query_admin = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
$admin = mysqli_fetch_assoc($query_admin);
$foto_admin = $admin['foto'] ?? $_SESSION['foto'] ?? '';
$foto_admin_exists = !empty($foto_admin) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $foto_admin);
if ($foto_admin_exists) {
    $_SESSION['foto'] = $foto_admin;
}
$foto_admin_url = $foto_admin_exists ? '../assets/uploads/profil/' . htmlspecialchars($foto_admin) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | BK SMA 07 Bungo</title>
    <meta name="description" content="Dashboard admin Sistem Informasi Bimbingan Konseling SMA 07 Bungo">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- ========================================================================== -->
    <!-- TOMBOL MENU HAMBURGER (Untuk navigasi mobile di pojok kanan atas)          -->
    <!-- ========================================================================== -->
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- ========================================================================== -->
    <!-- SIDEBAR / BILAH NAVIGASI SAMPING UTAMA                                     -->
    <!-- ========================================================================== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Admin Panel</p>
        </div>
        
        <!-- Bagian Menu Utama: Navigasi dasar pengolahan pengguna & sekolah -->
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="kelola_siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
            <li><a href="kelola_guru.php"><i class="fas fa-chalkboard-teacher"></i> Kelola Wali Kelas</a></li>
            <li><a href="kelola_kelas.php"><i class="fas fa-school"></i> Kelola Kelas</a></li>
        </ul>
        <div class="sidebar-label">Data &amp; Laporan</div>
        <ul class="sidebar-menu">
            <li><a href="kelola_jenis_pelanggaran.php"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
            <li><a href="pelanggaran.php"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil Admin</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <!-- Mengecek apakah pengguna memiliki foto profil yang tersimpan di sistem -->
                <?php if ($foto_admin_exists): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="<?php echo $foto_admin_url; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>

    <!-- ========================================================================== -->
    <!-- KONTEN UTAMA HALAMAN DASBOR (MAIN CONTENT)                                 -->
    <!-- ========================================================================== -->
    <div class="main-content">
        <!-- Spanduk Sambutan (Banner Header) beserta informasi Status Login Admin -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; width: 100%;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05); flex-shrink: 0;">
                    <i class="fas fa-user-shield" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; width: 100%;">
                        <h1 style="margin: 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Dashboard Overview</h1>
                        <div class="user-info" style="background: rgba(255,255,255,0.05); padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 8px; color: #cbd5e1; backdrop-filter: blur(8px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                            <span style="position: relative; display: flex; height: 8px; width: 8px;">
                                <span style="position: absolute; display: inline-flex; height: 100%; width: 100%; border-radius: 50%; background-color: #4ade80; opacity: 0.75; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
                                <span style="position: relative; display: inline-flex; border-radius: 50%; height: 8px; width: 8px; background-color: #4ade80;"></span>
                            </span>
                            Halo, <strong style="color: white; font-weight: 700;"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        </div>
                    </div>
                    <p style="margin: 8px 0 0 0; color: #94a3b8; font-size: 0.925rem; line-height: 1.5; max-width: 780px;">Selamat datang kembali! Kelola data kedisiplinan siswa serta pantau aktivitas Bimbingan &amp; Konseling secara terpadu.</p>
                </div>
            </div>
        </div>

        <!-- ====================================================================== -->
        <!-- GRID STATISTIK SISTEM: Menampilkan 4 kartu ringkasan jumlah data       -->
        <!-- ====================================================================== -->
        <div class="stats-grid">
            <!-- Kartu 1: Jumlah Total Siswa Aktif -->
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-body">
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-value"><?php echo $total_siswa; ?></div>
                    <div class="stat-sub">Terdaftar dalam sistem</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-body">
                    <div class="stat-label">Total Guru</div>
                    <div class="stat-value"><?php echo $total_guru; ?></div>
                    <div class="stat-sub">Wali kelas aktif</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-body">
                    <div class="stat-label">Total Pelanggaran</div>
                    <div class="stat-value"><?php echo $total_pelanggaran; ?></div>
                    <div class="stat-sub">Catatan pelanggaran</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-school"></i></div>
                <div class="stat-body">
                    <div class="stat-label">Kelas Aktif</div>
                    <div class="stat-value"><?php echo $total_kelas; ?></div>
                    <div class="stat-sub">Kelas terdaftar</div>
                </div>
            </div>
        </div>
        
        <!-- ====================================================================== -->
        <!-- PANEL AKSES CEPAT (QUICK ACCESS): Tombol jalan Pintas ke halaman utama -->
        <!-- ====================================================================== -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-bolt"></i> Akses Cepat</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; padding: 1.5rem 1.75rem;">
                <a href="kelola_siswa.php" style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:1.5rem 1rem;background:rgba(59,130,246,0.07);border:1px solid rgba(59,130,246,0.15);border-radius:14px;text-decoration:none;transition:all 0.25s;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--blue-bg);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);text-align:center;">Kelola Siswa</span>
                </a>
                <a href="kelola_guru_bk.php" style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:1.5rem 1rem;background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.15);border-radius:14px;text-decoration:none;transition:all 0.25s;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--green-bg);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);text-align:center;">Kelola Guru BK</span>
                </a>
                <a href="kelola_kelas.php" style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:1.5rem 1rem;background:rgba(139,92,246,0.07);border:1px solid rgba(139,92,246,0.15);border-radius:14px;text-decoration:none;transition:all 0.25s;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--purple-bg);color:var(--purple);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-school"></i>
                    </div>
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);text-align:center;">Kelola Kelas</span>
                </a>
                <a href="pelanggaran.php" style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:1.5rem 1rem;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.15);border-radius:14px;text-decoration:none;transition:all 0.25s;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--red-bg);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);text-align:center;">Pelanggaran</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ========================================================================== -->
    <!-- BAGIAN SCRIPT JAVASCRIPT: Mengatur Interaksi Menu & Responsivitas Tabel    -->
    <!-- ========================================================================== -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Logika Toggle Tombol Hamburger (Menu 3 Garis di layar HP/Mobile)
        const toggleBtn = document.getElementById('mobile-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (toggleBtn && sidebar) {
            // Mengecek atau mendirikan lapisan bayangan hitam (overlay) di latar belakang saat sidebar mobile terbuka
            let overlay = document.getElementById("sidebar-overlay");
            if (!overlay) {
                overlay = document.createElement("div");
                overlay.className = "sidebar-overlay";
                overlay.id = "sidebar-overlay";
                document.body.appendChild(overlay);
                
                // Jika area latar belakang overlay diklik, otomatis tutup kembali sidebar
                overlay.addEventListener("click", function() {
                    sidebar.classList.remove("active");
                    overlay.classList.remove("active");
                });
            }

            // Aksi saat tombol hamburger diklik
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Mencegah benturan aksi dengan event document
                if (window.innerWidth <= 992) {
                    // Mode layar kecil (HP/Tablet): munculkan atau hilangkan class active
                    sidebar.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active', sidebar.classList.contains('active'));
                } else {
                    // Mode layar besar (Laptop/Desktop): sembunyikan atau lebarkan sidebar
                    document.body.classList.toggle('sidebar-closed');
                }
            });
            
            // Tutup sidebar otomatis jika pengguna mengklik area luar menu pada layar HP
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
        }

        // 2. Otomatisasi Label Tabel Responsif untuk kenyamanan tampilan di HP
        // Melakukan ekstraksi teks pada header tabel (<thead>) lalu memasukkannya sebagai 'data-label' ke dalam tiap sel (<td>)
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            // Deteksi otomatis apakah tabel menyajikan data pelanggaran atau profil siswa
            const headersLower = headers.map(h => h.toLowerCase());
            if (headersLower.includes('pelanggaran') || headersLower.includes('nisn')) {
                table.classList.add('table-pelanggaran-mobile');
            }

            // Menerapkan label responsif ke semua sel baris tabel
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
