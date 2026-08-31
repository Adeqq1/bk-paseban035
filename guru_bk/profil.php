<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek apakah user sudah login dan memiliki role guru_bk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php"); // Jika bukan guru_bk, tendang ke halaman login
    exit();
}

$user_id = $_SESSION['id'];
$success = '';
$error = '';

// Ambil data user saat ini
$query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
$user = mysqli_fetch_assoc($query_user);

// Ambil data guru saat ini
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id='$user_id'");
$guru = mysqli_fetch_assoc($query_guru);

// Proses ketika tombol 'Simpan Perubahan' diklik
if (isset($_POST['update'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    
    // BAGIAN YANG MENGUNCI DATA: Pengecekan if ini mencegah email di-overwrite
    if (!empty($user['email'])) {
        $email = $user['email']; // Paksa gunakan email lama yang sudah terdaftar
    } else {
        $email = mysqli_real_escape_string($koneksi, $_POST['email'] ?? ''); // Simpan email baru
    }

    // Kunci Nama Lengkap & NIP: Hanya Admin yang memiliki wewenang mengubah data ini
    $nama_lengkap = mysqli_real_escape_string($koneksi, $guru['nama_lengkap'] ?? '');
    $nip          = mysqli_real_escape_string($koneksi, $guru['nip'] ?? '');
    $password_baru = $_POST['password_baru'];

    // Update data guru
    $sql_guru = "UPDATE guru SET nama_lengkap='$nama_lengkap', nip='$nip' WHERE user_id='$user_id'";
    $update_guru = mysqli_query($koneksi, $sql_guru);

    // Query dasar untuk memperbarui username dan email pada users
    $sql_user = "UPDATE user SET username='$username', email='$email' WHERE id='$user_id'";
    
    // Jika kolom password baru diisi, maka update juga password-nya
    if (!empty($password_baru)) {
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT); // Hash password untuk keamanan
        $sql_user = "UPDATE user SET username='$username', email='$email', password='$hashed_password' WHERE id='$user_id'";
    }

    $update_user = mysqli_query($koneksi, $sql_user);

    if ($update_guru && $update_user) {
        $_SESSION['username'] = $username; // Update session username
        $success = "Profil dan Sandi berhasil diperbarui!";
        // Ambil ulang data terbaru dari database
        $query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
        $user = mysqli_fetch_assoc($query_user);
        $query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id='$user_id'");
        $guru = mysqli_fetch_assoc($query_guru);
    } else {
        $error = "Gagal memperbarui profil.";
    }
}

// Proses ketika tombol 'Unggah Foto' diklik (atau auto-submit foto)
if (isset($_POST['update_foto'])) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png');
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $error = "Format file tidak diizinkan! Gunakan JPG, JPEG, atau PNG.";
        } elseif ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
            $error = "Ukuran file terlalu besar! Maksimal 5MB.";
        } elseif (!@getimagesize($_FILES['foto']['tmp_name'])) {
            $error = "File yang diunggah bukan gambar yang valid!";
        } else {
            $new_filename = 'guru_bk_' . $user_id . '_' . time() . '.' . $ext;
            $destination = '../assets/uploads/profil/' . $new_filename;
            
            // Ambil foto lama dari database secara langsung
            $q_old = mysqli_query($koneksi, "SELECT foto FROM user WHERE id='$user_id'");
            $d_old = mysqli_fetch_assoc($q_old);
            $old_photo = $d_old['foto'] ?? '';

            // Buat folder uploads/profil jika belum ada
            if (!file_exists('../assets/uploads/profil')) {
                mkdir('../assets/uploads/profil', 0777, true);
            }

            // Bersihkan file yatim/orphan akibat reset database
            $old_files = glob('../assets/uploads/profil/' . 'guru_bk_' . $user_id . '_*.*');
            if ($old_files) {
                foreach ($old_files as $f) {
                    if (is_file($f)) {
                        unlink($f);
                    }
                }
            }
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                // Hapus foto lama jika ada
                if (!empty($old_photo) && file_exists('../assets/uploads/profil/' . $old_photo)) {
                    unlink('../assets/uploads/profil/' . $old_photo);
                }
                
                if (mysqli_query($koneksi, "UPDATE user SET foto='$new_filename' WHERE id='$user_id'")) {
                    $success = "Foto profil berhasil diperbarui!";
                    $_SESSION['foto'] = $new_filename; // Update session
                    // Ambil ulang data terbaru dari database
                    $query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
                    $user = mysqli_fetch_assoc($query_user);
                } else {
                    $error = "Gagal menyimpan data foto ke database.";
                }
            } else {
                $error = "Gagal mengunggah foto ke folder server.";
            }
        }
    } else {
        $error = "Gagal mengunggah file foto.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Ubah Sandi Guru BK | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .profile-container { max-width: 600px; margin: 2rem auto; }
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            transition: 0.3s;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1); }
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
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        
        .profile-avatar-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 600;
            opacity: 0;
            transition: all 0.2s ease-in-out;
            pointer-events: auto;
            border-radius: 50%;
        }
        .profile-avatar-container:hover .profile-avatar-overlay {
            opacity: 1 !important;
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
            <li><a href="kunjungan_rumah.php"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
            <li><a href="rekap_poin.php"><i class="fas fa-chart-line"></i> Rekap Poin</a></li>
            <li><a href="profil.php" class="active"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
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
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: flex-start; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                <i class="fas fa-user-cog" style="font-size: 1.8rem; color: #60a5fa;"></i>
            </div>
            <div style="position: relative; z-index: 1;">
                <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Profil &amp; Ubah Sandi</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Kelola informasi pribadi, foto profil, dan pengaturan keamanan akun Anda.</p>
            </div>
        </div>

        <div class="profile-container">
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-header" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                    <div class="profile-avatar-container" style="width: 100px; height: 100px; border-radius: 50%; position: relative; cursor: pointer; overflow: hidden; border: 4px solid #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <?php if (!empty($user['foto'])): ?>
                            <!-- Jika ada, tampilkan foto profil tersebut -->
                            <img src="../assets/uploads/profil/<?php echo $user['foto']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: linear-gradient(135deg, #2563eb, #d97706); color: white; font-size: 2.5rem;">
                                <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload Overlay -->
                        <div class="profile-avatar-overlay" onclick="document.getElementById('foto-upload').click();" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 5px;">
                            <i class="fas fa-camera" style="font-size: 1.2rem; margin: 0; line-height: 1;"></i>
                            <span style="font-size: 11px; font-weight: 600; line-height: 1.2; text-align: center; display: block; white-space: nowrap; font-family: sans-serif;">Ubah Foto</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:1.15rem;color:#0f172a;"><?php echo $guru['nama_lengkap'] ?? $user['username']; ?></div>
                        <div style="font-size:0.78rem;color:#475569;margin-top:2px;">Guru BK (NIP: <?php echo $guru['nip'] ?? '-'; ?>)</div>
                    </div>
                </div>

                <!-- Form untuk Upload Foto secara terpisah (auto-submit) -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-upload-foto" style="display:none;">
                    <input id="foto-upload" type="file" name="foto" accept="image/jpeg, image/png, image/jpg" onchange="document.getElementById('form-upload-foto').submit();">
                    <input type="hidden" name="update_foto" value="1">
                </form>

                <form action="" method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user-circle" style="margin-right:6px;color:#94a3b8;"></i>Nama Lengkap</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($guru['nama_lengkap'] ?? ''); ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="Nama lengkap hanya dapat diubah oleh Administrator">
                        <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-lock"></i> Nama Lengkap terdaftar dan hanya dapat diubah oleh Administrator.</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-id-badge" style="margin-right:6px;color:#94a3b8;"></i>NIP</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($guru['nip'] ?? ''); ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="NIP hanya dapat diubah oleh Administrator">
                        <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-lock"></i> NIP terdaftar dan hanya dapat diubah oleh Administrator.</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user" style="margin-right:6px;color:#94a3b8;"></i>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo $user['username'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope" style="margin-right:6px;color:#94a3b8;"></i>Email (Untuk Pemulihan Password)</label>
                        <?php if (!empty($user['email'])): ?>
                            <input type="email" class="form-control" value="<?php echo $user['email']; ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="Email pemulihan tidak dapat diubah setelah didaftarkan">
                            <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-check-circle"></i> Email pemulihan telah terdaftar dan tidak dapat diubah.</small>
                        <?php else: ?>
                            <input type="email" name="email" class="form-control" placeholder="Masukkan email aktif" required>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock" style="margin-right:6px;color:#94a3b8;"></i>Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                        <div style="position: relative;">
                            <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Masukkan password baru" style="padding-right: 3rem;">
                            <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6c757d;"></i>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; align-items: center;">
                        <button type="submit" name="update" class="btn-submit" style="flex: 2;"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <a href="index.php" class="btn-cancel" style="flex: 1;"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>

                <script>
                    const togglePassword = document.querySelector('#togglePassword');
                    const password = document.querySelector('#password_baru');

                    togglePassword.addEventListener('click', function (e) {
                        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                        password.setAttribute('type', type);
                        this.classList.toggle('fa-eye-slash');
                    });
                </script>
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
