<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

/*
 * Arsip Siswa (GURU BK VIEW)
 * Halaman ini digunakan oleh Guru BK untuk melihat arsip pelanggaran dan bimbingan 
 * dari siswa yang dipilih secara mendetail berdasarkan semester dan tahun tertentu.
 */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;


// Mengambil seluruh daftar siswa untuk dropdown filter di UI
$query_siswa_list = mysqli_query($koneksi, "SELECT s.id, s.nama_lengkap, s.nisn, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id ORDER BY k.nama_kelas, s.nama_lengkap");

$selected_siswa_id = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : (date('m') >= 7 ? '1' : '2');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$siswa_detail = null;
$res_pelanggaran = null;
$res_bimbingan = null;

if ($selected_siswa_id) {
    // Menarik informasi data pribadi siswa yang dipilih
    $query_sd = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = '$selected_siswa_id'");
    $siswa_detail = mysqli_fetch_assoc($query_sd);

    // Menentukan rentang tanggal filter berdasarkan semester yang dipilih
    if ($semester == '1') {
        $start_date = "$tahun-07-01";
        $end_date = "$tahun-12-31";
    } else {
        $start_date = "$tahun-01-01";
        $end_date = "$tahun-06-30";
    }

    // Mengambil riwayat Pelanggaran siswa pada semester ini
    $res_pelanggaran = mysqli_query($koneksi, "
        SELECT cp.tanggal, jp.nama_pelanggaran, jp.poin, cp.keterangan
        FROM catatan_pelanggaran cp
        JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        WHERE cp.siswa_id = '$selected_siswa_id' AND cp.tanggal BETWEEN '$start_date' AND '$end_date'
        ORDER BY cp.tanggal ASC
    ");

    // Mengambil riwayat Bimbingan siswa pada semester ini
    $res_bimbingan = mysqli_query($koneksi, "
        SELECT k.tanggal, k.masalah, k.solusi, k.jenis_konseling
        FROM konseling k
        LEFT JOIN guru g ON k.guru_id = g.id
        WHERE k.siswa_id = '$selected_siswa_id' AND k.tanggal BETWEEN '$start_date' AND '$end_date'
        ORDER BY k.tanggal ASC
    ");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Konseling Siswa | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .archive-section { margin-top: 2rem; }
        .section-header { 
            background: #f8fafc; 
            padding: 12px 20px; 
            border-radius: 8px; 
            margin-bottom: 1rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border-left: 4px solid var(--primary);
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
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php" class="active"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
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
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-folder-open" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Arsip Konseling &amp; <span style="color: #ef4444;">Pelanggaran</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Lihat dan kelola seluruh riwayat rekam jejak siswa secara detail.</p>
                </div>
            </div>
        </div>

        <div class="data-card" style="margin-bottom: 2rem;">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div style="flex: 2 1 280px; min-width: 200px;">
                    <label>Pilih Siswa</label>
                    <select name="siswa_id" class="form-control" required>
                        <option value="">-- Cari Siswa --</option>
                        <?php mysqli_data_seek($query_siswa_list, 0); while($sl = mysqli_fetch_assoc($query_siswa_list)): ?>
                            <option value="<?php echo $sl['id']; ?>" <?php echo $selected_siswa_id == $sl['id'] ? 'selected' : ''; ?>>
                                [<?php echo $sl['nama_kelas']; ?>] <?php echo $sl['nama_lengkap']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="flex: 1 1 150px; min-width: 120px;">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Ganjil (Jul-Des)</option>
                        <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Genap (Jan-Jun)</option>
                    </select>
                </div>
                <div style="flex: 1 1 120px; min-width: 90px;">
                    <label>Tahun</label>
                    <select name="tahun" class="form-control">
                        <?php for($i=date('Y'); $i>=2023; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $tahun == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="flex: 1 1 140px; height: 42px; justify-content: center; display: inline-flex; align-items: center; margin-bottom: 0;">Lihat Arsip</button>
            </form>
        </div>

        <?php if ($selected_siswa_id && $siswa_detail): ?>
            <div class="data-card">
                <div style="border-bottom: 2px solid #f3f4f6; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1; min-width: 280px; display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 55px; height: 55px; border-radius: 12px; background: rgba(37, 99, 235, 0.1); display: flex; justify-content: center; align-items: center; color: #2563eb; flex-shrink: 0;">
                            <i class="fas fa-user-graduate" style="font-size: 1.75rem;"></i>
                        </div>
                        <div>
                            <h2 style="color: var(--text-primary); margin: 0; font-size: 1.4rem; text-transform: capitalize; font-weight: 800; letter-spacing: -0.02em;">
                                <?php echo htmlspecialchars($siswa_detail['nama_lengkap']); ?>
                            </h2>
                            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 6px; font-size: 0.85rem; color: var(--text-secondary);">
                                <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <i class="fas fa-id-card" style="color: #64748b;"></i>
                                    <span>NISN: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($siswa_detail['nisn']); ?></strong></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                    <i class="fas fa-school" style="color: #64748b;"></i>
                                    <span>Kelas: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($siswa_detail['nama_kelas']); ?></strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="cetak_arsip_siswa.php?siswa_id=<?php echo $selected_siswa_id; ?>&semester=<?php echo $semester; ?>&tahun=<?php echo $tahun; ?>" 
                       target="_blank" class="btn btn-primary">
                        <i class="fas fa-print"></i> Rekap Poin
                    </a>
                </div>

                <!-- TABEL PELANGGARAN -->
                <div class="archive-section">
                    <div class="section-header" style="border-left-color: #ef4444;">
                        <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Riwayat Pelanggaran</h3>
                        <span class="badge badge-danger"><?php echo mysqli_num_rows($res_pelanggaran); ?> Record</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggaran</th>
                                    <th>Keterangan</th>
                                    <th>Poin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($res_pelanggaran) > 0): ?>
                                    <?php while($p = mysqli_fetch_assoc($res_pelanggaran)): ?>
                                        <tr>
                                            <td style="white-space: nowrap;"><?php echo tgl_indo($p['tanggal']); ?></td>
                                            <td><span style="font-weight: 400; color: #334155; font-size: 0.85rem;"><?php echo htmlspecialchars($p['nama_pelanggaran']); ?></span></td>
                                            <td style="max-width: 400px;">
                                                <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.875rem; color: #475569; line-height: 1.5;" title="<?php echo htmlspecialchars($p['keterangan'] ?? ''); ?>">
                                                    <?php echo nl2br(htmlspecialchars($p['keterangan'] ?? '-')); ?>
                                                </div>
                                            </td>
                                            <td><span style="color: #ef4444; font-weight: bold;">+<?php echo $p['poin']; ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center;" class="text-muted">Tidak ada riwayat pelanggaran.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TABEL BIMBINGAN -->
                <div class="archive-section">
                    <div class="section-header" style="border-left-color: #10b981;">
                        <h3><i class="fas fa-hands-helping" style="color: #10b981;"></i> Riwayat Bimbingan & Konseling</h3>
                        <span class="badge badge-success"><?php echo mysqli_num_rows($res_bimbingan); ?> Sesi</span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis Layanan</th>
                                    <th>Masalah / Topik</th>
                                    <th>Hasil / Solusi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($res_bimbingan) > 0): ?>
                                    <?php while($b = mysqli_fetch_assoc($res_bimbingan)): ?>
                                        <tr>
                                            <td style="white-space: nowrap;"><?php echo tgl_indo($b['tanggal']); ?></td>
                                            <td><span class="badge <?php echo $b['jenis_konseling'] == 'Mandiri' ? 'badge-info' : 'badge-warning'; ?>"><?php echo $b['jenis_konseling'] == 'Tindak Lanjut' ? 'Konferensi Kasus' : 'Konseling Individu'; ?></span></td>
                                            <td style="max-width: 350px;">
                                                <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.875rem; color: #334155; line-height: 1.5;" title="<?php echo htmlspecialchars($b['masalah']); ?>">
                                                    <?php echo nl2br(htmlspecialchars($b['masalah'])); ?>
                                                </div>
                                            </td>
                                            <td style="max-width: 300px;">
                                                <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; font-size: 0.875rem; color: #475569; line-height: 1.5;" title="<?php echo htmlspecialchars($b['solusi'] ?? ''); ?>">
                                                    <?php if(!empty($b['solusi'])): ?>
                                                        <?php echo nl2br(htmlspecialchars($b['solusi'])); ?>
                                                    <?php else: ?>
                                                        <span style="color:#94a3b8; font-style:italic;">Belum ada hasil terdokumentasi</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center;" class="text-muted">Tidak ada riwayat bimbingan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="data-card" style="text-align: center; color: #9ca3af; padding: 4rem;">
                <i class="fas fa-search fa-3x" style="margin-bottom: 1rem;"></i>
                <p>Silakan pilih siswa dan periode untuk melihat arsip terpisah.</p>
            </div>
        <?php endif; ?>
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
