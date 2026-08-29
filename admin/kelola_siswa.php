<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek apakah user sudah login dan memiliki role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Jika bukan admin, tendang ke halaman login
    exit();
}

$error_msg = '';

// Logika Proses Tambah Siswa
if (isset($_POST['tambah'])) {
    $nisn = mysqli_real_escape_string($koneksi, trim($_POST['nisn']));
    $nama = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $kelas_id = mysqli_real_escape_string($koneksi, $_POST['kelas_id']);
    $jk = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    
    // Akun siswa otomatis dibuat menggunakan NISN sebagai username dan password default
    $username = $nisn; 
    $password = password_hash($nisn, PASSWORD_BCRYPT); 

    // Menggunakan Transaction agar data tersimpan di kedua tabel (users & siswa) secara bersamaan
    mysqli_begin_transaction($koneksi);

    try {
        // Cek apakah NISN / Username sudah terdaftar
        $check_user = mysqli_query($koneksi, "SELECT id FROM user WHERE username='$username'");
        if ($check_user && mysqli_num_rows($check_user) > 0) {
            throw new Exception("NISN '$nisn' sudah terdaftar sebagai akun pengguna!");
        }

        $check_siswa = mysqli_query($koneksi, "SELECT id FROM siswa WHERE nisn='$nisn'");
        if ($check_siswa && mysqli_num_rows($check_siswa) > 0) {
            throw new Exception("NISN '$nisn' sudah ada di daftar siswa!");
        }

        // 1. Tambahkan ke tabel user (untuk login)
        $query_user = "INSERT INTO user (username, password, role) VALUES ('$username', '$password', 'siswa')";
        $res_u = mysqli_query($koneksi, $query_user);
        if (!$res_u) {
            throw new Exception("Gagal membuat akun user: " . mysqli_error($koneksi));
        }
        $user_id = mysqli_insert_id($koneksi); // Ambil ID yang baru saja dibuat

        // 2. Tambahkan ke tabel siswa (untuk profil data diri)
        $query_siswa = "INSERT INTO siswa (user_id, nisn, nama_lengkap, kelas_id, jenis_kelamin) 
                        VALUES ('$user_id', '$nisn', '$nama', '$kelas_id', '$jk')";
        $res_s = mysqli_query($koneksi, $query_siswa);
        if (!$res_s) {
            throw new Exception("Gagal membuat data siswa: " . mysqli_error($koneksi));
        }

        // Jika semua query berhasil, simpan permanen
        mysqli_commit($koneksi);
        header("Location: kelola_siswa.php?msg=success_tambah");
        exit();
    } catch (Exception $e) {
        // Jika ada satu saja yang gagal, batalkan semua perubahan
        mysqli_rollback($koneksi);
        $error_msg = $e->getMessage();
    }
}

// Logika Proses Edit Siswa & Profil oleh Admin
if (isset($_POST['edit_siswa'])) {
    $id            = mysqli_real_escape_string($koneksi, $_POST['id']);
    $user_id       = mysqli_real_escape_string($koneksi, $_POST['user_id']);
    $nisn          = mysqli_real_escape_string($koneksi, trim($_POST['nisn']));
    $nama          = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $kelas_id      = !empty($_POST['kelas_id']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['kelas_id']) . "'" : "NULL";
    $jk            = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $tempat_lahir  = mysqli_real_escape_string($koneksi, trim($_POST['tempat_lahir']));
    $tanggal_lahir = !empty($_POST['tanggal_lahir']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']) . "'" : "NULL";
    $alamat        = mysqli_real_escape_string($koneksi, trim($_POST['alamat']));
    $status        = mysqli_real_escape_string($koneksi, $_POST['status']);

    mysqli_begin_transaction($koneksi);

    try {
        // Cek keunikan NISN di tabel siswa jika NISN diubah
        $check_siswa = mysqli_query($koneksi, "SELECT id FROM siswa WHERE nisn='$nisn' AND id != '$id'");
        if ($check_siswa && mysqli_num_rows($check_siswa) > 0) {
            throw new Exception("NISN '$nisn' sudah terdaftar pada siswa lain!");
        }

        // Cek keunikan username di tabel user jika NISN diubah
        $check_user = mysqli_query($koneksi, "SELECT id FROM user WHERE username='$nisn' AND id != '$user_id'");
        if ($check_user && mysqli_num_rows($check_user) > 0) {
            throw new Exception("NISN '$nisn' sudah terdaftar sebagai username pengguna lain!");
        }

        // 1. Update tabel user (sinkronkan username dengan NISN baru)
        $update_u = mysqli_query($koneksi, "UPDATE user SET username='$nisn' WHERE id='$user_id'");
        if (!$update_u) {
            throw new Exception("Gagal memperbarui username akun siswa: " . mysqli_error($koneksi));
        }

        // 2. Update tabel siswa dengan profil lengkap
        $query_update = "UPDATE siswa SET 
                            nisn = '$nisn',
                            nama_lengkap = '$nama',
                            kelas_id = $kelas_id,
                            jenis_kelamin = '$jk',
                            tempat_lahir = '$tempat_lahir',
                            tanggal_lahir = $tanggal_lahir,
                            alamat = '$alamat',
                            status = '$status'
                        WHERE id = '$id'";
        $update_s = mysqli_query($koneksi, $query_update);
        if (!$update_s) {
            throw new Exception("Gagal memperbarui data siswa: " . mysqli_error($koneksi));
        }

        mysqli_commit($koneksi);
        header("Location: kelola_siswa.php?msg=success_edit");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $error_msg = $e->getMessage();
    }
}

// Logika Proses Hapus Siswa
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    $res = mysqli_query($koneksi, "SELECT user_id FROM siswa WHERE id='$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $user_id = $row['user_id'];
        if (mysqli_query($koneksi, "DELETE FROM user WHERE id='$user_id'")) {
            header("Location: kelola_siswa.php?msg=success_hapus");
            exit();
        } else {
            $error_msg = "Gagal menghapus siswa.";
        }
    } else {
        $error_msg = "Data siswa tidak ditemukan.";
    }
}

// Logika Proses Reset Sandi Siswa
if (isset($_GET['reset_sandi'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['reset_sandi']);
    
    $res = mysqli_query($koneksi, "SELECT user_id, nisn FROM siswa WHERE id='$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $user_id = $row['user_id'];
        $nisn = $row['nisn'];
        
        $password_default = password_hash($nisn, PASSWORD_BCRYPT);
        
        if (mysqli_query($koneksi, "UPDATE user SET password='$password_default', email=NULL WHERE id='$user_id'")) {
            header("Location: kelola_siswa.php?msg=success_reset");
            exit();
        } else {
            $error_msg = "Gagal mereset sandi siswa.";
        }
    } else {
        $error_msg = "Data siswa tidak ditemukan.";
    }
}

// Logika Proses Toggle Status Siswa (Aktif <=> Alumni)
if (isset($_GET['toggle_status'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['toggle_status']);
    
    $res = mysqli_query($koneksi, "SELECT status FROM siswa WHERE id='$id'");
    if ($row = mysqli_fetch_assoc($res)) {
        $new_status = ($row['status'] == 'aktif') ? 'alumni' : 'aktif';
        
        if (mysqli_query($koneksi, "UPDATE siswa SET status='$new_status' WHERE id='$id'")) {
            header("Location: kelola_siswa.php?msg=success_status");
            exit();
        } else {
            $error_msg = "Gagal memperbarui status siswa.";
        }
    } else {
        $error_msg = "Data siswa tidak ditemukan.";
    }
}

// Inisialisasi filter pencarian
$search_query = $_GET['search'] ?? '';
$kelas_filter = $_GET['kelas_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$where_clauses = [];
if (!empty($search_query)) {
    $search_safe = mysqli_real_escape_string($koneksi, $search_query);
    $where_clauses[] = "(s.nama_lengkap LIKE '%$search_safe%' OR s.nisn LIKE '%$search_safe%')";
}
if (!empty($kelas_filter)) {
    $kelas_safe = mysqli_real_escape_string($koneksi, $kelas_filter);
    $where_clauses[] = "s.kelas_id = '$kelas_safe'";
}
if (!empty($status_filter)) {
    $status_safe = mysqli_real_escape_string($koneksi, $status_filter);
    $where_clauses[] = "s.status = '$status_safe'";
}
$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Ambil data siswa
$query_siswa = mysqli_query($koneksi, "
    SELECT s.*, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    $where_sql
    ORDER BY s.id DESC
");

// Ambil data kelas untuk dropdown
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa | BK SMA 07 Bungo</title>
    <meta name="description" content="Manajemen data siswa - BK SMA 07 Bungo">
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
            <li><a href="kelola_siswa.php" class="active"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
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
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
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
                    <i class="fas fa-users" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Kelola Siswa</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Manajemen data dan akun seluruh siswa.</p>
                </div>
            </div>
            <button class="btn-tambah-utama" onclick="openModal('modalTambah')" id="btnTambahSiswa">
                <i class="fas fa-plus"></i> Tambah Siswa
            </button>
        </div>

        <?php 
        $m = $_GET['msg'] ?? '';
        if (!empty($m) || !empty($error_msg)): 
        ?>
            <div class="alert <?php echo ($m == 'success_hapus' ? 'alert-delete' : (!empty($error_msg) || strpos($m, 'error') !== false ? 'alert-danger' : 'alert-success')); ?>">
                <i class="fas <?php echo ($m == 'success_hapus' ? 'fa-trash-alt' : (!empty($error_msg) || strpos($m, 'error') !== false ? 'fa-times-circle' : 'fa-check-circle')); ?>"></i>
                <?php 
                    if ($m == 'success_tambah') echo "Data siswa berhasil ditambahkan!";
                    if ($m == 'success_edit') echo "Data profil siswa berhasil diperbarui!";
                    if ($m == 'success_hapus') echo "Data siswa berhasil dihapus!";
                    if ($m == 'success_reset') echo "Sandi siswa berhasil direset ke default (NISN) dan email pemulihan dikosongkan.";
                    if ($m == 'success_status') echo "Status keaktifan siswa berhasil diperbarui!";
                    if ($m == 'error_not_found') echo "Data siswa tidak ditemukan.";
                    if (!empty($error_msg)) echo htmlspecialchars($error_msg);
                ?>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <div class="data-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h2 style="margin: 0;"><i class="fas fa-users"></i> Daftar Siswa</h2>
                <form method="GET" action="" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <select name="status_filter" class="form-control" style="width: auto; padding: 8px 32px 8px 12px; font-size: 0.85rem; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; cursor: pointer;">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="alumni" <?php echo $status_filter == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                    </select>
                    <select name="kelas_filter" class="form-control" style="width: auto; padding: 8px 32px 8px 12px; font-size: 0.85rem; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; cursor: pointer;">
                        <option value="">Semua Kelas</option>
                        <?php if ($query_kelas): mysqli_data_seek($query_kelas, 0); while($k = mysqli_fetch_assoc($query_kelas)): ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo $kelas_filter == $k['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama atau NISN..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 250px; padding: 8px 12px; font-size: 0.85rem; height: 38px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none;">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; height: 38px; display: inline-flex; align-items: center; gap: 5px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($search_query) || !empty($kelas_filter) || !empty($status_filter)): ?>
                        <a href="kelola_siswa.php" class="btn" style="background: #e2e8f0; color: #475569; padding: 8px 16px; font-size: 0.85rem; height: 38px; display: inline-flex; align-items: center; text-decoration: none; border-radius: 8px;">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="table-responsive">
                <table id="tableSiswa">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Lengkap</th>
                            <th>Kelas</th>
                            <th>Jenis Kelamin</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        if ($query_siswa && mysqli_num_rows($query_siswa) > 0):
                            while($row = mysqli_fetch_assoc($query_siswa)): 
                        ?>
                        <tr>
                            <td><span style="color:var(--text-muted);font-size:.8rem;"><?php echo $no++; ?></span></td>
                            <td><span style="font-family:monospace;font-size:.85rem;color:var(--text-accent);"><?php echo htmlspecialchars($row['nisn']); ?></span></td>
                            <td><span style="font-weight:600;color:var(--text-primary);"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span></td>
                            <td>
                                <?php if (!empty($row['nama_kelas'])): ?>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($row['nama_kelas']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['jenis_kelamin'] == 'L'): ?>
                                    <span class="badge badge-info"><i class="fas fa-mars"></i> Laki-laki</span>
                                <?php else: ?>
                                    <span class="badge badge-purple"><i class="fas fa-venus"></i> Perempuan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'aktif'): ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; font-size: 0.72rem; padding: 4px 8px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-graduation-cap"></i> Alumni</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" class="btn btn-warning btn-sm btn-icon" onclick='openEditModal(<?php echo htmlspecialchars(json_encode([
                                        "id" => $row["id"],
                                        "user_id" => $row["user_id"],
                                        "nisn" => $row["nisn"],
                                        "nama_lengkap" => $row["nama_lengkap"],
                                        "kelas_id" => $row["kelas_id"],
                                        "jenis_kelamin" => $row["jenis_kelamin"],
                                        "tempat_lahir" => $row["tempat_lahir"],
                                        "tanggal_lahir" => $row["tanggal_lahir"],
                                        "alamat" => $row["alamat"],
                                        "status" => $row["status"]
                                    ]), ENT_QUOTES, "UTF-8"); ?>)' title="Edit Profil Siswa">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($row['status'] == 'aktif'): ?>
                                        <a href="?toggle_status=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm btn-icon" onclick="return confirm('Tandai siswa ini sebagai Alumni?')" title="Luluskan (Jadikan Alumni)">
                                            <i class="fas fa-graduation-cap"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle_status=<?php echo $row['id']; ?>" class="btn btn-success btn-sm btn-icon" onclick="return confirm('Aktifkan kembali siswa ini?')" title="Aktifkan Siswa">
                                            <i class="fas fa-user-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?reset_sandi=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm btn-icon" onclick="return confirm('Reset sandi siswa ini ke NISN?')" title="Reset Sandi">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Yakin hapus siswa ini?')" title="Hapus">
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
                            <td colspan="7" style="text-align: center; color: #94a3b8; padding: 2rem;">Tidak ada data siswa.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Siswa -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Tambah Siswa Baru</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Password default adalah NISN siswa.</p>
                </div>
                <div class="close" onclick="closeModal('modalTambah')">&#x2715;</div>
            </div>
            <form action="kelola_siswa.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-id-card" style="color: var(--primary);"></i> NISN
                    </label>
                    <input type="text" name="nisn" class="form-control" placeholder="Masukkan NISN" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-user" style="color: var(--primary);"></i> Nama Lengkap
                    </label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan Nama Lengkap" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-school" style="color: var(--primary);"></i> Kelas
                    </label>
                    <select name="kelas_id" class="form-control" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php 
                        if ($query_kelas):
                            mysqli_data_seek($query_kelas, 0);
                            while($k = mysqli_fetch_assoc($query_kelas)): 
                        ?>
                            <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php 
                            endwhile;
                        endif; 
                        ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-venus-mars" style="color: var(--primary);"></i> Jenis Kelamin
                    </label>
                    <select name="jenis_kelamin" class="form-control" required>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Siswa -->
    <div id="modalEdit" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Edit Data & Profil Siswa</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Perbarui NISN, nama lengkap, kelas, tempat/tanggal lahir, alamat & status.</p>
                </div>
                <div class="close" onclick="closeModal('modalEdit')">&#x2715;</div>
            </div>
            <form action="kelola_siswa.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-id-card" style="color: var(--primary);"></i> NISN
                        </label>
                        <input type="text" name="nisn" id="edit_nisn" class="form-control" required style="border-radius:8px;">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-user" style="color: var(--primary);"></i> Nama Lengkap
                        </label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required style="border-radius:8px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-school" style="color: var(--primary);"></i> Kelas
                        </label>
                        <select name="kelas_id" id="edit_kelas_id" class="form-control" style="border-radius:8px;">
                            <option value="">-- Tanpa Kelas --</option>
                            <?php 
                            if ($query_kelas):
                                mysqli_data_seek($query_kelas, 0);
                                while($k = mysqli_fetch_assoc($query_kelas)): 
                            ?>
                                <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kelas']); ?></option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-venus-mars" style="color: var(--primary);"></i> Jenis Kelamin
                        </label>
                        <select name="jenis_kelamin" id="edit_jenis_kelamin" class="form-control" style="border-radius:8px;">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-user-check" style="color: var(--primary);"></i> Status
                        </label>
                        <select name="status" id="edit_status" class="form-control" style="border-radius:8px;">
                            <option value="aktif">Aktif</option>
                            <option value="alumni">Alumni</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> Tempat Lahir
                        </label>
                        <input type="text" name="tempat_lahir" id="edit_tempat_lahir" class="form-control" placeholder="Contoh: Bungo" style="border-radius:8px;">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                            <i class="fas fa-calendar-alt" style="color: var(--primary);"></i> Tanggal Lahir
                        </label>
                        <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" class="form-control" style="border-radius:8px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase;">
                        <i class="fas fa-home" style="color: var(--primary);"></i> Alamat Lengkap
                    </label>
                    <textarea name="alamat" id="edit_alamat" class="form-control" rows="2" placeholder="Masukkan alamat lengkap siswa..." style="border-radius:8px; resize:vertical;"></textarea>
                </div>

                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="edit_siswa" class="btn btn-warning" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            var modal = document.getElementById(id);
            if (modal) modal.style.display = 'flex';
        }

        function closeModal(id) {
            var modal = document.getElementById(id);
            if (modal) modal.style.display = 'none';
        }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id || '';
            document.getElementById('edit_user_id').value = data.user_id || '';
            document.getElementById('edit_nisn').value = data.nisn || '';
            document.getElementById('edit_nama_lengkap').value = data.nama_lengkap || '';
            document.getElementById('edit_kelas_id').value = data.kelas_id || '';
            document.getElementById('edit_jenis_kelamin').value = data.jenis_kelamin || 'L';
            document.getElementById('edit_tempat_lahir').value = data.tempat_lahir || '';
            document.getElementById('edit_tanggal_lahir').value = data.tanggal_lahir || '';
            document.getElementById('edit_alamat').value = data.alamat || '';
            document.getElementById('edit_status').value = data.status || 'aktif';
            openModal('modalEdit');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

    <!-- Script Toggle Menu Mobile & Tabel Responsif -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            const headersLower = headers.map(h => h.toLowerCase());
            if (headersLower.includes('pelanggaran') || headersLower.includes('nisn')) {
                table.classList.add('table-pelanggaran-mobile');
            }

            table.querySelectorAll('tbody tr').forEach(function(row) {
                row.querySelectorAll('td').forEach(function(td, index) {
                    if (headers[index]) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
