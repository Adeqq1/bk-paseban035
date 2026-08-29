<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Proteksi Halaman Guru BK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// Proses Hapus Data Kunjungan Rumah
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = mysqli_real_escape_string($koneksi, $_GET['id']);
    mysqli_query($koneksi, "DELETE FROM kunjungan_rumah WHERE id = '$delete_id' AND guru_id = '$guru_id'");
    header("Location: kunjungan_rumah.php?pesan=success_delete");
    exit();
}

// =========================================================================
// 3. QUERY PENAMPILAN DAFTAR KUNJUNGAN RUMAH & MULTI-FILTER
// =========================================================================
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Query untuk dropdown nama siswa
$query_siswa_filter = mysqli_query($koneksi, "
    SELECT DISTINCT s.id, s.nama_lengkap 
    FROM siswa s 
    JOIN kunjungan_rumah kr ON s.id = kr.siswa_id 
    WHERE kr.guru_id = '$guru_id' 
    ORDER BY s.nama_lengkap ASC
");

$status_filter = "";

if (!empty($filter_siswa_id)) {
    $status_filter .= " AND kr.siswa_id = '$filter_siswa_id'";
}

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $status_filter .= " AND kr.tanggal_pelaksanaan BETWEEN '$start_date' AND '$end_date'";
} elseif ($semester == '2') {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $status_filter .= " AND kr.tanggal_pelaksanaan BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($tahun)) {
    $status_filter .= " AND YEAR(kr.tanggal_pelaksanaan) = '$tahun'";
}

$query_list = mysqli_query($koneksi, "
    SELECT kr.*, s.nama_lengkap as nama_siswa, s.nisn, s.jenis_kelamin, k.nama_kelas, g.nama_lengkap as nama_guru
    FROM kunjungan_rumah kr
    JOIN siswa s ON kr.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    JOIN guru g ON kr.guru_id = g.id
    WHERE kr.guru_id = '$guru_id' $status_filter
    ORDER BY kr.tanggal_pelaksanaan DESC, kr.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kunjungan Rumah (Home Visit) | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body, .main-content, h1, h2, h3, .sidebar {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
        /* Modern Filter Box Styling */
        .filter-card-modern {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
            padding: 1.75rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }
        .filter-grid-modern {
            display: grid;
            grid-template-columns: minmax(180px, 1.2fr) minmax(160px, 1fr) minmax(160px, 1fr) auto;
            gap: 1.25rem;
            align-items: flex-end;
        }
        @media (max-width: 992px) {
            .filter-grid-modern {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
        }
        .filter-group-modern label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
        }
        .filter-select-modern {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.65rem 2.2rem 0.65rem 1rem;
            font-size: 0.875rem;
            color: #334155;
            height: 44px;
            width: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            font-family: inherit;
        }
        .filter-select-modern:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        .btn-reset-custom {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-reset-custom:hover {
            background: #e2e8f0;
            color: #334155;
        }
        .data-card {
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            background: white !important;
            padding: 1.5rem !important;
            margin-bottom: 2rem !important;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .table-custom th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .table-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            color: #334155;
            vertical-align: middle;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-print { background: #0284c7; }
        .btn-print:hover { background: #0369a1; }
        .btn-edit { background: #f59e0b; }
        .btn-edit:hover { background: #d97706; }
        .btn-delete { background: #ef4444; }
        .btn-delete:hover { background: #dc2626; }
    
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
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); position: relative; overflow: hidden;">
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-house-user" style="font-size: 1.8rem; color: #38bdf8;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white;">Laporan <span style="color: #38bdf8;">Kunjungan Rumah (Home Visit)</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Kelola & Cetak Hasil Kunjungan Rumah Ke Konseli (Siswa)</p>
                </div>
            </div>
            <div style="position: relative; z-index: 1;">
                <a href="tambah_kunjungan_rumah.php" class="btn-tambah-utama">
                    <i class="fas fa-plus-circle"></i> Buat Laporan Home Visit
                </a>
            </div>
        </div>

        <?php if (isset($_GET['pesan'])): ?>
            <?php if ($_GET['pesan'] == 'success_tambah'): ?>
                <div class="alert badge-success" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #dcfce7; color: #15803d; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> Berhasil menambahkan Laporan Kunjungan Rumah baru!
                </div>
            <?php elseif ($_GET['pesan'] == 'success_edit'): ?>
                <div class="alert badge-success" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #dcfce7; color: #15803d; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> Berhasil memperbarui data Laporan Kunjungan Rumah!
                </div>
            <?php elseif ($_GET['pesan'] == 'success_delete'): ?>
                <div class="alert badge-success" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-weight: 600;">
                    <i class="fas fa-trash-alt"></i> Laporan Kunjungan Rumah berhasil dihapus.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- KARTU FILTER MODERN PRESISI -->
        <div class="filter-card-modern">
            <form method="GET" action="">
                <div class="filter-grid-modern">
                    <div class="filter-group-modern">
                        <label for="siswa_id">Nama Siswa</label>
                        <select name="siswa_id" id="siswa_id" class="filter-select-modern">
                            <option value="">Semua Siswa</option>
                            <?php 
                            if ($query_siswa_filter && mysqli_num_rows($query_siswa_filter) > 0) {
                                while($sf = mysqli_fetch_assoc($query_siswa_filter)): 
                            ?>
                                <option value="<?php echo $sf['id']; ?>" <?php echo $filter_siswa_id == $sf['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sf['nama_lengkap']); ?></option>
                            <?php 
                                endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group-modern">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" class="filter-select-modern">
                            <option value="">Semua Semester</option>
                            <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                            <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                        </select>
                    </div>
                    <div class="filter-group-modern">
                        <label for="tahun">Tahun</label>
                        <select name="tahun" id="tahun" class="filter-select-modern">
                            <option value="">Semua Tahun</option>
                            <?php 
                            $thn_skrg = date('Y');
                            for ($t = $thn_skrg; $t >= $thn_skrg - 3; $t--): 
                            ?>
                                <option value="<?php echo $t; ?>" <?php echo ($tahun == $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-cari-custom">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <?php if(isset($_GET['siswa_id']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                            <a href="kunjungan_rumah.php" class="btn-reset-custom" title="Reset Filter">
                                <i class="fas fa-undo"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="data-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <h2 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #1e293b;">Riwayat Kunjungan Rumah (Home Visit)</h2>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Tanggal</th>
                            <th>Nama Konseli (Siswa)</th>
                            <th>Kelas</th>
                            <th>Yang Ditemui</th>
                            <th>Hasil Kunjungan</th>
                            <th style="width: 120px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if (mysqli_num_rows($query_list) > 0):
                            while ($r = mysqli_fetch_assoc($query_list)):
                        ?>
                            <tr>
                                <td style="text-align: center; color: #64748b;"><?php echo $no++; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($r['tanggal_pelaksanaan'])); ?></td>
                                <td>
                                    <span style="color: #0f172a; font-weight: normal; display: block;"><?php echo htmlspecialchars($r['nama_siswa']); ?></span>
                                    <small style="color: #64748b;">NISN: <?php echo htmlspecialchars($r['nisn'] ?? '-'); ?></small>
                                </td>
                                <td><span style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;"><?php echo htmlspecialchars($r['nama_kelas'] ?? '-'); ?></span></td>
                                <td><?php echo htmlspecialchars($r['yang_ditemui'] ?? '-'); ?></td>
                                <td><small style="color: #475569; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($r['hasil_home_visit'] ?? '-'); ?></small></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <a href="cetak_kunjungan_rumah.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-action btn-print" title="Cetak Surat Laporan">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="edit_kunjungan_rumah.php?id=<?php echo $r['id']; ?>" class="btn-action btn-edit" title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="kunjungan_rumah.php?action=delete&id=<?php echo $r['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus Laporan Kunjungan Rumah ini?')" title="Hapus Data">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                                    <i class="fas fa-folder-open" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                                    Belum ada data Laporan Kunjungan Rumah (Home Visit).
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Script Toggle Menu Mobile -->
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
