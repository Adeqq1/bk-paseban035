<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$report_id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil detail laporan
$query_report = mysqli_query($koneksi, "
    SELECT cp.*, s.nama_lengkap as nama_siswa, jp.nama_pelanggaran, jp.poin, g.nama_lengkap as nama_pelapor
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    JOIN guru g ON cp.guru_id = g.id
    WHERE cp.id = '$report_id'
");
$report = mysqli_fetch_assoc($query_report);

if (!$report) {
    header("Location: index.php");
    exit();
}

// Proses Simpan Bimbingan
if (isset($_POST['simpan'])) {
    $solusi = mysqli_real_escape_string($koneksi, $_POST['solusi']);
    $tanggal_pertemuan = mysqli_real_escape_string($koneksi, $_POST['tanggal_pertemuan']);
    $jam_pertemuan = mysqli_real_escape_string($koneksi, $_POST['jam_pertemuan']);
    $waktu = $tanggal_pertemuan . ' ' . $jam_pertemuan . ':00';
    $waktu_val = "'$waktu'";
    $tempat = mysqli_real_escape_string($koneksi, $_POST['tempat_pertemuan']);
    $tempat_val = "'$tempat'";
    $tanggal = date('Y-m-d');
    $siswa_id = $report['siswa_id'];
    $masalah = mysqli_real_escape_string($koneksi, $_POST['masalah']);
    $topik_permasalahan = mysqli_real_escape_string($koneksi, $_POST['topik_permasalahan']);
    $bidang_bimbingan = mysqli_real_escape_string($koneksi, $_POST['bidang_bimbingan']);

    $query = "INSERT INTO konseling (siswa_id, guru_id, catatan_pelanggaran_id, tanggal, masalah, solusi, status, waktu_pertemuan, tempat_pertemuan, jenis_konseling, topik_permasalahan, bidang_bimbingan) 
              VALUES ('$siswa_id', '$guru_id', '$report_id', '$tanggal', '$masalah', '$solusi', 'Selesai', $waktu_val, $tempat_val, 'Tindak Lanjut', '$topik_permasalahan', '$bidang_bimbingan')";
    
    if (mysqli_query($koneksi, $query)) {
        header("Location: pelanggaran_masuk.php?pesan=success_tindak");
        exit();
    } else {
        $error = "Gagal menyimpan bimbingan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tindak Lanjut | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800:wght@300;400;600;700&display=swap" rel="stylesheet">
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
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php" class="active"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
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
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-handshake" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Tindak Lanjut <span style="color: #60a5fa;">Pelanggaran</span></h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Selesaikan laporan pelanggaran dari wali kelas</p>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert badge-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b; display: block; border: 1px solid #fee2e2;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 2rem; margin-bottom: 2rem;">
            <label style="display: block; margin-bottom: 1rem; color: #475569; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.05em; text-transform: uppercase;">DETAIL LAPORAN DARI WALI KELAS</label>
            <div class="student-preview" style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.95rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                <div style="display: flex; margin-bottom: 12px;">
                    <div style="width: 160px; font-weight: 600; color: #475569;">Nama Siswa</div>
                    <div style="color: #1e293b; font-weight: 600;">: <?php echo $report['nama_siswa']; ?></div>
                </div>
                <div style="display: flex; margin-bottom: 12px; align-items: center;">
                    <div style="width: 160px; font-weight: 600; color: #475569;">Pelanggaran</div>
                    <div style="color: #1e293b; display: flex; align-items: center; gap: 8px;">
                        : <?php echo $report['nama_pelanggaran']; ?> 
                        <span style="background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">+<?php echo $report['poin']; ?> Poin</span>
                    </div>
                </div>
                <div style="display: flex; margin-bottom: 12px;">
                    <div style="width: 160px; font-weight: 600; color: #475569;">Dilaporkan Oleh</div>
                    <div style="color: #1e293b;">: <?php echo $report['nama_pelapor']; ?> (Wali Kelas)</div>
                </div>
                <div style="display: flex; margin-bottom: 12px;">
                    <div style="width: 160px; font-weight: 600; color: #475569;">Tanggal Lapor</div>
                    <div style="color: #1e293b;">: <?php echo date('d/m/Y', strtotime($report['tanggal'])); ?></div>
                </div>
                <div style="display: flex;">
                    <div style="width: 160px; font-weight: 600; color: #475569;">Keterangan</div>
                    <div style="color: #1e293b;">: <?php echo $report['keterangan']; ?></div>
                </div>
            </div>
        </div>

        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 2rem;">
            <label style="display: block; margin-bottom: 4px; color: #475569; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.05em; text-transform: uppercase;">FORM TINDAK LANJUT KASUS</label>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 2rem;">Silakan isi rincian pelaksanaan layanan konseling untuk menyelesaikan laporan pelanggaran ini.</p>
            
            <form action="" method="POST">
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">TANGGAL PERTEMUAN</label>
                        <input type="date" name="tanggal_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">WAKTU PERTEMUAN</label>
                        <input type="time" name="jam_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo date('H:i'); ?>" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">TEMPAT PERTEMUAN</label>
                        <input type="text" name="tempat_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="Ruang BK" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">TOPIK PERMASALAHAN</label>
                    <input type="text" name="topik_permasalahan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo htmlspecialchars($report['nama_pelanggaran']); ?>" required>
                    <small style="color: #64748b; font-size: 0.8rem; margin-top: 4px; display: block;">Topik ini akan tercatat dalam laporan resmi layanan bimbingan konseling.</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">BIDANG BIMBINGAN</label>
                    <input type="text" name="bidang_bimbingan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="Pribadi, Sosial" required>
                    <small style="color: #64748b; font-size: 0.8rem; margin-top: 4px; display: block;">Pilihan resmi: Pribadi, Sosial, Belajar, Karir</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">DESKRIPSI PERMASALAHAN</label>
                    <textarea name="masalah" class="form-control" rows="8" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc; resize: vertical;" placeholder="Tuliskan ringkasan masalah berdasarkan asesmen konseling BK..." required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">KESEPAKATAN SOLUSI & RENCANA TINDAKAN</label>
                    <textarea name="solusi" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Tuliskan kesepakatan solusi konseling, tindak lanjut, atau rujukan kasus jika diperlukan." required></textarea>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Simpan & Selesaikan Kasus
                    </button>
                    <a href="index.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Toggle Menu Mobile & Tabel Responsif -->
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
