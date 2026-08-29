<?php
/**
 * ====================================================================================
 * MODUL RESET PASSWORD - SISTEM INFORMASI BIMBINGAN KONSELING (BK SMA 07 Bungo)
 * SMAN 7 BUNGO
 * ====================================================================================
 * Halaman ini berfungsi untuk memproses pembuatan password baru bagi pengguna 
 * (Siswa, Guru BK, Wali Kelas, Admin) yang telah lolos verifikasi data pada halaman lupa_password.php.
 */

// 1. Inisialisasi Session untuk mengakses data verifikasi pengguna
session_start();

// 2. Hubungkan ke database MySQL melalui file koneksi.php
require_once 'config/koneksi.php';

/** @var mysqli $koneksi */

// 3. PROTEKSI HALAMAN (SECURITY CHECK):
// Pengecekan apakah pengguna sudah berhasil melalui tahap verifikasi di lupa_password.php.
// Jika session 'reset_user_id' belum ada, pengguna akan langsung dialihkan (ditendang) kembali ke lupa_password.php.
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: lupa_password.php");
    exit();
}

// Inisialisasi variabel untuk menampung pesan kesalahan (error) atau pesan berhasil (success)
$error = '';
$success = '';

// 4. PROSES SUBMIT RESET PASSWORD:
// Dijalankan ketika pengguna menekan tombol 'Simpan Password Baru' (name="reset")
if (isset($_POST['reset'])) {
    // Ambil data input dan bersihkan dari spasi berlebih (trim)
    $password_baru = trim($_POST['password_baru'] ?? '');
    $konfirmasi    = trim($_POST['konfirmasi'] ?? '');

    // Validasi 1: Memastikan input password baru dan konfirmasi tidak boleh kosong
    if (empty($password_baru) || empty($konfirmasi)) {
        $error = "Password baru dan konfirmasi tidak boleh kosong!";
    } 
    // Validasi 2: Memastikan nilai password baru dan konfirmasi password sama persis
    elseif ($password_baru !== $konfirmasi) {
        $error = "Konfirmasi password tidak cocok! Pastikan penulisan karakter sama.";
    } 
    // Jika seluruh validasi terpenuhi, proses pembaruan password ke database
    else {
        // Ambil ID pengguna dari session verifikasi
        $id = $_SESSION['reset_user_id'];
        
        // Enkripsi (Hash) password baru menggunakan algoritma standar aman (BCRYPT / PASSWORD_DEFAULT)
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
        
        // Jalankan perintah SQL UPDATE untuk mengganti password di tabel 'user'
        $update = mysqli_query($koneksi, "UPDATE user SET password='$hashed_password' WHERE id='$id'");
        
        if ($update) {
            // KEAMANAN TAMBAHAN:
            // Hapus session 'reset_user_id' agar halaman reset ini tidak dapat diakses atau di-refresh ulang
            unset($_SESSION['reset_user_id']);
            
            // Set pesan keberhasilan
            $success = "Password berhasil diperbarui! Silakan login menggunakan password baru Anda.";
        } else {
            // Set pesan gagal jika terjadi gangguan pada koneksi database
            $error = "Gagal memperbarui password ke dalam sistem. Silakan coba beberapa saat lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | BK SMA 07 Bungo</title>
    
    <!-- Library Ikon FontAwesome versi 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Font Modern: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ====================================================================
         * DESAIN TEMA & STYLE CSS HALAMAN RESET PASSWORD
         * ==================================================================== */
        
        /* Variabel Warna Tema Utama Sistem */
        :root {
            --primary: #2563eb;        /* Warna Biru Utama (Royal Blue) */
            --primary-hover: #1d4ed8;  /* Warna Biru saat kursor Hover */
            --accent: #d97706;         /* Warna Emas / Amber Identity */
            --success: #10b981;        /* Warna Hijau Sukses (Emerald) */
            --dark: #0f172a;           /* Warna Gelap Background Header (Slate 900) */
            --white: #ffffff;          /* Warna Putih */
            --bg-grad: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); /* Degradasi Background Halaman */
            --text-secondary: #475569;  /* Warna Teks Sekunder (Abu-abu Gelap) */
            --text-muted: #94a3b8;      /* Warna Teks Pudar (Abu-abu Terang) */
            --border: #e2e8f0;          /* Warna Garis Tepi Border Input */
        }

        /* Reset Margin dan Padding Seluruh Elemen */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', sans-serif; 
        }

        /* Tampilan Utama Layar (Full Screen Center Alignment) */
        body {
            background: var(--bg-grad);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Dekoras Lingkaran Cahaya 1 (Background Glow Atas Kiri) */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            top: -10%;
            left: 15%;
            z-index: 0;
        }

        /* Dekoras Lingkaran Cahaya 2 (Background Glow Bawah Kanan) */
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -10%;
            right: 15%;
            z-index: 0;
        }

        /* Wadah Utama Kartu Reset Password */
        .login-card {
            background: #ffffff;
            padding: 3rem 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 420px;
            text-align: center;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-top: 5px solid var(--accent); /* Akses List Emas di bagian atas kartu */
            z-index: 1;
            transition: all 0.3s ease;
        }

        /* Judul Utama Kartu */
        .login-card h2 { 
            font-weight: 800;
            font-size: 1.85rem;
            margin-bottom: 0.25rem; 
            color: var(--dark); 
            letter-spacing: -0.03em;
        }

        /* Sub-Judul / Deskripsi Kartu */
        .login-card p { 
            color: var(--text-secondary); 
            font-size: 0.85rem;
            margin-bottom: 2rem; 
        }

        /* Group Pasangan Label & Input */
        .form-group { 
            margin-bottom: 1.25rem; 
            text-align: left; 
        }

        /* Label nama kolom input */
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--text-secondary); 
            font-size: 0.8rem;
            font-weight: 600; 
            letter-spacing: 0.01em;
        }

        /* Container Input dengan Ikon */
        .input-group {
            position: relative;
            width: 100%;
        }

        /* Ikon Kiri di dalam Kolom Input */
        .input-group .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
        }

        /* Tombol Ikon Mata (Show/Hide Password) di Kanan Input */
        .input-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .input-toggle:hover {
            color: var(--primary);
        }

        /* Style Input Field Form */
        .form-control {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 2.5rem;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--dark);
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        /* Efek saat Input diklik / Fokus */
        .form-control:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); 
        }

        /* Style Tombol 'Simpan Password Baru' */
        .btn-recover {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 1rem;
        }

        .btn-recover:hover { 
            background: linear-gradient(135deg, var(--primary-hover) 0%, #1e40af 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        
        .btn-recover:active {
            transform: translateY(0);
        }

        /* Base Notifikasi Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }

        /* Alert Tipe Kesalahan (Merah) */
        .alert-danger {
            background: rgba(220, 38, 38, 0.06);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.15);
        }

        /* Alert Tipe Berhasil (Hijau) */
        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
    </style>
</head>
<body>

    <!-- =================================================================== -->
    <!-- KARTU UTAMA TAMPILAN RESET PASSWORD                                -->
    <!-- =================================================================== -->
    <div class="login-card">
        
        <!-- Logo Resmi SMAN 7 Bungo -->
        <div style="margin-bottom: 1.25rem;">
            <img src="guru_bk/images/logo_sma.png?v=2" alt="Logo SMAN 7 Bungo" style="height: 80px; width: auto; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.06));">
        </div>
        
        <!-- Header Judul & Deskripsi -->
        <h2>Reset Password</h2>
        <p>Masukkan password baru untuk akun Anda</p>
        
        <!-- BLOCK NOTIFIKASI PESAN KESALAHAN (ERROR) -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- BLOCK NOTIFIKASI PESAN BERHASIL (SUCCESS) -->
        <?php if ($success): ?>
            <div class="alert alert-success" style="flex-direction: column; align-items: flex-start; gap: 6px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                    <strong>Berhasil!</strong>
                </div>
                <div><?php echo htmlspecialchars($success); ?></div>
                
                <!-- Tombol Akses Kembali ke Halaman Login Utama -->
                <a href="index.php" class="btn-recover" style="text-decoration: none; margin-top: 0.75rem; width: 100%;">
                    <i class="fas fa-sign-in-alt"></i> Menuju Halaman Login
                </a>
            </div>
        
        <!-- TAMPILAN FORM KETIKA PROSES BERHASIL BELUM SELESAI -->
        <?php else: ?>
            <form action="" method="POST">
                
                <!-- Field Input 1: Password Baru -->
                <div class="form-group">
                    <label>Password Baru</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password_baru" id="password_baru" class="form-control" placeholder="Masukkan password baru" required autocomplete="new-password">
                        
                        <!-- Tombol Ikon Mata untuk mengintip Password Baru -->
                        <i class="fas fa-eye input-toggle" id="togglePassword1" title="Lihat/Sembunyikan Password" onclick="togglePass('password_baru', 'togglePassword1')"></i>
                    </div>
                </div>
                
                <!-- Field Input 2: Konfirmasi Password Baru -->
                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <div class="input-group">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" name="konfirmasi" id="konfirmasi" class="form-control" placeholder="Ulangi password baru" required autocomplete="new-password">
                        
                        <!-- Tombol Ikon Mata untuk mengintip Konfirmasi Password -->
                        <i class="fas fa-eye input-toggle" id="togglePassword2" title="Lihat/Sembunyikan Password" onclick="togglePass('konfirmasi', 'togglePassword2')"></i>
                    </div>
                </div>
                
                <!-- Tombol Submit Form simpan password -->
                <button type="submit" name="reset" class="btn-recover">
                    <i class="fas fa-shield-alt"></i> Simpan Password Baru
                </button>
            </form>
            
            <!-- Link Batal / Kembali ke Halaman Login -->
            <div style="margin-top: 1.5rem;">
                <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 0.85rem; transition: 0.2s; font-weight: 500;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='#64748b'"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- =================================================================== -->
    <!-- SCRIPT JAVASCRIPT: SKRIP FUNGSI TOGGLE LIHAT / SEMBUNYIKAN PASSWORD   -->
    <!-- =================================================================== -->
    <script>
        /**
         * Fungsi JavaScript togglePass()
         * Berfungsi untuk mengonversi tipe input kata sandi antara mode tersembunyi ('password')
         * dan mode teks terlihat ('text'), serta mengganti ikon mata terbuka / tertutup.
         * 
         * @param {string} inputId - ID dari elemen input password yang ingin diubah
         * @param {string} iconId  - ID dari elemen ikon mata yang diklik
         */
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);
            
            if (input.type === 'password') {
                // Tampilkan password sebagai teks biasa
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                // Sembunyikan kembali password menjadi simbol titik
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>