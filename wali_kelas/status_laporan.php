<?php
/**
 * ====================================================================================
 * MODUL STATUS LAPORAN PELANGGARAN - PANEL WALI KELAS (BK SMA 07 Bungo SMAN 7 BUNGO)
 * ====================================================================================
 * Halaman ini digunakan oleh Wali Kelas untuk memantau progres dan status tindak lanjut
 * dari laporan pelanggaran siswa perwalian yang telah dikirimkan ke Guru Bimbingan Konseling.
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN: Memastikan pengguna berstatus 'wali_kelas'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'wali_kelas') {
    header("Location: ../index.php");
    exit();
}

// 4. MENGAMBIL DATA GURU / WALI KELAS DARI DATABASE
$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru['id'] ?? 0;

// Format nama guru dan penulisan gelar resmi
$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? 'Wali Kelas'));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// 5. MENGAMBIL DATA KELAS PERWALIAN
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas = mysqli_fetch_assoc($query_kelas);
$kelas_id = $kelas['id'] ?? 0;

// 6. PARAMETER FILTER LAPORAN
$filter_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$filter_status   = isset($_GET['status_bk']) ? mysqli_real_escape_string($koneksi, $_GET['status_bk']) : '';
$filter_semester = isset($_GET['semester']) ? mysqli_real_escape_string($koneksi, $_GET['semester']) : (date('m') >= 7 ? '1' : '2');
$filter_tahun    = isset($_GET['tahun']) ? mysqli_real_escape_string($koneksi, $_GET['tahun']) : date('Y');

// 7. MENGAMBIL DAFTAR SISWA PERWALIAN UNTUK DROPDOWN FILTER
$list_siswa = [];
if ($kelas_id) {
    $q_siswa_list = mysqli_query($koneksi, "SELECT id, nama_lengkap, nisn FROM siswa WHERE kelas_id = '$kelas_id' ORDER BY nama_lengkap ASC");
    while ($rs = mysqli_fetch_assoc($q_siswa_list)) {
        $list_siswa[] = $rs;
    }
}

// 8. MEMBANGUN KLAUSA WHERE DYNAMIC SQL QUERY
$where_clauses = [];

if ($kelas_id) {
    $where_clauses[] = "s.kelas_id = '$kelas_id'";
} else {
    $where_clauses[] = "cp.guru_id = '$guru_id'";
}

if ($filter_siswa_id !== '') {
    $where_clauses[] = "cp.siswa_id = '$filter_siswa_id'";
}

if ($filter_semester == '1') {
    $start_date = "$filter_tahun-07-01";
    $end_date   = "$filter_tahun-12-31";
    $where_clauses[] = "cp.tanggal BETWEEN '$start_date' AND '$end_date'";
} elseif ($filter_semester == '2') {
    $start_date = "$filter_tahun-01-01";
    $end_date   = "$filter_tahun-06-30";
    $where_clauses[] = "cp.tanggal BETWEEN '$start_date' AND '$end_date'";
} else {
    $where_clauses[] = "YEAR(cp.tanggal) = '$filter_tahun'";
}

if ($filter_status == 'Menunggu') {
    $where_clauses[] = "(kon.id IS NULL OR kon.status = 'Menunggu')";
} elseif ($filter_status == 'Diproses') {
    $where_clauses[] = "(kon.status = 'Diproses' OR kon.status = 'Proses')";
} elseif ($filter_status == 'Selesai') {
    $where_clauses[] = "kon.status = 'Selesai'";
} elseif ($filter_status == 'Ditolak') {
    $where_clauses[] = "kon.status = 'Ditolak'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// 9. EKSEKUSI QUERY AMBIL DATA LAPORAN PELANGGARAN
$query_laporan = mysqli_query($koneksi, "
    SELECT cp.*, s.nama_lengkap as nama_siswa, s.nisn, s.kelas_id,
           jp.nama_pelanggaran, jp.poin, jp.kategori,
           g.nama_lengkap as nama_pelapor, g.id as id_pelapor_guru,
           kon.id as konseling_id, kon.status as status_bk, kon.solusi as tindakan_solusi, kon.tanggal as tanggal_tindak_lanjut
    FROM catatan_pelanggaran cp
    JOIN siswa s ON cp.siswa_id = s.id
    JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    LEFT JOIN guru g ON cp.guru_id = g.id
    LEFT JOIN konseling kon ON cp.id = kon.catatan_pelanggaran_id
    $where_sql
    ORDER BY cp.tanggal DESC, cp.id DESC
");

$total_laporan = $query_laporan ? mysqli_num_rows($query_laporan) : 0;

// Helper Format Tanggal Indonesia
function tgl_indo_singkat($tanggal) {
    if (empty($tanggal) || $tanggal == '0000-00-00') return '-';
    $time = strtotime($tanggal);
    $d = date('j', $time);
    $m = date('n', $time);
    $y = date('Y', $time);
    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    return $d . ' ' . ($bulan[$m] ?? '') . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Laporan Pelanggaran | BK SMA 07 Bungo</title>
    
    <!-- Memuat file CSS admin & FontAwesome -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .filter-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
        }
        .filter-title {
            font-size: 0.825rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-form-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1 1 180px;
            min-width: 150px;
        }
        .form-group-custom label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
        }
        .form-control-custom {
            width: 100%;
            height: 42px;
            padding: 0 2.25rem 0 1rem;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            font-size: 0.875rem;
            color: #1e293b;
            outline: none;
            transition: all 0.2s;
            box-sizing: border-box;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%252364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.85rem center;
            background-size: 1.1em;
        }
        .btn-filter-submit {
            height: 42px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0 1.25rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 3px 8px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-filter-reset {
            height: 42px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .status-badge-menunggu {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-badge-diproses {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-badge-selesai {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-badge-ditolak {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR NAVIGASI PANEL WALI KELAS -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Wali Kelas Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="siswa_perwalian.php"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
            <li><a href="status_laporan.php" class="active"><i class="fas fa-tasks"></i> Status Laporan</a></li>
            <li><a href="status_disiplin.php"><i class="fas fa-user-shield"></i> Status Disiplin</a></li>
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
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'W', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($nama_guru); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Wali Kelas <?php echo htmlspecialchars($kelas['nama_kelas'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <!-- AREA KONTEN UTAMA -->
    <div class="main-content">
        <!-- Header Banner Halaman Status Laporan -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 16px; margin-bottom: 1.75rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <i class="fas fa-tasks" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.015em;">Status Laporan Pelanggaran</h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5;">Pantau status penanganan kasus pelanggaran siswa perwalian Anda oleh Guru BK</p>
                </div>
            </div>
        </div>

        <!-- Alert Notifikasi Laporan Terkirim Berhasil -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'success'): ?>
            <div class="alert alert-success" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                <span>Laporan pelanggaran siswa berhasil dikirimkan dan akan segera ditindaklanjuti oleh Guru BK!</span>
            </div>
        <?php endif; ?>

        <!-- KARTU FILTER RIWAYAT LAPORAN -->
        <div class="filter-card">
            <div class="filter-title">
                <i class="fas fa-filter"></i> Filter Data Laporan
            </div>
            <form method="GET" action="" class="filter-form-grid">
                
                <!-- Filter 1: Pilih Siswa Perwalian -->
                <div class="form-group-custom">
                    <label>Siswa</label>
                    <select name="siswa_id" class="form-control-custom">
                        <option value="">Semua Siswa</option>
                        <?php foreach ($list_siswa as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($filter_siswa_id == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter 2: Status Penanganan BK -->
                <div class="form-group-custom">
                    <label>Status Penanganan BK</label>
                    <select name="status_bk" class="form-control-custom">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?php echo ($filter_status == 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="Diproses" <?php echo ($filter_status == 'Diproses') ? 'selected' : ''; ?>>Diproses</option>
                        <option value="Selesai" <?php echo ($filter_status == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                        <option value="Ditolak" <?php echo ($filter_status == 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>

                <!-- Filter 3: Semester -->
                <div class="form-group-custom">
                    <label>Semester</label>
                    <select name="semester" class="form-control-custom">
                        <option value="1" <?php echo ($filter_semester == '1') ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                        <option value="2" <?php echo ($filter_semester == '2') ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                    </select>
                </div>

                <!-- Filter 4: Tahun -->
                <div class="form-group-custom">
                    <label>Tahun</label>
                    <select name="tahun" class="form-control-custom">
                        <?php 
                        $thn_skrg = date('Y');
                        for ($t = $thn_skrg; $t >= $thn_skrg - 3; $t--): 
                        ?>
                            <option value="<?php echo $t; ?>" <?php echo ($filter_tahun == $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Tombol Submit & Reset Filter -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-filter-submit">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(isset($_GET['siswa_id']) || isset($_GET['status_bk']) || isset($_GET['semester']) || isset($_GET['tahun'])): ?>
                    <a href="status_laporan.php" class="btn-filter-reset" title="Reset Filter">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TABEL HASIL DOKUMEN STATUS LAPORAN PELANGGARAN -->
        <div class="data-card" style="border-radius: 16px; border: 1px solid #f1f5f9; background: white; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Riwayat Laporan Pelanggaran Siswa</h3>
                <span style="background: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;"><?php echo $total_laporan; ?> Laporan</span>
            </div>

            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; width: 50px; text-align: center;">No</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Tanggal</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Nama Siswa</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Pelanggaran</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Poin</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">Status BK</th>
                            <th style="padding: 14px 18px; color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Tindak Lanjut / Solusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if ($query_laporan && mysqli_num_rows($query_laporan) > 0):
                            while($row = mysqli_fetch_assoc($query_laporan)):
                                $st = $row['status_bk'] ?? 'Menunggu';
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;">
                            <td style="padding: 14px 18px; text-align: center; color: #64748b; font-size: 0.875rem;"><?php echo $no++; ?></td>
                            <td style="padding: 14px 18px; color: #475569; font-size: 0.875rem; font-weight: 500; white-space: nowrap;"><?php echo tgl_indo_singkat($row['tanggal']); ?></td>
                            <td style="padding: 14px 18px; vertical-align: middle; white-space: nowrap;">
                                <div style="color: #0f172a; font-size: 0.875rem; font-weight: 500;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <div style="font-size: 0.8rem; color: #64748b; font-weight: 400; margin-top: 3px;">NISN: <?php echo htmlspecialchars($row['nisn']); ?></div>
                            </td>
                            <td style="padding: 14px 18px; color: #334155; font-size: 0.875rem;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <span style="background: #fef2f2; color: #dc2626; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; border: 1px solid #fecaca;">+<?php echo $row['poin']; ?></span>
                            </td>
                            <td style="padding: 14px 18px; text-align: center;">
                                <?php if ($st == 'Diproses' || $st == 'Proses'): ?>
                                    <span class="status-badge-diproses"><i class="fas fa-spinner fa-spin"></i> Diproses</span>
                                <?php elseif ($st == 'Selesai'): ?>
                                    <span class="status-badge-selesai"><i class="fas fa-check-circle"></i> Selesai</span>
                                <?php elseif ($st == 'Ditolak'): ?>
                                    <span class="status-badge-ditolak"><i class="fas fa-times-circle"></i> Ditolak</span>
                                <?php else: ?>
                                    <span class="status-badge-menunggu"><i class="fas fa-clock"></i> Menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 18px; color: #475569; font-size: 0.85rem;">
                                <?php if (!empty($row['tindakan_solusi'])): ?>
                                    <div style="font-weight: 500; color: #1e293b;"><?php echo htmlspecialchars($row['tindakan_solusi']); ?></div>

                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Belum ada catatan solusi dari Guru BK.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 3rem 1rem;">
                                <i class="fas fa-folder-open" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #cbd5e1;"></i>
                                Belum ada riwayat laporan pelanggaran yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT: TOGGLE SIDEBAR MOBILE -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('mobile-toggle');
        var sidebar   = document.querySelector('.sidebar');
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
    });
    </script>
</body>
</html>
