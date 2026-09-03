<?php
// =========================================================================
// 1. BACA .env (JIKA ADA)
// =========================================================================
$env_file = __DIR__ . '/../.env';
$env_vars = [];
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env_vars[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

// Helper untuk mengambil config
$get_cfg = function($key, $default = null) use ($env_vars) {
    if (isset($env_vars[$key]) && $env_vars[$key] !== '') {
        return $env_vars[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $default;
};

// =========================================================================
// 2. DETEKSI OTOMATIS HOST DOCKER VS LOCALHOST
// =========================================================================
$is_docker = (gethostbyname('mysql_shared') !== 'mysql_shared');

if ($is_docker) {
    // Lingkungan Docker Server (VPS)
    $host = 'mysql_shared';
    $user = $get_cfg('DB_USERNAME', 'app2_user');
    // Jika env_vars DB_USERNAME adalah 'root' dari template lokal, gunakan user docker 'app2_user'
    if ($user === 'root') $user = 'app2_user';
    $pass = $get_cfg('DB_PASSWORD', 'App2_Secr3t_P@ss2026!');
    if ($pass === '' || $pass === null) $pass = 'App2_Secr3t_P@ss2026!';
    $db   = $get_cfg('DB_DATABASE', 'db_bk7');
} else {
    // Lingkungan Lokal (XAMPP / Laragon / WAMP)
    $host = $get_cfg('DB_HOST', 'localhost');
    $user = $get_cfg('DB_USERNAME', 'root');
    $pass = $get_cfg('DB_PASSWORD', '');
    $db   = $get_cfg('DB_DATABASE', 'db_bk7');
}

// =========================================================================
// 3. KONEKSI KE DATABASE
// =========================================================================
mysqli_report(MYSQLI_REPORT_OFF); // Matikan unhandled fatal error agar bisa ditangani manual

$koneksi = @mysqli_connect($host, $user, $pass, $db);

// Jika gagal di lokal dengan host 'localhost', coba dengan '127.0.0.1'
if (!$koneksi && !$is_docker && $host === 'localhost') {
    $host = '127.0.0.1';
    $koneksi = @mysqli_connect($host, $user, $pass, $db);
}

// Jika tetap gagal, tampilkan pesan error ramah
if (!$koneksi) {
    $err_msg = mysqli_connect_error() ?: "Koneksi database gagal ke ($host).";
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Koneksi Database Gagal - SI BK</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; line-height: 1.6; }
        .box { max-width: 650px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h2 { color: #dc2626; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        .badge { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; }
        pre { background: #0f172a; color: #f8fafc; padding: 12px 16px; border-radius: 8px; overflow-x: auto; font-size: 0.9rem; }
        ol { padding-left: 20px; }
        li { margin-bottom: 8px; }
        .code { background: #f1f5f9; color: #0f172a; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Gagal Terhubung ke Database</h2>
        <p><span class="badge">Detail Error:</span> ' . htmlspecialchars($err_msg) . '</p>
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
        <p><strong>Panduan Menjalankan di XAMPP (Laptop / Komputer Lokal):</strong></p>
        <ol>
            <li>Buka <strong>XAMPP Control Panel</strong> dan klik tombol <strong>Start</strong> pada modul <em>Apache</em> dan <em>MySQL</em>.</li>
            <li>Buka browser dan buka <a href="http://localhost/phpmyadmin" target="_blank" class="code">http://localhost/phpmyadmin</a>.</li>
            <li>Buat database baru bernama: <span class="code">db_bk7</span></li>
            <li>Pilih tab <strong>Import</strong>, pilih file SQL dari project ini: <span class="code">database/database_bk.sql</span>, lalu klik tombol <strong>Import / Go</strong> di bagian bawah.</li>
            <li>Jika MySQL XAMPP Anda memiliki password khusus, Anda dapat membuat file <span class="code">.env</span> di root project:
                <pre>DB_HOST=localhost' . "\n" . 'DB_USERNAME=root' . "\n" . 'DB_PASSWORD=password_anda' . "\n" . 'DB_DATABASE=db_bk7</pre>
            </li>
        </ol>
    </div>
</body>
</html>';
    exit();
}

// Aktifkan kembali reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set charset & konfigurasi sql_mode agar kompatibel dengan MySQL 8 / MariaDB
mysqli_set_charset($koneksi, "utf8mb4");
mysqli_query($koneksi, "SET SESSION sql_mode = (SELECT REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY,', ''))");

// Cek apakah fungsi tgl_indo belum pernah dibuat sebelumnya
if (!function_exists('tgl_indo')) {
    // Fungsi untuk mengubah format tanggal database (YYYY-MM-DD) menjadi format penulisan Indonesia
    function tgl_indo($tanggal) {
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
        $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}
?>
