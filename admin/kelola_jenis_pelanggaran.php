<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// =========================================================================
// 1. CEK OTENTIKASI SISTEM & HAK AKSES USER (ROLE ADMIN)
// Mengamankan halaman agar hanya pengguna dengan peran 'admin' yang bisa mengakses.
// =========================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Jika bukan admin, alihkan ke login
    exit();
}

// =========================================================================
// 2. PROSES TAMBAH JENIS PELANGGARAN BARU
// Menambahkan master data Jenis Pelanggaran beserta bobot poin dan kategorinya.
// =========================================================================
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_pelanggaran']);
    $nama_escaped = mysqli_real_escape_string($koneksi, $nama);
    $poin = mysqli_real_escape_string($koneksi, $_POST['poin']);
    $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);

    $cek = mysqli_query($koneksi, "SELECT id FROM jenis_pelanggaran WHERE LOWER(TRIM(nama_pelanggaran)) = LOWER(TRIM('$nama_escaped'))");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $msg = "error_duplikat";
    } else {
        try {
            $query = "INSERT INTO jenis_pelanggaran (nama_pelanggaran, poin, kategori) VALUES ('$nama_escaped', '$poin', '$kategori')";
            if (mysqli_query($koneksi, $query)) {
                $msg = "success_tambah";
            } else {
                $msg = "error";
            }
        } catch (Throwable $e) {
            $msg = "error";
        }
    }
}

// =========================================================================
// 3. PROSES EDIT / PERBARUI DATA JENIS PELANGGARAN
// Mengubah nama pelanggaran, bobot poin, atau tingkat kategori.
// =========================================================================
if (isset($_POST['edit'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $nama = trim($_POST['nama_pelanggaran']);
    $nama_escaped = mysqli_real_escape_string($koneksi, $nama);
    $poin = mysqli_real_escape_string($koneksi, $_POST['poin']);
    $kategori = mysqli_real_escape_string($koneksi, $_POST['kategori']);

    $cek = mysqli_query($koneksi, "SELECT id FROM jenis_pelanggaran WHERE LOWER(TRIM(nama_pelanggaran)) = LOWER(TRIM('$nama_escaped')) AND id != '$id'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $msg = "error_duplikat";
    } else {
        try {
            $query = "UPDATE jenis_pelanggaran SET nama_pelanggaran='$nama_escaped', poin='$poin', kategori='$kategori' WHERE id='$id'";
            if (mysqli_query($koneksi, $query)) {
                $msg = "success_edit";
            } else {
                $msg = "error";
            }
        } catch (Throwable $e) {
            $msg = "error";
        }
    }
}

// =========================================================================
// 4. PROSES HAPUS JENIS PELANGGARAN
// Menghapus jenis pelanggaran jika tidak sedang digunakan oleh catatan siswa.
// =========================================================================
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    try {
        if (mysqli_query($koneksi, "DELETE FROM jenis_pelanggaran WHERE id='$id'")) {
            $msg = "success_hapus";
        } else {
            $msg = "error_hapus";
        }
    } catch (Throwable $e) {
        $msg = "error_hapus";
    }
}

// =========================================================================
// 5. QUERY PENAMPILAN DATA & FILTER PENCARIAN / KATEGORI
// Mengambil data jenis pelanggaran dengan dukungan filter tab kategori & kata kunci.
// =========================================================================
$current_kategori = $_GET['kategori_filter'] ?? 'semua';
$search_query = $_GET['search'] ?? '';

$where_clauses = [];
if ($current_kategori !== 'semua') {
    $where_clauses[] = "kategori = '" . mysqli_real_escape_string($koneksi, $current_kategori) . "'";
}
if (!empty($search_query)) {
    $where_clauses[] = "nama_pelanggaran LIKE '%" . mysqli_real_escape_string($koneksi, $search_query) . "%'";
}

$where_clause = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Jalankan Query SQL untuk menarik data jenis pelanggaran
$query_jenis = mysqli_query($koneksi, "SELECT * FROM jenis_pelanggaran $where_clause ORDER BY kategori DESC, poin ASC");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jenis Pelanggaran | BK SMA 07 Bungo</title>
    <meta name="description" content="Manajemen Jenis Pelanggaran - BK SMA 07 Bungo">
    <!-- File CSS Utama Admin & CDN Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- =================================================================== -->
    <!-- NAVIGATION SIDEBAR UTAMA (PANEL ADMIN)                              -->
    <!-- =================================================================== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="kelola_siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
            <li><a href="kelola_guru.php"><i class="fas fa-chalkboard-teacher"></i> Kelola Wali Kelas</a></li>
            <li><a href="kelola_kelas.php"><i class="fas fa-school"></i> Kelola Kelas</a></li>
        </ul>
        <div class="sidebar-label">Data & Laporan</div>
        <ul class="sidebar-menu">
            <li><a href="kelola_jenis_pelanggaran.php" class="active"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
            <li><a href="pelanggaran.php"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil Admin</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($_SESSION['username'] ?? 'Admin', 'A'); ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- KONTEN UTAMA HALAMAN KELOLA JENIS PELANGGARAN                       -->
    <!-- =================================================================== -->
    <div class="main-content">
        <!-- Banner Header Halaman -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-list-ul" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Kelola Jenis Pelanggaran</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Daftar jenis dan kategori tingkat poin pelanggaran siswa.</p>
                </div>
            </div>
            <!-- Tombol Pemicu Modal Tambah Jenis Baru -->
            <button class="btn-tambah-utama" onclick="openModal('modalTambah')" id="btnTambahJenis" style="position: relative; z-index: 1;">
                <i class="fas fa-plus"></i> Tambah Jenis Baru
            </button>
        </div>

        <!-- Blok Notifikasi Umpan Balik (Alert Message) -->
        <?php if (isset($msg)): ?>
            <div class="alert <?php echo $msg == 'success_hapus' ? 'alert-delete' : (strpos($msg, 'success') !== false ? 'alert-success' : 'alert-danger'); ?>">
                <i class="fas <?php echo $msg == 'success_hapus' ? 'fa-trash-alt' : (strpos($msg, 'success') !== false ? 'fa-check-circle' : 'fa-times-circle'); ?>"></i>
                <div>
                    <?php 
                        if ($msg == 'success_tambah') echo "Jenis Pelanggaran berhasil ditambahkan!";
                        if ($msg == 'success_edit') echo "Jenis Pelanggaran berhasil diperbarui!";
                        if ($msg == 'success_hapus') echo "Jenis Pelanggaran berhasil dihapus!";
                        if ($msg == 'error_duplikat') echo "Nama jenis pelanggaran tersebut sudah terdaftar di sistem!";
                        if ($msg == 'error') echo "Terjadi kesalahan sistem.";
                        if ($msg == 'error_hapus') echo "Tidak dapat menghapus. Jenis ini mungkin sedang digunakan dalam catatan pelanggaran.";
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Kartu Tabel Data Jenis Pelanggaran & Filter Kategori -->
        <div class="data-card">
            <!-- Filter Kategori & Form Pencarian -->
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 1.1rem 1.5rem; border-bottom: 1px solid #f1f5f9;">
                <!-- Tab Kategori Filter -->
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <?php
                    $tabs = [
                        'semua'  => ['label' => 'Semua Pelanggaran', 'active_bg' => 'var(--primary)', 'shadow' => 'rgba(59,123,191,0.25)'],
                        'Ringan' => ['label' => 'Ringan',            'active_bg' => '#10b981',        'shadow' => 'rgba(16,185,129,0.25)'],
                        'Sedang' => ['label' => 'Sedang',            'active_bg' => '#f59e0b',        'shadow' => 'rgba(245,158,11,0.25)'],
                        'Berat'  => ['label' => 'Berat',             'active_bg' => '#ef4444',        'shadow' => 'rgba(239,68,68,0.25)'],
                    ];
                    foreach ($tabs as $key => $tab):
                        $is_active = $current_kategori === $key;
                        $style = $is_active
                            ? "background:{$tab['active_bg']}; color:#fff; border:1px solid {$tab['active_bg']}; box-shadow: 0 4px 10px {$tab['shadow']};"
                            : 'background:#fff; color:#64748b; border:1px solid #e2e8f0;';
                    ?>
                    <a href="?kategori_filter=<?php echo $key; ?>&search=<?php echo urlencode($search_query); ?>"
                       style="<?php echo $style; ?> padding:6px 16px; border-radius:8px; font-weight:600; font-size:0.82rem; text-decoration:none; transition:all 0.2s;">
                        <?php echo $tab['label']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Form Filter Pencarian -->
                <form method="GET" action="" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <input type="hidden" name="kategori_filter" value="<?php echo htmlspecialchars($current_kategori); ?>">
                    <input type="text" name="search" placeholder="Cari jenis pelanggaran..."
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           style="padding: 0.55rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; width: 280px; outline: none; font-family: inherit; color: #0f172a;">
                    <button type="submit" class="btn btn-primary"
                            style="padding: 0.55rem 1rem; border: none; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($search_query)): ?>
                    <a href="?kategori_filter=<?php echo htmlspecialchars($current_kategori); ?>"
                       style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; padding:0.55rem 1rem; font-size:0.85rem; font-weight:600; border-radius:8px; text-decoration:none; display:flex; align-items:center;">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabel Data Jenis Pelanggaran -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 75px; text-align: center;">No</th>
                            <th>Nama Pelanggaran</th>
                            <th style="text-align: center;">Poin</th>
                            <th style="text-align: center;">Kategori</th>
                            <th style="width: 140px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if (mysqli_num_rows($query_jenis) > 0) {
                            while ($row = mysqli_fetch_assoc($query_jenis)):
                        ?>
                        <tr>
                            <!-- Kolom 1: Nomor Urut Data -->
                            <td style="text-align: center; color: #64748b; font-weight: 400;"><?php echo $no++; ?></td>

                            <!-- Kolom 2: Nama Pelanggaran -->
                            <td style="padding-top: 0.85rem; padding-bottom: 0.85rem;">
                                <span style="font-size: 0.875rem; color: #334155; font-weight: 400; line-height: 1.5;"><?php echo htmlspecialchars($row['nama_pelanggaran']); ?></span>
                            </td>

                            <!-- Kolom 3: Poin Pelanggaran -->
                            <td style="text-align: center;">
                                <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                    +<?php echo $row['poin']; ?> Poin
                                </span>
                            </td>

                            <!-- Kolom 4: Kategori Pelanggaran -->
                            <td style="text-align: center;">
                                <?php
                                    $badge_class = 'badge-info';
                                    if($row['kategori'] == 'Sedang') $badge_class = 'badge-warning';
                                    if($row['kategori'] == 'Berat') $badge_class = 'badge-danger';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['kategori']); ?></span>
                            </td>

                            <!-- Kolom 5: Tombol Aksi (Edit & Hapus) -->
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button class="btn btn-warning btn-sm btn-icon" onclick='editJenis(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="Edit Jenis Pelanggaran">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Apakah Anda yakin ingin menghapus Jenis Pelanggaran ini?')" title="Hapus Jenis Pelanggaran">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding:2rem; color:#94a3b8;'>Data jenis pelanggaran tidak ditemukan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DIALOG POPUP: TAMBAH JENIS PELANGGARAN                        -->
    <!-- =================================================================== -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Tambah Jenis Pelanggaran</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Tentukan nama, poin, dan kategori pelanggaran.</p>
                </div>
                <div class="close" onclick="closeModal('modalTambah')">&#x2715;</div>
            </div>
            <form action="kelola_jenis_pelanggaran.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-exclamation-circle" style="color: var(--primary);"></i> Nama Pelanggaran
                    </label>
                    <input type="text" name="nama_pelanggaran" class="form-control" placeholder="Contoh: Merokok di sekolah" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-star" style="color: var(--primary);"></i> Poin
                    </label>
                    <input type="number" name="poin" class="form-control" placeholder="Masukkan jumlah poin" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-layer-group" style="color: var(--primary);"></i> Kategori
                    </label>
                    <select name="kategori" class="form-control" required>
                        <option value="Ringan">Ringan</option>
                        <option value="Sedang">Sedang</option>
                        <option value="Berat">Berat</option>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Jenis</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- MODAL DIALOG POPUP: EDIT JENIS PELANGGARAN                          -->
    <!-- =================================================================== -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Edit Jenis Pelanggaran</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Perbarui informasi jenis pelanggaran.</p>
                </div>
                <div class="close" onclick="closeModal('modalEdit')">&#x2715;</div>
            </div>
            <form action="kelola_jenis_pelanggaran.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-exclamation-circle" style="color: var(--primary);"></i> Nama Pelanggaran
                    </label>
                    <input type="text" name="nama_pelanggaran" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-star" style="color: var(--primary);"></i> Poin
                    </label>
                    <input type="number" name="poin" id="edit_poin" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-layer-group" style="color: var(--primary);"></i> Kategori
                    </label>
                    <select name="kategori" id="edit_kategori" class="form-control" required>
                        <option value="Ringan">Ringan</option>
                        <option value="Sedang">Sedang</option>
                        <option value="Berat">Berat</option>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- SCRIPT JAVASCRIPT KONTROL POPUP MODAL DIALOG                        -->
    <!-- =================================================================== -->
    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function editJenis(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama').value = data.nama_pelanggaran;
            document.getElementById('edit_poin').value = data.poin;
            document.getElementById('edit_kategori').value = data.kategori;
            openModal('modalEdit');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

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
