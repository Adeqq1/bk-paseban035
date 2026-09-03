<?php
/**
 * ====================================================================================
 * MODUL PENGATURAN PROFIL & KATA SANDI - WALI KELAS (BK SMA 07 Bungo SMAN 7 BUNGO)
 * ====================================================================================
 * Halaman ini digunakan oleh Wali Kelas untuk mengelola informasi pribadi akun (username,
 * email pemulihan), mengubah foto profil, serta memperbarui kata sandi (password) baru.
 */

// 1. Memulai sesi PHP untuk mengakses data login pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN (SECURITY CHECK): Memastikan pengguna berstatus 'wali_kelas'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'wali_kelas') {
    header("Location: ../index.php");
    exit();
}

// 4. MENGAMBIL DATA PROFIL AKUN WALI KELAS
$user_id = $_SESSION['id'];
$success = '';
$error   = '';

$query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
$user       = mysqli_fetch_assoc($query_user);

$query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id='$user_id'");
$guru       = mysqli_fetch_assoc($query_guru);
$guru_id    = $guru['id'] ?? 0;
$query_kelas = mysqli_query($koneksi, "SELECT * FROM kelas WHERE wali_kelas_id = '$guru_id'");
$kelas       = mysqli_fetch_assoc($query_kelas);

$nama_guru = ucwords(strtolower($guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'Wali Kelas'));
$nama_guru = preg_replace('/,?\s*s\.?pd\.?/i', ', S.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*m\.?pd\.?/i', ', M.Pd.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?kom\.?/i', ', S.Kom.', $nama_guru);
$nama_guru = preg_replace('/,?\s*s\.?ag\.?/i', ', S.Ag.', $nama_guru);
$nama_guru = str_replace([',,', '..'], [',', '.'], $nama_guru);

// Helper fungsi upload foto profil Wali Kelas yang aman dan reliabel
function uploadFotoProfilWaliKelas($file, $user_id, $koneksi, $foto_lama = '') {
    $allowed  = array('jpg', 'jpeg', 'png', 'webp');
    $filename = $file['name'];
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'error' => 'Format file tidak diizinkan! Gunakan format JPG, JPEG, PNG, atau WEBP.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Ukuran file terlalu besar! Maksimal 10MB.'];
    }
    if (!@getimagesize($file['tmp_name'])) {
        return ['success' => false, 'error' => 'File yang dipilih bukan gambar yang valid!'];
    }

    $upload_dir = __DIR__ . '/../assets/uploads/profil/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    $new_filename = 'wk_' . $user_id . '_' . time() . '.' . $ext;
    $destination  = $upload_dir . $new_filename;

    // Bersihkan file lama untuk user ini
    $old_files = glob($upload_dir . 'wk_' . $user_id . '_*.*');
    if ($old_files) {
        foreach ($old_files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        @chmod($destination, 0664);
        if (!empty($foto_lama) && file_exists($upload_dir . $foto_lama) && $foto_lama !== $new_filename) {
            @unlink($upload_dir . $foto_lama);
        }
        
        if (mysqli_query($koneksi, "UPDATE user SET foto='$new_filename' WHERE id='$user_id'")) {
            $_SESSION['foto'] = $new_filename;
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'error' => 'Gagal memperbarui database foto: ' . mysqli_error($koneksi)];
        }
    } else {
        return ['success' => false, 'error' => 'Gagal mengunggah foto ke folder server.'];
    }
}

// 5. PROSES UPDATE KREDENSIAL AKUN (USERNAME, EMAIL, PASSWORD)
if (isset($_POST['update'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);

    // Penguncian Keamanan Email Pemulihan: Jika email sudah ada di database, kunci permanently
    if (!empty($user['email'])) {
        $email = $user['email'];
    } else {
        $email = mysqli_real_escape_string($koneksi, $_POST['email'] ?? '');
    }

    // Nama Lengkap dan NIP dikunci (Hanya Administrator yang dapat mengubahnya)
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $guru['nama_lengkap'] ?? '');
    $nip           = mysqli_real_escape_string($koneksi, $guru['nip'] ?? '');
    $password_baru = $_POST['password_baru'];

    // Update data tabel guru
    $sql_guru = "UPDATE guru SET nama_lengkap='$nama_lengkap', nip='$nip' WHERE user_id='$user_id'";
    $update_guru = mysqli_query($koneksi, $sql_guru);

    // Update data tabel user (Username, Email, & Password jika diisi)
    $sql_user = "UPDATE user SET username='$username', email='$email' WHERE id='$user_id'";
    if (!empty($password_baru)) {
        $hashed   = password_hash($password_baru, PASSWORD_DEFAULT);
        $sql_user = "UPDATE user SET username='$username', email='$email', password='$hashed' WHERE id='$user_id'";
    }
    $update_user = mysqli_query($koneksi, $sql_user);

    if ($update_user) {
        $_SESSION['username'] = $username;
        $success = "Profil dan Sandi berhasil diperbarui!";
        
        // Cek jika ada foto yang disertakan saat klik Simpan Perubahan
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $res = uploadFotoProfilWaliKelas($_FILES['foto'], $user_id, $koneksi, $user['foto'] ?? '');
            if ($res['success']) {
                $success = "Profil, sandi, dan foto berhasil diperbarui!";
            } else {
                $error = $res['error'];
            }
        }
        
        // Refresh data terbaru dari database
        $query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
        $user       = mysqli_fetch_assoc($query_user);
        $query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id='$user_id'");
        $guru       = mysqli_fetch_assoc($query_guru);
    } else {
        $error = "Gagal memperbarui data profil: " . mysqli_error($koneksi);
    }
}

// 6. PROSES UNGGAH (UPLOAD) FOTO PROFIL TERPISAH
if (isset($_POST['update_foto'])) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $res = uploadFotoProfilWaliKelas($_FILES['foto'], $user_id, $koneksi, $user['foto'] ?? '');
        if ($res['success']) {
            $success = "Foto profil berhasil diperbarui!";
            $query_user = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
            $user       = mysqli_fetch_assoc($query_user);
            $query_guru = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id='$user_id'");
            $guru       = mysqli_fetch_assoc($query_guru);
        } else {
            $error = $res['error'];
        }
    } else {
        $upload_err = $_FILES['foto']['error'] ?? -1;
        if ($upload_err === UPLOAD_ERR_INI_SIZE || $upload_err === UPLOAD_ERR_FORM_SIZE) {
            $error = "Ukuran file terlalu besar melebihi batas server!";
        } elseif ($upload_err === UPLOAD_ERR_NO_FILE) {
            $error = "Silakan pilih file foto terlebih dahulu sebelum menyimpan!";
        } else {
            $error = "Terjadi kesalahan saat mengunggah foto (Kode Error: $upload_err).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Ubah Sandi Wali Kelas | BK SMA 07 Bungo</title>
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
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        .form-card h2 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.75rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-control[readonly] {
            background-color: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
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
        }
        
        .profile-avatar-container {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 1.5rem auto;
        }
        .profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .profile-avatar-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            opacity: 0;
            transition: opacity 0.2s ease;
            cursor: pointer;
        }
        .profile-avatar-container:hover .profile-avatar-overlay { opacity: 1 !important; }
    </style>
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR NAVIGASI WALI KELAS -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Wali Kelas Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="siswa_perwalian.php"><i class="fas fa-users"></i> Siswa Perwalian</a></li>
            <li><a href="form_lapor.php"><i class="fas fa-bullhorn"></i> Lapor Pelanggaran</a></li>
            <li><a href="status_laporan.php"><i class="fas fa-tasks"></i> Status Laporan</a></li>
            <li><a href="status_disiplin.php"><i class="fas fa-user-shield"></i> Status Disiplin</a></li>
            <li><a href="profil.php" class="active"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($nama_guru ?? $guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'Wali Kelas', 'W'); ?>
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
        <!-- Header Banner Halaman Profil -->
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
            <!-- Alert Notifikasi Berhasil -->
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Alert Notifikasi Gagal -->
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="form-card">
                <?php
                $has_photo = !empty($user['foto']) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $user['foto']);
                $photo_src = $has_photo ? '../assets/uploads/profil/' . htmlspecialchars($user['foto']) : '';
                ?>
                <!-- Form upload foto tersembunyi (otomatis langsung submit saat file dipilih) -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-upload-foto" style="display:none;">
                    <input type="hidden" name="update_foto" value="1">
                    <input id="foto-upload" type="file" name="foto" accept="image/jpeg, image/png, image/jpg, image/webp" onchange="autoUploadFoto(this);">
                </form>

                <div class="form-card-header" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                    <div class="profile-avatar-container" id="avatar-container" onclick="document.getElementById('foto-upload').click();" style="width: 110px; height: 110px; border-radius: 50%; position: relative; cursor: pointer; overflow: hidden; border: 4px solid #ffffff; box-shadow: 0 4px 10px rgba(0,0,0,0.15);" title="Klik untuk langsung mengubah foto profil">
                        <?php if ($has_photo): ?>
                            <img id="preview-avatar-img" src="<?php echo $photo_src; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div id="avatar-placeholder" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: linear-gradient(135deg, #2563eb, #d97706); color: white; font-size: 2.5rem;">
                                <?php echo strtoupper(substr($user['username'] ?? 'W', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Overlay Tombol Ubah Foto saat Hover -->
                        <div class="profile-avatar-overlay" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 5px;">
                            <i class="fas fa-camera" style="font-size: 1.2rem; margin: 0; line-height: 1;"></i>
                            <span style="font-size: 11px; font-weight: 600; line-height: 1.2; text-align: center; display: block; white-space: nowrap; font-family: sans-serif;">Ubah Foto</span>
                        </div>
                    </div>

                    <!-- Tombol Pilih Foto Langsung (Terlihat jelas di Mobile & Desktop) -->
                    <button type="button" class="btn-change-photo" onclick="document.getElementById('foto-upload').click();" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                        <i class="fas fa-camera"></i> Pilih / Ubah Foto
                    </button>

                    <div>
                        <div style="font-weight:700;font-size:1.15rem;color:#0f172a;"><?php echo htmlspecialchars($nama_guru); ?></div>
                        <div style="font-size:0.78rem;color:#475569;margin-top:2px;">Wali Kelas (NIP: <?php echo htmlspecialchars($guru['nip'] ?? '-'); ?>)</div>
                    </div>
                </div>

                <form action="" method="POST" id="form-profil">

                    <!-- Field 1: Nama Lengkap (Read-Only) -->
                    <div class="form-group">
                        <label><i class="fas fa-user-circle" style="margin-right:6px;color:#94a3b8;"></i>Nama Lengkap</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($nama_guru); ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="Nama lengkap hanya dapat diubah oleh Administrator">
                        <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-lock"></i> Nama Lengkap terdaftar dan hanya dapat diubah oleh Administrator.</small>
                    </div>
                    
                    <!-- Field 2: NIP (Read-Only) -->
                    <div class="form-group">
                        <label><i class="fas fa-id-badge" style="margin-right:6px;color:#94a3b8;"></i>NIP</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($guru['nip'] ?? ''); ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="NIP hanya dapat diubah oleh Administrator">
                        <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-lock"></i> NIP terdaftar dan hanya dapat diubah oleh Administrator.</small>
                    </div>
                    
                    <!-- Field 3: Username -->
                    <div class="form-group">
                        <label><i class="fas fa-user" style="margin-right:6px;color:#94a3b8;"></i>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required autocomplete="username">
                    </div>
                    
                    <!-- Field 4: Email Pemulihan Password -->
                    <div class="form-group">
                        <label><i class="fas fa-envelope" style="margin-right:6px;color:#94a3b8;"></i>Email (Untuk Pemulihan Password)</label>
                        <?php if (!empty($user['email'])): ?>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;" title="Email pemulihan tidak dapat diubah setelah didaftarkan">
                            <small style="color: #059669; display: block; margin-top: 0.5rem;"><i class="fas fa-check-circle"></i> Email pemulihan telah terdaftar dan tidak dapat diubah.</small>
                        <?php else: ?>
                            <input type="email" name="email" class="form-control" placeholder="Masukkan email aktif">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Field 5: Password Baru (Opsional) -->
                    <div class="form-group">
                        <label><i class="fas fa-lock" style="margin-right:6px;color:#94a3b8;"></i>Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                        <div style="position: relative;">
                            <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Masukkan password baru" style="padding-right: 3rem;" autocomplete="new-password">
                            <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6c757d;"></i>
                        </div>
                    </div>
                    
                    <!-- Tombol Aksi Simpan & Batal -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem; align-items: center;">
                        <button type="submit" name="update" class="btn-submit" style="flex: 2;"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <a href="index.php" class="btn-cancel" style="flex: 1;"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>

                <!-- Script Toggle Show/Hide Password & Preview Foto -->
                <script>
                    function autoUploadFoto(input) {
                        if (input.files && input.files[0]) {
                            const file = input.files[0];
                            if (file.size > 5 * 1024 * 1024) {
                                alert("Ukuran file terlalu besar! Maksimal 5MB.");
                                input.value = "";
                                return;
                            }
                            const container = document.getElementById('avatar-container');
                            if (container) {
                                container.style.opacity = '0.5';
                                container.style.pointerEvents = 'none';
                            }
                            document.getElementById('form-upload-foto').submit();
                        }
                    }

                    const togglePassword = document.querySelector('#togglePassword');
                    const password       = document.querySelector('#password_baru');
                    togglePassword.addEventListener('click', function() {
                        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                        password.setAttribute('type', type);
                        this.classList.toggle('fa-eye-slash');
                    });
                </script>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT: TOGGLE SIDEBAR MOBILE -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById("mobile-toggle");
        const sidebar   = document.querySelector(".sidebar");
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
