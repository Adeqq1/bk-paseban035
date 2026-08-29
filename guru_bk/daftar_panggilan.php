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

// Update Status Panggilan
if (isset($_GET['update_status'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $status = mysqli_real_escape_string($koneksi, $_GET['update_status']);
    mysqli_query($koneksi, "UPDATE panggilan_orang_tua SET status='$status' WHERE id='$id' AND guru_id='$guru_id'");
    header("Location: daftar_panggilan.php?pesan=success_update&tab=selesai");
    exit();
}

// Proses Hapus Panggilan
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $tab = isset($_GET['tab']) ? mysqli_real_escape_string($koneksi, $_GET['tab']) : 'aktif';
    // Validasi kepemilikan data sebelum dihapus
    $check = mysqli_query($koneksi, "SELECT id FROM panggilan_orang_tua WHERE id = '$id' AND guru_id = '$guru_id'");
    if (mysqli_num_rows($check) > 0) {
        if (mysqli_query($koneksi, "DELETE FROM panggilan_orang_tua WHERE id = '$id'")) {
            header("Location: daftar_panggilan.php?pesan=success_hapus&tab=" . $tab);
            exit();
        }
    }
}

// Ambil daftar siswa untuk dropdown filter Nama Siswa (hanya nama_lengkap)
$query_semua_siswa = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM siswa ORDER BY nama_lengkap ASC");

$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($koneksi, $_GET['status']) : '';

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $where_date = "AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'";
} elseif ($semester == '2') {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $where_date = "AND DATE(p.created_at) BETWEEN '$start_date' AND '$end_date'";
} else {
    $where_date = "AND YEAR(p.created_at) = '$tahun'";
}

$current_tab = $_GET['tab'] ?? 'aktif';
if (!empty($filter_status)) {
    $status_filter = "AND p.status = '$filter_status'";
} else {
    if ($current_tab == 'aktif') {
        $status_filter = "AND p.status = 'Dikirim'";
    } else {
        $status_filter = "AND p.status IN ('Hadir', 'Tidak Hadir')";
    }
}

$status_filter .= " $where_date";
if (!empty($filter_siswa_id)) {
    $status_filter .= " AND p.siswa_id = '$filter_siswa_id'";
}

// Ambil daftar panggilan
$query_panggilan = mysqli_query($koneksi, "
    SELECT p.*, s.nama_lengkap as nama_siswa, s.nisn, s.status as status_siswa, k.nama_kelas
    FROM panggilan_orang_tua p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE p.guru_id = '$guru_id' $status_filter
    ORDER BY p.tanggal_panggilan DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Panggilan Ortu | SI BK7</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabs-container,
        .filter-tabs {
            display: inline-flex;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
            gap: 6px;
            margin-bottom: 20px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }
        .tab-item,
        .tab-btn,
        a.tab-item,
        a.tab-btn {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b !important;
            text-decoration: none !important;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .tab-item:hover,
        .tab-btn:hover,
        a.tab-item:hover,
        a.tab-btn:hover {
            color: #0f172a !important;
            background: rgba(255, 255, 255, 0.7);
            text-decoration: none !important;
        }
        .tab-item.active,
        .tab-btn.active,
        a.tab-item.active,
        a.tab-btn.active {
            background: #ffffff !important;
            color: #2563eb !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04) !important;
            text-decoration: none !important;
        }
        th.text-center, td.text-center {
            text-align: center;
            vertical-align: middle;
        }
        td {
            vertical-align: middle;
        }
        .action-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-action-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-status-custom {
            padding: 6px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-status-ditolak {
            background-color: #fef2f2;
            color: #ef4444;
            border-color: #fee2e2;
        }
        .btn-status-ditolak:hover {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .btn-status-proses {
            background-color: #eff6ff;
            color: #3b82f6;
            border-color: #dbeafe;
        }
        .btn-status-proses:hover {
            background-color: #dbeafe;
            color: #2563eb;
        }
        .btn-status-selesai {
            background-color: #f0fdf4;
            color: #22c55e;
            border-color: #dcfce7;
        }
        .btn-status-selesai:hover {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .btn-cetak-surat {
            background-color: #ffffff;
            color: #3b82f6;
            border: 1px solid #cbd5e1;
            padding: 5px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-cetak-surat:hover {
            background-color: #eff6ff;
            color: #2563eb;
            border-color: #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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
            <li><a href="daftar_panggilan.php" class="active"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
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
                    <i class="fas fa-envelope-open-text" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Panggilan <span style="color: #60a5fa;">Orang Tua</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Kelola surat pemanggilan orang tua/wali murid berdasarkan poin pelanggaran.</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success_buat'): ?>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #22c55e; color: #166534; padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <i class="fas fa-check-circle" style="font-size: 1.1rem; color: #22c55e;"></i>
                Surat panggilan berhasil diterbitkan!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success_update'): ?>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #22c55e; color: #166534; padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <i class="fas fa-check-circle" style="font-size: 1.1rem; color: #22c55e;"></i>
                Status kehadiran orang tua berhasil diperbarui!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success_hapus'): ?>
            <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; color: #991b1b; padding: 1rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; font-weight: 500; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <i class="fas fa-trash-alt" style="font-size: 1.1rem; color: #ef4444;"></i>
                Data panggilan orang tua berhasil dihapus!
            </div>
        <?php endif; ?>

        <!-- Filter Form (Selaras dengan gambar & Laporan Masuk) -->
        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
                
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
                    <select name="status" class="form-control" style="min-width: 170px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <option value="">-- Semua Status --</option>
                        <option value="Dikirim" <?php echo $filter_status == 'Dikirim' ? 'selected' : ''; ?>>Belum Hadir (Dikirim)</option>
                        <option value="Hadir" <?php echo $filter_status == 'Hadir' ? 'selected' : ''; ?>>Hadir</option>
                        <option value="Tidak Hadir" <?php echo $filter_status == 'Tidak Hadir' ? 'selected' : ''; ?>>Tidak Hadir</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Semester</label>
                    <select name="semester" class="form-control" style="min-width: 140px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <option value="">-- Semua --</option>
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem;">Tahun</label>
                    <select name="tahun" class="form-control" style="min-width: 100px; padding: 0.6rem 2.5rem 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <?php 
                        $tahun_sekarang = date('Y');
                        for ($i = $tahun_sekarang; $i >= $tahun_sekarang - 3; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-search" style="margin-right: 6px;"></i> Cari
                    </button>
                    <?php if(isset($_GET['siswa_id']) || isset($_GET['status']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                        <a href="daftar_panggilan.php?tab=<?php echo htmlspecialchars($current_tab); ?>" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Reset Filter" onmouseover="this.style.background='#e2e8f0'; this.style.color='#334155'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-card">
            <div class="tabs-container">
                <a href="?tab=aktif" class="tab-item <?php echo $current_tab == 'aktif' ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane"></i> Panggilan Aktif / Belum Hadir
                </a>
                <a href="?tab=selesai" class="tab-item <?php echo $current_tab == 'selesai' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Riwayat Panggilan (Selesai)
                </a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">Tanggal Panggilan</th>
                            <th class="text-center">Jam Panggilan</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Tempat</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="120">Aksi</th>
                            <th class="text-center">Aksi Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($query_panggilan) > 0) {
                            while($row = mysqli_fetch_assoc($query_panggilan)): 
                        ?>
                        <tr>
                            <td class="text-center" style="white-space: nowrap;"><?php echo !empty($row['tanggal_panggilan']) ? tgl_indo($row['tanggal_panggilan']) : 'Ditulis Manual'; ?></td>
                            <td class="text-center" style="white-space: nowrap;"><?php echo !empty($row['jam_panggilan']) ? date('H:i', strtotime($row['jam_panggilan'])) . ' WIB' : 'Ditulis Manual'; ?></td>
                            <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['nisn']); ?></td>
                            <td style="white-space: nowrap; text-transform: capitalize;">
                                <?php echo htmlspecialchars($row['nama_siswa']); ?>
                                <?php if ($row['status_siswa'] == 'alumni'): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; font-size:0.65rem; padding: 2px 6px; border-radius: 4px; font-weight:600; margin-left: 5px;"><i class="fas fa-graduation-cap"></i> Alumni</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></td>
                            <td style="white-space: nowrap; min-width: 150px;"><?php echo htmlspecialchars($row['tempat']); ?></td>
                            <td class="text-center" style="white-space: nowrap;">
                                <?php 
                                    $badge = 'badge-info';
                                    if($row['status'] == 'Hadir') $badge = 'badge-success';
                                    if($row['status'] == 'Tidak Hadir') $badge = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badge; ?>"><?php echo $row['status']; ?></span>
                            </td>
                            <td class="text-center" style="white-space: nowrap;">
                                <a href="cetak_panggilan.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="Cetak Surat">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="?hapus=<?php echo $row['id']; ?>&tab=<?php echo $current_tab; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data panggilan ini?')" title="Hapus Panggilan" style="margin-left: 4px; padding: 0.4rem 0.6rem;">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <?php if($row['status'] == 'Dikirim'): ?>
                                    <div class="action-container">
                                        <a href="?id=<?php echo $row['id']; ?>&update_status=Hadir" class="btn btn-success btn-sm" title="Tandai Hadir">
                                            <i class="fas fa-user-check"></i> Hadir
                                        </a>
                                        <a href="?id=<?php echo $row['id']; ?>&update_status=Tidak Hadir" class="btn btn-danger btn-sm" title="Tandai Tidak Hadir">
                                            <i class="fas fa-user-times"></i> Tidak Hadir
                                        </a>
                                    </div>
                                <?php elseif($row['status'] == 'Tidak Hadir'): ?>
                                    <div class="btn-action-column" style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                        <span class="badge badge-danger" style="font-size: 0.75rem !important; padding: 5px 10px; font-weight: 600;">
                                            <i class="fas fa-user-slash"></i> Ortu Tidak Hadir
                                        </span>
                                        <a href="buat_panggilan.php?siswa_id=<?php echo $row['nisn']; ?>" class="btn btn-warning btn-sm" style="font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;">
                                            <i class="fas fa-redo"></i> Panggil Ulang
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="badge badge-success" style="font-size: 0.75rem; padding: 5px 10px;"><i class="fas fa-check-double"></i> Selesai (Ortu Hadir)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<tr><td colspan='10' style='text-align:center;'>Belum ada data panggilan orang tua.</td></tr>";
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
        // 1. Fungsionalitas Toggle Sidebar (Desktop & Mobile)
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
