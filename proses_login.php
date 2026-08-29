<?php
// 1. Memulai session PHP untuk menyimpan status login pengguna secara persisten di server
session_start();

// 2. Mengimpor file koneksi database agar dapat melakukan kueri ke server MySQL
require_once 'config/koneksi.php';

/** @var mysqli $koneksi */

// 3. Mengambil data username dan password dari input form login (metode POST)
// Menggunakan mysqli_real_escape_string untuk menyaring karakter input agar terhindar dari peretasan SQL Injection
$username = mysqli_real_escape_string($koneksi, $_POST['username']);
$password = $_POST['password'];

// 4. Proses pencarian data ke database MySQL untuk memeriksa apakah username terdaftar
// Fungsi mysqli_query() mengirimkan perintah SQL ke database menggunakan koneksi ($koneksi)
$query = mysqli_query($koneksi, "
    /* 
       - SELECT u.* : Mengambil semua data kolom dari tabel 'user' (diberi alias 'u')
       - s.status as status_siswa : Mengambil kolom 'status' dari tabel 'siswa' (diberi alias 's') 
                                    dan dinamai ulang sebagai 'status_siswa' agar mudah dibaca
    */
    SELECT u.*, s.status as status_siswa 
    
    /* FROM user u : Menjadikan tabel 'user' sebagai tabel utama pencarian (diberi alias 'u') */
    FROM user u 
    
    /* 
       LEFT JOIN siswa s ON u.id = s.user_id :
       Menghubungkan/menggabungkan tabel 'user' dengan tabel 'siswa' berdasarkan ID yang sama.
       Ini digunakan untuk mengecek apakah user tersebut merupakan siswa (untuk tahu statusnya aktif/alumni)
    */
    LEFT JOIN siswa s ON u.id = s.user_id 
    
    /* WHERE u.username='$username' : Membatasi pencarian hanya untuk baris yang username-nya cocok dengan input user */
    WHERE u.username='$username'
");

// mysqli_fetch_assoc() mengubah hasil pencarian database (resource query) 
// menjadi bentuk data Array di PHP agar kolom-kolomnya bisa diakses (contoh: $user['password'])
$user = mysqli_fetch_assoc($query);

// 5. Cek apakah akun dengan username tersebut ditemukan di database
if ($user) {
    // 6. Verifikasi password teks biasa yang diinput dengan password hash (terenkripsi) di database
    if (password_verify($password, $user['password'])) {
        // 7. Validasi aturan sistem: Jika role adalah siswa dan status siswanya adalah alumni, maka tidak boleh masuk
        if ($user['role'] == 'siswa' && $user['status_siswa'] == 'alumni') {
            header("Location: index.php?pesan=alumni");
            exit(); // Hentikan eksekusi skrip agar tidak melanjutkan proses login
        }

        // 8. Menyimpan informasi pengguna ke dalam Sesi (Session) setelah login sukses
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['foto'] = $user['foto'] ?? null;

        // Simpan nama_lengkap di session agar selalu tampil nama bukannya NIP/username
        if ($user['role'] == 'guru_bk' || $user['role'] == 'wali_kelas') {
            $q_g = mysqli_query($koneksi, "SELECT nama_lengkap FROM guru WHERE user_id = '{$user['id']}' OR id = '{$user['id']}'");
            if ($d_g = mysqli_fetch_assoc($q_g)) {
                $_SESSION['nama_lengkap'] = $d_g['nama_lengkap'];
            }
        } elseif ($user['role'] == 'siswa') {
            $q_s = mysqli_query($koneksi, "SELECT nama_lengkap FROM siswa WHERE user_id = '{$user['id']}' OR id = '{$user['id']}'");
            if ($d_s = mysqli_fetch_assoc($q_s)) {
                $_SESSION['nama_lengkap'] = $d_s['nama_lengkap'];
            }
        } // Mengisi dengan null jika foto tidak diatur

        // 9. Mengarahkan halaman dashboard pengguna berdasarkan Hak Akses (Role) masing-masing
        if ($user['role'] == 'admin') {
            header("Location: admin/index.php");
        } elseif ($user['role'] == 'guru_bk') {
            header("Location: guru_bk/index.php");
        } elseif ($user['role'] == 'wali_kelas') {
            header("Location: wali_kelas/index.php");
        } elseif ($user['role'] == 'siswa') {
            header("Location: siswa/index.php");
        }
    } else {
        // Jika password tidak cocok/salah, arahkan kembali ke form login dengan parameter pesan gagal
        header("Location: index.php?pesan=gagal");
    }
} else {
    // Jika username tidak ditemukan di database, arahkan kembali ke form login dengan parameter pesan gagal
    header("Location: index.php?pesan=gagal");
}
?>
