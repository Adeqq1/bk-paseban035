<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Pengecekan keamanan: Memastikan pengguna sudah login dan berposisi sebagai 'guru_bk'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// ==============================================================================
// BAGIAN 2: PENGAMBILAN DATA STATISTIK SECARA REAL-TIME DARI DATABASE
// ==============================================================================

// 1. Menghitung jumlah siswa yang memiliki poin pelanggaran tinggi (Kritis: >= 50 poin)
$query_siswa_kritis = mysqli_query($koneksi, "
    SELECT COUNT(*) as total_kritis FROM (
        SELECT s.id, SUM(jp.poin) as total_poin
        FROM catatan_pelanggaran cp
        JOIN siswa s ON cp.siswa_id = s.id
        JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        WHERE s.status = 'aktif'
        GROUP BY s.id
        HAVING total_poin >= 50
    ) as subquery
");
$siswa_kritis = mysqli_fetch_assoc($query_siswa_kritis)['total_kritis'] ?? 0;

// 2. Menghitung jumlah laporan pelanggaran baru dari Wali Kelas yang belum ditangani (belum dikonseling)
$query_laporan = mysqli_query($koneksi, "
    SELECT cp.id
    FROM catatan_pelanggaran cp
    JOIN guru g ON cp.guru_id = g.id
    LEFT JOIN konseling kon ON cp.id = kon.catatan_pelanggaran_id
    WHERE g.jabatan = 'Wali Kelas' AND kon.id IS NULL
");

// 3. Menghitung total bimbingan atau konseling yang sudah berhasil diselesaikan oleh Guru BK ini
$query_total_bimbingan = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM konseling WHERE guru_id = '$guru_id'");
$total_bimbingan = mysqli_fetch_assoc($query_total_bimbingan)['total'];

// 4. Mengambil data foto Guru BK secara real-time dari database
$user_id = $_SESSION['id'];
$query_user = mysqli_query($koneksi, "SELECT foto FROM user WHERE id='$user_id'");
$user_row = mysqli_fetch_assoc($query_user);
$foto_guru = $user_row['foto'] ?? $_SESSION['foto'] ?? '';
$foto_guru_exists = !empty($foto_guru) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $foto_guru);
if ($foto_guru_exists) {
    $_SESSION['foto'] = $foto_guru;
}
$foto_guru_url = $foto_guru_exists ? '../assets/uploads/profil/' . htmlspecialchars($foto_guru) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru BK | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Guru BK Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
            <li><a href="daftar_panggilan.php"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
            <li><a href="alih_kasus.php"><i class="fas fa-share-square"></i> Alih Tangan Kasus</a></li>
            <li><a href="kunjungan_rumah.php"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
            <li><a href="rekap_poin.php"><i class="fas fa-chart-line"></i> Rekap Poin</a></li>
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'Guru BK', 'G'); ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($guru['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Guru BK'); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Guru BK</div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; width: 100%;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05); flex-shrink: 0;">
                    <i class="fas fa-hand-sparkles" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; width: 100%;">
                        <h1 style="margin: 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Halo, Guru BK <span style="color: #60a5fa;"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></span></h1>
                        <div style="background: rgba(255,255,255,0.05); padding: 6px 14px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 8px; color: #cbd5e1; backdrop-filter: blur(8px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                            <span style="position: relative; display: flex; height: 8px; width: 8px;">
                                <span style="position: absolute; display: inline-flex; height: 100%; width: 100%; border-radius: 50%; background-color: #22c55e; opacity: 0.75; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
                                <span style="position: relative; display: inline-flex; border-radius: 50%; height: 8px; width: 8px; background-color: #22c55e;"></span>
                            </span>
                            <span style="color: white; font-weight: 700;">Konselor Aktif</span>
                        </div>
                    </div>
                    <p style="margin: 8px 0 0 0; color: #94a3b8; font-size: 0.925rem; line-height: 1.5;">Selamat datang di portal BK SMA 07 Bungo. Kelola layanan konseling, pantau kedisiplinan, serta fasilitasi perkembangan potensi peserta didik secara optimal.</p>
                </div>
            </div>
        </div>

        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; border: 1px solid #e2e8f0; border-left: 5px solid #ef4444;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                    <i class="fas fa-inbox"></i>
                </div>
                <div>
                    <span class="label" style="display: block; color: #64748b; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Laporan Baru</span>
                    <span class="value" style="display: block; font-size: 2rem; font-weight: 700; color: #1e293b; line-height: 1;"><?php echo mysqli_num_rows($query_laporan); ?></span>
                </div>
            </div>
            
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; border: 1px solid #e2e8f0; border-left: 5px solid #10b981;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #d1fae5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <span class="label" style="display: block; color: #64748b; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Total Bimbingan</span>
                    <span class="value" style="display: block; font-size: 2rem; font-weight: 700; color: #1e293b; line-height: 1;"><?php echo $total_bimbingan; ?></span>
                </div>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; border: 1px solid #e2e8f0; border-left: 5px solid #f59e0b;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #fef3c7; color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <span class="label" style="display: block; color: #64748b; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Perhatian Khusus</span>
                    <span class="value" style="display: block; font-size: 2rem; font-weight: 700; color: #1e293b; line-height: 1;"><?php echo $siswa_kritis; ?></span>
                </div>
            </div>
        </div>
    </div>

            <!-- Script Toggle Menu Mobile & Tabel Responsif -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Fungsionalitas Toggle Sidebar 3 Garis (Pojok Kanan Atas di Mobile)
        const toggleBtn = document.getElementById('mobile-toggle');
        const sidebar = document.querySelector('.sidebar');
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

            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (window.innerWidth <= 992) {
                    sidebar.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active', sidebar.classList.contains('active'));
                } else {
                    document.body.classList.toggle('sidebar-closed');
                }
            });
            
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
        }

        // 2. Injeksi data-label & Kelas Responsif untuk Tabel
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            // Deteksi jika tabel berisi data pelanggaran (memiliki kolom NISN atau Pelanggaran)
            const headersLower = headers.map(h => h.toLowerCase());
            if (headersLower.includes('pelanggaran') || headersLower.includes('nisn')) {
                table.classList.add('table-pelanggaran-mobile');
            }

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
