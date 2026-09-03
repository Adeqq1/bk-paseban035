<?php
session_start();
require_once '../config/koneksi.php';

/** @var mysqli $koneksi */

// Cek login & role admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Proses Tambah Kelas
if (isset($_POST['tambah'])) {
    $nama_kelas = trim($_POST['nama_kelas']);
    $nama_kelas_escaped = mysqli_real_escape_string($koneksi, $nama_kelas);
    $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? mysqli_real_escape_string($koneksi, $_POST['wali_kelas_id']) : null;
    $wali_kelas_val = $wali_kelas_id ? "'$wali_kelas_id'" : "NULL";

    // Cek apakah nama kelas sudah ada di database (case-insensitive)
    $cek_nama = mysqli_query($koneksi, "SELECT id FROM kelas WHERE LOWER(TRIM(nama_kelas)) = LOWER(TRIM('$nama_kelas_escaped'))");
    if ($cek_nama && mysqli_num_rows($cek_nama) > 0) {
        $msg = "error_duplikat";
    } elseif ($wali_kelas_id && mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM kelas WHERE wali_kelas_id = '$wali_kelas_id'")) > 0) {
        $msg = "error_wali";
    } else {
        try {
            $query = "INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES ('$nama_kelas_escaped', $wali_kelas_val)";
            if (mysqli_query($koneksi, $query)) {
                $msg = "success_tambah";
            } else {
                $msg = "error";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'nama_kelas') !== false) {
                    $msg = "error_duplikat";
                } else {
                    $msg = "error_wali";
                }
            } else {
                $msg = "error";
            }
        }
    }
}

// Proses Edit Kelas
if (isset($_POST['edit'])) {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $nama_kelas = trim($_POST['nama_kelas']);
    $nama_kelas_escaped = mysqli_real_escape_string($koneksi, $nama_kelas);
    $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? mysqli_real_escape_string($koneksi, $_POST['wali_kelas_id']) : null;
    $wali_kelas_val = $wali_kelas_id ? "'$wali_kelas_id'" : "NULL";

    // Cek apakah nama kelas sudah digunakan oleh kelas lain
    $cek_nama = mysqli_query($koneksi, "SELECT id FROM kelas WHERE LOWER(TRIM(nama_kelas)) = LOWER(TRIM('$nama_kelas_escaped')) AND id != '$id'");
    if ($cek_nama && mysqli_num_rows($cek_nama) > 0) {
        $msg = "error_duplikat";
    } elseif ($wali_kelas_id && mysqli_num_rows(mysqli_query($koneksi, "SELECT id FROM kelas WHERE wali_kelas_id = '$wali_kelas_id' AND id != '$id'")) > 0) {
        $msg = "error_wali";
    } else {
        try {
            $query = "UPDATE kelas SET nama_kelas='$nama_kelas_escaped', wali_kelas_id=$wali_kelas_val WHERE id='$id'";
            if (mysqli_query($koneksi, $query)) {
                $msg = "success_edit";
            } else {
                $msg = "error";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'nama_kelas') !== false) {
                    $msg = "error_duplikat";
                } else {
                    $msg = "error_wali";
                }
            } else {
                $msg = "error";
            }
        }
    }
}

// Proses Hapus Kelas
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    try {
        if (mysqli_query($koneksi, "DELETE FROM kelas WHERE id='$id'")) {
            $msg = "success_hapus";
        } else {
            $msg = "error";
        }
    } catch (Throwable $e) {
        $msg = "error";
    }
}

// Ambil data kelas dengan jumlah siswa dan nama wali kelas
$query_kelas = mysqli_query($koneksi, "
    SELECT k.*, g.nama_lengkap as nama_wali, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id) as jumlah_siswa 
    FROM kelas k 
    LEFT JOIN guru g ON k.wali_kelas_id = g.id
    ORDER BY k.nama_kelas ASC
");

// Ambil data guru yang jabatannya Wali Kelas beserta info kelas yang diampu untuk dropdown
$query_wali = mysqli_query($koneksi, "
    SELECT g.id, g.nama_lengkap, k.nama_kelas 
    FROM guru g 
    LEFT JOIN kelas k ON g.id = k.wali_kelas_id 
    WHERE g.jabatan='Wali Kelas' 
    ORDER BY g.nama_lengkap ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kelas | BK SMA 07 Bungo</title>
    <meta name="description" content="Manajemen data Kelas - BK SMA 07 Bungo">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Tombol Menu Hamburger (Garis Tiga) untuk memunculkan/menyembunyikan Sidebar pada tampilan Mobile (HP) -->
    <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>BK SMA<span>07</span></h3>
            <p>Admin Panel</p>
        </div>
        <div class="sidebar-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="kelola_siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a></li>
            <li><a href="kelola_guru_bk.php"><i class="fas fa-user-shield"></i> Kelola Guru BK</a></li>
            <li><a href="kelola_guru.php"><i class="fas fa-chalkboard-teacher"></i> Kelola Wali Kelas</a></li>
            <li><a href="kelola_kelas.php" class="active"><i class="fas fa-school"></i> Kelola Kelas</a></li>
        </ul>
        <div class="sidebar-label">Data & Laporan</div>
        <ul class="sidebar-menu">
            <li><a href="kelola_jenis_pelanggaran.php"><i class="fas fa-list-ul"></i> Jenis Pelanggaran</a></li>
            <li><a href="pelanggaran.php"><i class="fas fa-exclamation-triangle"></i> Pelanggaran</a></li>
        </ul>
        <div class="sidebar-label">Akun</div>
        <ul class="sidebar-menu">
            <li><a href="profil.php"><i class="fas fa-user-cog"></i> Profil Admin</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <!-- Bagian Bawah Sidebar (Menampilkan Profil Pengguna yang Sedang Login) -->
        <div class="sidebar-footer">
            <div class="avatar">
                <?php echo render_sidebar_avatar($_SESSION['username'] ?? 'Admin', 'A'); ?>
            </div>
            <div>
                <!-- Menampilkan nama lengkap pengguna -->
                <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                <!-- Menampilkan peran/jabatan pengguna -->
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(96,165,250,0.12) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none;"></div>
            <div style="display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1;">
                <div style="background: rgba(255,255,255,0.06); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(255,255,255,0.05);">
                    <i class="fas fa-school" style="font-size: 1.8rem; color: #60a5fa;"></i>
                </div>
                <div>
                    <h1 style="margin: 0 0 6px 0; font-size: 1.6rem; font-weight: 800; color: white; letter-spacing: -0.01em;">Kelola Kelas</h1>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.925rem;">Manajemen daftar kelas dan penugasan Wali Kelas.</p>
                </div>
            </div>
            <button class="btn-tambah-utama" onclick="openModal('modalTambah')" id="btnTambahKelas" style="position: relative; z-index: 1;">
                <i class="fas fa-plus"></i> Tambah Kelas Baru
            </button>
        </div>

        <?php if (isset($msg)): ?>
            <div class="alert <?php echo $msg == 'success_hapus' ? 'alert-delete' : (strpos($msg, 'success') !== false ? 'alert-success' : 'alert-danger'); ?>">
                <i class="fas <?php echo $msg == 'success_hapus' ? 'fa-trash-alt' : (strpos($msg, 'success') !== false ? 'fa-check-circle' : 'fa-times-circle'); ?>"></i>
                <div>
                    <?php 
                        if ($msg == 'success_tambah') echo "Data kelas berhasil ditambahkan!";
                        if ($msg == 'success_edit') echo "Data kelas berhasil diperbarui!";
                        if ($msg == 'success_hapus') echo "Data kelas berhasil dihapus!";
                        if ($msg == 'error_duplikat') echo "Nama kelas tersebut sudah ada! Silakan gunakan nama kelas yang berbeda.";
                        if ($msg == 'error_wali') echo "Guru yang dipilih sudah menjadi Wali Kelas untuk kelas lain!";
                        if ($msg == 'error') echo "Terjadi kesalahan sistem saat memproses data kelas.";
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="data-card">
            <div class="data-card-header">
                <h2><i class="fas fa-school"></i> Daftar Kelas</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($query_kelas)): ?>
                        <tr>
                            <td><span style="color:var(--text-muted);font-size:.8rem;"><?php echo $no++; ?></span></td>
                            <td><span style="font-weight:700;color:var(--text-primary);"><?php echo $row['nama_kelas']; ?></span></td>
                            <td>
                                <?php if($row['nama_wali']): ?>
                                    <span style="color:var(--text-secondary);"><?php echo $row['nama_wali']; ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-style:italic;font-size:.82rem;">Belum diset</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $row['jumlah_siswa']; ?> Siswa</span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <button class="btn btn-warning btn-sm btn-icon" onclick='editKelas(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?hapus=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Hapus kelas ini? Siswa di kelas ini akan menjadi tidak terdaftar kelas.')" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Tambah Kelas Baru</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Buat kelas dan opsional tentukan wali kelas.</p>
                </div>
                <div class="close" onclick="closeModal('modalTambah')">&#x2715;</div>
            </div>
            <form action="kelola_kelas.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-school" style="color: var(--primary);"></i> Nama Kelas
                    </label>
                    <input type="text" name="nama_kelas" class="form-control" placeholder="Contoh: X.A, XI.MIPA 1" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-chalkboard-teacher" style="color: var(--primary);"></i> Wali Kelas <span style="color:var(--text-muted);font-weight:400;text-transform:lowercase;font-size:0.75rem;">(opsional)</span>
                    </label>
                    <select name="wali_kelas_id" class="form-control">
                        <option value="">-- Pilih Wali Kelas --</option>
                        <?php 
                        mysqli_data_seek($query_wali, 0);
                        while($w = mysqli_fetch_assoc($query_wali)): 
                            $hasClass = !empty($w['nama_kelas']);
                        ?>
                            <option value="<?php echo $w['id']; ?>" <?php echo $hasClass ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($w['nama_lengkap']) . ($hasClass ? ' (Sudah mengampu kelas ' . htmlspecialchars($w['nama_kelas']) . ')' : ''); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">Edit Data Kelas</h2>
                    <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Perbarui nama kelas dan wali kelas.</p>
                </div>
                <div class="close" onclick="closeModal('modalEdit')">&#x2715;</div>
            </div>
            <form action="kelola_kelas.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-school" style="color: var(--primary);"></i> Nama Kelas
                    </label>
                    <input type="text" name="nama_kelas" id="edit_nama" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.6rem; font-weight: 600; font-size: 0.825rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fas fa-chalkboard-teacher" style="color: var(--primary);"></i> Wali Kelas <span style="color:var(--text-muted);font-weight:400;text-transform:lowercase;font-size:0.75rem;">(opsional)</span>
                    </label>
                    <select name="wali_kelas_id" id="edit_wali" class="form-control">
                        <option value="">-- Pilih Wali Kelas --</option>
                        <?php 
                        mysqli_data_seek($query_wali, 0);
                        while($w = mysqli_fetch_assoc($query_wali)): 
                            $hasClass = !empty($w['nama_kelas']);
                        ?>
                            <option value="<?php echo $w['id']; ?>" data-has-class="<?php echo $hasClass ? 'true' : 'false'; ?>" <?php echo $hasClass ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($w['nama_lengkap']) . ($hasClass ? ' (Sudah mengampu kelas ' . htmlspecialchars($w['nama_kelas']) . ')' : ''); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;"><i class="fas fa-save" style="margin-right: 6px;"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function editKelas(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_nama').value = data.nama_kelas;
            
            const selectWali = document.getElementById('edit_wali');
            // Reset status disabled pilihan guru:
            // Guru yang sudah punya kelas akan di-disabled, kecuali jika itu adalah wali kelas lama dari kelas ini
            for (let i = 0; i < selectWali.options.length; i++) {
                const opt = selectWali.options[i];
                const hasClass = opt.getAttribute('data-has-class') === 'true';
                if (opt.value == data.wali_kelas_id) {
                    opt.disabled = false;
                } else {
                    opt.disabled = hasClass;
                }
            }
            
            document.getElementById('edit_wali').value = data.wali_kelas_id || "";
            openModal('modalEdit');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>

    <!-- Script Toggle Menu Mobile & Tabel Responsif -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Fungsionalitas Toggle Sidebar (Mobile Friendly dengan Backdrop & Close Button)
        const toggleBtn = document.getElementById("mobile-toggle");
        const sidebar = document.querySelector(".sidebar");
        if (toggleBtn && sidebar) {


            // Injeksi backdrop overlay jika belum ada
            let overlay = document.getElementById("sidebar-overlay");
            if (!overlay) {
                overlay = document.createElement("div");
                overlay.className = "sidebar-overlay";
                overlay.id = "sidebar-overlay";
                document.body.appendChild(overlay);
                overlay.addEventListener("click", function() {
                    sidebar.classList.remove("active");
                    const icon = toggleBtn.querySelector("i");
                    if (icon) {
                        icon.classList.remove("fa-times");
                        icon.classList.add("fa-bars");
                    }
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

        // 2. Injeksi data-label & Kelas Responsif untuk Tabel
        document.querySelectorAll('.table-responsive table').forEach(function(table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
                return th.textContent.trim();
            });
            
            // Deteksi jika tabel berisi data pelanggaran (memiliki kolom NISN atau Pelanggaran)
            const headersLower = headers.map(h => h.toLowerCase());
            if (headersLower.includes('pelanggaran') || headersLower.includes('nisn')) {
                table.classList.add('table-pelanggaran-mobile');
            }

            table.querySelectorAll('tbody tr').forEach(function(row) {
                row.querySelectorAll('td').forEach(function(td, index) {
                    if (headers[index]) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
