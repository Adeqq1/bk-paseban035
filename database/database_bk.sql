-- =============================================================
--  DATABASE  : db_bk7
--  SISTEM    : Sistem Informasi Bimbingan Konseling (SI BK7)
--  KETERANGAN: Skrip ini membuat ulang seluruh database beserta
--              tabel dan data awal (seeding) untuk sistem SI BK7.
--              Urutan pembuatan tabel mengikuti dependensi FK.
-- =============================================================

DROP DATABASE IF EXISTS db_bk7;
CREATE DATABASE db_bk7
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_bk7;

-- =============================================================
--  BAGIAN 1 : TABEL AUTENTIKASI & PENGGUNA
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : user
--  Fungsi: Menyimpan akun login semua pengguna (admin, guru,
--          wali kelas, dan siswa).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user (
    id         INT           NOT NULL AUTO_INCREMENT,
    username   VARCHAR(50)   NOT NULL UNIQUE,
    email      VARCHAR(100)           DEFAULT NULL,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin', 'guru_bk', 'wali_kelas', 'siswa') NOT NULL,
    foto       VARCHAR(255)           DEFAULT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB;


-- =============================================================
--  BAGIAN 2 : TABEL MASTER DATA SEKOLAH
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : guru
--  Fungsi: Menyimpan data Guru BK dan Wali Kelas.
--          Terhubung ke tabel users melalui user_id.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guru (
    id           INT           NOT NULL AUTO_INCREMENT,
    user_id      INT           NOT NULL,
    nip          VARCHAR(20)   NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100)  NOT NULL,
    jabatan      ENUM('Guru BK', 'Wali Kelas') NOT NULL,

    PRIMARY KEY (id),
    CONSTRAINT fk_guru_user
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- -------------------------------------------------------------
--  Tabel : kelas
--  Fungsi: Menyimpan data kelas dan relasi ke wali kelas.
--          wali_kelas_id merujuk ke guru yang bertindak
--          sebagai wali kelas kelas tersebut.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelas (
    id            INT          NOT NULL AUTO_INCREMENT,
    nama_kelas    VARCHAR(20)  NOT NULL UNIQUE,
    wali_kelas_id INT                   DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_kelas_walikelas (wali_kelas_id),
    CONSTRAINT fk_kelas_walikelas
        FOREIGN KEY (wali_kelas_id) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- -------------------------------------------------------------
--  Tabel : siswa
--  Fungsi: Menyimpan data lengkap siswa beserta relasi ke
--          akun login (users) dan kelas (kelas).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS siswa (
    id            INT           NOT NULL AUTO_INCREMENT,
    user_id       INT           NOT NULL,
    nisn          VARCHAR(20)   NOT NULL UNIQUE,
    nama_lengkap  VARCHAR(100)  NOT NULL,
    kelas_id      INT                    DEFAULT NULL,
    jenis_kelamin ENUM('L', 'P')         DEFAULT NULL,
    tempat_lahir  VARCHAR(50)            DEFAULT NULL,
    tanggal_lahir DATE                   DEFAULT NULL,
    alamat        TEXT                   DEFAULT NULL,
    foto          VARCHAR(255)           DEFAULT NULL,
    status        ENUM('aktif', 'alumni') NOT NULL DEFAULT 'aktif',

    PRIMARY KEY (id),
    CONSTRAINT fk_siswa_user
        FOREIGN KEY (user_id)  REFERENCES user(id) ON DELETE CASCADE,
    CONSTRAINT fk_siswa_kelas
        FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- -------------------------------------------------------------
--  Tabel : jenis_pelanggaran
--  Fungsi: Menyimpan daftar master jenis pelanggaran beserta
--          poin sanksi dan kategori tingkat keparahannya.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jenis_pelanggaran (
    id               INT           NOT NULL AUTO_INCREMENT,
    nama_pelanggaran VARCHAR(255)  NOT NULL,
    poin             INT                    DEFAULT 0,
    kategori         ENUM('Ringan', 'Sedang', 'Berat') NOT NULL,

    PRIMARY KEY (id)
) ENGINE=InnoDB;


-- =============================================================
--  BAGIAN 3 : TABEL PELANGGARAN & BIMBINGAN KONSELING
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : catatan_pelanggaran
--  Fungsi: Mencatat setiap laporan pelanggaran siswa yang
--          dibuat oleh Wali Kelas. Guru BK akan menindaklanjuti
--          laporan ini melalui tabel konseling.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catatan_pelanggaran (
    id             INT           NOT NULL AUTO_INCREMENT,
    siswa_id       INT           NOT NULL,   -- Siswa yang melanggar
    pelanggaran_id INT           NOT NULL,   -- Jenis pelanggaran
    guru_id        INT           NOT NULL,   -- Wali Kelas pelapor
    pelapor_asli   VARCHAR(100)             DEFAULT NULL, -- Nama guru/piket asli (opsional)
    tanggal        DATE                     DEFAULT (CURRENT_DATE),
    keterangan     TEXT                     DEFAULT NULL,

    PRIMARY KEY (id),
    CONSTRAINT fk_cp_siswa
        FOREIGN KEY (siswa_id)       REFERENCES siswa(id)            ON DELETE CASCADE,
    CONSTRAINT fk_cp_pelanggaran
        FOREIGN KEY (pelanggaran_id) REFERENCES jenis_pelanggaran(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_guru
        FOREIGN KEY (guru_id)        REFERENCES guru(id)             ON DELETE CASCADE
) ENGINE=InnoDB;


-- -------------------------------------------------------------
--  Tabel : konseling
--  Fungsi: Menyimpan hasil bimbingan konseling yang dilakukan
--          oleh Guru BK. Terdiri dari dua jenis:
--            1. 'Tindak Lanjut' — dari laporan pelanggaran
--               (relasi ke catatan_pelanggaran)
--            2. 'Mandiri'       — siswa mengajukan sendiri
--               (relasi ke jadwal_bimbingan)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS konseling (
    id                     INT           NOT NULL AUTO_INCREMENT,
    siswa_id               INT           NOT NULL,
    guru_id                INT           NOT NULL,
    tanggal                DATE                   DEFAULT (CURRENT_DATE),
    masalah                TEXT          NOT NULL,
    solusi                 TEXT                   DEFAULT NULL,
    status                 ENUM('Proses', 'Selesai') DEFAULT 'Proses',
    -- Kolom khusus Tindak Lanjut Pelanggaran
    catatan_pelanggaran_id INT                    DEFAULT NULL,
    -- Kolom khusus Bimbingan Mandiri
    topik_permasalahan     VARCHAR(255)           DEFAULT NULL,
    bidang_bimbingan       VARCHAR(100)           DEFAULT NULL,
    -- Data waktu & tempat pertemuan tatap muka langsung
    waktu_pertemuan        DATETIME               DEFAULT NULL,
    tempat_pertemuan       VARCHAR(255)           DEFAULT NULL,
    -- Jenis & status
    jenis_konseling        ENUM('Tindak Lanjut', 'Mandiri') DEFAULT 'Tindak Lanjut',

    PRIMARY KEY (id),
    CONSTRAINT fk_konseling_siswa
        FOREIGN KEY (siswa_id)               REFERENCES siswa(id)               ON DELETE CASCADE,
    CONSTRAINT fk_konseling_guru
        FOREIGN KEY (guru_id)                REFERENCES guru(id)                ON DELETE CASCADE,
    CONSTRAINT fk_konseling_catatan
        FOREIGN KEY (catatan_pelanggaran_id) REFERENCES catatan_pelanggaran(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- -------------------------------------------------------------
--  Tabel : jadwal_bimbingan
--  Fungsi: Mengelola pengajuan jadwal bimbingan yang dibuat
--          oleh SISWA secara mandiri.
--
--  Alur  :
--    Siswa mengajukan (status='Menunggu')
--      → Guru BK menyetujui / menolak
--      → Pertemuan dilakukan
--      → Guru BK mengarsipkan ke tabel konseling (status='Selesai')
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jadwal_bimbingan (
    id                 INT           NOT NULL AUTO_INCREMENT,
    -- Pihak yang terlibat
    siswa_id           INT           NOT NULL,   -- Siswa pengaju
    guru_id            INT           NOT NULL,   -- Guru BK tujuan
    -- Isi pengajuan dari siswa
    topik              VARCHAR(255)  NOT NULL,
    kategori_masalah   VARCHAR(50)            DEFAULT NULL,
    tanggal_preferensi DATE          NOT NULL,
    waktu_preferensi   ENUM(
                           'Pagi (07:00-09:00)',
                           'Istirahat (09:30-10:00)',
                           'Siang (11:00-12:00)',
                           'Siang (12:00-13:00)'
                       )                      DEFAULT NULL,
    catatan            TEXT                   DEFAULT NULL,
    bersifat_rahasia   TINYINT(1)             DEFAULT 0,
    -- Status & respons dari Guru BK
    status             ENUM('Menunggu', 'Disetujui', 'Ditolak', 'Selesai') DEFAULT 'Menunggu',
    tanggal_disetujui  DATETIME               DEFAULT NULL,
    lokasi             VARCHAR(100)           DEFAULT NULL,
    catatan_guru       TEXT                   DEFAULT NULL,
    -- Metadata
    created_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_jb_siswa
        FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_jb_guru
        FOREIGN KEY (guru_id)  REFERENCES guru(id)  ON DELETE CASCADE
) ENGINE=InnoDB;


-- =============================================================
--  BAGIAN 4 : TABEL PANGGILAN ORANG TUA
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : panggilan_orang_tua
--  Fungsi: Menyimpan surat/notifikasi panggilan orang tua
--          yang dibuat oleh Guru BK untuk siswa bermasalah.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS panggilan_orang_tua (
    id               INT           NOT NULL AUTO_INCREMENT,
    nomor_urut       VARCHAR(20)            DEFAULT NULL,
    siswa_id         INT           NOT NULL,
    guru_id          INT           NOT NULL,
    tanggal_panggilan DATE         DEFAULT NULL,
    jam_panggilan    TIME          DEFAULT NULL,
    tempat           VARCHAR(255)  NOT NULL,
    alasan           TEXT          NOT NULL,
    status           ENUM('Dikirim', 'Hadir', 'Tidak Hadir') DEFAULT 'Dikirim',
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_pot_siswa
        FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_pot_guru
        FOREIGN KEY (guru_id)  REFERENCES guru(id)  ON DELETE CASCADE
) ENGINE=InnoDB;


-- =============================================================
--  BAGIAN 5 : DATA AWAL (SEEDING)
--  CATATAN  : Digunakan untuk keperluan pengembangan dan uji
--             coba sistem. Semua password = "password".
-- =============================================================

-- Akun pengguna awal (Username Guru BK = NIP, Wali Kelas = NIP, Siswa = NISN)
INSERT INTO user (username, password, role) VALUES
    ('admin',              '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
    ('198001012005011001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru_bk'),
    ('198505052010012002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'wali_kelas'),
    ('0012345678',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siswa');

-- Data guru awal
INSERT INTO guru (user_id, nip, nama_lengkap, jabatan) VALUES
    (2, '198001012005011001', 'Budi Santoso, S.Pd', 'Guru BK'),
    (3, '198505052010012002', 'Siti Aminah, S.Pd',  'Wali Kelas');

-- Data kelas (wali kelas merujuk id guru di atas: id=2)
INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES
    ('X.A',    2), ('X.B',  NULL), ('X.C',  NULL), ('X.D',  NULL), ('X.E',  NULL),
    ('X.F',  NULL), ('X.G',  NULL),
    ('XI.A1', NULL), ('XI.A2', NULL), ('XI.B1', NULL), ('XI.B2', NULL),
    ('XI.C1', NULL), ('XI.C2', NULL),
    ('XII.A1', NULL), ('XII.A2', NULL), ('XII.B1', NULL), ('XII.B2', NULL),
    ('XII.C1', NULL), ('XII.C2', NULL);

-- Data siswa awal
INSERT INTO siswa (user_id, nisn, nama_lengkap, kelas_id, jenis_kelamin) VALUES
    (4, '0012345678', 'Ahmad Dhani', 1, 'L');

-- Daftar jenis pelanggaran beserta poin dan kategori
INSERT INTO jenis_pelanggaran (nama_pelanggaran, poin, kategori) VALUES
    -- Kategori Ringan
    ('Terlambat masuk sekolah',                                        5,  'Ringan'),
    ('Berpakaian tidak rapi / atribut tidak lengkap',                  5,  'Ringan'),
    ('Membuang sampah sembarangan',                                     5,  'Ringan'),
    ('Tidak melaksanakan tugas piket kelas',                           5,  'Ringan'),
    ('Siswa laki-laki berambut gondrong / tidak rapi',                 10, 'Ringan'),
    ('Membuat kegaduhan saat jam pelajaran',                           10, 'Ringan'),
    ('Keluar lingkungan sekolah tanpa izin saat jam pelajaran',        15, 'Ringan'),
    -- Kategori Sedang
    ('Membolos / meninggalkan kelas tanpa keterangan',                 20, 'Sedang'),
    ('Membawa/menggunakan HP di kelas saat jam pelajaran tanpa izin',  25, 'Sedang'),
    ('Melakukan perundungan (bullying) secara verbal',                 30, 'Sedang'),
    ('Bersikap tidak sopan atau membangkang perintah Guru',           30, 'Sedang'),
    ('Merusak fasilitas sekolah (mencoret meja/dinding)',              40, 'Sedang'),
    ('Merokok di lingkungan sekolah / membawa rokok',                  50, 'Sedang'),
    -- Kategori Berat
    ('Melakukan perjudian atau membawa kartu judi',                    75,  'Berat'),
    ('Membawa senjata tajam atau senjata berbahaya',                   100, 'Berat'),
    ('Tawuran atau perkelahian di dalam/luar sekolah',                 100, 'Berat'),
    ('Melakukan kekerasan fisik atau penganiayaan',                    100, 'Berat'),
    ('Mengonsumsi atau membawa minuman keras',                         100, 'Berat'),
    ('Mengonsumsi, membawa, atau mengedarkan narkoba',                 100, 'Berat'),
    ('Melakukan tindakan asusila atau pelecehan',                      100, 'Berat'),
    ('Mencuri barang milik sekolah atau milik orang lain',             100, 'Berat');

-- =============================================================
--  BAGIAN 4.5 : TABEL ALIH TANGAN KASUS
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : alih_kasus
--  Fungsi: Mencatat data Alih Tangan Kasus yang dirujuk
--          kepada pihak/ahli lain oleh Guru BK.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alih_kasus (
    id                INT           NOT NULL AUTO_INCREMENT,
    nomor_urut        VARCHAR(20)            DEFAULT NULL,
    siswa_id          INT           NOT NULL,
    guru_id           INT           NOT NULL,
    tanggal           DATE          NOT NULL,
    ringkasan_masalah TEXT          NOT NULL,
    penerima_kasus    VARCHAR(255)  NOT NULL,
    jabatan_penerima  VARCHAR(255)           DEFAULT NULL,
    alamat_penerima   TEXT                   DEFAULT NULL,
    nip_kepsek        VARCHAR(50)            DEFAULT NULL,
    nama_kepsek       VARCHAR(255)           DEFAULT NULL,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_alih_siswa
        FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_alih_guru
        FOREIGN KEY (guru_id)  REFERENCES guru(id)  ON DELETE CASCADE
) ENGINE=InnoDB;


-- =============================================================
--  BAGIAN 4.6 : TABEL KUNJUNGAN RUMAH (HOME VISIT)
-- =============================================================

-- -------------------------------------------------------------
--  Tabel : kunjungan_rumah
--  Fungsi: Menyimpan data laporan kunjungan rumah (home visit)
--          yang dilakukan oleh Guru BK ke rumah siswa.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kunjungan_rumah (
    id                  INT           NOT NULL AUTO_INCREMENT,
    nomor_urut          VARCHAR(20)            DEFAULT NULL,
    siswa_id            INT           NOT NULL,
    guru_id             INT           NOT NULL,
    tanggal_pelaksanaan DATE          NOT NULL,
    nama_ortu           VARCHAR(100)  NOT NULL,
    alamat              TEXT          NOT NULL,
    yang_ditemui        VARCHAR(100)  NOT NULL,
    permasalahan        TEXT          NOT NULL,
    tujuan_home_visit   TEXT          NOT NULL,
    hasil_home_visit    TEXT          NOT NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_kunjungan_siswa
        FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    CONSTRAINT fk_kunjungan_guru
        FOREIGN KEY (guru_id)  REFERENCES guru(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
--  SELESAI
-- =============================================================

