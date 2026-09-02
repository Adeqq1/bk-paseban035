<?php
// =========================================================================
// 1. LOAD ENVIRONMENT VARIABLES (.env) JIKA ADA
// =========================================================================
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// =========================================================================
// 2. DETEKSI LINGKUNGAN (DOCKER / PRODUCTION VS LOCAL XAMPP / LARAGON)
// =========================================================================
$db_host_env = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? null));

if ($db_host_env === 'mysql_shared' || ($db_host_env && !in_array($db_host_env, ['localhost', '127.0.0.1']))) {
    // Lingkungan Docker Server / Production
    $host = $db_host_env;
    $user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? "app2_user"));
    $pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? "App2_Secr3t_P@ss2026!"));
    $db   = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ($_SERVER['DB_DATABASE'] ?? "db_bk7"));
} else {
    // Lingkungan Pengembangan Lokal (XAMPP / Laragon / WampServer)
    $host = $db_host_env ?: "localhost";
    $user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? "root"));
    $pass = getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== null ? getenv('DB_PASSWORD') : ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? ""));
    $db   = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ($_SERVER['DB_DATABASE'] ?? "db_bk7"));
}

// =========================================================================
// 3. KONEKSI KE DATABASE SERVER MYSQL
// =========================================================================
try {
    $koneksi = @mysqli_connect($host, $user, $pass, $db);
} catch (Throwable $e) {
    $koneksi = false;
}

// Jika koneksi gagal, berikan instruksi bantuan yang jelas untuk developer
if (!$koneksi) {
    $err_msg = mysqli_connect_error() ?: "Tidak dapat terhubung ke database server ($host).";
    
    // Tampilan pesan error yang informatif dan ramah pengguna lokal XAMPP
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
