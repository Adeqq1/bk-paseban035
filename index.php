<?php
/**
 * ====================================================================================
 * HALAMAN UTAMA LOGIN - SISTEM INFORMASI BIMBINGAN KONSELING (SI BK7)
 * SMAN 7 BUNGO
 * ====================================================================================
 * Halaman ini merupakan gerbang utama (landing page) untuk autentikasi masuk ke sistem.
 * Pengguna yang sudah login akan otomatis dialihkan ke dashboard sesuai dengan hak akses (role) masing-masing.
 */

// 1. Memulai atau melanjutkan sesi pengguna (Session Handling)
session_start();

// 2. OTO-REDIRECT SECURITY CHECK:
// Pengecekan apakah pengguna sudah memiliki sesi login aktif di sistem.
// Jika sesi role sudah ada, sistem akan langsung mengarahkan pengguna ke halaman dashboard yang sesuai.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin");       // Redirect ke Dashboard Admin
        exit();
    } elseif ($_SESSION['role'] == 'guru_bk') {
        header("Location: guru_bk/index.php");     // Redirect ke Dashboard Guru BK
        exit();
    } elseif ($_SESSION['role'] == 'wali_kelas') {
        header("Location: wali_kelas/index.php");   // Redirect ke Dashboard Wali Kelas
        exit();
    } elseif ($_SESSION['role'] == 'siswa') {
        header("Location: siswa/index.php");        // Redirect ke Dashboard Siswa
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SI BK7 SMAN 7 Bungo</title>
    
    <!-- Library Ikon FontAwesome versi 6.4.0 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Font Modern: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ====================================================================
         * DESAIN TEMA & STYLE CSS HALAMAN UTAMA LOGIN
         * ==================================================================== */
        
        /* Variabel Warna Utama Sistem (Tema Modern Dashboard) */
        :root {
            --primary: #2563eb;        /* Warna Biru Utama (Royal Blue) */
            --primary-hover: #1d4ed8;  /* Warna Biru saat Kursor Hover */
            --accent: #d97706;         /* Warna Emas / Amber Identity Line */
            --dark: #0f172a;           /* Warna Gelap Background Header (Slate 900) */
            --white: #ffffff;          /* Warna Putih Elemen */
            --bg-grad: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); /* Degradasi Background Halaman */
            --text-secondary: #475569;  /* Warna Teks Sekunder (Abu-abu Gelap) */
            --text-muted: #94a3b8;      /* Warna Teks Pudar (Abu-abu Terang) */
            --border: #e2e8f0;          /* Warna Garis Tepi Border Input */
        }

        /* Reset Margin, Padding, dan Font Default Seluruh Elemen */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }

        /* Tampilan Utama Layar (Full Screen Flexbox Center) */
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

        /* Dekoras Lingkaran Cahaya 1 (Background Radial Glow Atas Kiri) */
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

        /* Dekoras Lingkaran Cahaya 2 (Background Radial Glow Bawah Kanan) */
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

        /* Wadah Utama Kartu Form Login (Login Card) */
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

        /* Style Judul Aplikasi */
        .login-card h2 { 
            font-weight: 800;
            font-size: 1.85rem;
            margin-bottom: 0.25rem; 
            color: var(--dark); 
            letter-spacing: -0.03em;
        }

        /* Style Sub-Judul Aplikasi */
        .login-card p { 
            color: var(--text-secondary); 
            font-size: 0.95rem;
            margin-bottom: 2rem; 
        }

        /* Group Pasangan Label dan Kolom Input Form */
        .form-group { 
            margin-bottom: 1.25rem; 
            text-align: left; 
        }

        /* Style Label Keterangan Input */
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--text-secondary); 
            font-size: 0.9rem;
            font-weight: 600; 
            letter-spacing: 0.01em;
        }

        /* Wrapper Container Input dengan Ikon */
        .input-group {
            position: relative;
            width: 100%;
        }

        /* Style Ikon di Sisi Kiri Input */
        .input-group .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
        }

        /* Style Kolom Input Teks & Password */
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.5rem;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--dark);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        /* Efek saat Kolom Input Fokus / Diklik */
        .form-control:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); 
        }

        /* Ikon Toggle Mata (Lihat Password) di Sisi Kanan Input */
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

        /* Menyembunyikan ikon mata bawaan browser (Edge/IE) agar tidak bentrok */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }

        /* Style Tombol Utama Login */
        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Efek Hover Tombol Login */
        .btn-login:hover { 
            background: linear-gradient(135deg, var(--primary-hover) 0%, #1e40af 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }

        /* Style Kotak Alert Notifikasi Pesan Warning/Error */
        .alert {
            padding: 0.75rem 1rem;
            background: rgba(220, 38, 38, 0.06);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.15);
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <!-- =================================================================== -->
    <!-- KARTU UTAMA TAMPILAN HALAMAN LOGIN                                  -->
    <!-- =================================================================== -->
    <div class="login-card">
        
        <!-- Logo Resmi SMAN 7 Bungo -->
        <div style="margin-bottom: 1.25rem;">
            <img src="guru_bk/images/logo_sma.png?v=2" alt="Logo SMAN 7 Bungo" style="height: 80px; width: auto; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.06));">
        </div>
        
        <!-- Judul & Sub-Judul Aplikasi -->
        <h2>SI BK7</h2>
        <p>Sistem Informasi Bimbingan Konseling</p>
        
        <!-- BLOCK NOTIFIKASI HASIL AUTENTIKASI: -->
        <!-- 1. Pengecekan jika URL berisi parameter ?pesan=gagal (Username/Password Salah) -->
        <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                Username atau Password salah!
            </div>
        
        <!-- 2. Pengecekan jika URL berisi parameter ?pesan=alumni (Akun Siswa Sudah Non-Aktif / Alumni) -->
        <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'alumni'): ?>
            <div class="alert" style="background: rgba(245, 158, 11, 0.06); color: #d97706; border-color: rgba(245, 158, 11, 0.15);">
                <i class="fas fa-exclamation-triangle"></i>
                Akun Anda sudah alumni / tidak aktif!
            </div>
        <?php endif; ?>

        <!-- FORM LOGIN: Mengirim data credential ke file proses_login.php via metode POST -->
        <form action="proses_login.php" method="POST">
            
            <!-- Field Input 1: Username / NISN / NIP -->
            <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i> <!-- Ikon Pengguna (User) -->
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autocomplete="username">
                </div>
            </div>
            
            <!-- Field Input 2: Password -->
            <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i> <!-- Ikon Gembok (Kunci) -->
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required style="padding-right: 3rem;" autocomplete="current-password">
                    
                    <!-- Ikon Mata untuk mengintip/menyembunyikan Password -->
                    <i class="fas fa-eye input-toggle" id="togglePassword" title="Lihat/Sembunyikan Password"></i>
                </div>
            </div>

            <!-- SCRIPT JAVASCRIPT: Interaktivitas Tombol Show/Hide Password -->
            <script>
                // Ambil elemen ikon mata berdasarkan ID 'togglePassword'
                const togglePassword = document.querySelector('#togglePassword');
                // Ambil elemen input password berdasarkan ID 'password'
                const password = document.querySelector('#password');

                // Tambahkan event listener saat ikon mata diklik
                togglePassword.addEventListener('click', function (e) {
                    // Cek tipe input saat ini: jika 'password' ubah ke 'text', dan sebaliknya
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Ubah bentuk ikon (menambah/menghapus garis coret pada ikon mata)
                    this.classList.toggle('fa-eye-slash');
                });
            </script>
            
            <!-- Tombol Submit Form: Kirim data ke proses_login.php -->
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk ke Dashboard
            </button>
            
            <!-- Tautan Navigasi ke Halaman Lupa Password (lupa_password.php) -->
            <div style="margin-top: 1.5rem;">
                <a href="lupa_password.php" style="color: #64748b; text-decoration: none; font-size: 0.95rem; transition: 0.2s; font-weight: 500;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='#64748b'">Lupa Password?</a>
            </div>
        </form>
    </div>
</body>
</html>
