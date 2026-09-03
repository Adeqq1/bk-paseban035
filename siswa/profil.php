<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Proteksi halaman siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$msg = '';

// Ambil data diri lengkap siswa
$query_siswa = mysqli_query($koneksi, "
    SELECT s.*, k.nama_kelas, u.username, u.email 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    JOIN user u ON s.user_id = u.id
    WHERE s.user_id = '$user_id'
");
$siswa = mysqli_fetch_assoc($query_siswa);

// Proses Update Profil (Data Diri)
if (isset($_POST['update_profil'])) {
    $tempat_lahir = mysqli_real_escape_string($koneksi, $_POST['tempat_lahir']);
    $tanggal_lahir = mysqli_real_escape_string($koneksi, $_POST['tanggal_lahir']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    $update = mysqli_query($koneksi, "UPDATE siswa SET tempat_lahir='$tempat_lahir', tanggal_lahir='$tanggal_lahir', alamat='$alamat' WHERE user_id='$user_id'");
    
    if ($update) {
        $msg = "success_profil";
        // Refresh data
        $query_siswa = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas, u.username, u.email FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id JOIN user u ON s.user_id = u.id WHERE s.user_id = '$user_id'");
        $siswa = mysqli_fetch_assoc($query_siswa);
    } else {
        $msg = "error";
    }
}

// Helper fungsi upload foto profil Siswa yang aman dan reliabel
function uploadFotoProfilSiswa($file, $user_id, $siswa_id, $koneksi, $foto_lama = '') {
    $allowed = array('jpg', 'jpeg', 'png', 'webp');
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'code' => 'error_ext'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'code' => 'error_size'];
    }
    if (!@getimagesize($file['tmp_name'])) {
        return ['success' => false, 'code' => 'error_invalid'];
    }
    
    $upload_dir = __DIR__ . '/../assets/uploads/profil/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    
    $new_filename = 'siswa_' . $siswa_id . '_' . time() . '.' . $ext;
    $destination = $upload_dir . $new_filename;
    
    // Bersihkan file lama untuk siswa ini
    $old_files = glob($upload_dir . 'siswa_' . $siswa_id . '_*.*');
    if ($old_files) {
        foreach ($old_files as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
    $old_files_user = glob($upload_dir . 'siswa_' . $user_id . '_*.*');
    if ($old_files_user) {
        foreach ($old_files_user as $f) {
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
        
        mysqli_query($koneksi, "UPDATE siswa SET foto='$new_filename' WHERE user_id='$user_id'");
        mysqli_query($koneksi, "UPDATE user SET foto='$new_filename' WHERE id='$user_id'");
        $_SESSION['foto'] = $new_filename;
        return ['success' => true, 'filename' => $new_filename];
    } else {
        return ['success' => false, 'code' => 'error_upload'];
    }
}

// Proses Update Foto Profil
if (isset($_POST['update_foto'])) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $res = uploadFotoProfilSiswa($_FILES['foto'], $user_id, $siswa['id'], $koneksi, $siswa['foto'] ?? '');
        if ($res['success']) {
            $msg = "success_foto";
            $query_siswa = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas, u.username, u.email FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id JOIN user u ON s.user_id = u.id WHERE s.user_id = '$user_id'");
            $siswa = mysqli_fetch_assoc($query_siswa);
        } else {
            $msg = $res['code'];
        }
    } else {
        $msg = "error_upload";
    }
}

// Proses Update Password & Email
if (isset($_POST['update_password'])) {
    $password_baru = trim($_POST['password_baru'] ?? '');
    $konfirmasi = trim($_POST['konfirmasi_password'] ?? '');
    $email_updated = false;
    
    // Update Email Pemulihan jika sebelumnya kosong
    if (empty($siswa['email']) && !empty($_POST['email_pemulihan'])) {
        $email = mysqli_real_escape_string($koneksi, $_POST['email_pemulihan']);
        if (mysqli_query($koneksi, "UPDATE user SET email='$email' WHERE id='$user_id'")) {
            $email_updated = true;
        }
    }
    
    if (!empty($password_baru) || !empty($konfirmasi)) {
        if (!empty($password_baru) && $password_baru === $konfirmasi) {
            $hash = password_hash($password_baru, PASSWORD_BCRYPT);
            if (mysqli_query($koneksi, "UPDATE user SET password='$hash' WHERE id='$user_id'")) {
                $msg = "success_keamanan";
            } else {
                $msg = "error";
            }
        } else {
            if ($email_updated && empty($konfirmasi)) {
                $msg = "success_keamanan";
            } else {
                $msg = "error_match";
            }
        }
    } else {
        $msg = "success_keamanan";
    }
    
    // Refresh data
    $query_siswa = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas, u.username, u.email FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id JOIN user u ON s.user_id = u.id WHERE s.user_id = '$user_id'");
    $siswa = mysqli_fetch_assoc($query_siswa);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Desain tata letak halaman profil */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        .profile-photo-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .profile-photo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .form-section h3 {
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-upload {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .btn-upload:hover {
            background: #cbd5e1;
        }
        input[type="file"] {
            display: none;
        }
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
            <p>Siswa Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="riwayat.php"><i class="fas fa-history"></i> Riwayat & Arsip</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php" class="active"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- Header -->
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: flex-start; gap: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-user-edit" style="font-size: 1.8rem; color: #60a5fa;"></i>
            </div>
            <div>
                <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Profil Saya</h1>
                <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Kelola data diri, foto, dan pengaturan keamanan akun Anda.</p>
            </div>
        </div>

        <!-- Notifikasi Status -->
        <?php if ($msg): ?>
            <div class="alert <?php echo strpos($msg, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <i class="fas <?php echo strpos($msg, 'success') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <div>
                    <?php 
                        if ($msg == 'success_profil') echo "Data diri berhasil diperbarui!";
                        if ($msg == 'success_foto') echo "Foto profil berhasil diperbarui!";
                        if ($msg == 'success_keamanan') echo "Keamanan akun & Email Pemulihan berhasil diperbarui!";
                        if ($msg == 'error_match') echo "Konfirmasi password tidak cocok!";
                        if ($msg == 'error_ext') echo "Format file foto tidak diizinkan! Gunakan JPG/PNG.";
                        if ($msg == 'error_size') echo "Ukuran file terlalu besar! Maksimal 5MB.";
                        if ($msg == 'error_invalid') echo "File yang diunggah bukan gambar yang valid!";
                        if ($msg == 'error_upload') echo "Gagal mengunggah foto.";
                        if ($msg == 'error') echo "Terjadi kesalahan sistem.";
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="profile-container" style="max-width: 600px; margin: 0 auto; display: block;">
            <div class="form-card">
                <?php
                $has_photo = !empty($siswa['foto']) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $siswa['foto']);
                $photo_src = $has_photo ? '../assets/uploads/profil/' . htmlspecialchars($siswa['foto']) : '';
                ?>
                <!-- HEADER FOTO -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-upload-foto">
                    <input type="hidden" name="update_foto" value="1">
                    <div class="form-card-header" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 0.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                        <div class="profile-avatar-container" id="avatar-container" onclick="document.getElementById('foto-upload').click();" style="width: 110px; height: 110px; border-radius: 50%; position: relative; cursor: pointer; overflow: hidden; border: 4px solid #ffffff; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                            <?php if ($has_photo): ?>
                                <img id="preview-avatar-img" src="<?php echo $photo_src; ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div id="avatar-placeholder" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: linear-gradient(135deg, #3b82f6, #10b981); color: white; font-size: 2.5rem;">
                                    <?php echo strtoupper(substr($siswa['nama_lengkap'] ?? 'S', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Upload Overlay -->
                            <div class="profile-avatar-overlay" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 5px;">
                                <i class="fas fa-camera" style="font-size: 1.2rem; margin: 0; line-height: 1;"></i>
                                <span style="font-size: 11px; font-weight: 600; line-height: 1.2; text-align: center; display: block; white-space: nowrap; font-family: sans-serif;">Ubah Foto</span>
                            </div>
                        </div>

                        <!-- Tombol Pilih Foto Langsung (Terlihat jelas di Mobile & Desktop) -->
                        <button type="button" class="btn-change-photo" onclick="document.getElementById('foto-upload').click();" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                            <i class="fas fa-camera"></i> Pilih / Ubah Foto
                        </button>

                        <input id="foto-upload" type="file" name="foto" accept="image/jpeg, image/png, image/jpg, image/webp" style="display:none;" onchange="autoUploadFoto(this);">

                        <div>
                            <div style="font-weight:700;font-size:1.2rem;color:#0f172a;"><?php echo htmlspecialchars(ucwords(strtolower($siswa['nama_lengkap']))); ?></div>
                            <div style="font-size:0.85rem;color:#64748b;margin-top:2px;">Siswa Aktif SMAN 7 Bungo</div>
                        </div>
                    </div>
                </form>

                <!-- FORM KELENGKAPAN DATA DIRI -->
                <div class="form-section" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: #334155;"><i class="fas fa-address-card" style="color: #3b82f6; margin-right: 8px;"></i> Kelengkapan Data Diri</h3>
                    <form action="" method="POST">
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">NISN</label>
                            <input type="text" class="form-control" value="<?php echo $siswa['nisn']; ?>" readonly disabled style="background:#f8fafc; color:#64748b; cursor:not-allowed; border-radius:8px; height:45px;" title="NISN hanya dapat diubah oleh Administrator">
                            <small style="color: #059669; font-weight: 500; display: block; margin-top: 0.4rem; font-size: 0.8rem;"><i class="fas fa-lock"></i> NISN terdaftar dan hanya dapat diubah oleh Administrator.</small>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Nama Lengkap</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucwords(strtolower($siswa['nama_lengkap']))); ?>" readonly disabled style="background:#f8fafc; color:#64748b; cursor:not-allowed; border-radius:8px; height:45px;" title="Nama lengkap hanya dapat diubah oleh Administrator">
                            <small style="color: #059669; font-weight: 500; display: block; margin-top: 0.4rem; font-size: 0.8rem;"><i class="fas fa-lock"></i> Nama Lengkap terdaftar dan hanya dapat diubah oleh Administrator.</small>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Kelas</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($siswa['nama_kelas']); ?>" readonly disabled style="background:#f8fafc; color:#64748b; cursor:not-allowed; border-radius:8px; height:45px;" title="Kelas hanya dapat diubah oleh Administrator">
                                <small style="color: #059669; font-weight: 500; display: block; margin-top: 0.4rem; font-size: 0.78rem;"><i class="fas fa-lock"></i> Hanya dapat diubah oleh Administrator.</small>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Jenis Kelamin</label>
                                <input type="text" class="form-control" value="<?php echo $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?>" readonly disabled style="background:#f8fafc; color:#64748b; cursor:not-allowed; border-radius:8px; height:45px;" title="Jenis kelamin hanya dapat diubah oleh Administrator">
                                <small style="color: #059669; font-weight: 500; display: block; margin-top: 0.4rem; font-size: 0.78rem;"><i class="fas fa-lock"></i> Hanya dapat diubah oleh Administrator.</small>
                            </div>
                        </div>
                        
                        <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 1.5rem 0;">
                        
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-control" value="<?php echo htmlspecialchars($siswa['tempat_lahir']); ?>" placeholder="Contoh: Bungo" required style="border-radius:8px; height:45px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control" value="<?php echo $siswa['tanggal_lahir']; ?>" required style="border-radius:8px; height:45px; border: 1px solid #cbd5e1;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control" rows="3" placeholder="Masukkan alamat rumah lengkap..." required style="border-radius:8px; border: 1px solid #cbd5e1;"><?php echo htmlspecialchars($siswa['alamat']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profil" class="btn btn-primary" style="width: 100%; border-radius: 8px; height: 45px; font-weight: 600;"><i class="fas fa-save"></i> Simpan Data Diri</button>
                    </form>
                </div>

                <!-- FORM KEAMANAN AKUN -->
                <div class="form-section">
                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: #334155; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;"><i class="fas fa-shield-alt" style="color: #10b981; margin-right: 8px;"></i> Keamanan Akun</h3>
                    <form action="" method="POST">
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Email Pemulihan</label>
                            <?php if(!empty($siswa['email'])): ?>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fas fa-envelope" style="position: absolute; left: 16px; color: #94a3b8;"></i>
                                    <input type="email" class="form-control" value="<?php echo $siswa['email']; ?>" readonly disabled style="padding-left:45px; background:#f8fafc; color:#64748b; cursor:not-allowed; border-radius:8px; height:45px;">
                                </div>
                                <small style="color: #10b981; font-weight: 600; display:block; margin-top: 6px; font-size: 0.8rem;"><i class="fas fa-lock"></i> Email pemulihan telah dikunci permanen.</small>
                            <?php else: ?>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fas fa-envelope" style="position: absolute; left: 16px; color: #94a3b8;"></i>
                                    <input type="email" name="email_pemulihan" class="form-control" placeholder="Masukkan email aktif..." required style="padding-left:45px; border-radius:8px; height:45px; border: 1px solid #cbd5e1;">
                                </div>
                                <small style="color: #dc2626; display:block; margin-top: 6px; font-size: 0.8rem;"><i class="fas fa-exclamation-circle"></i> Pastikan email benar! Tidak dapat diubah nanti.</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Password Baru <span style="font-weight:400; color:#94a3b8; font-size:0.8rem;">(Opsional)</span></label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fas fa-key" style="position: absolute; left: 16px; color: #94a3b8;"></i>
                                <input type="password" name="password_baru" class="form-control" placeholder="Biarkan kosong jika tidak diganti" autocomplete="new-password" style="padding-left:45px; border-radius:8px; height:45px; border: 1px solid #cbd5e1;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 0.9rem;">Konfirmasi Password Baru</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fas fa-check-double" style="position: absolute; left: 16px; color: #94a3b8;"></i>
                                <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password" style="padding-left:45px; border-radius:8px; height:45px; border: 1px solid #cbd5e1;">
                            </div>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-warning" style="width:100%; border-radius: 8px; height: 45px; font-weight: 600; border: none;"><i class="fas fa-shield-alt"></i> Perbarui Keamanan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
