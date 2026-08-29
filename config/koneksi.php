<?php
// Konfigurasi akses database
$host = "localhost"; // Alamat server database (localhost)
$user = "root";      // Nama pengguna database (default XAMPP adalah root)
$pass = "";          // Kata sandi database (default XAMPP kosong)
$db   = "db_bk7";    // Nama database yang ingin dihubungkan

// Menghubungkan skrip PHP ke database server MySQL
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek status koneksi, jika gagal maka tampilkan pesan error dan hentikan program
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

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
