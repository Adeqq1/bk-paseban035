<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru['id'];

if (!isset($_GET['id'])) {
    header("Location: kunjungan_rumah.php");
    exit();
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

$query_edit = mysqli_query($koneksi, "SELECT * FROM kunjungan_rumah WHERE id = '$id' AND guru_id = '$guru_id'");
$data = mysqli_fetch_assoc($query_edit);

if (!$data) {
    header("Location: kunjungan_rumah.php");
    exit();
}

// Daftar siswa aktif
$query_siswa = mysqli_query($koneksi, "
    SELECT s.id, s.nama_lengkap, s.nisn, s.jenis_kelamin, s.alamat, s.kelas_id, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    WHERE s.status = 'aktif' OR s.id = '{$data['siswa_id']}'
    ORDER BY k.nama_kelas, s.nama_lengkap
");

$siswa_list = [];
$siswa_js_data = [];

while ($row = mysqli_fetch_assoc($query_siswa)) {
    $siswa_list[] = $row;
    $siswa_js_data[$row['id']] = [
        'nama_lengkap' => $row['nama_lengkap'],
        'nisn' => $row['nisn'],
        'kelas_nama' => $row['nama_kelas'] ?? '-',
        'jk_text' => $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : ($row['jenis_kelamin'] == 'P' ? 'Perempuan' : '-'),
        'alamat' => !empty($row['alamat']) ? $row['alamat'] : ''
    ];
}

// Proses Update Data
if (isset($_POST['update'])) {
    $siswa_id = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    $nomor_urut = mysqli_real_escape_string($koneksi, $_POST['nomor_urut'] ?? '');
    $tanggal_pelaksanaan = mysqli_real_escape_string($koneksi, $_POST['tanggal_pelaksanaan']);
    $nama_ortu = mysqli_real_escape_string($koneksi, $_POST['nama_ortu']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $yang_ditemui = mysqli_real_escape_string($koneksi, $_POST['yang_ditemui']);
    $permasalahan = mysqli_real_escape_string($koneksi, $_POST['permasalahan']);
    $tujuan_home_visit = mysqli_real_escape_string($koneksi, $_POST['tujuan_home_visit']);
    $hasil_home_visit = mysqli_real_escape_string($koneksi, $_POST['hasil_home_visit']);

    if (!empty($alamat)) {
        mysqli_query($koneksi, "UPDATE siswa SET alamat = '$alamat' WHERE id = '$siswa_id'");
    }

    $query_update = "UPDATE kunjungan_rumah SET 
                     nomor_urut = '$nomor_urut',
                     siswa_id = '$siswa_id', 
                     tanggal_pelaksanaan = '$tanggal_pelaksanaan', 
                     nama_ortu = '$nama_ortu', 
                     alamat = '$alamat', 
                     yang_ditemui = '$yang_ditemui', 
                     permasalahan = '$permasalahan', 
                     tujuan_home_visit = '$tujuan_home_visit', 
                     hasil_home_visit = '$hasil_home_visit'
                     WHERE id = '$id' AND guru_id = '$guru_id'";
    
    if (mysqli_query($koneksi, $query_update)) {
        header("Location: kunjungan_rumah.php?pesan=success_edit");
        exit();
    } else {
        $error = "Gagal memperbarui Laporan Kunjungan Rumah: " . mysqli_error($koneksi);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Laporan Home Visit | SI BK7</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body, .main-content, h1, h2, h3, .sidebar {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
        .data-card {
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            background: white !important;
            padding: 2rem !important;
            margin-bottom: 2.5rem !important;
        }
        .form-grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .full-width-col {
            grid-column: span 2;
        }
        @media (max-width: 992px) {
            .form-grid-layout { grid-template-columns: 1fr; }
            .full-width-col { grid-column: span 1; }
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

    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SI BK<span>7</span></h3>
            <p>Guru BK Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
            <li><a href="daftar_panggilan.php"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
            <li><a href="alih_kasus.php"><i class="fas fa-share-square"></i> Alih Tangan Kasus</a></li>
            <li><a href="kunjungan_rumah.php" class="active"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
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
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-edit" style="font-size: 1.8rem; color: #38bdf8;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white;">Edit Laporan <span style="color: #38bdf8;">Kunjungan Rumah (Home Visit)</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Perbarui Data Laporan Hasil Kunjungan Rumah Konseli</p>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert badge-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b; font-weight: 500;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <h2 style="margin-bottom: 1.5rem; color: #1e293b; font-size: 1.2rem; font-weight: 700;">Formulir Kunjungan Rumah (Home Visit)</h2>
            <form action="" method="POST">
                <div class="form-grid-layout">
                    <div>
                        <!-- Section A: Identitas Konseli (Siswa) -->
                        <div style="background: #f0fdf4; padding: 1.25rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 1.5rem;">
                            <p style="margin: 0 0 12px 0; font-weight: 700; color: #166534; font-size: 0.85rem; text-transform: uppercase;"><i class="fas fa-user-check" style="margin-right: 6px;"></i>A. IDENTITAS KONSELI (SISWA)</p>
                            
                            <div style="margin-bottom: 1rem;">
                                <label for="siswa_id" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">PILIH SISWA (KONSELI)</label>
                                <select name="siswa_id" id="siswa_id" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" required>
                                    <option value="">-- Cari & Pilih Siswa --</option>
                                    <?php foreach ($siswa_list as $sl): ?>
                                        <option value="<?php echo $sl['id']; ?>" <?php echo $sl['id'] == $data['siswa_id'] ? 'selected' : ''; ?>>
                                            [<?php echo htmlspecialchars($sl['nama_kelas'] ?? '-'); ?>] <?php echo htmlspecialchars($sl['nama_lengkap']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom: 1rem;">
                                <label for="alamat" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">ALAMAT KUNJUNGAN RUMAH</label>
                                <input type="text" name="alamat" id="alamat" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: Jln. Lintas Sumatera, Tanah Abang" value="<?php echo htmlspecialchars($data['alamat'] ?? ''); ?>" required>
                            </div>

                            <div style="margin-bottom: 1rem;">
                                <label for="nama_ortu" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NAMA ORANG TUA / WALI KONSELI</label>
                                <input type="text" name="nama_ortu" id="nama_ortu" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: Kasmi Dewi" value="<?php echo htmlspecialchars($data['nama_ortu'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- Section D: Pelaksanaan Kunjungan Rumah -->
                        <div style="background: #f0f9ff; padding: 1.25rem; border-radius: 8px; border: 1px solid #bae6fd; margin-bottom: 1.5rem;">
                            <p style="margin: 0 0 12px 0; font-weight: 700; color: #0369a1; font-size: 0.85rem; text-transform: uppercase;"><i class="fas fa-calendar-alt" style="margin-right: 6px;"></i>D. PELAKSANAAN KUNJUNGAN RUMAH</p>
                            
                            <div style="margin-bottom: 1rem;">
                                <label for="tanggal_pelaksanaan" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">1. TANGGAL PELAKSANAAN</label>
                                <input type="date" name="tanggal_pelaksanaan" id="tanggal_pelaksanaan" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" value="<?php echo htmlspecialchars($data['tanggal_pelaksanaan']); ?>" required>
                            </div>

                            <div>
                                <label for="yang_ditemui" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">2. YANG DITEMUI DI RUMAH</label>
                                <input type="text" name="yang_ditemui" id="yang_ditemui" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: Ortu Konseli (Bapak dan Ibu)" value="<?php echo htmlspecialchars($data['yang_ditemui']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <!-- Section B: Permasalahan Konseli -->
                        <div style="margin-bottom: 1.5rem;">
                            <label for="permasalahan" style="display: block; font-weight: 700; color: #334155; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">B. PERMASALAHAN KONSELI</label>
                            <textarea name="permasalahan" id="permasalahan" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required><?php echo htmlspecialchars($data['permasalahan']); ?></textarea>
                        </div>

                        <!-- Section C: Tujuan Home Visit -->
                        <div style="margin-bottom: 1.5rem;">
                            <label for="tujuan_home_visit" style="display: block; font-weight: 700; color: #334155; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">C. TUJUAN HOME VISIT</label>
                            <textarea name="tujuan_home_visit" id="tujuan_home_visit" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required><?php echo htmlspecialchars($data['tujuan_home_visit']); ?></textarea>
                        </div>
                    </div>

                    <!-- Section E: Hasil Home Visit -->
                    <div class="full-width-col" style="margin-bottom: 1.5rem;">
                        <label for="hasil_home_visit" style="display: block; font-weight: 700; color: #334155; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">E. HASIL HOME VISIT</label>
                        <textarea name="hasil_home_visit" id="hasil_home_visit" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required><?php echo htmlspecialchars($data['hasil_home_visit']); ?></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <button type="submit" name="update" class="btn-submit">
                        <i class="fas fa-save"></i> Perbarui Laporan
                    </button>
                    <a href="kunjungan_rumah.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
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
