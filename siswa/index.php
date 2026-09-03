<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Memastikan user sudah login dan berstatus sebagai siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];

// Mengambil data siswa, nama kelas, dan foto profil dari database
$query_siswa = mysqli_query($koneksi, "
    SELECT s.*, k.nama_kelas, u.foto as user_foto 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    LEFT JOIN user u ON s.user_id = u.id
    WHERE s.user_id = '$user_id'
");
$siswa = mysqli_fetch_assoc($query_siswa);
$siswa_id = $siswa['id'];
$foto_siswa = !empty($siswa['foto']) ? $siswa['foto'] : ($siswa['user_foto'] ?? '');
$foto_siswa_exists = !empty($foto_siswa) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $foto_siswa);
if ($foto_siswa_exists) {
    $_SESSION['foto'] = $foto_siswa;
}
$foto_siswa_url = $foto_siswa_exists ? '../assets/uploads/profil/' . htmlspecialchars($foto_siswa) : '';

// Menghitung akumulasi poin pelanggaran semester ini
$current_semester = date('m') >= 7 ? '1' : '2';
$current_tahun = date('Y');
if ($current_semester == '1') {
    $start_date = "$current_tahun-07-01";
    $end_date = "$current_tahun-12-31";
    $label_semester = "Semester Ganjil " . $current_tahun;
} else {
    $start_date = "$current_tahun-01-01";
    $end_date = "$current_tahun-06-30";
    $label_semester = "Semester Genap " . $current_tahun;
}

$query_poin_semester = mysqli_query($koneksi, "
    SELECT SUM(jp.poin) as total 
    FROM catatan_pelanggaran cp 
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id 
    WHERE cp.siswa_id = '$siswa_id' AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
");
$poin_semester = mysqli_fetch_assoc($query_poin_semester)['total'] ?? 0;

// Menghitung akumulasi total poin pelanggaran siswa (selama sekolah)
$query_poin = mysqli_query($koneksi, "
    SELECT SUM(jp.poin) as total 
    FROM catatan_pelanggaran cp 
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id 
    WHERE cp.siswa_id = '$siswa_id'
");
$total_poin = mysqli_fetch_assoc($query_poin)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa | BK SMA 07 Bungo</title>
    <!-- Menghubungkan berkas style admin agar tampilan sejalan -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR (MENU SAMPING NAVIGASI) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Siswa Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="riwayat.php"><i class="fas fa-history"></i> Riwayat & Arsip</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Footer Sidebar (Foto & Info Siswa Aktif) -->
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <?php if ($foto_siswa_exists): ?>
                <!-- Jika ada, tampilkan foto profil tersebut -->
                <img src="<?php echo $foto_siswa_url; ?>" alt="Foto Profil" class="avatar" style="object-fit: cover;">
            <?php else: ?>
                <div class="avatar"><?php echo strtoupper(substr($siswa['nama_lengkap'] ?? 'S', 0, 1)); ?></div>
            <?php endif; ?>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars(ucwords(strtolower($siswa['nama_lengkap'] ?? 'Siswa'))); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Siswa SMAN 7</div>
            </div>
        </div>
    </div>

    <!-- AREA UTAMA KONTEN -->
    <div class="main-content">
        <!-- Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-hand-sparkles" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Halo, <?php echo htmlspecialchars(ucwords(strtolower(explode(' ', $siswa['nama_lengkap'] ?? 'Siswa')[0]))); ?></h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Selamat datang di portal Bimbingan Konseling SMAN 7 Bungo.</p>
                </div>
            </div>
            <div class="user-info" style="background: rgba(0,0,0,0.2); padding: 8px 18px; border-radius: 30px; font-weight: 600; font-size: 0.95rem; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-circle" style="color: #4ade80; font-size: 0.5rem;"></i>
                <span>Status: <strong style="color: white;">Siswa Aktif</strong></span>
            </div>
        </div>

        <!-- Grid Kartu Statistik -->
        <div class="stats-grid">
            <!-- Kartu 1: Poin Semester Ini -->
            <div class="stat-card">
                <div class="stat-icon amber">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Poin Semester Ini</div>
                    <div class="stat-value"><?php echo $poin_semester; ?></div>
                    <div class="stat-sub"><?php echo $label_semester; ?></div>
                </div>
            </div>

            <!-- Kartu 2: Akumulasi Poin -->
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Total Akumulasi</div>
                    <div class="stat-value"><?php echo $total_poin; ?></div>
                    <div class="stat-sub">Poin keseluruhan selama sekolah</div>
                </div>
            </div>
            
            <!-- Kartu 3: Status Perilaku -->
            <div class="stat-card">
                <div class="stat-icon <?php echo $total_poin >= 50 ? 'amber' : 'green'; ?>">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Status Perilaku</div>
                    <div class="stat-value">
                        <?php 
                            if($total_poin >= 100) echo 'Sangat Berat';
                            elseif($total_poin >= 50) echo 'Berat';
                            elseif($total_poin >= 25) echo 'Sedang';
                            else echo 'Aman (Baik)';
                        ?>
                    </div>
                    <div class="stat-sub">Kategori berdasarkan poin</div>
                </div>
            </div>

            <!-- Kartu 4: Kelas Aktif -->
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-body">
                    <div class="stat-label">Kelas Saat Ini</div>
                    <div class="stat-value"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></div>
                    <div class="stat-sub">Tahun Ajaran Aktif</div>
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
