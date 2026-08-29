<?php
/**
 * =========================================================================
 * RIWAYAT SESI KONSELING SISWA - BK SMA 07 Bungo
 * Halaman ini menampilkan seluruh arsip dan riwayat penanganan konseling
 * (baik Bimbingan Mandiri maupun Tindak Lanjut Pelanggaran) yang telah diselesaikan.
 * =========================================================================
 */

session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 1. CEK OTENTIKASI SISTEM & HAK AKSES USER (ROLE GURU BK)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// 2. PROSES HAPUS RIWAYAT KONSELING (100% HAPUS BESERTA CATATAN PELANGGARAN TERKAIT)
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    $check = mysqli_query($koneksi, "SELECT id, catatan_pelanggaran_id FROM konseling WHERE id = '$id'");
    if (mysqli_num_rows($check) > 0) {
        $row_del = mysqli_fetch_assoc($check);
        $cat_id = $row_del['catatan_pelanggaran_id'];
        
        // Hapus data konseling
        mysqli_query($koneksi, "DELETE FROM konseling WHERE id = '$id'");
        
        // Hapus juga catatan pelanggaran terkait jika berasal dari laporan masuk agar 100% terhapus
        if (!empty($cat_id)) {
            mysqli_query($koneksi, "DELETE FROM catatan_pelanggaran WHERE id = '$cat_id'");
        }
        
        header("Location: konseling.php?pesan=success_hapus");
        exit();
    }
}

// 3. QUERY PENAMPILAN RIWAYAT KONSELING & MULTI-FILTER
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Ambil daftar siswa aktif untuk pilihan dropdown Nama Siswa
$query_siswa_list = mysqli_query($koneksi, "SELECT s.id, s.nama_lengkap, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif' ORDER BY s.nama_lengkap ASC");

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
}

$filter_sql = " AND kon.tanggal BETWEEN '$start_date' AND '$end_date'";
if (!empty($filter_siswa_id)) {
    $filter_sql .= " AND kon.siswa_id = '$filter_siswa_id'";
}

// Query 1: Bimbingan Mandiri
$query_mandiri = mysqli_query($koneksi, "
    SELECT kon.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas
    FROM konseling kon
    JOIN siswa s ON kon.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE kon.jenis_konseling = 'Mandiri' $filter_sql
    ORDER BY kon.tanggal DESC, kon.id DESC
");

// Query 2: Tindak Lanjut Pelanggaran
$query_pelanggaran = mysqli_query($koneksi, "
    SELECT kon.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas, jp.nama_pelanggaran, g.jabatan as jabatan_guru
    FROM konseling kon
    JOIN siswa s ON kon.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN catatan_pelanggaran cp ON kon.catatan_pelanggaran_id = cp.id
    LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN guru g ON kon.guru_id = g.id
    WHERE kon.jenis_konseling = 'Tindak Lanjut' $filter_sql
    ORDER BY kon.tanggal DESC, kon.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Konseling Siswa | BK SMA 07 Bungo</title>
    <!-- File CSS Utama & CDN Font Awesome untuk Ikon -->
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
            <h3>BK SMA<span>07</span></h3>
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
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil &amp; Sandi</a></li>
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
                    <i class="fas fa-history" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white;">Riwayat Sesi Konseling</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Arsip rekam jejak bimbingan konseling dan penanganan siswa.</p>
                </div>
            </div>
            <a href="tambah_bimbingan.php" class="btn-tambah-utama">
                <i class="fas fa-plus-circle"></i> Tambah Bimbingan
            </a>
        </div>

        <!-- Blok Notifikasi Berhasil -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success_tambah'): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span>Catatan bimbingan &amp; konseling berhasil ditambahkan dan disimpan ke dalam riwayat!</span>
            </div>
        <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'success_hapus'): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);">
                <i class="fas fa-trash-alt" style="font-size: 1.25rem;"></i>
                <span>Data riwayat konseling dan catatan pelanggaran terkait berhasil dihapus dari sistem!</span>
            </div>
        <?php elseif (isset($_GET['pesan']) && ($_GET['pesan'] == 'success_tindak' || $_GET['pesan'] == 'success')): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span>Tindak lanjut laporan pelanggaran berhasil disimpan dan dicatat dalam riwayat konseling!</span>
            </div>
        <?php endif; ?>

        <!-- Kartu Filter -->
        <div class="data-card" style="margin-bottom: 2rem; padding: 1.5rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div style="flex: 2; min-width: 200px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Nama Siswa</label>
                    <select name="siswa_id" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                        <option value="">-- Semua Siswa --</option>
                        <?php while($sw = mysqli_fetch_assoc($query_siswa_list)): ?>
                            <option value="<?php echo $sw['id']; ?>" <?php echo $filter_siswa_id == $sw['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sw['nama_lengkap']); ?> (<?php echo htmlspecialchars($sw['nama_kelas'] ?? '-'); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 175px;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.85rem; color: #475569;">Semester</label>
                    <select name="semester" class="form-control" style="width: 100%; padding: 0.6rem 2.2rem 0.6rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.875rem;">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 110px;">
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
                <div style="display: flex; gap: 8px; height: 42px;">
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.25rem; font-weight: 600; height: 100%; display: flex; align-items: center; gap: 6px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(isset($_GET['siswa_id']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                    <a href="konseling.php" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Reset Filter" onmouseover="this.style.background='#e2e8f0'; this.style.color='#334155'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                        <i class="fas fa-undo"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TABEL 1: Riwayat Bimbingan Mandiri -->
        <div class="data-card" style="margin-bottom: 2rem;">
            <div class="data-card-header">
                <h2><i class="fas fa-calendar-check" style="color: #22c55e;"></i> Riwayat Bimbingan Mandiri</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 55px; text-align: center;">NO</th>
                            <th>TANGGAL</th>
                            <th>NAMA KONSELI (SISWA)</th>
                            <th>KELAS</th>
                            <th>JENIS LAYANAN</th>
                            <th>TOPIK</th>
                            <th>RINGKASAN MASALAH</th>
                            <th>HASIL &amp; SOLUSI</th>
                            <th style="width: 110px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_mandiri && mysqli_num_rows($query_mandiri) > 0):
                            while($row = mysqli_fetch_assoc($query_mandiri)):
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no++; ?></td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></small></td>
                            <td>
                                <div style="font-weight: 500; color: #1e293b; font-size: 0.9rem; margin-bottom: 2px;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <small style="color: #64748b; font-size: 0.8rem; font-weight: 400;">NISN: <?php echo htmlspecialchars($row['nisn']); ?></small>
                            </td>
                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span></td>
                            <td><span class="badge badge-info" style="background: #e0f2fe; color: #0369a1; font-weight: 500;">Konseling Individu</span></td>
                            <td style="max-width: 200px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['topik_permasalahan'] ?? '-'); ?>
                                </div>
                            </td>
                            <td style="max-width: 220px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['masalah']); ?>
                                </div>
                            </td>
                            <td style="max-width: 240px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['solusi']); ?>
                                </div>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <a href="cetak_konseling.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-primary btn-sm btn-icon" title="Cetak Berita Acara">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Hapus data bimbingan mandiri ini secara permanen?')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada riwayat bimbingan mandiri pada periode ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL 2: Riwayat Tindak Lanjut Pelanggaran -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Riwayat Tindak Lanjut Pelanggaran</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 55px; text-align: center;">NO</th>
                            <th>TANGGAL</th>
                            <th>NAMA KONSELI (SISWA)</th>
                            <th>KELAS</th>
                            <th>JENIS LAYANAN</th>
                            <th>TOPIK</th>
                            <th>RINGKASAN MASALAH</th>
                            <th>HASIL &amp; SOLUSI</th>
                            <th style="width: 110px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_pelanggaran && mysqli_num_rows($query_pelanggaran) > 0):
                            while($row = mysqli_fetch_assoc($query_pelanggaran)):
                        ?>
                        <tr>
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no++; ?></td>
                            <td><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></small></td>
                            <td>
                                <div style="font-weight: 500; color: #1e293b; font-size: 0.9rem; margin-bottom: 2px;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <small style="color: #64748b; font-size: 0.8rem; font-weight: 400;">NISN: <?php echo htmlspecialchars($row['nisn']); ?></small>
                            </td>
                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span></td>
                            <td><span class="badge badge-warning" style="background: #fef3c7; color: #d97706; font-weight: 500;">Konferensi Kasus</span></td>
                            <td style="max-width: 200px;">
                                <?php $topik_tindak = !empty($row['topik_permasalahan']) ? $row['topik_permasalahan'] : ($row['nama_pelanggaran'] ?? '-'); ?>
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;">
                                    <?php echo htmlspecialchars($topik_tindak); ?>
                                </div>
                            </td>
                            <td style="max-width: 220px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #475569; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['masalah']); ?>
                                </div>
                            </td>
                            <td style="max-width: 240px;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                                    <?php echo htmlspecialchars($row['solusi']); ?>
                                </div>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <?php 
                                    // Pengecekan: Jika jabatan yang menangani BUKAN Guru BK
                                    // (artinya ini adalah pelanggaran kecil/ringan yang diselesaikan otomatis oleh sistem/Wali Kelas)
                                    if(isset($row['jabatan_guru']) && $row['jabatan_guru'] != 'Guru BK'): 
                                    ?>
                                        <!-- Tombol cetak dimatikan (berwarna abu-abu) karena tidak perlu cetak RPL untuk penyelesaian otomatis -->
                                        <button class="btn btn-secondary btn-sm btn-icon" style="background: #e2e8f0; color: #94a3b8; border: none; cursor: not-allowed;" title="Selesai otomatis (Tidak Perlu RPL)" disabled>
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php 
                                    // Sebaliknya: Jika yang menangani ADALAH Guru BK
                                    // (artinya ini pelanggaran sedang/berat yang ditangani langsung oleh Anda)
                                    else: 
                                    ?>
                                        <!-- Tombol cetak diaktifkan (berwarna biru) agar Guru BK bisa mencetak RPL / Berita Acara -->
                                        <a href="cetak_konseling.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-primary btn-sm btn-icon" title="Cetak Berita Acara">
                                            <i class="fas fa-print">
                                            </i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Tombol hapus tetap dibiarkan aktif agar Guru BK bisa membatalkan/menghapus riwayat apa pun (termasuk yang poin kecil otomatis) -->
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Hapus data tindak lanjut pelanggaran ini secara permanen?')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada riwayat tindak lanjut pelanggaran pada periode ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DETAIL SESI KONSELING                                         -->
    <!-- =================================================================== -->
    <div id="modalDetailKonseling" class="modal">
        <div class="modal-content" style="max-width: 650px; border-radius: 16px;">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Detail Sesi Konseling</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Informasi dan catatan lengkap layanan bimbingan konseling.</p>
                </div>
                <div class="close" onclick="closeModal('modalDetailKonseling')">&#x2715;</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: #f8fafc; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; border: 1px solid #e2e8f0;">
                <div>
                    <small style="color: #64748b; display: block; font-weight: 600; font-size: 0.78rem; text-transform: uppercase;">Nama Siswa</small>
                    <span id="detail_nama_siswa" style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">-</span>
                </div>
                <div>
                    <small style="color: #64748b; display: block; font-weight: 600; font-size: 0.78rem; text-transform: uppercase;">NISN (Kelas)</small>
                    <span id="detail_nisn_kelas" style="color: #334155; font-size: 0.9rem; font-weight: 600;">-</span>
                </div>
                <div>
                    <small style="color: #64748b; display: block; font-weight: 600; font-size: 0.78rem; text-transform: uppercase;">Jenis Layanan</small>
                    <span id="detail_jenis_layanan" class="badge badge-info" style="margin-top: 4px; display: inline-block;">-</span>
                </div>
                <div>
                    <small style="color: #64748b; display: block; font-weight: 600; font-size: 0.78rem; text-transform: uppercase;">Tanggal Sesi</small>
                    <span id="detail_tanggal" style="color: #334155; font-size: 0.9rem; font-weight: 600;">-</span>
                </div>
            </div>

            <div style="margin-bottom: 1.25rem; display: none;" id="box_topik">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 0.8rem; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.03em;">Topik Permasalahan</label>
                <div id="detail_topik" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 0.75rem 1rem; border-radius: 8px; color: #1e293b; font-size: 0.9rem; font-weight: 600;">-</div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 0.8rem; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.03em;">Deskripsi Pelanggaran / Masalah</label>
                <div id="detail_masalah" style="background: #fff1f2; border: 1px solid #fecdd3; padding: 0.85rem 1rem; border-radius: 8px; color: #9f1239; font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">-</div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 0.8rem; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.03em;">Hasil Bimbingan &amp; Solusi Konseling</label>
                <div id="detail_solusi" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.85rem 1rem; border-radius: 8px; color: #166534; font-size: 0.9rem; line-height: 1.6; white-space: pre-line;">-</div>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; border-top: 1px solid #f1f5f9; padding-top: 1rem; margin-top: 1rem;">
                <a id="btn_cetak_detail" href="#" target="_blank" class="btn-submit">
                    <i class="fas fa-print"></i> Cetak Berita Acara
                </a>
                <button type="button" class="btn-cancel" onclick="closeModal('modalDetailKonseling')"><i class="fas fa-times"></i> Tutup</button>
            </div>
        </div>
    </div>

    <script>
        function showDetailKonseling(nama, nisn, kelas, jenis, masalah, solusi, tanggal, topik, id) {
            document.getElementById('detail_nama_siswa').innerText = nama;
            document.getElementById('detail_nisn_kelas').innerText = nisn + ' (' + kelas + ')';
            
            const badgeEl = document.getElementById('detail_jenis_layanan');
            badgeEl.innerText = jenis;
            if (jenis === 'Konseling Individu') {
                badgeEl.className = 'badge badge-info';
                badgeEl.style.background = '#e0f2fe';
                badgeEl.style.color = '#0369a1';
            } else {
                badgeEl.className = 'badge badge-warning';
                badgeEl.style.background = '#fef3c7';
                badgeEl.style.color = '#d97706';
            }

            document.getElementById('detail_tanggal').innerText = tanggal;
            document.getElementById('detail_masalah').innerText = masalah || '-';
            document.getElementById('detail_solusi').innerText = solusi || '-';
            
            if (topik && topik.trim() !== '') {
                document.getElementById('box_topik').style.display = 'block';
                document.getElementById('detail_topik').innerText = topik;
            } else {
                document.getElementById('box_topik').style.display = 'none';
            }
            
            document.getElementById('btn_cetak_detail').href = 'cetak_konseling.php?id=' + id;
            document.getElementById('modalDetailKonseling').style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('modalDetailKonseling');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };

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
