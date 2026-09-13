<?php
/**
 * ====================================================================================
 * MODUL LAPOR PELANGGARAN SISWA - PANEL WALI KELAS (BK SMA 07 Bungo SMAN 7 BUNGO)
 * ====================================================================================
 * Halaman ini digunakan oleh Wali Kelas untuk melaporkan insiden pelanggaran tata tertib
 * yang dilakukan oleh siswa perwaliannya langsung ke Guru Bimbingan Konseling (BK).
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN: Memastikan pengguna telah login dan memiliki role 'wali_kelas'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'wali_kelas') {
    header("Location: ../index.php");
    exit();
}

// 4. MENGAMBIL DATA GURU / WALI KELAS DARI DATABASE
$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru['id'];
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas       = mysqli_fetch_assoc($query_kelas);

// Format nama guru dan penulisan gelar resmi
$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? ''));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// 5. PROSES SUBMIT FORM PELAPORAN PELANGGARAN
if (isset($_POST['lapor'])) {
    $siswa_id       = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    $pelanggaran_id = mysqli_real_escape_string($koneksi, $_POST['pelanggaran_id']);
    $tanggal        = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $keterangan     = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $tindak_lanjut  = isset($_POST['tindak_lanjut']) ? mysqli_real_escape_string($koneksi, $_POST['tindak_lanjut']) : '';

    // Ambil detail kategori & nama pelanggaran
    $q_jp = mysqli_query($koneksi, "SELECT nama_pelanggaran, kategori FROM jenis_pelanggaran WHERE id = '$pelanggaran_id'");
    $jp_data = mysqli_fetch_assoc($q_jp);
    $kategori_pelanggaran = $jp_data['kategori'] ?? 'Ringan';
    $nama_pelanggaran     = $jp_data['nama_pelanggaran'] ?? 'Pelanggaran';

    // Gabungkan keterangan kronologi dengan catatan pembinaan awal Wali Kelas jika diisi pada Sedang/Berat
    $keterangan_simpan = $keterangan;
    if (!empty($tindak_lanjut) && $kategori_pelanggaran != 'Ringan') {
        $keterangan_simpan .= " | Pembinaan Wali Kelas: " . $tindak_lanjut;
    }

    // Simpan catatan pelanggaran baru ke tabel 'catatan_pelanggaran'
    $query = "INSERT INTO catatan_pelanggaran (siswa_id, pelanggaran_id, guru_id, tanggal, keterangan) 
              VALUES ('$siswa_id', '$pelanggaran_id', '$guru_id', '$tanggal', '$keterangan_simpan')";
    
    if (mysqli_query($koneksi, $query)) {
        $cp_id = mysqli_insert_id($koneksi);

        // Jika Pelanggaran RINGAN, selesaikan otomatis dengan membuat entri di tabel konseling
        if ($kategori_pelanggaran == 'Ringan') {
            $masalah_val = mysqli_real_escape_string($koneksi, $keterangan);
            $solusi_val  = mysqli_real_escape_string($koneksi, !empty($tindak_lanjut) ? $tindak_lanjut : "Pembinaan langsung oleh Wali Kelas");
            $topik_val   = mysqli_real_escape_string($koneksi, $nama_pelanggaran);

            $query_kon = "INSERT INTO konseling 
                (siswa_id, guru_id, catatan_pelanggaran_id, tanggal, masalah, solusi, status, jenis_konseling, topik_permasalahan, bidang_bimbingan)
                VALUES 
                ('$siswa_id', '$guru_id', '$cp_id', '$tanggal', '$masalah_val', '$solusi_val', 'Selesai', 'Tindak Lanjut', '$topik_val', 'Pribadi, Sosial')";
            mysqli_query($koneksi, $query_kon);
        }

        // Jika berhasil, alihkan pengguna ke halaman status_laporan.php dengan notifikasi sukses
        header("Location: status_laporan.php?pesan=success");
        exit();
    } else {
        $error = "Gagal mengirim laporan pelanggaran ke database.";
    }
}

// 6. MENGAMBIL DAFTAR SISWA PERWALIAN AKTIF KELAS INI
$query_siswa = mysqli_query($koneksi, "
    SELECT s.* FROM siswa s 
    JOIN kelas k ON s.kelas_id = k.id 
    WHERE k.wali_kelas_id = '$guru_id' AND s.status = 'aktif'
    ORDER BY s.nama_lengkap ASC
");

// 7. MENGAMBIL DAFTAR JENIS PELANGGARAN BESERTA KATEGORI DAN POINNYA
$query_pelanggaran = mysqli_query($koneksi, "SELECT * FROM jenis_pelanggaran ORDER BY FIELD(kategori, 'Ringan', 'Sedang', 'Berat'), nama_pelanggaran ASC");
$pelanggaran_by_kat = [];
while($p = mysqli_fetch_assoc($query_pelanggaran)) {
    $pelanggaran_by_kat[$p['kategori']][] = $p;
}

// Ambil ID siswa terpilih jika diakses melalui tombol aksi di halaman lain
$selected_siswa_id = isset($_GET['siswa_id']) ? $_GET['siswa_id'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lapor Pelanggaran | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Styling Khusus Formulir Pelaporan Pelanggaran */
        .form-container {
            max-width: 650px;
            margin: 1.5rem auto 3rem auto;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 1.5rem 2rem;
            position: relative;
        }
        .form-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-header p {
            color: #94a3b8;
            font-size: 0.85rem;
            margin: 0;
        }
        .form-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.825rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.65rem 1rem;
            font-size: 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
            color: #1e293b;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%252364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.2em;
            padding-right: 2.5rem;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease-in-out;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
        }
        .btn-cancel,
        a.btn-cancel {
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
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.08) !important;
            transition: all 0.2s ease-in-out !important;
        }
        .btn-cancel:hover,
        a.btn-cancel:hover {
            background: #fee2e2 !important;
            color: #b91c1c !important;
            border-color: #fca5a5 !important;
            transform: translateY(-2px) !important;
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
            <li><a href="siswa_perwalian.php"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php" class="active"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
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

    <!-- AREA KONTEN UTAMA HALAMAN -->
    <div class="main-content">
        <!-- Banner Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: flex-start; gap: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-bullhorn" style="font-size: 1.8rem; color: #60a5fa;"></i>
            </div>
            <div>
                <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Lapor Pelanggaran Siswa</h1>
                <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Laporkan pelanggaran tata tertib siswa perwalian ke Guru Bimbingan Konseling.</p>
            </div>
        </div>

        <!-- Alert Notifikasi Kesalahan Submit -->
        <?php if (isset($error)): ?>
            <div class="alert badge-danger" style="max-width: 650px; margin: 0 auto 1.5rem auto; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- FORMULIR PELAPORAN PELANGGARAN SISWA -->
        <div class="form-container">
            <div class="form-card">
                <form action="" method="POST" style="padding: 2rem;">
                    
                    <!-- Form Input 1: Pilihan Siswa Perwalian -->
                    <div class="form-group">
                        <label><i class="fas fa-user-graduate" style="color: #3b82f6;"></i> Siswa yang Dilaporkan</label>
                        <select name="siswa_id" class="form-control" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php while($s = mysqli_fetch_assoc($query_siswa)): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($selected_siswa_id == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo $s['nisn']; ?> - <?php echo htmlspecialchars(ucwords(strtolower($s['nama_lengkap']))); ?>
                                    </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Form Input 2: Pilihan Jenis Pelanggaran & Kategori -->
                    <div class="form-group">
                        <label><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Jenis Pelanggaran</label>
                        <select name="pelanggaran_id" id="pelanggaran_select" class="form-control" required onchange="showKategoriBadge(this)">
                            <option value="">-- Pilih Jenis Pelanggaran --</option>
                            <?php 
                            $kat_labels = [
                                'Ringan' => '🟢 Pelanggaran Ringan',
                                'Sedang' => '🟡 Pelanggaran Sedang',
                                'Berat'  => '🔴 Pelanggaran Berat'
                            ];
                            foreach (['Ringan', 'Sedang', 'Berat'] as $kat):
                                if (!empty($pelanggaran_by_kat[$kat])):
                            ?>
                                <optgroup label="<?php echo $kat_labels[$kat]; ?>">
                                    <?php foreach ($pelanggaran_by_kat[$kat] as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-kategori="<?php echo $p['kategori']; ?>">
                                            [<?php echo $p['kategori']; ?>] <?php echo htmlspecialchars($p['nama_pelanggaran']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <div id="badge_preview_container" style="margin-top: 10px; display: none;"></div>
                    </div>

                    <!-- Form Input 3: Tanggal Kejadian -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt" style="color: #10b981;"></i> Tanggal Kejadian</label>
                        <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Form Input 4: Keterangan Kronologi Kejadian -->
                    <div class="form-group">
                        <label><i class="fas fa-align-left" style="color: #f59e0b;"></i> Keterangan Kejadian</label>
                        <textarea name="keterangan" class="form-control" rows="4" placeholder="Jelaskan detail kronologi kejadian secara terperinci..." required></textarea>
                    </div>

                    <!-- Form Input 5: Tindak Lanjut / Pembinaan Langsung Wali Kelas -->
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <span><i class="fas fa-user-check" style="color: #8b5cf6;"></i> Tindak Lanjut / Pembinaan Langsung Wali Kelas</span>
                            <span id="tindak_lanjut_status_badge" style="font-size: 0.72rem; padding: 3px 10px; border-radius: 6px; font-weight: 700; background: #f1f5f9; color: #64748b; text-transform: uppercase;">(PILIH JENIS PELANGGARAN TERLEBIH DAHULU)</span>
                        </label>
                        <textarea name="tindak_lanjut" id="tindak_lanjut_input" class="form-control" rows="3" placeholder="Pilih jenis pelanggaran di atas terlebih dahulu..." disabled></textarea>
                        <div id="tindak_lanjut_info" style="font-size: 0.825rem; margin-top: 8px; display: none; line-height: 1.4; padding: 8px 12px; border-radius: 6px;"></div>
                    </div>

                    <!-- Tombol Aksi Submit & Batal -->
                    <div style="display: flex; gap: 1rem; margin-top: 2.25rem; align-items: center;">
                        <button type="submit" name="lapor" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Kirim Laporan ke Guru BK
                        </button>
                        <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT: TOGGLE SIDEBAR MOBILE & TABEL RESPONSIF -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle Sidebar Drawer Mobile
        const toggleBtn = document.getElementById('mobile-toggle');
        const sidebar   = document.querySelector('.sidebar');
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

    function showKategoriBadge(select) {
        const container = document.getElementById('badge_preview_container');
        const input = document.getElementById('tindak_lanjut_input');
        const badge = document.getElementById('tindak_lanjut_status_badge');
        const info = document.getElementById('tindak_lanjut_info');

        if (!container) return;
        const selectedOpt = select.options[select.selectedIndex];
        const kat = selectedOpt ? selectedOpt.getAttribute('data-kategori') : '';

        if (!kat) {
            container.style.display = 'none';
            container.innerHTML = '';
            if (input) {
                input.disabled = true;
                input.required = false;
                input.value = '';
                input.placeholder = 'Pilih jenis pelanggaran di atas terlebih dahulu...';
            }
            if (badge) {
                badge.style.background = '#f1f5f9';
                badge.style.color = '#64748b';
                badge.innerText = '(PILIH JENIS PELANGGARAN TERLEBIH DAHULU)';
            }
            if (info) {
                info.style.display = 'none';
            }
            return;
        }

        let bg = '#eff6ff', color = '#2563eb', border = '#bfdbfe', icon = 'fa-info-circle', label = 'Pelanggaran Ringan';
        if (kat === 'Ringan') {
            bg = '#f0fdf4'; color = '#16a34a'; border = '#bbf7d0'; icon = 'fa-info-circle'; label = 'Pelanggaran Ringan';
        } else if (kat === 'Sedang') {
            bg = '#fffbeb'; color = '#d97706'; border = '#fde68a'; icon = 'fa-exclamation-circle'; label = 'Pelanggaran Sedang';
        } else if (kat === 'Berat') {
            bg = '#fef2f2'; color = '#dc2626'; border = '#fecaca'; icon = 'fa-exclamation-triangle'; label = 'Pelanggaran Berat';
        }

        container.style.display = 'block';
        container.innerHTML = `
            <div style="background: ${bg}; color: ${color}; border: 1px solid ${border}; padding: 8px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas ${icon}"></i> Tingkat Keparahan: <strong>${label}</strong>
            </div>
        `;

        if (input && badge && info) {
            input.disabled = false;
            if (kat === 'Ringan') {
                input.required = true;
                input.placeholder = 'Tuliskan tindakan pembinaan/teguran langsung yang telah dilakukan Wali Kelas kepada siswa...';
                badge.style.background = '#dcfce7';
                badge.style.color = '#15803d';
                badge.innerText = '(WAJIB DIISI - SELESAI OTOMATIS)';
                info.style.display = 'block';
                info.style.background = '#f0fdf4';
                info.style.color = '#15803d';
                info.style.border = '1px solid #bbf7d0';
                info.innerHTML = '<i class="fas fa-check-circle"></i> Untuk <strong>Pelanggaran Ringan</strong>, setelah Wali Kelas menginput pembinaan, laporan akan <strong>diselesaikan secara otomatis</strong> di sistem.';
            } else {
                input.required = false;
                input.placeholder = 'Tuliskan pembinaan awal dari Wali Kelas (jika ada)...';
                badge.style.background = '#e0f2fe';
                badge.style.color = '#0369a1';
                badge.innerText = '(OPSIONAL)';
                info.style.display = 'block';
                info.style.background = '#fffbeb';
                info.style.color = '#b45309';
                info.style.border = '1px solid #fde68a';
                info.innerHTML = '<i class="fas fa-info-circle"></i> Untuk Pelanggaran <strong>' + kat + '</strong>, laporan akan diteruskan ke Guru BK untuk ditindaklanjuti lebih lanjut (Layanan Konseling).';
            }
        }
    }
    </script>
</body>
</html>
