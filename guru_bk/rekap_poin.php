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


// Inisialisasi variabel filter
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_siswa_id = $_GET['siswa_id'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
} else {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
}

// Build Query
$where = ["s.status = 'aktif'"];
$having = [];

if (!empty($filter_siswa_id)) {
    $siswa_clean = mysqli_real_escape_string($koneksi, $filter_siswa_id);
    $where[] = "s.id = '$siswa_clean'";
}

$where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

if ($filter_kategori == 'sangat_kritis') {
    $having[] = "total_poin >= 100";
} elseif ($filter_kategori == 'kritis') {
    $having[] = "total_poin >= 50 AND total_poin < 100";
} elseif ($filter_kategori == 'waspada') {
    $having[] = "total_poin >= 25 AND total_poin < 50";
} elseif ($filter_kategori == 'ringan') {
    $having[] = "total_poin > 0 AND total_poin < 25";
} else {
    // Default: hanya tampilkan yang punya poin
    $having[] = "total_poin > 0";
}

$having_clause = "HAVING " . implode(" AND ", $having);

// Ambil ranking poin siswa
$query_rekap = mysqli_query($koneksi, "
    SELECT s.id, s.nisn, s.nama_lengkap, k.nama_kelas, 
           COALESCE(SUM(jp.poin), 0) as total_poin,
           (SELECT status FROM panggilan_orang_tua WHERE siswa_id = s.id ORDER BY created_at DESC LIMIT 1) as status_panggilan
    FROM siswa s
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    $where_clause
    GROUP BY s.id, s.nisn, s.nama_lengkap, k.nama_kelas
    $having_clause
    ORDER BY total_poin DESC
");

// Ambil daftar kelas untuk fallback/kebutuhan lain (opsional)
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY tingkat, nama_kelas");

// Ambil seluruh daftar siswa untuk dropdown filter
$query_siswa_list = mysqli_query($koneksi, "SELECT s.id, s.nama_lengkap, s.kelas_id, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif' ORDER BY k.nama_kelas, s.nama_lengkap");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Poin Siswa | SI BK7</title>
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
        .reset-btn {
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
        }
        .reset-btn:hover { background: #e2e8f0; }

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
<body class="rekap-poin-page">
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
            <li><a href="kunjungan_rumah.php"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
            <li><a href="rekap_poin.php" class="active"><i class="fas fa-chart-line"></i> Rekap Poin</a></li>
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
                    <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Rekapitulasi <span style="color: #ef4444;">Poin Pelanggaran</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Lihat dan analisis distribusi poin pelanggaran siswa secara komprehensif.</p>
                </div>
            </div>
        </div>

        <div class="filter-container">
            <form action="" method="GET" class="filter-form">
                <div class="form-group-filter">
                    <label>Pilih Siswa</label>
                    <select name="siswa_id" class="form-control">
                        <option value="">-- Semua Siswa --</option>
                        <?php while($sl = mysqli_fetch_assoc($query_siswa_list)): ?>
                            <option value="<?php echo $sl['id']; ?>" <?php if($filter_siswa_id == $sl['id']) echo 'selected'; ?>>
                                [<?php echo $sl['nama_kelas'] ?? '-'; ?>] <?php echo $sl['nama_lengkap']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group-filter">
                    <label>Kategori Tindakan</label>
                    <select name="kategori" class="form-control">
                        <option value="">-- Semua Kategori --</option>
                        <option value="sangat_kritis" <?php if($filter_kategori == 'sangat_kritis') echo 'selected'; ?>>Sangat Kritis (>= 100 Poin)</option>
                        <option value="kritis" <?php if($filter_kategori == 'kritis') echo 'selected'; ?>>Peringatan Keras (50 - 99 Poin)</option>
                    </select>
                </div>
                <div class="form-group-filter" style="flex: 0.5; min-width: 140px;">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>
                <div class="form-group-filter" style="flex: 0.5; min-width: 100px;">
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
                    <?php if(isset($_GET['siswa_id']) || isset($_GET['kategori']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                        <a href="rekap_poin.php" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; border-radius: 8px; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease;" title="Reset Filter" onmouseover="this.style.background='#e2e8f0'; this.style.color='#334155'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b'">
                            <i class="fas fa-undo"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-card">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; background: #f8fafc; padding: 1rem 1.25rem; border-radius: 8px; border-left: 4px solid var(--primary); margin-bottom: 1.5rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.1rem; color: #1e293b; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-trophy" style="color: #fbbf24;"></i> Peringkat Poin Terbanyak
                    </h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Daftar siswa dengan total akumulasi poin pelanggaran tertinggi.</p>
                </div>
                <div class="badge badge-info" style="font-size: 0.8rem; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 20px; font-weight: 700;">
                    <i class="fas fa-sync-alt fa-spin" style="margin-right: 4px; animation-duration: 3s;"></i> Update Real-time
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 80px;">Rank</th>
                            <th>Nama Siswa</th>
                            <th style="text-align: center; width: 100px;">Kelas</th>
                            <th style="text-align: center; width: 120px;">Total Poin</th>
                            <th style="text-align: center; width: 220px;">Kategori Tindakan</th>
                            <th style="text-align: center; width: 200px;">Aksi Panggilan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        $has_data = false;
                        while($row = mysqli_fetch_assoc($query_rekap)): 
                            $has_data = true;
                        ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php 
                                    if ($rank == 1) {
                                        echo '<span class="badge" style="background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; font-weight: 700; font-size: 0.85rem; padding: 4px 10px; border-radius: 12px;">1</span>';
                                    } elseif ($rank == 2) {
                                        echo '<span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; font-size: 0.85rem; padding: 4px 10px; border-radius: 12px;">2</span>';
                                    } elseif ($rank == 3) {
                                        echo '<span class="badge" style="background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 700; font-size: 0.85rem; padding: 4px 10px; border-radius: 12px;">3</span>';
                                    } else {
                                        echo '<span class="badge" style="background: #f3f4f6; color: #6b7280; font-weight: 600; font-size: 0.8rem; padding: 4px 8px; border-radius: 12px;">' . $rank . '</span>';
                                    }
                                    $rank++;
                                ?>
                            </td>
                            <td style="vertical-align: middle;">
                                <div style="color: #1e293b; font-size: 0.875rem; font-weight: 500; text-transform: capitalize;"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 3px;">NISN: <?php echo $row['nisn']; ?></div>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <span class="badge badge-info" style="font-weight: 600;"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php 
                                    if ($row['total_poin'] >= 100) {
                                        echo '<span class="badge" style="background: #ffe4e6; color: #e11d48; border: 1px solid #fecdd3; font-weight: 700; font-size: 0.9rem; padding: 4px 10px;">' . $row['total_poin'] . ' Poin</span>';
                                    } elseif ($row['total_poin'] >= 50) {
                                        echo '<span class="badge" style="background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; font-weight: 700; font-size: 0.9rem; padding: 4px 10px;">' . $row['total_poin'] . ' Poin</span>';
                                    } elseif ($row['total_poin'] >= 25) {
                                        echo '<span class="badge" style="background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; font-weight: 700; font-size: 0.9rem; padding: 4px 10px;">' . $row['total_poin'] . ' Poin</span>';
                                    } else {
                                        echo '<span class="badge" style="background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; font-weight: 700; font-size: 0.9rem; padding: 4px 10px;">' . $row['total_poin'] . ' Poin</span>';
                                    }
                                ?>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <?php 
                                    if($row['total_poin'] >= 100) echo '<span class="badge badge-danger" style="font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-gavel"></i> Orang Tua / SP</span>';
                                    elseif($row['total_poin'] >= 50) echo '<span class="badge badge-warning" style="font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-exclamation-triangle"></i> Peringatan Keras</span>';
                                    elseif($row['total_poin'] >= 25) echo '<span class="badge badge-info" style="font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-comments"></i> Bimbingan Khusus</span>';
                                    else echo '<span class="badge badge-primary" style="font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-info-circle"></i> Teguran Lisan</span>';
                                ?>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 6px;">
                                    <?php if($row['total_poin'] >= 50): ?>
                                        <?php if($row['status_panggilan'] == 'Hadir'): ?>
                                            <span class="badge badge-success" style="font-size: 0.72rem; padding: 4px 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-check-double"></i> Orang Tua Hadir
                                            </span>
                                            <a href="buat_panggilan.php?siswa_id=<?php echo $row['nisn']; ?>" class="btn btn-primary btn-sm" style="font-size: 0.72rem; padding: 4px 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;">
                                                <i class="fas fa-plus"></i> Panggil Lagi
                                            </a>
                                        <?php elseif($row['status_panggilan'] == 'Tidak Hadir'): ?>
                                            <span class="badge badge-danger" style="font-size: 0.72rem; padding: 4px 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-user-slash"></i> Orang Tua Tidak Hadir
                                            </span>
                                            <a href="buat_panggilan.php?siswa_id=<?php echo $row['nisn']; ?>" class="btn btn-warning btn-sm" style="font-size: 0.72rem; padding: 4px 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;">
                                                <i class="fas fa-redo"></i> Panggil Ulang
                                            </a>
                                        <?php elseif($row['status_panggilan'] == 'Dikirim'): ?>
                                            <span class="badge badge-info" style="font-size: 0.72rem; padding: 4px 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-paper-plane"></i> Menunggu Orang Tua
                                            </span>
                                            <small style="color: #64748b; font-size: 0.7rem; font-weight: 500;">Surat sudah dikirim</small>
                                        <?php else: ?>
                                            <a href="buat_panggilan.php?siswa_id=<?php echo $row['nisn']; ?>" class="btn btn-danger btn-sm" style="font-size: 0.72rem; padding: 6px 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px;">
                                                <i class="fas fa-envelope"></i> Panggil Ortu
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge" style="background: #ecfdf5; color: #065f46; font-size: 0.75rem; padding: 4px 10px; font-weight: 600; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-check-circle" style="color: #10b981;"></i> Poin Aman
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        endwhile; 
                        if(!$has_data) echo "<tr><td colspan='7' style='text-align:center;'>Belum ada siswa yang memiliki poin pelanggaran.</td></tr>";
                        ?>
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
