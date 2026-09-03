<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek apakah user sudah login dan memiliki role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Jika bukan admin, tendang ke halaman login
    exit();
}

$user_id = $_SESSION['id'];
$success = '';
$error = '';

// Ambil data admin saat ini
$query_admin = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
$admin = mysqli_fetch_assoc($query_admin);

// Helper fungsi upload foto profil yang aman dan reliabel
function uploadFotoProfil($file, $user_id, $koneksi, $foto_lama = '') {
    $allowed = array('jpg', 'jpeg', 'png', 'webp');
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
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
    
    $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
    $destination = $upload_dir . $new_filename;
    
    // Hapus file lama yang mungkin tertinggal untuk user ini
    $old_files = glob($upload_dir . 'admin_' . $user_id . '_*.*');
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

// Proses ketika tombol 'Simpan Perubahan' diklik
if (isset($_POST['update'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    
    if (!empty($admin['email'])) {
        $email = $admin['email']; // Paksa gunakan email lama yang sudah terdaftar
    } else {
        $email = mysqli_real_escape_string($koneksi, $_POST['email'] ?? ''); // Simpan email baru
    }
    $password_baru = $_POST['password_baru'];

    // Query dasar untuk memperbarui username dan email
    $sql = "UPDATE user SET username='$username', email='$email' WHERE id='$user_id'";
    if (!empty($password_baru)) {
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
        $sql = "UPDATE user SET username='$username', email='$email', password='$hashed_password' WHERE id='$user_id'";
    }

    if (mysqli_query($koneksi, $sql)) {
        $_SESSION['username'] = $username;
        $success = "Profil berhasil diperbarui!";
        
        // Cek jika ada foto yang disertakan saat klik Simpan Perubahan
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $res = uploadFotoProfil($_FILES['foto'], $user_id, $koneksi, $admin['foto'] ?? '');
            if ($res['success']) {
                $success = "Profil dan foto berhasil diperbarui!";
            } else {
                $error = $res['error'];
            }
        }
        
        // Refresh data admin terbaru
        $query_admin = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
        $admin = mysqli_fetch_assoc($query_admin);
    } else {
        $error = "Gagal memperbarui profil: " . mysqli_error($koneksi);
    }
}

// Proses ketika tombol 'Unggah Foto' / 'Simpan Foto' diklik tersendiri
if (isset($_POST['update_foto'])) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $res = uploadFotoProfil($_FILES['foto'], $user_id, $koneksi, $admin['foto'] ?? '');
        if ($res['success']) {
            $success = "Foto profil berhasil diperbarui!";
            $query_admin = mysqli_query($koneksi, "SELECT * FROM user WHERE id='$user_id'");
            $admin = mysqli_fetch_assoc($query_admin);
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
    <title>Profil Admin | BK SMA 07 Bungo</title>
    <meta name="description" content="Pengaturan profil administrator BK SMA 07 Bungo">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        }
        .profile-avatar:hover .profile-avatar-overlay {
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
            <li><a href="kelola_jenis_pelanggaran.php"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
            <li><a href="pelanggaran.php"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php" class="active"><i class="fas fa-user-cog"></i> Profil Admin</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php if (!empty($admin['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $admin['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($admin['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo $admin['username']; ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Administrator</div>
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
                <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Pengaturan Profil</h1>
                <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Kelola username, email pemulihan, dan pengaturan keamanan akun Anda.</p>
            </div>
        </div>

        <div class="profile-container" style="max-width: 600px; margin: 0 auto;">
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <?php
                $has_photo = !empty($admin['foto']) && file_exists(__DIR__ . '/../assets/uploads/profil/' . $admin['foto']);
                $photo_src = $has_photo ? '../assets/uploads/profil/' . htmlspecialchars($admin['foto']) : '';
                ?>
                <!-- Form upload foto tersembunyi (otomatis langsung submit saat file dipilih) -->
                <form action="" method="POST" enctype="multipart/form-data" id="form-upload-foto" style="display:none;">
                    <input type="hidden" name="update_foto" value="1">
                    <input id="foto-upload" type="file" name="foto" accept="image/jpeg, image/png, image/jpg, image/webp" onchange="autoUploadFoto(this);">
                </form>

                <div class="form-card-header" style="flex-direction: column; align-items: center; text-align: center; gap: 0.75rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border);">
                    <div class="profile-avatar" id="avatar-container" onclick="document.getElementById('foto-upload').click();" style="width: 110px; height: 110px; border-radius: 50%; position: relative; cursor: pointer; overflow: hidden; border: 4px solid #ffffff; box-shadow: var(--shadow-md);" title="Klik untuk langsung mengubah foto profil">
                        <?php if ($has_photo): ?>
                            <img id="preview-avatar-img" src="<?php echo $photo_src; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div id="avatar-placeholder" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; font-size: 2.5rem;">
                                <i class="fas fa-user-shield"></i>
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

                    <div>
                        <div style="font-weight:700;font-size:1.15rem;color:var(--text-primary);"><?php echo htmlspecialchars($admin['username']); ?></div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">Administrator Sistem</div>
                    </div>
                </div>

                <form action="" method="POST" id="form-profil">

                    <div class="form-group" style="margin-bottom: 1.5rem; margin-top: 1.5rem;">
                        <label style="font-weight: 600; color: #334155; margin-bottom: 8px; display: block;">Username</label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <i class="fas fa-user" style="position: absolute; left: 16px; color: #94a3b8; font-size: 1.1rem;"></i>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required style="padding-left: 45px; height: 50px; border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; font-size: 1rem; width: 100%; box-sizing: border-box; transition: all 0.2s;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="font-weight: 600; color: #334155; margin-bottom: 8px; display: block;">Email Pemulihan Password</label>
                        <?php if (!empty($admin['email'])): ?>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fas fa-envelope" style="position: absolute; left: 16px; color: #94a3b8; font-size: 1.1rem;"></i>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" readonly title="Email pemulihan tidak dapat diubah setelah didaftarkan" style="padding-left: 45px; height: 50px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 1rem; width: 100%; box-sizing: border-box; cursor: not-allowed; user-select: none;">
                            </div>
                            <small style="color: #10b981; display: block; margin-top: 8px; font-weight: 600; font-size: 0.85rem;"><i class="fas fa-shield-alt"></i> Email pemulihan telah terdaftar dan dikunci.</small>
                        <?php else: ?>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fas fa-envelope" style="position: absolute; left: 16px; color: #94a3b8; font-size: 1.1rem;"></i>
                                <input type="email" name="email" class="form-control" placeholder="Masukkan email aktif Anda..." required style="padding-left: 45px; height: 50px; border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; font-size: 1rem; width: 100%; box-sizing: border-box; transition: all 0.2s;">
                            </div>
                            <small style="color: #64748b; display: block; margin-top: 8px; font-size: 0.85rem;"><i class="fas fa-info-circle"></i> Email ini digunakan jika Anda lupa password.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="font-weight: 600; color: #334155; margin-bottom: 8px; display: block;">Password Baru <span style="color: #94a3b8; font-weight: 400; font-size: 0.9rem;">(kosongkan jika tidak ingin mengubah)</span></label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <i class="fas fa-lock" style="position: absolute; left: 16px; color: #94a3b8; font-size: 1.1rem;"></i>
                            <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Ketik sandi rahasia baru Anda..." style="padding-left: 45px; padding-right: 45px; height: 50px; border-radius: 10px; border: 1px solid #cbd5e1; background: #ffffff; font-size: 1rem; width: 100%; box-sizing: border-box; transition: all 0.2s;">
                            <i class="fas fa-eye input-toggle" id="togglePassword" style="position: absolute; right: 16px; color: #94a3b8; cursor: pointer; font-size: 1.1rem;"></i>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1.5rem;">
                        <button type="submit" name="update" class="btn-submit" style="flex: 2;"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        <a href="index.php" class="btn-cancel" style="flex: 1;"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>
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

        // Logika untuk menampilkan/menyembunyikan password (fitur mata)
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password_baru');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>

            <!-- Script Toggle Menu Mobile & Tabel Responsif -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Fungsionalitas Toggle Sidebar
        const toggleBtn = document.getElementById('mobile-toggle');
        const sidebar = document.querySelector('.sidebar');
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

        // 2. Injeksi data-label & Kelas Responsif untuk Tabel
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            // Deteksi jika tabel berisi data pelanggaran (memiliki kolom NISN atau Pelanggaran)
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


