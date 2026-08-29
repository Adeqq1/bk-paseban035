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
$guru       = mysqli_fetch_assoc($query_guru);
$guru_id    = $guru ? $guru['id'] : 0;

// ==============================================================================
// BAGIAN PROSES 1: PENJAGAAN & PEMROSESAN KONFIRMASI (SETUJUI JADWAL BIMBINGAN)
// ==============================================================================
// Mengecek apakah ada request pengiriman form bermetode POST dengan tombol submit bertuliskan 'setujui'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setujui'])) {
    
    // 1. PENGAMBILAN & PEMBERSIHAN DATA (SANITASI INPUT)
    // Mengamankan ID Jadwal dari karakter berbahaya (SQL Injection) sebelum diproses ke database
    $jadwal_id        = mysqli_real_escape_string($koneksi, $_POST['jadwal_id']);
    
    // Mengambil ketikkan Tanggal Pertemuan. Fungsi trim() membuang spasi kosong di depan/belakang agar tidak bisa mengakalinya dengan spasi saja
    $tgl              = isset($_POST['tanggal_pertemuan']) ? trim(mysqli_real_escape_string($koneksi, $_POST['tanggal_pertemuan'])) : '';
    
    // Mengambil ketikan Jam Pertemuan dari form yang dipupuk oleh Guru BK
    $jam              = isset($_POST['jam_pertemuan']) ? trim(mysqli_real_escape_string($koneksi, $_POST['jam_pertemuan'])) : '';
    
    // Mengambil teks Lokasi Pertemuan (contoh: Ruang BK / Perpustakaan)
    $lokasi           = isset($_POST['lokasi']) ? trim(mysqli_real_escape_string($koneksi, $_POST['lokasi'])) : '';
    
    // Mengambil Catatan Opsional yang diketik guru untuk dibaca oleh siswa di dashboard mereka
    $catatan_guru     = isset($_POST['catatan_guru']) ? mysqli_real_escape_string($koneksi, $_POST['catatan_guru']) : '';
    
    // Menyimpan posisi tab filter (Semua / Menunggu / Disetujui) agar halaman kembali tepat ke tab yang sedang dilihat
    $filter           = isset($_POST['filter']) ? mysqli_real_escape_string($koneksi, $_POST['filter']) : 'semua';
    
    // 2. VALIDASI KEAMANAN: BENTENG PENCEGAH JADWAL KOSONG (SERVER-SIDE PROTECTION)
    // Jika Tanggal ($tgl), Jam ($jam), atau Lokasi ($lokasi) ternyata kosong (empty) atau hanya berisi spasi kosong:
    if (empty($tgl) || empty($jam) || empty($lokasi)) {
        // Hentikan proses! Tolak penyimpanan ke database, lalu kembalikan pengguna ke halaman bimbingan
        // dengan membawa pesan error khusus ('error_empty') agar muncul kotak merah peringatan di layar
        header("Location: bimbingan_mandiri.php?pesan=error_empty&filter=" . $filter);
        exit(); // Mencegah kode di bawahnya ter-eksekusi (mengakhiri pembacaan program saat itu juga)
    }

    // 3. PENGGABUNGAN FORMAT WAKTU (DATETIME)
    // Menggabungkan tanggal dan jam menjadi satu string (contoh: '2026-08-04' + ' ' + '09:30' => '2026-08-04 09:30')
    $tgl_disetujui    = $tgl . ' ' . $jam;
    
    // 4. PENYIMPANAN KE DATABASE (QUERY UPDATE)
    // Memperbarui status pengajuan menjadi 'Disetujui' pada tabel jadwal_bimbingan sesuai ID Jadwal dan ID Guru BK
    mysqli_query($koneksi, "
        UPDATE jadwal_bimbingan 
        SET status='Disetujui', tanggal_disetujui='$tgl_disetujui', lokasi='$lokasi', catatan_guru='$catatan_guru'
        WHERE id='$jadwal_id' AND guru_id='$guru_id'
    ");
    
    // 5. PENGALIHAN HALAMAN DENGAN PESAN SUKSES
    // Mengarahkan kembali ke halaman bimbingan mandiri sambil melampirkan parameter pesan sukses warna hijau
    header("Location: bimbingan_mandiri.php?pesan=setujui_success&filter=" . $filter);
    exit();
}

// Proses: Tolak pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tolak'])) {
    $jadwal_id    = mysqli_real_escape_string($koneksi, $_POST['jadwal_id']);
    $catatan_guru = mysqli_real_escape_string($koneksi, $_POST['catatan_guru']);
    $filter       = isset($_POST['filter']) ? mysqli_real_escape_string($koneksi, $_POST['filter']) : 'semua';
    
    mysqli_query($koneksi, "
        UPDATE jadwal_bimbingan 
        SET status='Ditolak', catatan_guru='$catatan_guru'
        WHERE id='$jadwal_id' AND guru_id='$guru_id'
    ");
    header("Location: bimbingan_mandiri.php?pesan=tolak_success&filter=" . $filter);
    exit();
}

// Proses: Selesaikan & arsipkan ke konseling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arsipkan'])) {
    $jadwal_id        = mysqli_real_escape_string($koneksi, $_POST['jadwal_id']);
    $siswa_id         = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    $topik            = mysqli_real_escape_string($koneksi, $_POST['topik']);
    $masalah          = mysqli_real_escape_string($koneksi, $_POST['masalah']);
    $solusi           = mysqli_real_escape_string($koneksi, $_POST['solusi']);
    $bidang_bimbingan = mysqli_real_escape_string($koneksi, $_POST['bidang_bimbingan']);
    $filter           = isset($_POST['filter']) ? mysqli_real_escape_string($koneksi, $_POST['filter']) : 'semua';
    $tanggal          = date('Y-m-d');

    // Simpan ke tabel konseling
    mysqli_query($koneksi, "
        INSERT INTO konseling (siswa_id, guru_id, tanggal, topik_permasalahan, masalah, solusi, status, waktu_pertemuan, tempat_pertemuan, jenis_konseling, bidang_bimbingan)
        VALUES ('$siswa_id', '$guru_id', '$tanggal', '$topik', '$masalah', '$solusi', 'Selesai', NOW(), 'Ruang BK', 'Mandiri', '$bidang_bimbingan')
    ");
    // Tandai jadwal selesai
    mysqli_query($koneksi, "UPDATE jadwal_bimbingan SET status='Selesai' WHERE id='$jadwal_id' AND guru_id='$guru_id'");
    header("Location: bimbingan_mandiri.php?pesan=arsip_success&filter=" . $filter);
    exit();
}

// Proses Hapus Pengajuan
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $filter = isset($_GET['filter']) ? mysqli_real_escape_string($koneksi, $_GET['filter']) : 'semua';
    
    // Validasi kepemilikan data sebelum dihapus
    $check = mysqli_query($koneksi, "SELECT id FROM jadwal_bimbingan WHERE id = '$id' AND guru_id = '$guru_id'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($koneksi, "DELETE FROM jadwal_bimbingan WHERE id = '$id'");
    }
    header("Location: bimbingan_mandiri.php?pesan=hapus_success&filter=" . $filter);
    exit();
}

// Ambil semua pengajuan yang masuk ke guru ini
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($koneksi, $_GET['filter']) : 'semua';
$where_filter = $filter !== 'semua' ? "AND jb.status = '$filter'" : '';

$query_jadwal = mysqli_query($koneksi, "
    SELECT jb.*, s.nama_lengkap as nama_siswa, s.nisn, k.nama_kelas
    FROM jadwal_bimbingan jb
    JOIN siswa s ON jb.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE jb.guru_id = '$guru_id' $where_filter
    ORDER BY FIELD(jb.status,'Menunggu','Disetujui','Selesai','Ditolak'), jb.created_at DESC
");

// Hitung pending
$q_pending = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM jadwal_bimbingan WHERE guru_id='$guru_id' AND status='Menunggu'");
$total_pending = mysqli_fetch_assoc($q_pending)['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bimbingan Mandiri | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .filter-tabs {
            display: inline-flex;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
            gap: 6px;
            margin-bottom: 25px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }
        .tab-btn,
        .tab-item,
        a.tab-btn,
        a.tab-item {
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
            gap: 6px;
            cursor: pointer;
        }
        .tab-btn:hover,
        .tab-item:hover,
        a.tab-btn:hover,
        a.tab-item:hover {
            color: #0f172a !important;
            background: rgba(255, 255, 255, 0.7);
            text-decoration: none !important;
        }
        .tab-btn.active,
        .tab-item.active,
        a.tab-btn.active,
        a.tab-item.active {
            background: #ffffff !important;
            color: #2563eb !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04) !important;
            text-decoration: none !important;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-menunggu { background: #fef3c7; color: #d97706; }
        .status-disetujui { background: #dcfce7; color: #166534; }
        .status-ditolak { background: #fee2e2; color: #991b1b; }
        .status-selesai { background: #e0e7ff; color: #3730a3; }
        .status-dibatalkan { background: #f1f5f9; color: #64748b; }

        /* Custom Modal Layout */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            animation: modalSlide 0.3s ease;
        }
        @keyframes modalSlide {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-head {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-head h3 { font-size: 1.1rem; color: #0f172a; margin: 0; }
        .modal-close { cursor: pointer; color: #64748b; font-size: 1.2rem; }
        .modal-body { padding: 24px; }
        .card-pengajuan {
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 14px;
            padding: 1.5rem; 
            margin-bottom: 1rem; 
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .card-pengajuan:hover { 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }
        .badge-menunggu  { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; display:inline-flex; align-items:center; gap:4px; }
        .badge-disetujui { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; display:inline-flex; align-items:center; gap:4px; }
        .badge-ditolak   { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; display:inline-flex; align-items:center; gap:4px; }
        .badge-selesai   { background: #e0f2fe; color: #075985; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; display:inline-flex; align-items:center; gap:4px; }
        .action-btns { display: flex; gap: 8px; margin-top: 1rem; flex-wrap: wrap; }
        
        .modal-overlay {
            display: none; 
            position: fixed; 
            inset: 0;
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(4px);
            justify-content: center; 
            align-items: flex-start; 
            z-index: 1000;
            padding-top: 60px; 
            padding-bottom: 60px;
            overflow-y: auto;
        }
        .modal-box {
            background: white; 
            padding: 2rem; 
            border-radius: 16px;
            width: 520px; 
            max-width: 92%; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { 
            from { transform: translateY(20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        .catatan-box {
            background: #f8fafc; 
            border-left: 3px solid #3b82f6;
            padding: 10px 14px; 
            border-radius: 6px; 
            font-size: 0.875rem; 
            color: #334155;
            margin-top: 10px;
            line-height: 1.5;
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

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>GURU BK PANEL</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php" class="active"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-calendar-check" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Bimbingan <span style="color: #60a5fa;">Mandiri</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Kelola pengajuan jadwal bimbingan mandiri dari siswa.</p>
                </div>
            </div>
            <?php if ($total_pending > 0): ?>
            <div style="position: relative; z-index: 1;">
                <span style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.25); padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-bell"></i> <?php echo $total_pending; ?> Menunggu Konfirmasi
                </span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['pesan'])):
            $pesan_map = [
                'setujui_success' => ['color' => '#065f46', 'bg' => '#d1fae5', 'border' => '#6ee7b7', 'icon' => 'check-circle', 'text' => 'Jadwal bimbingan berhasil dikonfirmasi. Siswa dapat melihat jadwalnya.'],
                'tolak_success'   => ['color' => '#991b1b', 'bg' => '#fee2e2', 'border' => '#fca5a5', 'icon' => 'times-circle', 'text' => 'Pengajuan telah ditolak dan siswa telah diberitahu.'],
                'arsip_success'   => ['color' => '#075985', 'bg' => '#e0f2fe', 'border' => '#7dd3fc', 'icon' => 'archive', 'text' => 'Sesi bimbingan berhasil diselesaikan dan masuk ke Arsip Siswa.'],
                'hapus_success'   => ['color' => '#991b1b', 'bg' => '#fef2f2', 'border' => '#fecaca', 'icon' => 'trash-alt', 'text' => 'Data bimbingan mandiri berhasil dihapus!'],
                'error_empty'     => ['color' => '#991b1b', 'bg' => '#fee2e2', 'border' => '#fca5a5', 'icon' => 'exclamation-triangle', 'text' => 'Gagal mengkonfirmasi! Tanggal, jam, dan lokasi pertemuan tidak boleh kosong dan wajib diisi.'],
            ];
            $p = $pesan_map[$_GET['pesan']] ?? null;
            if ($p): ?>
            <div style="background:<?php echo $p['bg']; ?>; color:<?php echo $p['color']; ?>; border:1px solid <?php echo $p['border']; ?>; padding:0.9rem 1.2rem; border-radius:10px; margin-bottom:1.5rem; display:flex; align-items:center; gap:10px; font-size:0.9rem; font-weight:600;">
                <i class="fas fa-<?php echo $p['icon']; ?>"></i> <?php echo $p['text']; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Filter Tab -->
        <div class="filter-tabs">
            <?php
            $tabs = ['semua' => 'Semua', 'Menunggu' => 'Menunggu', 'Disetujui' => 'Disetujui', 'Selesai' => 'Selesai', 'Ditolak' => 'Ditolak'];
            foreach ($tabs as $key => $label):
            ?>
            <a href="?filter=<?php echo $key; ?>" class="tab-btn <?php echo $filter === $key ? 'active' : ''; ?>">
                <?php echo $label; ?>
                <?php if ($key === 'Menunggu' && $total_pending > 0): ?>
                    <span style="background:#ef4444; color:white; font-size:0.7rem; padding:1px 6px; border-radius:10px; margin-left:4px;"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Daftar Pengajuan -->
        <div class="data-card" style="padding: 1.25rem;">
            <?php if (mysqli_num_rows($query_jadwal) > 0): ?>
                <?php while ($jdw = mysqli_fetch_assoc($query_jadwal)): ?>
                <div class="card-pengajuan">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div style="flex: 1; min-width: 280px;">
                            <div style="font-size:1.1rem; font-weight:700; color:#1e293b; margin-bottom:6px;">
                                <?php echo htmlspecialchars($jdw['topik']); ?>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:8px; font-size:0.85rem; color:#475569;">
                                <span style="font-weight:700; color:#1e293b;"><i class="fas fa-user-graduate" style="color:#3b82f6;"></i> <?php echo htmlspecialchars($jdw['nama_siswa']); ?></span>
                                <span style="color:#cbd5e1;">•</span>
                                <span><?php echo htmlspecialchars($jdw['nama_kelas'] ?? '-'); ?></span>
                                <span style="color:#cbd5e1;">•</span>
                                <span>NISN: <?php echo htmlspecialchars($jdw['nisn']); ?></span>
                                <?php if (!empty($jdw['kategori_masalah'])): ?>
                                <span style="background:#eff6ff; color:#2563eb; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px; border:1px solid #bfdbfe;">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($jdw['kategori_masalah']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($jdw['bersifat_rahasia'])): ?>
                                <span style="background:#fef2f2; color:#b91c1c; font-size:0.75rem; font-weight:600; padding:2px 10px; border-radius:10px; border:1px solid #fecaca;">
                                    <i class="fas fa-lock"></i> Rahasia
                                </span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:0.83rem; color:#64748b; margin-top:4px; display:flex; flex-wrap:wrap; gap:12px;">
                                <span><i class="far fa-calendar-alt" style="color:#64748b;"></i> Preferensi: <strong><?php echo date('d M Y', strtotime($jdw['tanggal_preferensi'])); ?></strong></span>
                                <?php if (!empty($jdw['waktu_preferensi'])): ?>
                                <span><i class="far fa-clock" style="color:#64748b;"></i> <strong><?php echo htmlspecialchars($jdw['waktu_preferensi']); ?></strong></span>
                                <?php endif; ?>
                                <span><i class="fas fa-paper-plane" style="color:#94a3b8;"></i> Diajukan: <?php echo date('d M Y, H:i', strtotime($jdw['created_at'])); ?></span>
                            </div>

                            <?php if (!empty($jdw['catatan'])): ?>
                            <div class="catatan-box"><strong><i class="far fa-comment-alt"></i> Catatan Siswa:</strong><br><?php echo nl2br(htmlspecialchars($jdw['catatan'])); ?></div>
                            <?php endif; ?>

                            <?php if (!empty($jdw['tanggal_disetujui'])): ?>
                            <div style="font-size:0.85rem; color:#059669; margin-top:8px; background:#ecfdf5; padding:8px 12px; border-radius:8px; border-left:3px solid #10b981;">
                                <i class="fas fa-calendar-check" style="margin-right:4px;"></i> Dikonfirmasi: <strong><?php echo date('d M Y, H:i', strtotime($jdw['tanggal_disetujui'])); ?> WIB</strong>
                                <?php if (!empty($jdw['lokasi'])): ?>
                                    &nbsp;•&nbsp; <i class="fas fa-map-marker-alt"></i> Lokasi: <strong><?php echo htmlspecialchars($jdw['lokasi']); ?></strong>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($jdw['catatan_guru'])): ?>
                            <div style="font-size:0.85rem; color:#1e293b; margin-top:8px; background:#fdf2f8; padding:8px 12px; border-radius:8px; border-left:3px solid #ec4899;">
                                <i class="fas fa-comment-dots" style="color:#ec4899; margin-right:5px;"></i><strong>Pesan dari Guru BK:</strong> <?php echo nl2br(htmlspecialchars($jdw['catatan_guru'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="text-align: right;">
                            <?php
                            $st = $jdw['status'];
                            if ($st === 'Menunggu')  echo '<span class="badge-menunggu"><i class="fas fa-clock"></i> Menunggu</span>';
                            elseif ($st === 'Disetujui') echo '<span class="badge-disetujui"><i class="fas fa-check"></i> Disetujui</span>';
                            elseif ($st === 'Ditolak')   echo '<span class="badge-ditolak"><i class="fas fa-times"></i> Ditolak</span>';
                            elseif ($st === 'Selesai')   echo '<span class="badge-selesai"><i class="fas fa-check-double"></i> Selesai</span>';
                            ?>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <?php if ($jdw['status'] === 'Menunggu'): ?>
                    <div class="action-btns">
                        <button onclick="bukaSetujui(<?php echo $jdw['id']; ?>, '<?php echo addslashes($jdw['nama_siswa']); ?>', '<?php echo $jdw['tanggal_preferensi']; ?>')" class="btn btn-success btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-check"></i> Konfirmasi Jadwal
                        </button>
                        <button onclick="bukaTolak(<?php echo $jdw['id']; ?>, '<?php echo addslashes($jdw['nama_siswa']); ?>')" class="btn btn-danger btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-times"></i> Tolak
                        </button>
                    </div>
                    <?php elseif ($jdw['status'] === 'Disetujui'): ?>
                    <div class="action-btns">
                        <button onclick="bukaArsip(<?php echo $jdw['id']; ?>, '<?php echo $jdw['siswa_id']; ?>', '<?php echo addslashes($jdw['topik']); ?>')" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                            <i class="fas fa-archive"></i> Selesaikan & Arsipkan
                        </button>
                    </div>
                    <?php elseif ($jdw['status'] === 'Selesai' || $jdw['status'] === 'Ditolak'): ?>
                    <div class="action-btns">
                        <a href="?hapus=<?php echo $jdw['id']; ?>&filter=<?php echo $filter; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus riwayat bimbingan mandiri ini?')" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px;">
                            <i class="fas fa-trash"></i> Hapus Riwayat
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding:4rem 2rem; color:#94a3b8;">
                    <i class="fas fa-calendar-times fa-4x" style="margin-bottom:1rem; opacity:0.3; color:#64748b;"></i>
                    <h3 style="font-size:1.1rem; font-weight:700; color:#475569; margin-bottom:0.5rem;">Tidak Ada Data Bimbingan</h3>
                    <p style="font-size:0.9rem; margin:0;">Belum ada pengajuan jadwal bimbingan mandiri<?php echo $filter !== 'semua' ? ' dengan status "' . htmlspecialchars($filter) . '"' : ''; ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Konfirmasi Setujui -->
    <div id="modal-setujui" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin:0 0 0.5rem; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-check-circle" style="color:#10b981;"></i> Konfirmasi Jadwal Bimbingan
            </h3>
            <p id="label-setujui" style="font-size:0.875rem; color:#64748b; margin-bottom:1.5rem; line-height:1.4;"></p>
            <form action="" method="POST">
                <input type="hidden" name="jadwal_id" id="input-jadwal-id-setujui">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Tanggal Pertemuan Tatap Muka</label>
                    <input type="date" name="tanggal_pertemuan" id="input-tgl-pertemuan" class="form-control" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Jam Pertemuan</label>
                    <input type="time" name="jam_pertemuan" id="input-jam-pertemuan" class="form-control" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Lokasi Pertemuan</label>
                    <input type="text" name="lokasi" id="input-lokasi-setujui" class="form-control" value="Ruang BK" required placeholder="Contoh: Ruang BK / Perpustakaan" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Pesan / Catatan untuk Siswa (Opsional)</label>
                    <textarea name="catatan_guru" class="form-control" rows="3" placeholder="Tuliskan petunjuk atau pesan untuk siswa..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;"></textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="setujui" class="btn btn-success" style="flex:1; justify-content:center;"><i class="fas fa-check"></i> Setujui & Konfirmasi</button>
                    <button type="button" onclick="document.getElementById('modal-setujui').style.display='none'" class="btn" style="background:#f1f5f9; color:#64748b; flex:1; justify-content:center;">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tolak Pengajuan -->
    <div id="modal-tolak" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin:0 0 0.5rem; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-times-circle" style="color:#ef4444;"></i> Tolak Pengajuan Bimbingan
            </h3>
            <p id="label-tolak" style="font-size:0.875rem; color:#64748b; margin-bottom:1.5rem; line-height:1.4;"></p>
            <form action="" method="POST">
                <input type="hidden" name="jadwal_id" id="input-jadwal-id-tolak">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Alasan Penolakan / Catatan Guru BK</label>
                    <textarea name="catatan_guru" class="form-control" rows="4" required placeholder="Tuliskan alasan penolakan atau waktu alternatif yang disarankan..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;"></textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="tolak" class="btn btn-danger" style="flex:1; justify-content:center;"><i class="fas fa-times"></i> Tolak Pengajuan</button>
                    <button type="button" onclick="document.getElementById('modal-tolak').style.display='none'" class="btn" style="background:#f1f5f9; color:#64748b; flex:1; justify-content:center;">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Arsipkan ke Konseling -->
    <div id="modal-arsip" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin:0 0 0.5rem; font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-archive" style="color:#4361ee;"></i> Selesaikan & Arsipkan
            </h3>
            <p style="font-size:0.875rem; color:#64748b; margin-bottom:1.5rem; line-height:1.4;">Ringkaskan hasil pertemuan bimbingan tatap muka untuk disimpan ke arsip resmi siswa.</p>
            <form action="" method="POST">
                <input type="hidden" name="jadwal_id" id="input-jadwal-id-arsip">
                <input type="hidden" name="siswa_id"  id="input-siswa-id-arsip">
                <input type="hidden" name="topik"     id="input-topik-arsip">
                <input type="hidden" name="filter"    value="<?php echo htmlspecialchars($filter); ?>">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Bidang Bimbingan</label>
                    <select name="bidang_bimbingan" class="form-control" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;">
                        <option value="Pribadi">Pribadi</option>
                        <option value="Sosial">Sosial</option>
                        <option value="Belajar">Belajar</option>
                        <option value="Karir">Karir</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Gambaran Masalah yang Dibahas</label>
                    <textarea name="masalah" class="form-control" rows="3" required placeholder="Ceritakan inti permasalahan siswa..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#334155; margin-bottom:4px;">Hasil / Solusi Bimbingan</label>
                    <textarea name="solusi" class="form-control" rows="4" required placeholder="Apa saran atau hasil akhir bimbingan?" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:8px;"></textarea>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" name="arsipkan" class="btn btn-primary" style="flex:1; justify-content:center;"><i class="fas fa-save"></i> Simpan ke Arsip</button>
                    <button type="button" onclick="document.getElementById('modal-arsip').style.display='none'" class="btn" style="background:#f1f5f9; color:#64748b; flex:1; justify-content:center;">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- BAGIAN SCRIPT JAVASCRIPT: Pengaturan Modal & Interaksi Latar Belakang          -->
    <!-- ============================================================================== -->
    <script>
        // ----------------------------------------------------------------------------
        // FUNGSI 1: Membuka dan Mempersiapkan Modal Konfirmasi (Setujui Jadwal)
        // ----------------------------------------------------------------------------
        function bukaSetujui(id, nama, tglPref) {
            // Menampilkan kotak modal konfirmasi persetujuan bimbingan ke layar (mengubah display dari 'none' ke 'flex')
            document.getElementById('modal-setujui').style.display = 'flex';
            
            // Mengisi ID jadwal yang akan disetujui ke dalam input tersembunyi (hidden input) untuk diproses PHP saat form disubmit
            document.getElementById('input-jadwal-id-setujui').value = id;
            
            // Menampilkan kalimat keterangan interaktif yang mencantumkan nama siswa yang dipanggil
            document.getElementById('label-setujui').textContent = 'Konfirmasi jadwal pertemuan tatap muka dengan ' + nama + '.';
            
            // Jika saat mendaftar siswa tersebut mengisi tanggal preferensi, otomatis pasang tanggal tersebut ke kotak input tanggal
            if (tglPref) {
                document.getElementById('input-tgl-pertemuan').value = tglPref;
            }
            // Mengosongkan kolom waktu/jam pertemuan agar Guru BK wajib mengisi jam pertemuan secara mandiri (tidak otomatis terkonfirmasi)
            document.getElementById('input-jam-pertemuan').value = '';
        }

        // ----------------------------------------------------------------------------
        // FUNGSI 2: Membuka Modal Penolakan Pengajuan Jadwal Bimbingan
        // ----------------------------------------------------------------------------
        function bukaTolak(id, nama) {
            // Memunculkan kotak modal tolak pengajuan ke layar
            document.getElementById('modal-tolak').style.display = 'flex';
            // Memasukkan ID jadwal ke dalam elemen formulir agar sistem tahu pengajuan mana yang sedang ditolak
            document.getElementById('input-jadwal-id-tolak').value = id;
            // Menyematkan teks konfirmasi penolakan dengan nama spesifik siswa
            document.getElementById('label-tolak').textContent = 'Tolak pengajuan bimbingan dari ' + nama + '.';
        }

        // ----------------------------------------------------------------------------
        // FUNGSI 3: Membuka Modal Selesaikan & Simpan ke Arsip Konseling Resmi
        // ----------------------------------------------------------------------------
        function bukaArsip(id, siswaId, topik) {
            // Menampilkan kotak modal arsip (perubahan status menjadi selesai/tertutup)
            document.getElementById('modal-arsip').style.display = 'flex';
            // Mentransmisikan 3 data inti (ID Jadwal, ID Siswa, dan Topik Bimbingan) ke formulir penyegel arsip
            document.getElementById('input-jadwal-id-arsip').value = id;
            document.getElementById('input-siswa-id-arsip').value = siswaId;
            document.getElementById('input-topik-arsip').value = topik;
        }

        // ----------------------------------------------------------------------------
        // FUNGSI 4: Pendeteksi Klik Latar Belakang Hitam (Overlay Backdrop)
        // ----------------------------------------------------------------------------
        // Memasang alatpendengar acara (event listener) untuk memantau setiap ketukan atau klik (click) mouse di jendela browser
        window.addEventListener('click', function(e) {
            // Mengecek apakah elemen yang baru saja diklik adalah 'modal-overlay' (latar belakang gelap yang mengapit kotak putih)
            // Bukan kotak modalnya (modal-box), melainkan bayangannya
            if (e.target && e.target.classList && e.target.classList.contains('modal-overlay')) {
                // Jika yang diklik memang latar belakang gelapnya, otomatis lenyap / sembunyikan modal dari layar (display = 'none')
                e.target.style.display = 'none';
            }
        });

        // Toggle Sidebar (Desktop & Mobile)
        const mobileToggle = document.getElementById('mobile-toggle');
        const sidebar = document.querySelector('.sidebar');
        if (mobileToggle && sidebar) {
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
            mobileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                if (window.innerWidth <= 992) {
                    sidebar.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active', sidebar.classList.contains('active'));
                } else {
                    document.body.classList.toggle('sidebar-closed');
                }
            });
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== mobileToggle && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
