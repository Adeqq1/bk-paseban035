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

// --- LOGIKA OTOMATIS: AUTO-CLOSE LAPORAN LAMA ---
$current_semester = date('m') >= 7 ? '1' : '2';
$current_tahun = date('Y');
$active_start_date = ($current_semester == '1') ? "$current_tahun-07-01" : "$current_tahun-01-01";

$query_unresolved_old = mysqli_query($koneksi, "
    SELECT cp.id, cp.siswa_id, jp.nama_pelanggaran 
    FROM catatan_pelanggaran cp
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN konseling kon ON cp.id = kon.catatan_pelanggaran_id
    WHERE cp.tanggal < '$active_start_date' AND kon.id IS NULL
");

if (mysqli_num_rows($query_unresolved_old) > 0) {
    while ($row_old = mysqli_fetch_assoc($query_unresolved_old)) {
        $cp_id = $row_old['id'];
        $s_id = $row_old['siswa_id'];
        $masalah = mysqli_real_escape_string($koneksi, "Tindak lanjut pelanggaran: " . $row_old['nama_pelanggaran']);
        $solusi = "Sistem: Ditutup otomatis karena pergantian semester berjalan.";
        
        mysqli_query($koneksi, "
            INSERT INTO konseling (siswa_id, guru_id, tanggal, masalah, solusi, status, catatan_pelanggaran_id, jenis_konseling)
            VALUES ('$s_id', '$guru_id', CURRENT_DATE(), '$masalah', '$solusi', 'Selesai', '$cp_id', 'Tindak Lanjut')
        ");
    }
}
// ------------------------------------------------

// Ambil data siswa untuk dropdown
$query_semua_siswa = mysqli_query($koneksi, "SELECT id, nama_lengkap, nisn FROM siswa ORDER BY nama_lengkap ASC");

// Filter Semester, Tahun, Siswa, dan Status yang dipilih
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'belum';

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
}

$where_siswa = $filter_siswa_id ? "AND cp.siswa_id = '$filter_siswa_id'" : "";
$where_status = "";

if ($filter_status == 'belum') {
    $where_status = "AND kon.id IS NULL";
} elseif ($filter_status == 'selesai') {
    $where_status = "AND kon.id IS NOT NULL";
}

$where_date = "AND cp.tanggal BETWEEN '$start_date' AND '$end_date'";

$query_laporan = mysqli_query($koneksi, "
    SELECT MAX(cp.id) as id, cp.siswa_id,
           s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas, 
           jp.nama_pelanggaran, jp.kategori,
           SUM(jp.poin) as total_poin,
           COUNT(cp.id) as jumlah_laporan,
           GROUP_CONCAT(DISTINCT g.nama_lengkap SEPARATOR ', ') as nama_pelapor,
           GROUP_CONCAT(cp.keterangan SEPARATOR ' | ') as semua_keterangan,
           MAX(cp.tanggal) as max_tanggal,
           MAX(kon.id) as konseling_id,
           GROUP_CONCAT(DISTINCT cp.pelapor_asli SEPARATOR ', ') as pelapor_asli_concat
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    JOIN guru g ON cp.guru_id = g.id
    LEFT JOIN konseling kon ON (cp.id = kon.catatan_pelanggaran_id OR EXISTS (SELECT 1 FROM catatan_pelanggaran cp_k JOIN konseling kon_k ON cp_k.id = kon_k.catatan_pelanggaran_id WHERE cp_k.siswa_id = cp.siswa_id AND cp_k.pelanggaran_id = cp.pelanggaran_id AND cp_k.id >= cp.id))
    WHERE 1=1 $where_date $where_siswa $where_status
    GROUP BY cp.siswa_id, cp.pelanggaran_id, s.nama_lengkap, s.nisn, k.nama_kelas, jp.nama_pelanggaran, jp.kategori, (CASE WHEN kon.id IS NOT NULL THEN cp.id ELSE 0 END)
    ORDER BY MAX(cp.tanggal) DESC, MAX(cp.id) DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Masuk | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        table {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            margin-top: 1rem !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            border: 1px solid #e2e8f0 !important;
        }
        th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 14px 16px !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        td {
            padding: 14px 16px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #334155 !important;
        }
        tr:last-child td {
            border-bottom: none !important;
        }
        tr:hover td {
            background-color: #f8fafc !important;
        }
        .badge {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            letter-spacing: 0.025em !important;
            padding: 6px 12px !important;
            border-radius: 6px !important;
            font-size: 0.78rem !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .btn-sm {
            padding: 6px 12px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
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
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-inbox" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Laporan Masuk <span style="color: #60a5fa;">Pelanggaran</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Tinjau dan tindaklanjuti laporan pelanggaran dari wali kelas.</p>
                </div>
            </div>
        </div>

        <!-- Blok Notifikasi Berhasil Tindak Lanjut -->
        <?php if (isset($_GET['pesan']) && ($_GET['pesan'] == 'success_tindak' || $_GET['pesan'] == 'success')): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span>Tindak lanjut laporan pelanggaran berhasil disimpan dan dicatat dalam riwayat konseling!</span>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Nama Siswa</label>
                    <select name="siswa_id" class="form-control" style="min-width: 200px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <option value="">Semua Siswa</option>
                        <?php while($s = mysqli_fetch_assoc($query_semua_siswa)): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filter_siswa_id == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Status</label>
                    <select name="status" class="form-control" style="min-width: 150px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <option value="belum" <?php echo $filter_status == 'belum' ? 'selected' : ''; ?>>Menunggu Tindak Lanjut</option>
                        <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Sudah Selesai</option>
                        <option value="semua" <?php echo $filter_status == 'semua' ? 'selected' : ''; ?>>Semua Status</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Semester</label>
                    <select name="semester" class="form-control" style="min-width: 140px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Tahun</label>
                    <select name="tahun" class="form-control" style="min-width: 100px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <?php for($i=date('Y'); $i>=2023; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-search" style="margin-right: 6px;"></i> Cari
                    </button>
                    <?php if(isset($_GET['siswa_id']) || isset($_GET['status']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                        <a href="pelanggaran_masuk.php" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Reset Filter" onmouseover="this.style.background='#e2e8f0'; this.style.color='#334155'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Data -->
        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 1.5rem;">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; background: #f8fafc; padding: 1.25rem 1.5rem; border-radius: 10px; border-left: 5px solid var(--primary); margin-bottom: 1.5rem;">
                <div>
                    <h2 style="margin: 0 0 4px 0; font-size: 1.2rem; color: #1e293b; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-list-alt" style="color: var(--primary);"></i> Daftar Laporan Masuk
                    </h2>
                    <p style="margin: 0; font-size: 0.9rem; color: #64748b;">Laporan pelanggaran dari Wali Kelas yang memerlukan tindak lanjut bimbingan.</p>
                </div>
                <span class="badge" style="font-size: 0.85rem; background: #e0f2fe; color: #0284c7; padding: 6px 14px; border-radius: 20px; font-weight: 700;">
                    <i class="fas fa-file-alt" style="margin-right: 4px;"></i> <?php echo mysqli_num_rows($query_laporan); ?> Laporan Ditemukan
                </span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 110px;">Tanggal</th>
                            <th style="width: 160px;">Nama Siswa</th>
                            <th style="text-align: center; width: 80px;">Kelas</th>
                            <th style="width: 220px;">Pelanggaran</th>
                            <th style="width: 200px;">Keterangan Kejadian</th>
                            <th style="width: 140px;">Wali Kelas</th>
                            <th style="width: 130px;">Pelapor Asli</th>
                            <th style="text-align: center; width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($query_laporan) > 0) {
                            while($row = mysqli_fetch_assoc($query_laporan)):
                        ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;"><small style="color: #475569; font-weight: 400;"><?php echo date('d/m/Y', strtotime($row['max_tanggal'])); ?></small></td>
                            <td style="vertical-align: middle; white-space: nowrap;">
                                <div style="color: #334155; font-size: 0.875rem; font-weight: 500; text-transform: capitalize;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 3px;">NISN: <?php echo $row['nisn']; ?></div>
                            </td>
                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                                <span class="badge badge-info" style="font-weight: 500;"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span>
                            </td>
                            <td style="vertical-align: middle; min-width: 250px;">
                                <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-start;">
                                    <span style="font-size: 0.9rem; color: #334155; font-weight: 400; line-height: 1.4;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></span>
                                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <span class="badge badge-danger" style="font-size: 0.72rem; padding: 4px 8px; font-weight: 700; border-radius: 4px; margin-left: 0;">+<?php echo $row['total_poin']; ?> Poin</span>
                                        <?php if ($row['jumlah_laporan'] > 1): ?>
                                            <span class="badge" style="font-size: 0.72rem; padding: 4px 8px; font-weight: 700; background: #fef3c7; color: #b45309; border-radius: 4px; border: 1px solid #fde68a;"><?php echo $row['jumlah_laporan']; ?>x Laporan</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="vertical-align: middle; min-width: 200px;"><small style="color: #475569; line-height: 1.4; display: block;"><?php echo $row['semua_keterangan'] ? htmlspecialchars($row['semua_keterangan']) : '-'; ?></small></td>
                            <td style="vertical-align: middle; white-space: nowrap; text-transform: capitalize;"><small style="color: #475569; font-weight: 500;"><?php echo htmlspecialchars($row['nama_pelapor']); ?></small></td>
                            <td style="vertical-align: middle; white-space: nowrap; text-transform: capitalize;">
                                <small style="color: #64748b; font-weight: 500;"><?php echo !empty($row['pelapor_asli_concat']) ? htmlspecialchars($row['pelapor_asli_concat']) : '-'; ?></small>
                            </td>
                            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                                <?php if ($row['konseling_id']): ?>
                                    <span class="badge badge-success" style="font-size: 0.75rem; padding: 4px 8px; font-weight: 600;">
                                        <i class="fas fa-check-circle"></i> Selesai
                                    </span>
                                <?php else: ?>
                                    <a href="tindak_lanjut.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" style="font-size: 0.72rem; padding: 6px 12px; font-weight: 600; border-radius: 6px;">
                                        <i class="fas fa-hands-helping"></i> Tindak Lanjut
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='9' style='text-align:center; color: #94a3b8; padding: 2rem;'>Semua laporan sudah ditindaklanjuti.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Script Toggle Menu Mobile & Tabel Responsif -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
                    const icon = toggleBtn.querySelector("i");
                    if (icon) { icon.classList.remove("fa-times"); icon.classList.add("fa-bars"); }
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

        // Injeksi data-label & Kelas Responsif untuk Tabel
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            const headersLower = headers.map(h => h.toLowerCase());
            if (headersLower.includes('pelanggaran') || headersLower.includes('nisn')) {
                table.classList.add('table-pelanggaran-mobile');
            }
            table.querySelectorAll('tbody tr').forEach(function(row) {
                row.querySelectorAll('td').forEach(function(td, index) {
                    if (headers[index]) td.setAttribute('data-label', headers[index]);
                });
            });
        });
    });
    </script>
</body>
</html>
