<?php
/**
 * ====================================================================================
 * MODUL DAFTAR SISWA PERWALIAN - PANEL WALI KELAS (BK SMA 07 Bungo SMAN 7 BUNGO)
 * ====================================================================================
 * Halaman ini menampilkan daftar lengkap siswa binaan yang terdaftar pada kelas 
 * perwalian Wali Kelas yang sedang aktif, dilengkapi fitur pencarian live dan tombol 
 * laporkan pelanggaran secara langsung.
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN: Memastikan pengguna berstatus 'wali_kelas'
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
$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? 'Wali Kelas'));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// 5. QUERY MENGAMBIL DATA KELAS & SISWA PERWALIAN AKTIF
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas       = mysqli_fetch_assoc($query_kelas);

$query_siswa = null;
$total_siswa = 0;

if ($kelas) {
    $kelas_id    = $kelas['id'];
    $query_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE kelas_id = '$kelas_id' AND status = 'aktif' ORDER BY nama_lengkap ASC");
    $total_siswa = $query_siswa ? mysqli_num_rows($query_siswa) : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Siswa Perwalian | BK SMA 07 Bungo</title>
    
    <!-- Memuat stylesheet utama admin -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <!-- Memuat ikon FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Memuat font Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Styling Khusus Input Pencarian & Badge Jenis Kelamin */
        .search-container {
            position: relative;
            max-width: 320px;
        }
        .search-container input {
            width: 100%;
            padding: 9px 16px 9px 40px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }
        .search-container input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .search-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .gender-badge-l {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-block;
        }
        .gender-badge-p {
            background: #fce7f3;
            color: #be185d;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-block;
        }
        .btn-lapor-custom {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
            transition: all 0.2s;
        }
        .btn-lapor-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35);
            color: white;
        }
    </style>
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
            <li><a href="siswa_perwalian.php" class="active"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
            <li><a href="status_laporan.php"><i class="fas fa-tasks"></i> Status Laporan</a></li>
            <li><a href="status_disiplin.php"><i class="fas fa-user-shield"></i> Status Disiplin</a></li>
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
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <i class="fas fa-users" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.015em;">Daftar Siswa Perwalian</h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5;">
                        <?php if ($kelas): ?>
                            Kelola &amp; pantau seluruh data siswa binaan di <strong>Kelas <?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong>.
                        <?php else: ?>
                            Daftar siswa perwalian aktif SMAN 7 Bungo.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($kelas): ?>
            <div style="background: rgba(255,255,255,0.1); padding: 0.75rem 1.25rem; border-radius: 999px; display: flex; align-items: center; gap: 10px; border: 1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-chalkboard" style="color: #60a5fa;"></i>
                <span style="font-size: 0.9rem; font-weight: 600; color: #f8fafc;">Kelas: <?php echo htmlspecialchars($kelas['nama_kelas']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($kelas): ?>
        <!-- KARTU TABEL DATA SISWA PERWALIAN -->
        <div class="data-card" style="border-radius: 16px; border: 1px solid #f1f5f9; background: white; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            
            <!-- Header Kartu & Kolom Input Pencarian Live -->
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Siswa Kelas <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h3>
                    <span style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;"><?php echo $total_siswa; ?> Siswa</span>
                </div>
                
                <!-- Input Filter Pencarian Nama Siswa -->
                <div style="flex: 1; min-width: 250px; max-width: 320px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Nama Siswa</label>
                    <div style="position: relative;">
                        <select id="cariSiswaInput" onchange="filterTabelSiswa()" style="width: 100%; padding: 0.6rem 2.2rem 0.6rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.875rem; outline: none; appearance: none; background: white; cursor: pointer; font-family: inherit; color: #1e293b;">
                            <option value="">Semua Siswa</option>
                            <?php 
                            if ($query_siswa && mysqli_num_rows($query_siswa) > 0) {
                                mysqli_data_seek($query_siswa, 0);
                                while($s_opt = mysqli_fetch_assoc($query_siswa)) {
                                    echo '<option value="' . htmlspecialchars($s_opt['nama_lengkap']) . '">' . htmlspecialchars($s_opt['nama_lengkap']) . ' (' . htmlspecialchars($s_opt['nisn']) . ')</option>';
                                }
                                // Kembalikan pointer ke awal untuk loop tabel di bawah
                                mysqli_data_seek($query_siswa, 0);
                            }
                            ?>
                        </select>
                        <i class="fas fa-chevron-down" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.75rem; pointer-events: none;"></i>
                    </div>
                </div>
            </div>

            <!-- Tabel Data Siswa -->
            <div class="table-responsive">
                <table id="tabelSiswaPerwalian" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; width: 60px; text-align: center;">No</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Nama Lengkap</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">L/P</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_siswa && mysqli_num_rows($query_siswa) > 0):
                            while($s = mysqli_fetch_assoc($query_siswa)): 
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;">
                            <td style="padding: 14px 18px; text-align: center; color: #64748b; font-size: 0.875rem;"><?php echo $no++; ?></td>
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <div style="color: #0f172a; font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($s['nama_lengkap']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 3px;">NISN: <?php echo htmlspecialchars($s['nisn']); ?></div>
                            </td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <?php if ($s['jenis_kelamin'] == 'L'): ?>
                                    <span class="gender-badge-l" title="Laki-laki">L</span>
                                <?php else: ?>
                                    <span class="gender-badge-p" title="Perempuan">P</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <!-- Tombol Aksi Laporkan Pelanggaran Langsung untuk Siswa Terkait -->
                                <a href="form_lapor.php?siswa_id=<?php echo $s['id']; ?>" class="btn-lapor-custom" title="Laporkan Pelanggaran untuk <?php echo htmlspecialchars($s['nama_lengkap']); ?>">
                                    <i class="fas fa-bullhorn"></i> Lapor Pelanggaran
                                </a>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem;">Belum ada siswa terdaftar pada kelas perwalian ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
            <!-- Alert jika belum ada kelas perwalian -->
            <div class="alert" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 1.25rem 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 12px; margin-top: 1rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.25rem;"></i>
                <div>
                    <strong>Perhatian:</strong> Anda belum ditugaskan sebagai wali kelas untuk kelas manapun saat ini. Silakan hubungi Administrator Sistem.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- SCRIPT JAVASCRIPT: FILTER PENCARIAN LIVE & TOGGLE SIDEBAR MOBILE -->
    <script>
        /**
         * Fungsi JavaScript filterTabelSiswa()
         * Berfungsi memfilter baris tabel secara real-time berdasarkan input NISN atau Nama Siswa.
         */
        function filterTabelSiswa() {
            var input  = document.getElementById("cariSiswaInput");
            var filter = input.value.toLowerCase();
            var table  = document.getElementById("tabelSiswaPerwalian");
            var tr     = table.getElementsByTagName("tr");

            for (var i = 1; i < tr.length; i++) {
                // Kolom index 1 = Nama Lengkap + NISN (sudah digabung)
                var tdNama = tr[i].getElementsByTagName("td")[1];
                if (tdNama) {
                    var txtNama = tdNama.textContent || tdNama.innerText;
                    tr[i].style.display = txtNama.toLowerCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }

        // Toggle Sidebar Drawer Mobile
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtn = document.getElementById('mobile-toggle');
            var sidebar   = document.querySelector('.sidebar');
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
        });
    </script>
</body>
</html>
