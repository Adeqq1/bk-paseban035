<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0;


$user_id = $_SESSION['id'];
$q_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id'");
$guru_data = mysqli_fetch_assoc($q_guru);
$guru_id = $guru_data['id'] ?? 0;

$siswa_param = isset($_GET['siswa_id']) ? mysqli_real_escape_string($koneksi, $_GET['siswa_id']) : (isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : '');
$siswa = null;

if (!empty($siswa_param)) {
    $q_siswa = mysqli_query($koneksi, "
        SELECT s.*, k.nama_kelas, COALESCE(SUM(jp.poin), 0) as total_poin 
        FROM siswa s 
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id
        LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
        WHERE s.id = '$siswa_param' OR s.nisn = '$siswa_param'
        GROUP BY s.id
    ");
    if ($q_siswa && mysqli_num_rows($q_siswa) > 0) {
        $siswa = mysqli_fetch_assoc($q_siswa);
    }
}

// Ambil daftar seluruh siswa untuk pilihan dropdown jika diperlukan
$siswa_list = [];
$q_list = mysqli_query($koneksi, "
    SELECT s.*, k.nama_kelas, COALESCE(SUM(jp.poin), 0) as total_poin 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN catatan_pelanggaran cp ON s.id = cp.siswa_id
    LEFT JOIN jenis_pelanggaran jp ON cp.pelanggaran_id = jp.id
    GROUP BY s.id
    ORDER BY s.nama_lengkap ASC
");
while ($r_l = mysqli_fetch_assoc($q_list)) {
    $siswa_list[] = $r_l;
}

if (isset($_POST['simpan'])) {
    $siswa_id_post = intval($_POST['siswa_id']);
    $nomor_urut = mysqli_real_escape_string($koneksi, trim($_POST['nomor_urut']));
    $tanggal_pertemuan = !empty($_POST['tanggal']) ? "'".mysqli_real_escape_string($koneksi, $_POST['tanggal'])."'" : "NULL";
    $jam_pertemuan = !empty($_POST['jam']) ? "'".mysqli_real_escape_string($koneksi, $_POST['jam'])."'" : "NULL";
    $tempat = mysqli_real_escape_string($koneksi, trim($_POST['tempat']));
    $alasan = mysqli_real_escape_string($koneksi, trim($_POST['alasan']));

    $q_ins = mysqli_query($koneksi, "
        INSERT INTO panggilan_orang_tua (siswa_id, guru_id, nomor_urut, tanggal_panggilan, jam_panggilan, tempat, alasan, status)
        VALUES ('$siswa_id_post', '$guru_id', '$nomor_urut', $tanggal_pertemuan, $jam_pertemuan, '$tempat', '$alasan', 'Dikirim')
    ");

    if ($q_ins) {
        $panggilan_id = mysqli_insert_id($koneksi);
        header("Location: cetak_panggilan.php?id=" . $panggilan_id);
        exit();
    } else {
        $error = "Gagal membuat surat panggilan: " . mysqli_error($koneksi);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Surat Panggilan | SI BK7</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #mobile-toggle {
            display: flex !important; /* Membuat elemen menggunakan flexbox (mempermudah memposisikan ikon di tengah) */
            position: fixed !important; /* Posisi tombol selalu tetap meskipun layar di-scroll */
            top: 18px !important; /* Jarak tombol dari bagian atas layar */
            right: 25px !important; /* Jarak tombol dari bagian kanan layar */
            z-index: 99999 !important; /* Memastikan tombol selalu ada di paling depan/atas, tidak tertimpa elemen lain */
            background: #0f172a !important; /* Warna latar belakang tombol (biru sangat gelap) */
            color: #ffffff !important; /* Warna ikon di dalam tombol (putih) */
            border: 1px solid rgba(255, 255, 255, 0.2) !important; /* Memberi garis pinggir tipis berwarna putih transparan */
            border-radius: 10px !important; /* Membuat sudut tombol menjadi membulat/tidak kaku */
            width: 42px !important; /* Lebar tombol */
            height: 42px !important; /* Tinggi tombol */
            align-items: center !important; /* Mengatur ikon agar berada persis di tengah secara vertikal */
            justify-content: center !important; /* Mengatur ikon agar berada persis di tengah secara horizontal */
            font-size: 1.15rem !important; /* Mengatur ukuran ikon di dalamnya */
            cursor: pointer !important; /* Mengubah bentuk kursor menjadi jari (tangan) saat diarahkan ke tombol */
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important; /* Memberikan efek bayangan agar tombol terlihat melayang */
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar">
        <div class="sidebar-header">
            <h2>SI BK7</h2>
            <p>Bimbingan Konseling</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
            <li><a href="daftar_panggilan.php" class="active"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
            <li><a href="alih_kasus.php"><i class="fas fa-share-square"></i> Alih Tangan Kasus</a></li>
            <li><a href="kunjungan_rumah.php"><i class="fas fa-home"></i> Kunjungan Rumah</a></li>
            <li><a href="rekap_poin.php"><i class="fas fa-chart-line"></i> Rekap Poin</a></li>
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil & Sandi</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <!-- Mengecek apakah pengguna memiliki foto profil yang tersimpan di sistem -->
                <?php if (!empty($_SESSION['foto']) && file_exists('../assets/uploads/profil/' . $_SESSION['foto'])): ?>
                    <!-- Jika ada, tampilkan foto profil tersebut -->
                    <img src="../assets/uploads/profil/<?php echo $_SESSION['foto']; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:10px;">
                <?php else: ?>
                    <!-- Jika tidak ada foto, tampilkan inisial (huruf pertama) dari nama pengguna -->
                    <?php echo strtoupper(substr($guru['nama_lengkap'] ?? $_SESSION['username'] ?? 'B', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo htmlspecialchars($guru['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Guru BK'); ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Guru BK</div>
            </div>
        </div>
    </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-envelope-open-text" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: white; letter-spacing: 0.025em;">Buat <span style="color: #60a5fa;">Surat Panggilan</span></h1>
                    <p style="margin: 0; color: #cbd5e1; font-size: 0.95rem;">Terbitkan surat panggilan resmi untuk orang tua/wali murid</p>
                </div>
            </div>
        </div>

        <?php if ($siswa): ?>
        <div class="data-card" style="margin-bottom: 2rem; border: 1px solid #fecaca; border-left: 5px solid #ef4444; background: #fef2f2; border-radius: 12px; padding: 1.5rem;">
            <h3 style="color: #b91c1c; margin-top: 0; display: flex; align-items: center; gap: 8px; font-size: 1.1rem;"><i class="fas fa-exclamation-triangle"></i> Peringatan Poin Tinggi</h3>
            <p style="margin-bottom: 0; color: #7f1d1d; font-size: 0.95rem;">Siswa <strong><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></strong> (<?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?>) saat ini memiliki akumulasi <strong style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 6px;"><?php echo $siswa['total_poin']; ?> poin</strong> pelanggaran.</p>
        </div>
        <?php endif; ?>

        <div class="data-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); padding: 2rem;">
            <form action="" method="POST">
                
                <?php if ($siswa): ?>
                    <input type="hidden" name="siswa_id" value="<?php echo $siswa['id']; ?>">
                <?php else: ?>
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">PILIH SISWA SISWI</label>
                        <select name="siswa_id" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required>
                            <option value="">-- Cari & Pilih Siswa --</option>
                            <?php foreach ($siswa_list as $sl): ?>
                                <option value="<?php echo $sl['id']; ?>">
                                    [<?php echo htmlspecialchars($sl['nama_kelas'] ?? '-'); ?>] <?php echo htmlspecialchars($sl['nama_lengkap']); ?> (<?php echo $sl['total_poin']; ?> Poin)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="background: #f0fdf4; padding: 1.25rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 1.5rem;">
                    <p style="margin: 0 0 12px 0; font-weight: 700; color: #166534; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em;"><i class="fas fa-hashtag" style="margin-right: 6px;"></i>NOMOR SURAT & KODE INSTANSI SEKOLAH</p>
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 10px;">
                        <div>
                            <label for="nomor_urut" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NOMOR URUT SURAT</label>
                            <input type="text" name="nomor_urut" id="nomor_urut" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: 001" maxlength="20">
                        </div>
                        <div>
                            <label for="kode_sekolah" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">KODE SEKOLAH / INSTANSI (DAPAT DISESUAIKAN SEKOLAH)</label>
                            <input type="text" id="kode_sekolah" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" value="SMAN 7-Bungo" placeholder="Misal: SMAN 7-Bungo">
                        </div>
                    </div>
                    <div style="background: #fff; border: 1px dashed #86efac; border-radius: 6px; padding: 10px 14px; font-size: 0.85rem; color: #374151;">
                        <span style="color: #6b7280; font-size: 0.75rem;">Pratinjau nomor surat yang akan dicetak:</span><br>
                        <strong id="preview_nomor" style="color: #15803d; font-size: 0.95rem;">421 / ___ / SMAN 7-Bungo / <?php echo ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][date('n')-1]; ?> / <?php echo date('Y'); ?></strong>
                    </div>
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">TANGGAL PERTEMUAN (OPSIONAL)</label>
                        <input type="date" name="tanggal" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <small class="text-muted" style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">Kosongkan jika ingin menulis manual di surat cetak.</small>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">JAM PERTEMUAN (OPSIONAL)</label>
                        <input type="time" name="jam" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;">
                        <small class="text-muted" style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">Kosongkan jika ingin menulis manual di surat cetak.</small>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">TEMPAT PERTEMUAN</label>
                    <input type="text" name="tempat" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Contoh: Ruang Kepala Sekolah / Ruang BK" value="Ruang BK SMAN 7" required>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.025em;">ALASAN PEMANGGILAN</label>
                    <textarea name="alasan" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required><?php 
                        if ($siswa) {
                            echo "Koordinasi terkait akumulasi poin pelanggaran siswa yang telah mencapai " . $siswa['total_poin'] . " poin. Mohon kehadiran orang tua/wali untuk membicarakan pembinaan siswa ke depan.";
                        } else {
                            echo "Koordinasi terkait pembinaan dan perkembangan kedisiplinan siswa di sekolah. Mohon kehadiran orang tua/wali untuk membicarakan langkah pembinaan ke depan.";
                        }
                    ?></textarea>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Simpan & Terbitkan Panggilan
                    </button>
                    <a href="daftar_panggilan.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updatePreviewNomor() {
            const numVal = document.getElementById('nomor_urut').value.trim();
            const kodeVal = document.getElementById('kode_sekolah') ? document.getElementById('kode_sekolah').value.trim() : 'SMAN 7-Bungo';
            const num = numVal ? numVal : '___';
            const kode = kodeVal ? kodeVal : 'SMAN 7-Bungo';
            const preview = document.getElementById('preview_nomor');
            if (preview) {
                preview.textContent = '421 / ' + num + ' / ' + kode + ' / <?php echo ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][date('n')-1]; ?> / <?php echo date('Y'); ?>';
            }
        }
        document.getElementById('nomor_urut').addEventListener('input', updatePreviewNomor);
        if (document.getElementById('kode_sekolah')) {
            document.getElementById('kode_sekolah').addEventListener('input', updatePreviewNomor);
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
