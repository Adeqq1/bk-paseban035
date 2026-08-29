<?php
// Memulai session PHP
session_start();
// Memuat file konfigurasi koneksi database
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Proteksi Halaman: Memastikan pengguna yang mengakses adalah Guru BK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru_bk') {
    header("Location: ../index.php");
    exit();
}

// Mengambil data Guru BK yang login
$user_id = $_SESSION['id'];
$query_guru = mysqli_query($koneksi, "SELECT id, nama_lengkap FROM guru WHERE user_id = '$user_id' OR id = '$user_id'");
$guru = mysqli_fetch_assoc($query_guru);
$guru_id = $guru ? $guru['id'] : 0; // ID Guru BK internal

// Mengambil daftar kelas untuk dropdown pilihan kelas
$query_kelas_list = mysqli_query($koneksi, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$kelas_list = [];
while($kl = mysqli_fetch_assoc($query_kelas_list)) {
    $kelas_list[] = $kl;
}

$query_siswa = mysqli_query($koneksi, "
    SELECT s.id, s.nama_lengkap, s.nisn, s.jenis_kelamin, s.alamat, s.tanggal_lahir, s.kelas_id, k.nama_kelas 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    ORDER BY k.nama_kelas, s.nama_lengkap
");

$siswa_list = [];
$siswa_js_data = [];

while ($row = mysqli_fetch_assoc($query_siswa)) {
    $siswa_list[] = $row; // Data untuk looping dropdown HTML
    // Struktur JSON data siswa untuk dibaca JavaScript
    $siswa_js_data[$row['id']] = [
        'nama_lengkap' => $row['nama_lengkap'],
        'nisn' => $row['nisn'],
        'kelas_id' => $row['kelas_id'] ?? '',
        'jk_code' => $row['jenis_kelamin'],
        'alamat' => !empty($row['alamat']) ? $row['alamat'] : ''
    ];
}

/*
 * Mengambil parameter masukan dari form, melakukan sanitasi karakter berbahaya,
 * lalu menyimpannya ke tabel alih_kasus.
 */
if (isset($_POST['simpan'])) {
    $siswa_id = mysqli_real_escape_string($koneksi, $_POST['siswa_id']);
    
    // Mengambil data siswa yang diedit dari form
    $nama_siswa = mysqli_real_escape_string($koneksi, $_POST['nama_siswa']);
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $kelas_id = mysqli_real_escape_string($koneksi, $_POST['kelas_id']);
    $alamat_siswa = mysqli_real_escape_string($koneksi, $_POST['alamat_siswa']);
    
    // Update data profil siswa di database agar tersimpan permanen
    mysqli_query($koneksi, "UPDATE siswa SET nama_lengkap = '$nama_siswa', jenis_kelamin = '$jenis_kelamin', kelas_id = '$kelas_id', alamat = '$alamat_siswa' WHERE id = '$siswa_id'");

    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $ringkasan_masalah = mysqli_real_escape_string($koneksi, $_POST['ringkasan_masalah']);
    $penerima_kasus = mysqli_real_escape_string($koneksi, $_POST['penerima_kasus']);
    $jabatan_penerima = mysqli_real_escape_string($koneksi, $_POST['jabatan_penerima']);
    $alamat_penerima = mysqli_real_escape_string($koneksi, $_POST['alamat_penerima']);
    $nip_kepsek = mysqli_real_escape_string($koneksi, $_POST['nip_kepsek']);
    $nama_kepsek = mysqli_real_escape_string($koneksi, $_POST['nama_kepsek']);

    $nomor_urut = mysqli_real_escape_string($koneksi, $_POST['nomor_urut']);

    // Menjalankan Query Insert ke tabel alih_kasus
    $query = "INSERT INTO alih_kasus (nomor_urut, siswa_id, guru_id, tanggal, ringkasan_masalah, penerima_kasus, jabatan_penerima, alamat_penerima, nip_kepsek, nama_kepsek) 
              VALUES ('$nomor_urut', '$siswa_id', '$guru_id', '$tanggal', '$ringkasan_masalah', '$penerima_kasus', '$jabatan_penerima', '$alamat_penerima', '$nip_kepsek', '$nama_kepsek')";
    
    if (mysqli_query($koneksi, $query)) {
        // Redirect kembali dengan pesan sukses
        header("Location: alih_kasus.php?pesan=success_tambah");
        exit();
    } else {
        $error = "Gagal menyimpan data alih tangan kasus: " . mysqli_error($koneksi);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Alih Kasus | BK SMA 07 Bungo</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body, .main-content, h1, h2, h3, .sidebar {
            font-family: 'Inter', sans-serif !important;
        }
        .data-card {
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            background: white !important;
            padding: 2rem !important;
            margin-bottom: 2.5rem !important;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-group.full-width {
            grid-column: span 2;
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
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Guru BK Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="pelanggaran_masuk.php"><i class="fas fa-inbox"></i> Laporan Masuk</a></li>
            <li><a href="konseling.php"><i class="fas fa-user-graduate"></i> Bimbingan/Konseling</a></li>
            <li><a href="bimbingan_mandiri.php"><i class="fas fa-calendar-check"></i> Bimbingan Mandiri</a></li>
            <li><a href="arsip_siswa.php"><i class="fas fa-folder-open"></i> Arsip Siswa</a></li>
            <li><a href="daftar_panggilan.php"><i class="fas fa-envelope-open-text"></i> Panggilan Ortu</a></li>
            <li><a href="alih_kasus.php" class="active"><i class="fas fa-share-square"></i> Alih Tangan Kasus</a></li>
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

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-share-square" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Buat Form <span style="color: #60a5fa;">Alih Tangan Kasus</span></h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Rujukan / Penyerahan Kasus Siswa ke Pihak Lanjutan</p>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert badge-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: #fee2e2; color: #991b1b; display: block; border: 1px solid #fee2e2; font-weight: 500;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <form action="" method="POST">
                <div class="form-grid">
                    <div>
                        <div style="background: #f0fdf4; padding: 1.25rem; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 1.5rem;">
                            <p style="margin: 0 0 12px 0; font-weight: 700; color: #166534; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em;"><i class="fas fa-hashtag" style="margin-right: 6px;"></i>NOMOR SURAT</p>
                            <div style="margin-bottom: 8px;">
                                <label for="nomor_urut" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NOMOR URUT SURAT</label>
                                <input type="text" name="nomor_urut" id="nomor_urut" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: 001" maxlength="10">
                            </div>
                            <div style="background: #fff; border: 1px dashed #86efac; border-radius: 6px; padding: 8px 12px; font-size: 0.85rem; color: #374151;">
                                <span style="color: #6b7280; font-size: 0.75rem;">Pratinjau nomor surat:</span><br>
                                <strong id="preview_nomor">___ / BK / SMAN 7-Bungo / <?php echo 
                                    ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][date('n')-1]; 
                                ?> / <?php echo date('Y'); ?></strong>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="siswa_id" style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase;">PILIH SISWA</label>
                            <select name="siswa_id" id="siswa_id" class="form-control" style="width: 100%; padding: 0.75rem 2.5rem 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" required>
                                <option value="">-- Cari & Pilih Siswa --</option>
                                <?php foreach ($siswa_list as $sl): ?>
                                    <option value="<?php echo $sl['id']; ?>">
                                        [<?php echo htmlspecialchars($sl['nama_kelas'] ?? '-'); ?>] <?php echo htmlspecialchars($sl['nama_lengkap']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="tanggal" style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase;">TANGGAL ALIH KASUS</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div style="background: #f0f9ff; padding: 1.25rem; border-radius: 8px; border: 1px solid #bae6fd; margin-bottom: 1.5rem;">
                            <p style="margin: 0 0 12px 0; font-weight: 700; color: #0369a1; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em;"><i class="fas fa-arrow-right" style="margin-right: 6px;"></i>DARI GURU BK KEPADA :</p>
                            <div style="margin-bottom: 1rem;">
                                <label for="penerima_kasus" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NAMA PENERIMA</label>
                                <input type="text" name="penerima_kasus" id="penerima_kasus" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: dr. Seto, Sp.KJ" required>
                                <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">Nama lengkap psikolog, dokter, atau pihak penerima kasus.</small>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="jabatan_penerima" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">JABATAN</label>
                                <input type="text" name="jabatan_penerima" id="jabatan_penerima" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Contoh: Psikolog Klinis / Dokter Spesialis">
                            </div>
                            <div>
                                <label for="alamat_penerima" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">ALAMAT</label>
                                <textarea name="alamat_penerima" id="alamat_penerima" class="form-control" rows="2" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Alamat instansi / praktik penerima kasus..."></textarea>
                            </div>
                        </div>

                        <div style="background: #fefce8; padding: 1.25rem; border-radius: 8px; border: 1px solid #fde68a; margin-bottom: 1.5rem;">
                            <p style="margin: 0 0 12px 0; font-weight: 700; color: #92400e; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.025em;"><i class="fas fa-user-tie" style="margin-right: 6px;"></i>DATA KEPALA SEKOLAH (TANDA TANGAN)</p>
                            <div style="margin-bottom: 1rem;">
                                <label for="nama_kepsek" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NAMA KEPALA SEKOLAH</label>
                                <input type="text" name="nama_kepsek" id="nama_kepsek" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Masukkan nama lengkap Kepala Sekolah">
                            </div>
                            <div>
                                <label for="nip_kepsek" style="display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.8rem;">NIP KEPALA SEKOLAH</label>
                                <input type="text" name="nip_kepsek" id="nip_kepsek" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Masukkan NIP Kepala Sekolah">
                            </div>
                            <small style="color: #78716c; font-size: 0.8rem; display: block; margin-top: 8px;">Kosongkan jika ingin mengisi manual saat dicetak.</small>
                        </div>
                    </div>

                    <div>
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px;">INFORMASI SISWA (DAPAT DISESUAIKAN)</label>
                        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #cbd5e1; display: flex; flex-direction: column; gap: 1rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                            <div>
                                <label for="nama_siswa" style="display: block; margin-bottom: 5px; color: #475569; font-weight: 600; font-size: 0.8rem;">NAMA SISWA</label>
                                <input type="text" name="nama_siswa" id="nama_siswa" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" required>
                            </div>
                            <div>
                                <label for="jenis_kelamin" style="display: block; margin-bottom: 5px; color: #475569; font-weight: 600; font-size: 0.8rem;">JENIS KELAMIN</label>
                                <select name="jenis_kelamin" id="jenis_kelamin" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" required>
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div>
                                <label for="kelas_id" style="display: block; margin-bottom: 5px; color: #475569; font-weight: 600; font-size: 0.8rem;">KELAS</label>
                                <select name="kelas_id" id="kelas_id" class="form-control" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelas_list as $kl): ?>
                                        <option value="<?php echo $kl['id']; ?>"><?php echo htmlspecialchars($kl['nama_kelas']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="alamat_siswa" style="display: block; margin-bottom: 5px; color: #475569; font-weight: 600; font-size: 0.8rem;">ALAMAT ASAL</label>
                                <textarea name="alamat_siswa" id="alamat_siswa" class="form-control" rows="2" style="width: 100%; padding: 0.7rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;" placeholder="Masukkan alamat siswa..." required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width" style="margin-bottom: 1.5rem;">
                        <label for="ringkasan_masalah" style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase;">MASALAH</label>
                        <textarea name="ringkasan_masalah" id="ringkasan_masalah" class="form-control" rows="4" style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="Tuliskan masalah siswa yang melatarbelakangi alih kasus..." required></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center; justify-content: flex-start; margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <button type="submit" name="simpan" class="btn-submit">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                    <a href="alih_kasus.php" class="btn-cancel"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Siswa untuk Auto-Complete via JavaScript -->
    <script>
        const siswaData = <?php echo json_encode($siswa_js_data); ?>;

        document.getElementById('siswa_id').addEventListener('change', function() {
            const siswaId = this.value;
            
            const inputNama = document.getElementById('nama_siswa');
            const selectJk = document.getElementById('jenis_kelamin');
            const selectKelas = document.getElementById('kelas_id');
            const textareaAlamat = document.getElementById('alamat_siswa');

            if (siswaId && siswaData[siswaId]) {
                const data = siswaData[siswaId];
                inputNama.value = data.nama_lengkap;
                selectJk.value = data.jk_code;
                selectKelas.value = data.kelas_id;
                textareaAlamat.value = data.alamat;
            } else {
                inputNama.value = '';
                selectJk.value = '';
                selectKelas.value = '';
                textareaAlamat.value = '';
            }
        });

        // Update preview nomor surat
        function updateNomorPreview() {
            const val = document.getElementById('nomor_urut').value.trim();
            const preview = document.getElementById('preview_nomor');
            const tanggalVal = document.getElementById('tanggal').value;
            
            let romanBulan = '<?php echo ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][date('n')-1]; ?>';
            let tahun = '<?php echo date('Y'); ?>';
            
            if (tanggalVal) {
                const dateObj = new Date(tanggalVal);
                if (!isNaN(dateObj.getTime())) {
                    const months = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
                    romanBulan = months[dateObj.getMonth()];
                    tahun = dateObj.getFullYear();
                }
            }
            
            const num = val ? val : '___';
            preview.textContent = num + ' / BK / SMAN 7-Bungo / ' + romanBulan + ' / ' + tahun;
        }

        document.getElementById('nomor_urut').addEventListener('input', updateNomorPreview);
        document.getElementById('tanggal').addEventListener('change', updateNomorPreview);
    </script>
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
