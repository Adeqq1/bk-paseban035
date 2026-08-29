<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek login & role siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
// Mengambil data siswa, termasuk kolom foto untuk sidebar
$query_siswa = mysqli_query($koneksi, "SELECT id, nama_lengkap, foto FROM siswa WHERE user_id = '$user_id'");
$siswa = mysqli_fetch_assoc($query_siswa);
$siswa_id = $siswa['id'];

// Ambil daftar Guru BK untuk pilihan select form
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE jabatan = 'Guru BK'");

// Proses Kirim Pengajuan Bimbingan Mandiri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan'])) {
    $guru_id      = mysqli_real_escape_string($koneksi, $_POST['guru_id']);
    $topik        = mysqli_real_escape_string($koneksi, $_POST['topik']);
    $kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori_masalah']);
    $tgl_pref     = mysqli_real_escape_string($koneksi, $_POST['tanggal_preferensi']);
    $waktu_pref   = mysqli_real_escape_string($koneksi, $_POST['waktu_preferensi']);
    $urgensi      = mysqli_real_escape_string($koneksi, $_POST['tingkat_urgensi']);
    $catatan_raw  = mysqli_real_escape_string($koneksi, $_POST['catatan']);
    $rahasia      = isset($_POST['bersifat_rahasia']) ? 1 : 0;

    $catatan = "Urgensi: " . $urgensi;
    if (!empty($catatan_raw)) {
        $catatan .= "\nCatatan Tambahan: " . $catatan_raw;
    }

    mysqli_query($koneksi, "
        INSERT INTO jadwal_bimbingan (siswa_id, guru_id, topik, kategori_masalah, tanggal_preferensi, waktu_preferensi, catatan, bersifat_rahasia, status, created_at)
        VALUES ('$siswa_id', '$guru_id', '$topik', '$kategori', '$tgl_pref', '$waktu_pref', '$catatan', '$rahasia', 'Menunggu', NOW())
    ");
    header("Location: bimbingan_mandiri.php?pesan=ajukan_success");
    exit();
}

// Ambil riwayat pengajuan milik siswa ini
$query_jadwal = mysqli_query($koneksi, "
    SELECT jb.*, g.nama_lengkap as nama_guru
    FROM jadwal_bimbingan jb
    JOIN guru g ON jb.guru_id = g.id
    WHERE jb.siswa_id = '$siswa_id'
    ORDER BY jb.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bimbingan Mandiri | SI BK7</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Gaya khusus kartu riwayat jadwal konseling */
        .card-jadwal {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 14px;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.25s ease;
        }
        .card-jadwal:hover { 
            box-shadow: 0 8px 16px rgba(0,0,0,0.06); 
            transform: translateY(-2px);
        }
        .card-jadwal.status-menunggu { border-left: 5px solid #d97706; }
        .card-jadwal.status-disetujui { border-left: 5px solid #059669; }
        .card-jadwal.status-ditolak { border-left: 5px solid #dc2626; }
        .card-jadwal.status-selesai { border-left: 5px solid #2563eb; }
        
        .badge-menunggu  { background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display:inline-flex; align-items:center; gap:4px; }
        .badge-disetujui { background: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display:inline-flex; align-items:center; gap:4px; }
        .badge-ditolak   { background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display:inline-flex; align-items:center; gap:4px; }
        .badge-selesai   { background: #e0f2fe; color: #2563eb; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; display:inline-flex; align-items:center; gap:4px; }

        /* Gaya Form Modal */
        .modal {
            display: none;
            position: fixed !important;
            z-index: 1000 !important;
            inset: 0 !important;
            background: rgba(15, 23, 42, 0.4) !important;
            backdrop-filter: blur(6px) !important;
            -webkit-backdrop-filter: blur(6px) !important;
            overflow-y: auto !important;
            padding: 1rem !important;
            box-sizing: border-box !important;
        }
        .modal.open {
            display: block !important;
        }
        .modal-content {
            border-radius: 16px !important;
            padding: 2.25rem !important;
            max-width: 600px !important;
            margin: 2rem auto !important;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            background: white !important;
            position: relative !important;
        }
        .modal-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-header p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }
        .modal-header {
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 14px !important;
            margin-bottom: 1.5rem !important;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.82rem;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-control {
            width: 100%;
            height: 42px;
            padding: 0 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        textarea.form-control {
            height: auto;
            padding: 0.7rem 1rem;
        }
        select.form-control:invalid {
            color: #94a3b8;
        }
        input[type="date"].form-control:invalid {
            color: #94a3b8;
        }
        .form-control:focus {
            border-color: #3b82f6;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.1em;
            padding-right: 2.5rem;
            cursor: pointer;
        }
        .btn-submit {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.15);
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        /* Custom styling for Rahasia warning box and checkbox */
        .rahasia-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
        .rahasia-box:hover {
            background: #fff9db;
            border-color: #fcd34d;
        }
        .rahasia-box input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #d97706;
            border-radius: 4px;
            background-color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
            flex-shrink: 0;
            transition: all 0.15s ease;
            position: relative;
        }
        .rahasia-box input[type="checkbox"]:checked {
            background-color: #d97706;
            border-color: #d97706;
        }
        .rahasia-box input[type="checkbox"]:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            font-size: 10px;
        }
        .rahasia-box label {
            cursor: pointer;
            font-size: 0.85rem;
            color: #d97706;
            line-height: 1.5;
            margin: 0;
            font-weight: 500;
            user-select: none;
        }
    </style>
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR (NAVIGASI MENU SAMPING) -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>SI BK<span>7</span></h3>
            <p>Siswa Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bimbingan_mandiri.php" class="active"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="riwayat.php"><i class="fas fa-history"></i> Riwayat & Arsip</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Footer Info Akun -->
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <?php if(!empty($siswa['foto']) && file_exists('../assets/uploads/profil/' . $siswa['foto'])): ?>
                <!-- Jika ada, tampilkan foto profil tersebut -->
                <img src="../assets/uploads/profil/<?php echo $siswa['foto']; ?>" alt="Foto Profil" class="avatar" style="object-fit: cover;">
            <?php else: ?>
                <div class="avatar"><?php echo strtoupper(substr($siswa['nama_lengkap'], 0, 1)); ?></div>
            <?php endif; ?>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars(ucwords(strtolower($siswa['nama_lengkap']))); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Siswa SMAN 7</div>
            </div>
        </div>
    </div>

    <!-- AREA UTAMA KONTEN -->
    <div class="main-content">
        <!-- Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-calendar-check" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Bimbingan Mandiri</h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Ajukan dan pantau jadwal konseling individu secara mandiri.</p>
                </div>
            </div>
            <!-- Tombol buka modal dengan animasi baru -->
            <button onclick="document.getElementById('modal-ajukan').classList.add('open')" class="btn btn-primary" style="background: #3b82f6; border: none; padding: 10px 20px; font-weight: 600; font-size: 0.95rem; border-radius: 8px;">
                <i class="fas fa-plus"></i> Ajukan Jadwal
            </button>
        </div>

        <!-- Notifikasi Sukses -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'ajukan_success'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Pengajuan jadwal bimbingan berhasil dikirim! Guru BK akan mengkonfirmasi lewat riwayat di bawah.
            </div>
        <?php endif; ?>

        <!-- Banner Informasi Kategori -->
        <div style="background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%); border: 1px solid #bfdbfe; border-left: 5px solid #3b82f6; padding: 1.25rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: flex-start; gap: 1.25rem; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.06);">
            <div style="background: #dbeafe; width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #2563eb;">
                <i class="fas fa-info-circle" style="font-size: 1.35rem;"></i>
            </div>
            <div>
                <h4 style="margin: 0 0 4px 0; color: #1e40af; font-size: 0.95rem; font-weight: 700;">Alur Layanan Konseling:</h4>
                <p style="margin: 0; color: #1e3a8a; font-size: 0.875rem; line-height: 1.5;">Ajukan jadwal pertemuan dengan memilih kategori masalah, tanggal, dan waktu preferensi. Guru BK akan meninjau pengajuan Anda. Pertemuan dilangsungkan secara tatap muka di ruang BK.</p>
            </div>
        </div>

        <!-- Daftar Pengajuan -->
        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-history"></i> Riwayat Pengajuan Saya</h2>
                <span class="badge badge-info"><?php echo mysqli_num_rows($query_jadwal); ?> Pengajuan</span>
            </div>
            
            <div class="table-responsive" style="padding-top: 1.5rem;">
                <?php if (mysqli_num_rows($query_jadwal) > 0): ?>
                    <?php while ($jdw = mysqli_fetch_assoc($query_jadwal)): 
                        $st = $jdw['status'];
                        $status_class = 'status-' . strtolower($st);
                        
                        $urgensi_badge = '';
                        $catatan_tambahan = '';
                        if (!empty($jdw['catatan'])) {
                            $lines = explode("\n", $jdw['catatan']);
                            foreach ($lines as $line) {
                                if (strpos($line, 'Urgensi:') === 0) {
                                    $urg_val = trim(substr($line, 8));
                                    if ($urg_val === 'Sangat Mendesak') {
                                        $urgensi_badge = '<span style="background:#fee2e2; color:#dc2626; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px;"><i class="fas fa-exclamation-triangle"></i> Mendesak</span>';
                                    } elseif ($urg_val === 'Penting') {
                                        $urgensi_badge = '<span style="background:#fff7ed; color:#d97706; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px;"><i class="fas fa-exclamation-circle"></i> Penting</span>';
                                    } else {
                                        $urgensi_badge = '<span style="background:#f0fdf4; color:#059669; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px;"><i class="fas fa-info-circle"></i> Biasa</span>';
                                    }
                                } elseif (strpos($line, 'Catatan Tambahan:') === 0) {
                                    $catatan_tambahan = trim(substr($line, 17));
                                }
                            }
                            if (empty($urgensi_badge) && empty($catatan_tambahan)) {
                                $catatan_tambahan = $jdw['catatan'];
                            }
                        }
                    ?>
                    <div class="card-jadwal <?php echo $status_class; ?>">
                        <div>
                            <div style="font-size:1.05rem; font-weight:700; color:#0f172a; margin-bottom:6px;"><?php echo htmlspecialchars($jdw['topik']); ?></div>
                            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px; align-items:center;">
                                <?php if (!empty($jdw['kategori_masalah'])): ?>
                                <span style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px;">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($jdw['kategori_masalah']); ?>
                                </span>
                                <?php endif; ?>
                                <?php echo $urgensi_badge; ?>
                                <?php if (!empty($jdw['bersifat_rahasia'])): ?>
                                <span style="background:#fee2e2; color:#dc2626; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px;">
                                    <i class="fas fa-lock"></i> Sangat Rahasia
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="font-size:0.85rem; color:#475569; line-height:1.6; margin-bottom:6px;">
                                <span style="display:inline-block; margin-right:12px;"><i class="fas fa-user-tie" style="color:#94a3b8; margin-right:5px;"></i> Guru BK: <strong><?php echo htmlspecialchars($jdw['nama_guru']); ?></strong></span>
                                <span style="display:inline-block;"><i class="fas fa-calendar-alt" style="color:#94a3b8; margin-right:5px;"></i> Rencana Tanggal: <?php echo date('d M Y', strtotime($jdw['tanggal_preferensi'])); ?> (<?php echo htmlspecialchars($jdw['waktu_preferensi'] ?? '-'); ?>)</span>
                            </div>

                            <?php if (!empty($catatan_tambahan)): ?>
                            <div style="font-size:0.83rem; color:#475569; margin-bottom:8px; background:#f8fafc; padding:8px 12px; border-radius:8px; border-left:3px solid #cbd5e1;">
                                <strong>Catatan Saya:</strong> <?php echo nl2br(htmlspecialchars($catatan_tambahan)); ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($jdw['tanggal_disetujui'])): ?>
                            <div style="font-size:0.85rem; color:#059669; margin-top:8px; background:#f0fdf4; padding:8px 12px; border-radius:8px; border-left:3px solid #059669; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-calendar-check" style="font-size:1rem;"></i> 
                                <div>
                                    Jadwal Pertemuan Dikonfirmasi: <strong><?php echo date('d M Y, H:i', strtotime($jdw['tanggal_disetujui'])); ?> WIB</strong> 
                                    <?php if (!empty($jdw['lokasi'])): ?>
                                     &nbsp;·&nbsp; Tempat: <strong><?php echo htmlspecialchars($jdw['lokasi']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($jdw['catatan_guru'])): ?>
                            <div style="font-size:0.85rem; color:#0f172a; margin-top:8px; background:#fdf2f8; padding:8px 12px; border-radius:8px; border-left:3px solid #6366f1;">
                                <i class="fas fa-comment-dots" style="color:#6366f1; margin-right:5px;"></i><strong>Pesan dari Guru BK:</strong> <?php echo nl2br(htmlspecialchars($jdw['catatan_guru'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                            <?php
                            if ($st === 'Menunggu')  echo '<span class="badge-menunggu"><i class="fas fa-clock"></i> Menunggu</span>';
                            elseif ($st === 'Disetujui') echo '<span class="badge-disetujui"><i class="fas fa-check"></i> Disetujui</span>';
                            elseif ($st === 'Ditolak') echo '<span class="badge-ditolak"><i class="fas fa-times"></i> Ditolak</span>';
                            elseif ($st === 'Selesai') echo '<span class="badge-selesai"><i class="fas fa-check-double"></i> Selesai</span>';
                            ?>
                            <div style="font-size:0.75rem; color:#94a3b8; font-weight:500;">Diajukan: <?php echo date('d M Y', strtotime($jdw['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:3rem; color:#94a3b8;">
                        <i class="fas fa-calendar-times fa-3x" style="margin-bottom:1rem; opacity:0.4;"></i>
                        <p>Belum ada pengajuan bimbingan. Klik <strong>Ajukan Jadwal</strong> untuk memulai.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL POPUP AJUKAN JADWAL -->
    <div id="modal-ajukan" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2><i class="fas fa-calendar-plus" style="color:#3b82f6; margin-right: 5px;"></i> Ajukan Bimbingan</h2>
                    <p>Isi formulir pengajuan bimbingan individu</p>
                </div>
                <div class="close" onclick="document.getElementById('modal-ajukan').classList.remove('open')">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label><i class="fas fa-user-tie" style="color: #3b82f6;"></i> Guru BK yang Dituju</label>
                    <select name="guru_id" class="form-control" required>
                        <option value="">-- Pilih Guru BK --</option>
                        <?php while ($g = mysqli_fetch_assoc($query_guru)): ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['nama_lengkap']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag" style="color: #10b981;"></i> Kategori Masalah</label>
                    <select name="kategori_masalah" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Akademik / Belajar">📚 Akademik / Belajar</option>
                        <option value="Sosial / Pertemanan">👥 Sosial / Pertemanan</option>
                        <option value="Keluarga">🏠 Keluarga</option>
                        <option value="Motivasi & Mental">💪 Motivasi & Mental</option>
                        <option value="Karir & Masa Depan">🎯 Karir & Masa Depan</option>
                        <option value="Lainnya">📌 Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment-alt" style="color: #f59e0b;"></i> Topik Pertemuan</label>
                    <input type="text" name="topik" class="form-control" required placeholder="Contoh: Kesulitan belajar di kelas...">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day" style="color: #ec4899;"></i> Tanggal Rencana</label>
                        <input type="date" name="tanggal_preferensi" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock" style="color: #8b5cf6;"></i> Waktu Preferensi</label>
                        <select name="waktu_preferensi" class="form-control" required>
                            <option value="">-- Pilih Waktu --</option>
                            <option value="Pagi (07:00-09:00)">🌅 Pagi (07:00–09:00)</option>
                            <option value="Istirahat (09:30-10:00)">☕ Istirahat (09:30–10:00)</option>
                            <option value="Siang (11:00-12:00)">🌤 Siang (11:00–12:00)</option>
                            <option value="Siang (12:00-13:00)">🕛 Siang (12:00–13:00)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> Tingkat Urgensi</label>
                    <select name="tingkat_urgensi" class="form-control" required>
                        <option value="Biasa">Biasa (Bisa dijadwalkan santai)</option>
                        <option value="Penting">Penting (Butuh bimbingan segera)</option>
                        <option value="Sangat Mendesak">Sangat Mendesak (Butuh penanganan cepat)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-edit" style="color: #64748b;"></i> Catatan Tambahan <small style="color:#94a3b8; text-transform: lowercase;">(opsional)</small></label>
                    <textarea name="catatan" class="form-control" rows="2" placeholder="Detail masalah yang ingin disampaikan..."></textarea>
                </div>
                <div class="rahasia-box">
                    <input type="checkbox" name="bersifat_rahasia" id="chk-rahasia">
                    <label for="chk-rahasia">
                        <strong>🔒 Bersifat Sangat Rahasia</strong> — Saya memohon agar isi permasalahan ini hanya diketahui oleh Guru BK yang bersangkutan.
                    </label>
                </div>
                <div class="modal-footer" style="margin-top: 1rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('modal-ajukan').classList.remove('open')" class="btn-cancel" style="padding: 0.7rem 1.25rem; font-size: 0.9rem;">
                        Batal
                    </button>
                    <button type="submit" name="ajukan" class="btn-submit" style="padding: 0.7rem 1.5rem; font-size: 0.9rem;">
                        <i class="fas fa-paper-plane"></i> Kirim Pengajuan
                    </button>
                </div>
            </form>
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
