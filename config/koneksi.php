<?php
// Konfigurasi akses database
$host = getenv('DB_HOST') ?: "mysql_shared"; // Alamat server database
$user = getenv('DB_USERNAME') ?: "app2_user";      // Nama pengguna database
$pass = getenv('DB_PASSWORD') ?: "App2_Secr3t_P@ss2026!"; // Kata sandi database
$db   = getenv('DB_DATABASE') ?: "db_bk7";    // Nama database yang ingin dihubungkan

// Menghubungkan skrip PHP ke database server MySQL
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek status koneksi, jika gagal maka tampilkan pesan error dan hentikan program
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset & konfigurasi sql_mode agar kompatibel dengan MySQL 8
mysqli_set_charset($koneksi, "utf8mb4");
mysqli_query($koneksi, "SET SESSION sql_mode = (SELECT REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY,', ''))");

// Cek apakah fungsi tgl_indo belum pernah dibuat sebelumnya untuk mencegah error bentrok fungsi
if (!function_exists('tgl_indo')) {
    // Fungsi untuk mengubah format tanggal database (YYYY-MM-DD) menjadi format penulisan Indonesia
    function tgl_indo($tanggal) {
        // Daftar nama bulan dalam Bahasa Indonesia, diindeks dari 1 (Januari) sampai 12 (Desember)
        $bulan = array (
            1 =>   'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );
        // Memecah format tanggal Tahun-Bulan-Tanggal berdasarkan karakter pemisah strip (-)
        $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
        
        // Menyusun kembali tanggal dengan format: Tanggal Nama_Bulan Tahun (Contoh: 13 Juli 2026)
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}
?>
