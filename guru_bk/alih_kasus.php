<?php
/**
 * =========================================================================
 * HALAMAN MANAGEMENT ALIH TANGAN KASUS (REFERRAL) GURU BK - SI BK7
 * Halaman ini mengelola dokumen rujukan/alih tangan kasus siswa kepada pihak
 * spesialis profesional (Psikolog, Psikiater, Kepolisian, Rumah Sakit, dll).
 * =========================================================================
 */

session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// =========================================================================
// 1. CEK OTENTIKASI SISTEM & HAK AKSES USER (ROLE GURU BK)
// =========================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;

// =========================================================================
// 2. PROSES HAPUS DOKUMEN ALIH TANGAN KASUS
// =========================================================================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    $check = mysqli_query($koneksi, "SELECT id FROM alih_kasus WHERE id = '$id' AND guru_id = '$guru_id'");
    if (mysqli_num_rows($check) > 0) {
        if (mysqli_query($koneksi, "DELETE FROM alih_kasus WHERE id = '$id'")) {
            header("Location: alih_kasus.php?pesan=success_hapus");
            exit();
        }
    }
}

// =========================================================================
// 3. QUERY PENAMPILAN DAFTAR ALIH TANGAN KASUS & MULTI-FILTER
// =========================================================================
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$status_siswa = isset($_GET['status_siswa']) ? mysqli_real_escape_string($koneksi, $_GET['status_siswa']) : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Query untuk dropdown nama siswa
$query_siswa_filter = mysqli_query($koneksi, "
    SELECT DISTINCT s.id, s.nama_lengkap 
    FROM siswa s 
    JOIN alih_kasus ak ON s.id = ak.siswa_id 
    WHERE ak.guru_id = '$guru_id' 
    ORDER BY s.nama_lengkap ASC
");

$status_filter = "";

if (!empty($filter_siswa_id)) {
    $status_filter .= " AND ak.siswa_id = '$filter_siswa_id'";
}

if ($status_siswa == 'aktif') {
    $status_filter .= " AND s.status = 'aktif'";
} elseif ($status_siswa == 'alumni') {
    $status_filter .= " AND (s.status = 'alumni' OR s.status = 'lulus')";
}

if ($semester == '1') {
    $start_date = "$tahun-07-01";
    $end_date = "$tahun-12-31";
    $status_filter .= " AND ak.tanggal BETWEEN '$start_date' AND '$end_date'";
} elseif ($semester == '2') {
    $start_date = "$tahun-01-01";
    $end_date = "$tahun-06-30";
    $status_filter .= " AND ak.tanggal BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($tahun)) {
    $status_filter .= " AND YEAR(ak.tanggal) = '$tahun'";
}

$query_alih = mysqli_query($koneksi, "
    SELECT ak.*, s.nama_lengkap as nama_siswa, s.nisn, s.jenis_kelamin, s.alamat as alamat_siswa, s.status as status_siswa, k.nama_kelas
    FROM alih_kasus ak
    JOIN siswa s ON ak.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE ak.guru_id = '$guru_id' $status_filter
    ORDER BY ak.tanggal DESC, ak.id DESC
");

$total_kasus = $query_alih ? mysqli_num_rows($query_alih) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Alih Tangan Kasus | SI BK7</title>
    <!-- File CSS Utama & CDN Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
            grid-template-columns: minmax(180px, 1.2fr) minmax(160px, 1fr) minmax(160px, 1fr) minmax(120px, 0.8fr) auto;
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

        .data-card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .kasus-badge-count {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.825rem;
            font-weight: 700;
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

    <!-- NAVIGATION SIDEBAR UTAMA -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SI BK<span>7</span></h3>
            <p>GURU BK PANEL</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
            <li><a href="daftar_panggilan.php"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
            <li><a href="alih_kasus.php" class="active"><i class="fas fa-share-square"></i> Alih Tangan Kasus</a></li>
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

    <!-- KONTEN UTAMA DOKUMEN ALIH TANGAN KASUS -->
    <div class="main-content">
        <!-- Banner Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 1.5rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); border: 1px solid rgba(255,255,255,0.05);">
            <div style="display: flex; align-items: center; gap: 1.25rem;">
                <div style="background: rgba(255,255,255,0.08); width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.12);">
                    <i class="fas fa-share-square" style="font-size: 1.6rem; color: #c084fc;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 4px 0; font-size: 1.5rem; font-weight: 800; color: white;">Layanan Alih Tangan Kasus</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.9rem;">Kelola rujukan penanganan kasus khusus kepada pihak terkait.</p>
                </div>
            </div>
            <a href="tambah_alih_kasus.php" class="btn-tambah-utama">
                <i class="fas fa-plus"></i> Buat Alih Kasus
            </a>
        </div>

        <!-- Blok Notifikasi Berhasil -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success_tambah'): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span>Dokumen rujukan alih tangan kasus berhasil dibuat!</span>
            </div>
        <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'success_edit'): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);">
                <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                <span>Dokumen rujukan alih tangan kasus berhasil diperbarui!</span>
            </div>
        <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'success_hapus'): ?>
            <div class="alert alert-danger" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);">
                <i class="fas fa-trash-alt" style="font-size: 1.25rem;"></i>
                <span>Dokumen rujukan alih tangan kasus berhasil dihapus dari sistem!</span>
            </div>
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
                        <label for="status_siswa">Status</label>
                        <select name="status_siswa" id="status_siswa" class="filter-select-modern">
                            <option value="">-- Semua Status --</option>
                            <option value="aktif" <?php echo $status_siswa == 'aktif' ? 'selected' : ''; ?>>Siswa Aktif</option>
                            <option value="alumni" <?php echo $status_siswa == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                        </select>
                    </div>

                    <div class="filter-group-modern">
                        <label for="semester">Semester</label>
                        <select name="semester" id="semester" class="filter-select-modern">
                            <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                            <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                            <option value="" <?php echo $semester == '' ? 'selected' : ''; ?>>-- Semua --</option>
                        </select>
                    </div>

                    <div class="filter-group-modern">
                        <label for="tahun">Tahun</label>
                        <select name="tahun" id="tahun" class="filter-select-modern">
                            <option value="">-- Semua --</option>
                            <?php 
                            $thn_skrg = date('Y');
                            for($t = $thn_skrg; $t >= $thn_skrg - 3; $t--): 
                            ?>
                                <option value="<?php echo $t; ?>" <?php echo $tahun == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn-cari-custom">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <?php if(isset($_GET['siswa_id']) || isset($_GET['status_siswa']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                        <a href="alih_kasus.php" class="btn-reset-custom" title="Reset Filter">
                            <i class="fas fa-undo"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Dokumen Alih Tangan Kasus -->
        <div class="data-card" style="border-radius: 16px; border: 1px solid #f1f5f9; background: white; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div class="data-card-header-flex">
                <h2 class="data-card-title">
                    <i class="fas fa-folder-open" style="color: #3b82f6;"></i> Riwayat Formulir Alih Tangan Kasus
                </h2>
                <span class="kasus-badge-count"><?php echo $total_kasus; ?> Kasus</span>
            </div>
            
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                            <th style="padding: 14px 18px; width: 50px; text-align: center; font-size: 0.825rem; font-weight: 700; color: #475569;">No</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">Tanggal</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">NISN</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">Nama Siswa</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">Kelas</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">Penerima Kasus</th>
                            <th style="padding: 14px 18px; font-size: 0.825rem; font-weight: 700; color: #475569;">Ringkasan Masalah</th>
                            <th style="padding: 14px 18px; width: 140px; text-align: center; font-size: 0.825rem; font-weight: 700; color: #475569;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_alih && mysqli_num_rows($query_alih) > 0):
                            while($row = mysqli_fetch_assoc($query_alih)):
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 18px; text-align: center; color: #64748b; font-size: 0.875rem;"><?php echo $no++; ?></td>
                            <td style="padding: 14px 18px; color: #334155; font-size: 0.875rem; white-space: nowrap;"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                            <td style="padding: 14px 18px; color: #475569; font-size: 0.875rem;"><?php echo htmlspecialchars($row['nisn']); ?></td>
                            <td style="padding: 14px 18px; color: #334155; font-size: 0.875rem; font-weight: 400;"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                            <td style="padding: 14px 18px; font-size: 0.85rem;"><span class="badge badge-primary" style="padding: 4px 10px; border-radius: 6px;"><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></span></td>
                            <td style="padding: 14px 18px; color: #334155; font-size: 0.875rem; font-weight: 400;"><?php echo htmlspecialchars($row['penerima_kasus'] ?? $row['pihak_penerima'] ?? '-'); ?></td>
                            <td style="padding: 14px 18px; color: #475569; font-size: 0.85rem; max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?php echo htmlspecialchars($row['ringkasan_masalah'] ?? $row['alasan'] ?? '-'); ?></td>
                            <td style="padding: 14px 18px; text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <a href="cetak_alih_kasus.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-primary btn-sm btn-icon" title="Cetak Surat Rujukan" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px;">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="edit_alih_kasus.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm btn-icon" title="Edit Rujukan" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #eab308; color: white;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Apakah Anda yakin ingin menghapus data rujukan alih kasus ini?')" title="Hapus Rujukan" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px;">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem;">Belum ada dokumen alih tangan kasus yang dicatat pada filter ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
