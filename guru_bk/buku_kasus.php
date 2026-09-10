<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap, nip FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// Inisialisasi variabel filter
$filter_kelas = isset($_GET['kelas_id']) ? $_GET['kelas_id'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $semester_label = "Ganjil";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $semester_label = "Genap";
}

// Build WHERE clause
$where_kelas = "";
if (!empty($filter_kelas)) {
    $kelas_clean = mysqli_real_escape_string($koneksi, $filter_kelas);
    $where_kelas = "AND s.kelas_id = '$kelas_clean'";
}

// Query untuk daftar kasus siswa
$query_kasus = mysqli_query($koneksi, "
    SELECT cp.id, cp.tanggal, cp.keterangan,
           s.nama_lengkap as nama_siswa, k.nama_kelas,
           jp.nama_pelanggaran,
           kon.solusi as tindak_lanjut,
           kon.masalah as catatan_konseling
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN konseling kon ON cp.id = kon.catatan_pelanggaran_id
    WHERE cp.tanggal BETWEEN '$start_date' AND '$end_date'
    $where_kelas
    ORDER BY cp.tanggal DESC, cp.id DESC
");

// Ambil daftar kelas untuk filter
$query_kelas_list = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama_kelas ASC");

// Hitung tahun pelajaran
$tahun_pelajaran = ($semester == '1') ? "$tahun/" . ($tahun + 1) : ($tahun - 1) . "/$tahun";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Catatan Kasus Siswa | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .filter-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .form-group-filter {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 200px;
        }
        .form-group-filter label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
        }
        .filter-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            height: 42px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 20%, transparent);
        }
        .filter-btn:hover {
            background: linear-gradient(135deg, var(--primary-hover) 0%, var(--primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 30%, transparent);
        }

        /* Custom Premium Table Styling */
        table, th, td, .badge, span, div {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
        }
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
            font-family: 'Inter', sans-serif !important;
            letter-spacing: 0.025em !important;
            padding: 6px 12px !important;
            border-radius: 6px !important;
            font-size: 0.78rem !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .btn-cetak-laporan {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 0.7rem 1.5rem !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            text-decoration: none !important;
        }
        .btn-cetak-laporan:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.38) !important;
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
            color: #ffffff !important;
        }
        .catatan-cell {
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
    </style>
</head>
<body class="rekap-poin-page">
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
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
            <li><a href="kunjungan_rumah.php"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
            <li><a href="buku_kasus.php" class="active"><i class="fas fa-book"></i> Buku Catatan Kasus</a></li>
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

    <div class="main-content">
        <!-- Header -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-book" style="font-size: 1.8rem; color: #f59e0b;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Buku <span style="color: #f59e0b;">Catatan Kasus Siswa</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Laporan lengkap daftar kasus/pelanggaran siswa untuk pencetakan arsip.</p>
                </div>
            </div>
            <a href="cetak_buku_kasus.php?kelas_id=<?php echo urlencode($filter_kelas); ?>&semester=<?php echo $semester; ?>&tahun=<?php echo $tahun; ?>" target="_blank" class="btn-cetak-laporan">
                <i class="fas fa-print"></i> Cetak Laporan
            </a>
        </div>

        <div class="filter-container">
            <form action="" method="GET" class="filter-form">
                <div class="form-group-filter">
                    <label>Filter Kelas</label>
                    <select name="kelas_id" class="form-control">
                        <option value="">Semua Kelas</option>
                        <?php while($k = mysqli_fetch_assoc($query_kelas_list)): ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo $filter_kelas == $k['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($k['nama_kelas']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group-filter" style="flex: 0.7; min-width: 140px;">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                    </select>
                </div>
                <div class="form-group-filter" style="flex: 0.5; min-width: 120px;">
                    <label>Tahun</label>
                    <select name="tahun" class="form-control">
                        <?php 
                        $tahun_sekarang = date('Y');
                        for ($i = $tahun_sekarang; $i >= $tahun_sekarang - 3; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group-filter" style="flex: 0; min-width: max-content; display: flex; flex-direction: row; gap: 10px; justify-content: flex-start; margin-bottom: 0;">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(isset($_GET['kelas_id']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                        <a href="buku_kasus.php" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Reset Filter" onmouseover="this.style.background='#e2e8f0'; this.style.color='#334155'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-card">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; background: #f8fafc; padding: 1rem 1.25rem; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 1.5rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clipboard-list" style="color: #f59e0b;"></i> Daftar Kasus Siswa
                    </h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Tahun Pelajaran <?php echo $tahun_pelajaran; ?> &mdash; Semester <?php echo $semester_label; ?></p>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 60px;">No</th>
                            <th style="width: 140px;">Hari, Tanggal</th>
                            <th style="text-align: center; width: 70px;">Kelas</th>
                            <th style="width: 150px;">Nama Siswa</th>
                            <th style="width: 180px;">Bentuk Pelanggaran</th>
                            <th style="width: 180px;">Tindak Lanjut</th>
                            <th>Catatan</th>
                            <th style="text-align: center; width: 130px;">Panggilan Ortu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $has_data = false;
                        while($row = mysqli_fetch_assoc($query_kasus)): 
                            $has_data = true;
                            // Format hari dan tanggal
                            $hari_en = date('l', strtotime($row['tanggal']));
                            $hari_map = [
                                'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                                'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
                            ];
                            $hari = $hari_map[$hari_en] ?? $hari_en;
                            
                            $bulan_map = [
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ];
                            $tgl = date('d', strtotime($row['tanggal']));
                            $bln = $bulan_map[(int)date('m', strtotime($row['tanggal']))];
                            $thn = date('Y', strtotime($row['tanggal']));
                            $tanggal_formatted = "$hari, $tgl $bln $thn";
                            
                            // Tentukan tindak lanjut
                            $tindak_lanjut = $row['tindak_lanjut'] ?? '';
                            if (empty($tindak_lanjut)) {
                                $tindak_lanjut = '<span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 0.75rem; padding: 3px 8px;"><i class="fas fa-clock"></i> Belum ditindak lanjuti</span>';
                            }
                            
                            // Catatan
                            $catatan = $row['keterangan'] ?? '';
                            $total_kasus = (int)($row['total_kasus_siswa'] ?? 1);
                        ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle; font-weight: 600; color: #64748b;">
                                <?php echo $no++; ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 0.875rem;"><?php echo $hari; ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;"><?php echo "$tgl $bln $thn"; ?></div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <span class="badge badge-info" style="font-weight: 600;"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span>
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="color: #1e293b; font-size: 0.875rem; font-weight: 600; text-transform: capitalize;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <?php if ($total_kasus >= 3): ?>
                                    <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.68rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-exclamation-triangle"></i> Berulang (<?php echo $total_kasus; ?>x Kasus)
                                    </span>
                                <?php elseif ($total_kasus == 2): ?>
                                    <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 0.68rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-redo"></i> 2x Kasus
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="color: #dc2626; font-size: 0.85rem; font-weight: 500;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></div>
                            </td>
                            <td style="vertical-align: middle;" class="catatan-cell">
                                <?php echo $tindak_lanjut; ?>
                            </td>
                            <td style="vertical-align: middle;" class="catatan-cell">
                                <div style="font-size: 0.85rem; color: #475569;"><?php echo htmlspecialchars($catatan); ?></div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php if ($total_kasus >= 3): ?>
                                    <a href="buat_panggilan.php?id=<?php echo $row['siswa_id']; ?>" class="btn" style="background: #dc2626; color: white; font-size: 0.75rem; padding: 5px 10px; border-radius: 6px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" title="Siswa telah melakukan <?php echo $total_kasus; ?>x pelanggaran. Terbitkan Surat Panggilan.">
                                        <i class="fas fa-envelope-open-text"></i> Panggil Ortu
                                    </a>
                                <?php else: ?>
                                    <a href="buat_panggilan.php?id=<?php echo $row['siswa_id']; ?>" class="btn" style="background: #f59e0b; color: white; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-envelope"></i> Panggil
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        endwhile; 
                        if(!$has_data): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem !important;">
                                    <div style="color: #94a3b8;">
                                        <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                                        <span style="font-size: 0.95rem;">Belum ada catatan kasus siswa pada periode ini.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
