# Sistem Informasi Bimbingan Konseling (SI BK - SMAN 7 Bungo)

Aplikasi web Sistem Informasi Bimbingan Konseling (BK) berbasis PHP Native & MySQL.

---

## 🚀 Panduan Menjalankan Project di Laptop (XAMPP / Localhost)

### 1. Prasyarat
- **XAMPP** (dengan PHP versi 7.4, 8.0, 8.1, 8.2, atau 8.3)
- Web browser (Google Chrome, Edge, Firefox, dll)

### 2. Langkah Instalasi

1. **Clone atau Letakkan Project di Folder `htdocs`:**
   - Masuk ke direktori instalasi XAMPP, biasanya:
     - **Windows:** `C:\xampp\htdocs\`
     - **macOS:** `/Applications/XAMPP/xamppfiles/htdocs/`
     - **Linux:** `/opt/lampp/htdocs/`
   - Letakkan folder project ini di dalam `htdocs/`, contoh nama folder: `bk-paseban035` atau `bk`.

2. **Jalankan Apache dan MySQL:**
   - Buka **XAMPP Control Panel**.
   - Klik tombol **Start** pada modul **Apache** dan **MySQL**.

3. **Import Database:**
   - Buka browser dan kunjungi: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
   - Klik **New** (Baru) dan buat database dengan nama: `db_bk7`.
   - Pilih database `db_bk7` yang baru dibuat.
   - Klik tab **Import**.
   - Klik **Choose File** (Pilih Berkas) dan pilih file: `database/database_bk.sql` yang ada di dalam folder project ini.
   - Gulir ke bawah dan klik tombol **Import** (atau **Go**).

4. **Konfigurasi Database (Opsional):**
   - Secara default, aplikasi sudah otomatis mendeteksi konfigurasi standar XAMPP:
     - **Host:** `localhost`
     - **User:** `root`
     - **Password:** *(kosong)*
     - **Database:** `db_bk7`
   - Jika MySQL XAMPP Anda memiliki password khusus, Anda cukup membuat file `.env` di root folder project (bisa menyalin dari `.env.example`):
     ```env
     DB_HOST=localhost
     DB_USERNAME=root
     DB_PASSWORD=password_mysql_anda
     DB_DATABASE=db_bk7
     ```

5. **Akses Aplikasi:**
   - Buka browser dan akses URL:
     ```text
     http://localhost/bk-paseban035/
     ```
     *(Sesuaikan nama folder jika Anda mengubah nama foldernya di dalam htdocs)*.

---

## 🔐 Akun Login Default (Data Uji Coba)

Semua akun default menggunakan password: `password`

| Role / Peran | Username / NIP / NISN | Password | Keterangan |
|---|---|---|---|
| **Administrator** | `admin` | `password` | Pengelola master data & sistem |
| **Guru BK** | `198001012005011001` | `password` | Budi Santoso, S.Pd |
| **Wali Kelas** | `198505052010012002` | `password` | Siti Aminah, S.Pd (Wali Kelas X.A) |
| **Siswa** | `0012345678` | `password` | Ahmad Dhani (Kelas X.A) |

---

## 📁 Struktur Direktori Project

```text
├── admin/               # Halaman panel Administrator
├── assets/              # File aset CSS, JS, Gambar, dan Upload
│   ├── css/             # Stylesheet aplikasi
│   ├── js/              # Javascript
│   └── uploads/profil/  # Direktori penyimpanan foto profil
├── config/              # Konfigurasi koneksi database & helper
│   └── koneksi.php      # Koneksi database otomatis (Local & Docker)
├── database/            # File SQL skema & data seeding
│   └── database_bk.sql  # Dump database untuk phpMyAdmin
├── guru_bk/             # Halaman panel Guru BK
├── siswa/               # Halaman panel Siswa
├── wali_kelas/          # Halaman panel Wali Kelas
├── .env.example         # Template konfigurasi environment
├── .gitignore           # Aturan berkas yang diabaikan git
├── index.php            # Halaman login utama
├── proses_login.php     # Logika otentikasi login
├── lupa_password.php    # Fitur lupa sandi / pemulihan akun
└── reset_password.php   # Fitur ubah sandi baru
```
