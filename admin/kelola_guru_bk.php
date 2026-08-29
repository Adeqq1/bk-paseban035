<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek apakah user sudah login dan memiliki role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Jika bukan admin, tendang ke halaman login
    exit();
}

// Logika Proses Tambah Guru BK
if (isset($_POST['tambah'])) {
    $nip = mysqli_real_escape_string($koneksi, $_POST['nip']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $jabatan = 'Guru BK';
    $role = 'guru_bk';
    $username = $nip; // Username default menggunakan NIP
    $password = password_hash($nip, PASSWORD_BCRYPT); // Password default menggunakan NIP

    // Cek apakah NIP sudah terdaftar
    $cek_nip = mysqli_query($koneksi, "SELECT nip FROM guru WHERE nip='$nip'");
    $cek_user = mysqli_query($koneksi, "SELECT username FROM user WHERE username='$username'");

    if (mysqli_num_rows($cek_nip) > 0 || mysqli_num_rows($cek_user) > 0) {
        $msg = "error_duplikat";
    } else {
        // Menggunakan Transaction agar data tersimpan di tabel users dan guru secara atomik
        mysqli_begin_transaction($koneksi);
        try {
            // 1. Tambahkan akun ke tabel users
            $query_user = "INSERT INTO user (username, password, role) VALUES ('$username', '$password', '$role')";
            $insert_user = mysqli_query($koneksi, $query_user);
            if (!$insert_user) throw new Exception("Gagal simpan user");
            
            $user_id = mysqli_insert_id($koneksi); // Ambil ID user yang baru dibuat

            // 2. Tambahkan data profil ke tabel guru
            $query_guru = "INSERT INTO guru (user_id, nip, nama_lengkap, jabatan) VALUES ('$user_id', '$nip', '$nama', '$jabatan')";
            $insert_guru = mysqli_query($koneksi, $query_guru);
            if (!$insert_guru) throw new Exception("Gagal simpan profil");

            mysqli_commit($koneksi); // Simpan semua perubahan
            $msg = "success_tambah";
        } catch (Exception $e) {
            mysqli_rollback($koneksi); // Batalkan jika ada yang gagal
            $msg = "error";
        }
    }
}

// Proses Edit Guru BK
if (isset($_POST['edit'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $nip = mysqli_real_escape_string($koneksi, $_POST['nip']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    
    $res = mysqli_query($koneksi, "SELECT user_id FROM guru WHERE id='$id'");
    $row = mysqli_fetch_assoc($res);
    $user_id = $row['user_id'];

    mysqli_begin_transaction($koneksi);
    try {
        mysqli_query($koneksi, "UPDATE user SET username='$nip' WHERE id='$user_id'");
        mysqli_query($koneksi, "UPDATE guru SET nip='$nip', nama_lengkap='$nama' WHERE id='$id'");
        mysqli_commit($koneksi);
        $msg = "success_edit";
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $msg = "error";
    }
}

// Proses Hapus Guru BK
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    $res = mysqli_query($koneksi, "SELECT user_id FROM guru WHERE id='$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $user_id = $row['user_id'];
        if (mysqli_query($koneksi, "DELETE FROM user WHERE id='$user_id'")) {
            $msg = "success_hapus";
        } else {
            $msg = "error";
        }
    }
}

// Proses Reset Sandi Guru BK
if (isset($_GET['reset_sandi'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['reset_sandi']);
    $res = mysqli_query($koneksi, "SELECT user_id, nip FROM guru WHERE id='$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $user_id = $row['user_id'];
        $nip = $row['nip'];
        
        // Reset password ke NIP
        $password_default = password_hash($nip, PASSWORD_BCRYPT);
        
        if (mysqli_query($koneksi, "UPDATE user SET username='$nip', password='$password_default', email=NULL WHERE id='$user_id'")) {
            $msg = "success_reset";
        } else {
            $msg = "error";
        }
    }
}

// Inisialisasi filter pencarian
$search_query = $_GET['search'] ?? '';
$where_sql = "WHERE jabatan='Guru BK'";
if (!empty($search_query)) {
    $search_safe = mysqli_real_escape_string($koneksi, $search_query);
    $where_sql .= " AND (nama_lengkap LIKE '%$search_safe%' OR nip LIKE '%$search_safe%')";
}

// Ambil data guru BK
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru $where_sql ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Guru BK | BK SMA 07 Bungo</title>
    <meta name="description" content="Manajemen data Guru Bimbingan Konseling - BK SMA 07 Bungo">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="kelola_siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php" class="active"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
            <li><a href="kelola_guru.php"><i class="fas fa-chalkboard-teacher"></i> Kelola Wali Kelas</a></li>
            <li><a href="kelola_kelas.php"><i class="fas fa-school"></i> Kelola Kelas</a></li>
        </ul>
        <div class="sidebar-label">Data & Laporan</div>
        <ul class="sidebar-menu">
            <li><a href="kelola_jenis_pelanggaran.php"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
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
                <!-- Mengecek apakah pengguna memiliki foto profil yang tersimpan di sistem -->
                <?php if (!empty($_SESSION['foto']) && file_exists('../assets/uploads/profil/' . $_SESSION['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $_SESSION['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-user-shield" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Kelola Guru BK</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Manajemen data dan akun Guru Bimbingan Konseling.</p>
                </div>
            </div>
            <button class="btn-tambah-utama" onclick="openModal('modalTambah')" id="btnTambahGuru" style="position: relative; z-index: 1;">
                <i class="fas fa-plus"></i> Tambah Guru BK
            </button>
        </div>

        <?php if (isset($msg)): ?>
            <div class="alert <?php echo $msg == 'success_hapus' ? 'alert-delete' : (strpos($msg, 'success') !== false ? 'alert-success' : 'alert-danger'); ?>">
                <i class="fas <?php echo $msg == 'success_hapus' ? 'fa-trash-alt' : (strpos($msg, 'success') !== false ? 'fa-check-circle' : 'fa-times-circle'); ?>"></i>
                <?php 
                    if ($msg == 'success_tambah') echo "Data Guru BK berhasil ditambahkan!";
                    if ($msg == 'success_edit') echo "Data Guru BK berhasil diperbarui!";
                    if ($msg == 'success_hapus') echo "Data Guru BK berhasil dihapus!";
                    if ($msg == 'success_reset') echo "Akun (Username & Sandi) berhasil direset ke NIP, dan email pemulihan dikosongkan.";
                    if ($msg == 'error_duplikat') echo "NIP tersebut sudah terdaftar di sistem!";
                    if ($msg == 'error') echo "Terjadi kesalahan sistem.";
                ?>
            </div>
        <?php endif; ?>
        <div class="data-card">
            <div class="data-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h2 style="margin: 0;"><i class="fas fa-user-shield"></i> Daftar Guru BK</h2>
                <form method="GET" action="" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama atau NIP..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 250px; padding: 8px 12px; font-size: 0.85rem; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none;">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; height: 38px; display: inline-flex; align-items: center; gap: 5px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($search_query)): ?>
                        <a href="kelola_guru_bk.php" class="btn" style="background: #e2e8f0; color: #475569; padding: 8px 16px; font-size: 0.85rem; height: 38px; display: inline-flex; align-items: center; text-decoration: none; border-radius: 8px;">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Nama Lengkap</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($query_guru)): ?>
                        <tr>
                            <td><span style="color:var(--text-muted);font-size:.8rem;"><?php echo $no++; ?></span></td>
                            <td><span style="font-family:monospace;font-size:.85rem;color:var(--text-accent);"><?php echo $row['nip']; ?></span></td>
                            <td><span style="font-weight:600;color:var(--text-primary);"><?php echo $row['nama_lengkap']; ?></span></td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <button class="btn btn-warning btn-sm btn-icon" onclick='editGuru(<?php echo json_encode($row); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?reset_sandi=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm btn-icon" onclick="return confirm('Reset sandi Guru BK ini ke NIP?')" title="Reset Sandi">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Hapus Guru BK ini?')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Tambah Guru BK Baru</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Username & Password default adalah NIP.</p>
                </div>
                <div class="close" onclick="closeModal('modalTambah')">&#x2715;</div>
            </div>
            <form action="kelola_guru_bk.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-id-badge" style="color: var(--primary);"></i> NIP
                    </label>
                    <input type="text" name="nip" class="form-control" placeholder="Masukkan NIP" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-user" style="color: var(--primary);"></i> Nama Lengkap
                    </label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan Nama Lengkap" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-briefcase" style="color: var(--primary);"></i> Jabatan
                    </label>
                    <input type="text" name="jabatan" class="form-control" value="Guru BK" readonly style="background-color: #f1f5f9; cursor: not-allowed;">
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Edit Data Guru BK</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Perbarui informasi Guru BK.</p>
                </div>
                <div class="close" onclick="closeModal('modalEdit')">&#x2715;</div>
            </div>
            <form action="kelola_guru_bk.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-id-badge" style="color: var(--primary);"></i> NIP
                    </label>
                    <input type="text" name="nip" id="edit_nip" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-user" style="color: var(--primary);"></i> Nama Lengkap
                    </label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-briefcase" style="color: var(--primary);"></i> Jabatan
                    </label>
                    <input type="text" name="jabatan" id="edit_jabatan" class="form-control" value="Guru BK" readonly style="background-color: #f1f5f9; cursor: not-allowed;">
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fungsi untuk menampilkan modal pop-up secara interaktif (bergerak tampil)
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        
        // Fungsi untuk menyembunyikan modal pop-up dari layar
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        // Fungsi untuk memindahkan data guru BK ke dalam form edit secara dinamis saat tombol edit diklik
        function editGuru(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nip').value = data.nip;
            document.getElementById('edit_nama').value = data.nama_lengkap;
            document.getElementById('edit_jabatan').value = data.jabatan || 'Guru BK';
            openModal('modalEdit'); // Membuka modal edit
        }

        // Menutup modal jika pengguna mengklik di luar area modal
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

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


