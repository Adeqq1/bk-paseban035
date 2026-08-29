<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// =========================================================================
// 1. CEK OTENTIKASI SISTEM & HAK AKSES USER (ROLE ADMIN)
// Mengamankan halaman agar hanya pengguna dengan peran 'admin' yang bisa mengakses.
// =========================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Jika bukan admin, alihkan ke login
    exit();
}

// =========================================================================
// 2. PROSES TAMBAH CATATAN PELANGGARAN SISWA
// Menambahkan rekaman catatan pelanggaran siswa ke tabel `catatan_pelanggaran`.
// =========================================================================
if (isset($_POST['tambah'])) {
    $siswa_id = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    $pelanggaran_id = mysqli_real_escape_string($koneksi, $_POST['pelanggaran_id']);
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $pelapor_asli = mysqli_real_escape_string($koneksi, $_POST['pelapor_asli']);

    // Cari Wali Kelas dari siswa yang bersangkutan secara otomatis
    $query_wali = mysqli_query($koneksi, "
        SELECT k.wali_kelas_id 
        FROM siswa s 
        JOIN kelas k ON s.kelas_id = k.id 
        WHERE s.id = '$siswa_id'
    ");
    $wali = mysqli_fetch_assoc($query_wali);
    $guru_id = $wali['wali_kelas_id'];

    if (empty($guru_id)) {
        $msg = "error_wali_kelas";
    } else {
        // Simpan data catatan pelanggaran
        $query = "INSERT INTO catatan_pelanggaran (siswa_id, pelanggaran_id, guru_id, tanggal, keterangan, pelapor_asli) 
                  VALUES ('$siswa_id', '$pelanggaran_id', '$guru_id', '$tanggal', '$keterangan', NULLIF('$pelapor_asli', ''))";
        
        if (mysqli_query($koneksi, $query)) {
            $report_id = mysqli_insert_id($koneksi);
            
            // Cek kategori jenis pelanggaran untuk otomatisasi penyelesaian pelanggaran ringan (1x & 2x)
            $query_jp = mysqli_query($koneksi, "SELECT nama_pelanggaran, kategori, poin FROM jenis_pelanggaran WHERE id = '$pelanggaran_id'");
            $jp = mysqli_fetch_assoc($query_jp);
            $kategori = $jp['kategori'] ?? '';
            $nama_pelanggaran = $jp['nama_pelanggaran'] ?? '';
            $poin_pelanggaran = (int)($jp['poin'] ?? 0);

            if ($kategori === 'Ringan' && $poin_pelanggaran <= 20) {
                // Hitung frekuensi laporan pelanggaran sejenis untuk siswa ini di semester berjalan
                $current_semester = date('m') >= 7 ? '1' : '2';
                $current_tahun = date('Y');
                $sem_start = ($current_semester == '1') ? "$current_tahun-07-01" : "$current_tahun-01-01";
                $sem_end = ($current_semester == '1') ? "$current_tahun-12-31" : "$current_tahun-06-30";

                $query_count = mysqli_query($koneksi, "
                    SELECT COUNT(cp.id) as total 
                    FROM catatan_pelanggaran cp
                    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
                    WHERE cp.siswa_id = '$siswa_id' 
                      AND jp.kategori = 'Ringan' 
                      AND jp.poin <= 20
                      AND cp.tanggal BETWEEN '$sem_start' AND '$sem_end'
                ");
                $row_count = mysqli_fetch_assoc($query_count);
                $count_laporan = $row_count['total'] ?? 1;

                if ($count_laporan <= 2) {
                    // Selesaikan secara otomatis oleh sistem (pembinaan Wali Kelas)
                    $masalah_auto = mysqli_real_escape_string($koneksi, "Siswa melakukan pelanggaran ringan: " . $nama_pelanggaran . " (Laporan ke-" . $count_laporan . ").");
                    $solusi_auto = mysqli_real_escape_string($koneksi, "Telah diberikan pembinaan langsung oleh Wali Kelas (Pembinaan ke-" . $count_laporan . ").");
                    $nama_pelanggaran_esc = mysqli_real_escape_string($koneksi, $nama_pelanggaran);
                    
                    $query_auto_konseling = "
                        INSERT INTO konseling (siswa_id, guru_id, catatan_pelanggaran_id, tanggal, masalah, solusi, status, jenis_konseling, topik_permasalahan)
                        VALUES ('$siswa_id', '$guru_id', '$report_id', '$tanggal', '$masalah_auto', '$solusi_auto', 'Selesai', 'Tindak Lanjut', '$nama_pelanggaran_esc')
                    ";
                    mysqli_query($koneksi, $query_auto_konseling);
                }
            }

            $msg = "success_tambah";
        } else {
            $msg = "error";
        }
    }
}

// =========================================================================
// 3. QUERY PENAMPILAN DATA & FILTER SEMESTER / TAHUN / SISWA
// Mengambil daftar catatan pelanggaran dengan gabungan (JOIN) data siswa, kelas, jenis pelanggaran, dan wali kelas.
// =========================================================================
$query_semua_siswa = mysqli_query($koneksi, "SELECT id, nama_lengkap, nisn FROM siswa ORDER BY nama_lengkap ASC");

// Tentukan rentang semester (Ganjil: Jul-Des, Genap: Jan-Jun) dan tahun ajaran
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
}

$where_siswa = $filter_siswa_id ? "AND cp.siswa_id = '$filter_siswa_id'" : "";

// Jalankan Query SQL Penarikan Data Catatan Pelanggaran
$query_catatan = mysqli_query($koneksi, "
    SELECT cp.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas, 
           jp.nama_pelanggaran, jp.poin, jp.kategori,
           g.nama_lengkap as nama_guru, cp.pelapor_asli
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    JOIN guru g ON cp.guru_id = g.id
    WHERE cp.tanggal BETWEEN '$start_date' AND '$end_date'
    $where_siswa
    ORDER BY cp.tanggal DESC, cp.id DESC
");

// Ambil daftar siswa & jenis pelanggaran untuk opsi dropdown modal
$data_siswa = mysqli_query($koneksi, "SELECT s.id, s.nama_lengkap, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id ORDER BY s.nama_lengkap ASC");
$data_pelanggaran = mysqli_query($koneksi, "SELECT * FROM jenis_pelanggaran ORDER BY nama_pelanggaran ASC");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran | SI BK7</title>
    <meta name="description" content="Catatan Pelanggaran Siswa - SI BK7">
    <!-- File CSS Utama Admin & CDN Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- =================================================================== -->
    <!-- NAVIGATION SIDEBAR UTAMA (PANEL ADMIN)                              -->
    <!-- =================================================================== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SI BK<span>7</span></h3>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="kelola_siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
            <li><a href="kelola_guru.php"><i class="fas fa-chalkboard-teacher"></i> Kelola Wali Kelas</a></li>
            <li><a href="kelola_kelas.php"><i class="fas fa-school"></i> Kelola Kelas</a></li>
        </ul>
        <div class="sidebar-label">Data & Laporan</div>
        <ul class="sidebar-menu">
            <li><a href="kelola_jenis_pelanggaran.php"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
            <li><a href="pelanggaran.php" class="active"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a></li>
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
                <?php if (!empty($_SESSION['foto']) && file_exists('../assets/uploads/profil/' . $_SESSION['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $_SESSION['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
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

    <!-- =================================================================== -->
    <!-- KONTEN UTAMA HALAMAN CATATAN PELANGGARAN                            -->
    <!-- =================================================================== -->
    <div class="main-content">
        <!-- Banner Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(239,68,68,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.8rem; color: #f87171;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Catatan Pelanggaran</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Rekam dan pantau riwayat pelanggaran seluruh siswa.</p>
                </div>
            </div>
            <!-- Tombol Pemicu Modal Input Pelanggaran Baru -->
            <button class="btn-pelanggaran-utama" onclick="openModal('modalTambah')" id="btnTambahPelanggaran" style="position: relative; z-index: 1;">
                <i class="fas fa-plus"></i> Input Pelanggaran
            </button>
        </div>

        <!-- Blok Notifikasi Umpan Balik (Alert Message) -->
        <?php if (isset($msg)): ?>
            <div class="alert <?php echo strpos($msg, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <i class="fas <?php echo strpos($msg, 'success') !== false ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <?php 
                    if ($msg == 'success_tambah') echo "Catatan pelanggaran siswa berhasil ditambahkan!";
                    if ($msg == 'error_wali_kelas') echo "Siswa yang dipilih belum memiliki Wali Kelas! Silakan atur Wali Kelas terlebih dahulu pada menu Kelola Kelas.";
                    if ($msg == 'error') echo "Terjadi kesalahan sistem.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Kartu Filter Periode & Pencarian Siswa Spesifik -->
        <div class="data-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 150px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Semester</label>
                    <select name="semester" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Semester Ganjil (Jul - Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Semester Genap (Jan - Jun)</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
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
                <div style="flex: 2; min-width: 200px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Filter Siswa (Opsional)</label>
                    <select name="siswa_id" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="">-- Semua Siswa --</option>
                        <?php while($s = mysqli_fetch_assoc($query_semua_siswa)): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filter_siswa_id == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 8px; height: 42px;">
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.25rem; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if($filter_siswa_id || isset($_GET['semester'])): ?>
                    <a href="pelanggaran.php" class="btn btn-secondary" style="padding: 0 1.25rem; text-decoration: none; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px; background: #e2e8f0; color: #475569;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Kartu Tabel Data Catatan Pelanggaran Siswa -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Daftar Pelanggaran Siswa</h2>
                <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.75rem; padding: 6px 14px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo mysqli_num_rows($query_catatan); ?> Pelanggaran
                </span>
            </div>
            <!-- Tabel Responsif Catatan Pelanggaran -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 75px; text-align: center;">No</th>
                            <th>Tanggal</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Pelanggaran</th>
                            <th style="text-align: center;">Poin</th>
                            <th>Wali Kelas</th>
                            <th>Pelapor Asli</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        if ($query_catatan && mysqli_num_rows($query_catatan) > 0):
                            while($row = mysqli_fetch_assoc($query_catatan)): 
                        ?>
                        <tr>
                            <!-- Kolom 1: Nomor Urut Data -->
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no++; ?></td>
                            
                            <!-- Kolom 2: Tanggal Pelanggaran -->
                            <td><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></small></td>
                            
                            <!-- Kolom 3: NISN Siswa -->
                            <td><small style="color: #475569; font-weight: 400;"><?php echo htmlspecialchars($row['nisn']); ?></small></td>
                            
                            <!-- Kolom 4: Nama Siswa -->
                            <td><span style="color: #334155; font-weight: 400;"><?php echo htmlspecialchars($row['nama_siswa']); ?></span></td>
                            
                            <!-- Kolom 5: Kelas -->
                            <td>
                                <?php if (!empty($row['nama_kelas'])): ?>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($row['nama_kelas']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Kolom 6: Jenis & Kategori Pelanggaran -->
                            <td>
                                <span style="display: block; margin-bottom: 4px; color: #334155; font-weight: 400; line-height: 1.4;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></span>
                                <?php 
                                    $badge_class = 'badge-info';
                                    if($row['kategori'] == 'Sedang') $badge_class = 'badge-warning';
                                    if($row['kategori'] == 'Berat') $badge_class = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['kategori']); ?></span>
                            </td>
                            
                            <!-- Kolom 7: Bobot Poin Pelanggaran -->
                            <td style="text-align: center;">
                                <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                    +<?php echo $row['poin']; ?> Poin
                                </span>
                            </td>
                            
                            <!-- Kolom 8: Wali Kelas Pengampu -->
                            <td><small style="color: #475569; font-weight: 400;"><?php echo htmlspecialchars($row['nama_guru']); ?></small></td>
                            
                            <!-- Kolom 9: Pelapor Asli Kasus -->
                            <td><small style="color: #64748b; font-weight: 400;"><?php echo !empty($row['pelapor_asli']) ? htmlspecialchars($row['pelapor_asli']) : '—'; ?></small></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #94a3b8; padding: 2rem;">Tidak ada data catatan pelanggaran pada periode ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DIALOG POPUP: INPUT PELANGGARAN BARU                          -->
    <!-- =================================================================== -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Input Pelanggaran Baru</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Rekam catatan pelanggaran siswa.</p>
                </div>
                <div class="close" onclick="closeModal('modalTambah')">&#x2715;</div>
            </div>
            <!-- Form Input Pelanggaran -->
            <form action="pelanggaran.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569;">
                        <i class="fas fa-user" style="color: var(--primary);"></i> Pilih Siswa
                    </label>
                    <select name="siswa_id" class="form-control" required style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <option value="">-- Pilih Siswa --</option>
                        <?php 
                        mysqli_data_seek($data_siswa, 0);
                        while($ds = mysqli_fetch_assoc($data_siswa)): 
                        ?>
                            <option value="<?php echo $ds['id']; ?>">
                                <?php echo htmlspecialchars($ds['nama_lengkap']) . ' (' . ($ds['nama_kelas'] ?? 'Tanpa Kelas') . ')'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569;">
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Jenis Pelanggaran
                    </label>
                    <select name="pelanggaran_id" class="form-control" required style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <option value="">-- Pilih Pelanggaran --</option>
                        <?php 
                        mysqli_data_seek($data_pelanggaran, 0);
                        while($dp = mysqli_fetch_assoc($data_pelanggaran)): 
                        ?>
                            <option value="<?php echo $dp['id']; ?>">
                                [<?php echo $dp['kategori']; ?>] <?php echo htmlspecialchars($dp['nama_pelanggaran']) . ' (+' . $dp['poin'] . ' Poin)'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569;">
                        <i class="fas fa-calendar" style="color: var(--primary);"></i> Tanggal Pelanggaran
                    </label>
                    <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569;">
                        <i class="fas fa-user-edit" style="color: var(--primary);"></i> Pelapor Asli (Opsional)
                    </label>
                    <input type="text" name="pelapor_asli" class="form-control" placeholder="Contoh: Guru Piket, Satpam, Guru Mapel" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569;">
                        <i class="fas fa-align-left" style="color: var(--primary);"></i> Keterangan / Detail Kejadian
                    </label>
                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Jelaskan kronologi singkat kejadian..." style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1;"></textarea>
                </div>

                <div class="modal-footer" style="margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600; background: #ef4444; border: none;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Catatan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- SCRIPT JAVASCRIPT KONTROL POPUP MODAL DIALOG                        -->
    <!-- =================================================================== -->
    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

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
