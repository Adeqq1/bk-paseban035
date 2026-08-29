<?php
session_start();
require_once 'config/koneksi.php';

$error = '';
$success = '';

// Proses ketika tombol 'Verifikasi Data' diklik
if (isset($_POST['verifikasi'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);

    $role = mysqli_real_escape_string($koneksi, $_POST['role']);

    // Cari user di database yang memiliki username, email, dan role yang cocok
    $query = mysqli_query($koneksi, "SELECT * FROM user WHERE username='$username' AND email='$email' AND role='$role'");
    $user = mysqli_fetch_assoc($query);

    if ($user) {
        // Jika data cocok, simpan ID user ke session untuk proses reset password di halaman berikutnya
        $_SESSION['reset_user_id'] = $user['id'];
        header("Location: reset_password.php");
        exit();
    } else {
        // Jika data tidak ditemukan atau tidak cocok
        $error = "Data tidak ditemukan atau tidak cocok!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | SI BK7</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel warna berdasarkan tema premium dashboard */
        :root {
            --primary: #2563eb;      /* Royal Blue */
            --primary-hover: #1d4ed8;
            --accent: #d97706;       /* Gold/Amber */
            --dark: #0f172a;         /* Slate 900 */
            --white: #ffffff;
            --bg-grad: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', sans-serif; 
        }

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

        /* Glowing background decorative circular blobs */
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

        /* Login Card */
        .login-card {
            background: #ffffff;
            padding: 3rem 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 420px;
            text-align: center;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-top: 5px solid var(--accent); /* Gold/Amber identity line */
            z-index: 1;
            transition: all 0.3s ease;
        }

        .login-card h2 { 
            font-weight: 800;
            font-size: 1.85rem;
            margin-bottom: 0.25rem; 
            color: var(--dark); 
            letter-spacing: -0.03em;
        }

        .login-card p { 
            color: var(--text-secondary); 
            font-size: 0.85rem;
            margin-bottom: 2rem; 
        }

        .form-group { 
            margin-bottom: 1.25rem; 
            text-align: left; 
        }

        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--text-secondary); 
            font-size: 0.8rem;
            font-weight: 600; 
            letter-spacing: 0.01em;
        }

        .input-group {
            position: relative;
            width: 100%;
        }

        .input-group .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--dark);
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        select.form-control {
            padding-right: 2rem;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .form-control:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); 
        }

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
    <div class="login-card">
        <!-- Logo Resmi SMAN 7 Bungo -->
        <div style="margin-bottom: 1.25rem;">
            <img src="guru_bk/images/logo_sma.png?v=2" alt="Logo SMAN 7" style="height: 80px; width: auto; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.06));">
        </div>
        <h2>Lupa Password</h2>
        <p>Verifikasi data akun Anda</p>
        
        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Hak Akses (Role)</label>
                <div class="input-group">
                    <i class="fas fa-user-shield input-icon"></i>
                    <select name="role" class="form-control" required>
                        <option value="" disabled selected>Pilih Hak Akses</option>
                        <option value="siswa">Siswa</option>
                        <option value="guru_bk">Guru BK</option>
                        <option value="wali_kelas">Wali Kelas</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username terdaftar" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email Terdaftar</label>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="contoh@domain.com" required>
                </div>
            </div>
            <button type="submit" name="verifikasi" class="btn-recover">
                <i class="fas fa-check-circle"></i> Verifikasi Data
            </button>
        </form>
        
        <div style="margin-top: 1.5rem;">
            <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 0.85rem; transition: 0.2s; font-weight: 500;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='#64748b'"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
        </div>
    </div>
</body>
</html>
