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

// Ambil daftar siswa untuk pilihan dropdown (hanya nama_lengkap dan kelas)
$query_siswa = mysqli_query($koneksi, "SELECT s.id, s.nama_lengkap, s.nisn, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif' ORDER BY s.nama_lengkap ASC");

// Proses Simpan Bimbingan Mandiri / Konseling Direct
if (isset($_POST['simpan'])) {
    $siswa_id = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    $jenis_konseling = mysqli_real_escape_string($koneksi, $_POST['jenis_konseling']);
    $topik = mysqli_real_escape_string($koneksi, $_POST['topik_permasalahan']);
    $masalah = mysqli_real_escape_string($koneksi, $_POST['masalah']);
    $solusi = mysqli_real_escape_string($koneksi, $_POST['solusi']);
    $tanggal_pertemuan = mysqli_real_escape_string($koneksi, $_POST['tanggal_pertemuan']);
    $jam_pertemuan = mysqli_real_escape_string($koneksi, $_POST['jam_pertemuan']);
    $waktu = $tanggal_pertemuan . ' ' . $jam_pertemuan;
    $tempat = mysqli_real_escape_string($koneksi, $_POST['tempat_pertemuan']);

    $query = "INSERT INTO konseling (siswa_id, guru_id, tanggal, topik_permasalahan, masalah, solusi, status, waktu_pertemuan, tempat_pertemuan, jenis_konseling) 
              VALUES ('$siswa_id', '$guru_id', '$tanggal_pertemuan', '$topik', '$masalah', '$solusi', 'Selesai', '$waktu', '$tempat', '$jenis_konseling')";
    
    if (mysqli_query($koneksi, $query)) {
        header("Location: konseling.php?pesan=success_tambah");
        exit();
    } else {
        $error = "Gagal menyimpan catatan bimbingan konseling.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Bimbingan & Konseling | SI BK7</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SI BK<span>7</span></h3>
            <p>Guru BK Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php" class="active"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
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
                <!-- Mengecek apakah pengguna memiliki foto profil yang tersimpan di sistem -->
                <?php if (!empty($_SESSION['foto']) && file_exists('../assets/uploads/profil/' . $_SESSION['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $_SESSION['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'B', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($guru['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Guru BK'); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Guru BK</div>
            </div>
        </div>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-edit" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white;">Input Sesi Bimbingan & Konseling</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Catat layanan Bimbingan Mandiri / Konseling siswa secara langsung.</p>
                </div>
            </div>
            
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 2rem;">
            <form action="" method="POST">
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                            <i class="fas fa-user-graduate" style="color: #3b82f6;"></i> Pilih Siswa
                        </label>
                        <select name="siswa_id" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php while($s = mysqli_fetch_assoc($query_siswa)): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_lengkap']); ?> (<?php echo htmlspecialchars($s['nama_kelas'] ?? '-'); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                            <i class="fas fa-layer-group" style="color: #3b82f6;"></i> Jenis Layanan
                        </label>
                        <select name="jenis_konseling" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required>
                            <option value="Mandiri">Konseling Individu (Mandiri)</option>
                            <option value="Tindak Lanjut">Konferensi Kasus (Tindak Lanjut)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                            <i class="fas fa-calendar-alt" style="color: #3b82f6;"></i> Tanggal Sesi
                        </label>
                        <input type="date" name="tanggal_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                            <i class="fas fa-clock" style="color: #3b82f6;"></i> Jam Pertemuan
                        </label>
                        <input type="text" name="jam_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo date('H:i'); ?>" placeholder="09:00" required>
                    </div>
                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                            <i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Lokasi / Tempat
                        </label>
                        <input type="text" name="tempat_pertemuan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="Ruang BK" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                        <i class="fas fa-comment-dots" style="color: #3b82f6;"></i> Topik Permasalahan
                    </label>
                    <input type="text" name="topik_permasalahan" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Contoh: Kesulitan Belajar, Bimbingan Karir, Masalah Pribadi" required>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                        <i class="fas fa-sticky-note" style="color: #3b82f6;"></i> Gambaran / Ringkasan Masalah
                    </label>
                    <textarea name="masalah" class="form-control" rows="3" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Tuliskan gambaran singkat keluhan atau permasalahan siswa..." required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.85rem; color: #475569;">
                        <i class="fas fa-check-double" style="color: #22c55e;"></i> Hasil Bimbingan / Solusi
                    </label>
                    <textarea name="solusi" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Tuliskan arahan, tindak lanjut, dan solusi konseling yang diberikan..." required></textarea>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; margin-top: 2rem;">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="fas fa-save"></i> Simpan Catatan Bimbingan
                    </button>
                    <a href="konseling.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
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
