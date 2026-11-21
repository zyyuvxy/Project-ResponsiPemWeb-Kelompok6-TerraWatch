# TerraWatch - Observe, Report, Protect
**Platform Web Partisipatif Pelaporan Kerusakan Lingkungan Darat**  
*Mendukung Aksi Nyata Sustainable Development Goals (SDG 15 – Life on Land)*  
*Proyek Akhir Praktikum Pemrograman Web I*

---

## 🌿 1. Deskripsi Proyek & Relevansi SDG 15
**TerraWatch** adalah aplikasi web pelaporan dan pemantauan kerusakan lingkungan darat yang dibangun menggunakan **PHP Native + PDO + MySQL**. Aplikasi ini dirancang untuk memfasilitasi peran aktif warga masyarakat dalam mendokumentasikan serta melaporkan berbagai insiden kerusakan ekosistem darat, seperti pembalakan liar, kebakaran hutan/lahan, pembuangan limbah padat, perusakan habitat satwa liar, degradasi lahan, dan pencemaran tanah.

Platform ini berkontribusi langsung pada pencapaian **SDG 15 (Life on Land)**, khususnya:
- **Target 15.1 & 15.2:** Pelestarian dan pengelolaan berkelanjutan kawasan hutan daratan.
- **Target 15.5:** Penghentian perusakan habitat alami dan pencegahan kepunahan keanekaragaman hayati.
- **Target 15.7:** Aksi mendesak mengakhiri perburuan liar dan perdagangan spesies terlindungi.

---

## 🔑 2. Akun Demo untuk Pengujian

Aplikasi telah dilengkapi *seed data* akun siap pakai dengan hak akses berbeda:

| Peran (Role) | Alamat Email | Kata Sandi | Deskripsi Hak Akses |
|---|---|---|---|
| **Administrator** | `admin@terrawatch.id` | `admin123` | Akses penuh dashboard admin, verifikasi laporan, kelola kategori, artikel, pengguna (aktif/nonaktif), dan log audit. |
| **Pelapor Warga** | `budi@terrawatch.id` | `user123` | Akses dashboard pelapor, buat laporan berbukti foto 1–5 & koordinat GPS, edit laporan (Pending/Revision), profil, cetak laporan. |

> **Catatan Keamanan:** Pengguna baru juga dapat mendaftar langsung melalui menu **Daftar Pelapor** (`auth/register.php`). Akun yang dinonaktifkan oleh administrator otomatis ditolak saat berusaha login.

---

## 🔄 3. Alur Status Laporan & Aturan Transisi

Alur status diatur secara ketat di sisi server (`ADMIN_TRANSITIONS` dan `NOTE_REQUIRED`) untuk mencegah inkonsistensi data:

```
[ Pelapor ]      -- Buat Laporan Baru --> (Pending)
                                              |
[ Administrator] -- Verifikasi -------------> (Verified) ---> Mulai Tindak Lanjut ---> (In Progress) ---> Selesai ---> (Completed) [FINAL]
                 -- Minta Perbaikan --------> (Revision)*
                 -- Tolak Laporan ----------> (Rejected)* [FINAL]
                                                  |
[ Pelapor ]      -- Kirim Ulang Perbaikan --------+-----> (Pending)
```

### Tabel Transisi Status

| Status Asal | Status Tujuan | Pelaku Aksi | Catatan Wajib? | Keterangan Alur |
|---|---|---|---|---|
| *(Baru)* | `Pending` | Pelapor | Tidak | Laporan pertama kali dikirim pelapor dengan 1–5 foto bukti. |
| `Pending` | `Verified` | Admin | Tidak | Admin memvalidasi keabsahan bukti foto dan titik koordinat. |
| `Pending` | `Revision` | Admin | **Ya** | Admin meminta pelapor melengkapi foto/data yang kurang. |
| `Pending` | `Rejected` | Admin | **Ya** | Admin menolak laporan fiktif/tidak berdasar (alasan tersimpan di `reports.rejection_reason`). |
| `Revision` | `Pending` | Pelapor | Tidak | Pelapor mengedit laporan dan mengirim perbaikan (notifikasi otomatis ke admin). |
| `Verified` | `In Progress` | Admin | Tidak | Tim verifikator atau penanganan lapangan mulai menindaklanjuti lokasi. |
| `In Progress` | `Completed` | Admin | **Ya** | Insiden tuntas ditangani beserta ringkasan hasil tindak lanjut. |

> `Completed` dan `Rejected` bersifat **final** (tidak dapat diubah kembali).

---

## ✨ 4. Fitur Utama Sistem

### A. Sisi Pelapor (User)
1. **Dasbor Warga Interaktif:**
   - Ringkasan statistik riil bersumber dari database (Total Laporan, Menunggu Verifikasi, Sedang Diproses, Laporan Selesai).
   - Kartu Laporan Terverifikasi riil menggantikan klaim fiktif.
   - Peta Lokasi Laporan Saya berbasis Leaflet.js dengan marker koordinat insiden milik pelapor.
   - Form cepat lokasi insiden yang mengarahkan ke form detail.
   - Tombol **Cetak** ramah dokumen (`window.print()`) dengan CSS `@media print` otomatis menyembunyikan navigasi.
2. **Form Buat Laporan Berstandar Bukti:**
   - Wajib 1 sampai 5 foto bukti lapangan (format JPG, JPEG, PNG, maks. 2 MB per berkas, total maks. 8 MB).
   - Validasi sisi server (`uploadImage()`, MIME type `finfo`, validasi struktur gambar riil `getimagesize()`, proteksi `post_max_size`).
   - Sisi klien interaktif: preview foto dengan tombol hapus per berkas berbasis `DataTransfer` dan validasi SweetAlert2.
   - Penentuan titik lokasi kejadian via integrasi peta Leaflet picker dan GPS perangkat.
3. **Edit & Perbaikan Laporan:**
   - Hanya diperbolehkan pada status `Pending` dan `Revision`.
   - Mengelola foto tersimpan (fitur centang hapus foto lama) dan unggah foto baru dengan kalkulasi ketat tetap 1–5 foto.
   - Pengiriman revisi otomatis mencatat audit di `report_updates` dan memberi notifikasi ke seluruh admin aktif.
4. **Pusat Notifikasi & Profil:**
   - Notifikasi riil saat status laporan diperbarui admin atau saat akun aktif.
   - Unggah foto profil tersimpan aman di `uploads/profiles/`.
   - Penggantian kata sandi berenkripsi bcrypt.

### B. Sisi Administrator
1. **Dasbor Statistik & Audit:**
   - 6 Kartu status lengkap (`Pending`, `Revision`, `Verified`, `In Progress`, `Completed`, `Rejected`).
   - Visualisasi grafik Doughnut Chart.js 6 status dengan palet warna natural.
   - Blok "Perlu Tindakan" menampilkan laporan berstatus `Pending` terbaru yang memerlukan respon verifikator.
2. **Verifikasi Laporan:**
   - Tombol tindakan kontekstual sesuai status saat ini (Verifikasi, Minta Perbaikan, Tolak, Mulai Tindak Lanjut, Tandai Selesai).
   - Modal konfirmasi SweetAlert2 sebelum eksekusi status.
   - Validasi atomik (`WHERE report_id=? AND status=?`) mencegah *race condition* antar admin.
3. **Manajemen Pengguna & Kontrol Akun:**
   - Tinjau seluruh akun terdaftar beserta total laporan yang dikirimkan.
   - Tombol aktifkan / nonaktifkan akun (`users.is_active`). Admin dilarang menonaktifkan dirinya sendiri atau sesama admin.
4. **Manajemen Kategori & Artikel Edukasi:**
   - CRUD Kategori lingkungan dan publikasi artikel literasi konservasi.
5. **Log Aktivitas & Notifikasi Admin:**
   - Riwayat audit trail IP address dan aktivitas pengguna.
   - Menu notifikasi admin tersinkronisasi badge lonceng di topbar dan sidebar saat ada laporan baru atau revisi masuk.

---

## 🎨 5. Palet Warna Resmi (Minimal Natural SDG 15)

Antarmuka dibangun secara konsisten tanpa merusak layout, menggunakan palet warna terestrial berikut:

| Nama Warna | Kode Hex | Penggunaan Utama |
|---|---|---|
| **Pine** | `#34483B` | Warna dominan, Sidebar, Header, Tombol utama, Teks judul |
| **Sage** | `#789078` | Aksen hijau daun, progress bar, border aktif, status terverifikasi |
| **Soft Leaf** | `#B5C2AA` | Garis tepi upload box, lingkaran progress, hover elemen |
| **Cloud Cream** | `#F6F6F0` | Latar belakang halaman aplikasi, card background lembut |
| **Stone Beige** | `#D6CBB5` | Border kartu, garis pembatas tabel, separator form |
| **Terracotta** | `#C17B58` | Aksen peringatan, status ditolak/hapus, tombol CTA sekunder |

---

## 📂 6. Struktur Direktori Proyek

```
TeraWatch/
├── admin/                      # Modul Halaman Administrator
│   ├── dashboard.php           # Dasbor ringkasan admin & grafik 6 status
│   ├── detail_laporan.php      # Verifikasi & perubahan status laporan
│   ├── ekspor_laporan.php      # Ekspor data CSV laporan
│   ├── log_aktivitas.php       # Audit trail aktivitas pengguna & admin
│   ├── manajemen_artikel.php   # Manajemen artikel konservasi
│   ├── manajemen_kategori.php  # Kelola kategori kerusakan lingkungan
│   ├── manajemen_laporan.php   # Filter & monitoring seluruh laporan
│   ├── manajemen_pengguna.php  # Manajemen pengguna & aktivasi akun
│   ├── notifikasi.php          # Pusat notifikasi admin
│   └── tambah_artikel.php      # Form tambah / edit artikel edukasi
├── assets/                     # Aset Statis
│   ├── css/style.css           # Lembar gaya utama palet natural
│   ├── img/                    # Gambar & placeholder ikon
│   └── js/                     # Skrip pembantu
├── auth/                       # Modul Autentikasi
│   ├── login.php               # Login terpadu (role user/admin, cek is_active)
│   ├── logout.php              # Logout & pembersihan sesi
│   └── register.php            # Pendaftaran akun pelapor warga
├── config/                     # Konfigurasi Sistem
│   ├── app.php                 # Konstanta URL, batasan berkas, dan palet
│   └── database.php            # Koneksi PDO MySQL & penanganan error
├── database/                   # Skrip Database
│   └── terrawatch_db.sql       # Skema DDL & seed data bersih
├── helpers/                    # Fungsi Pembantu Global
│   └── functions.php           # Sanitasi e(), uploadImage(), requireUser/Admin(), alur status
├── includes/                   # Komponen Tata Letak Bersama
│   ├── admin_sidebar.php       # Sidebar navigasi admin
│   ├── footer.php              # Footer terpadu aplikasi
│   ├── header.php              # Header & pemanggilan stylesheet
│   ├── sidebar.php             # Sidebar navigasi pelapor warga
│   └── topbar.php              # Topbar info tanggal, profil & lonceng notifikasi
├── uploads/                    # Direktori Penyimpanan Berkas Dinamis
│   ├── .htaccess               # Proteksi pencegahan eksekusi skrip PHP
│   ├── articles/               # Gambar sampul artikel
│   ├── profiles/               # Foto profil pengguna
│   └── reports/                # Foto bukti laporan terestrial (16-hex acak)
├── user/                       # Modul Halaman Pelapor Warga
│   ├── artikel.php             # Katalog artikel edukasi publik
│   ├── buat_laporan.php        # Form buat laporan (1–5 foto + GPS Leaflet)
│   ├── dashboard.php           # Dasbor pelapor warga + cetak bersih
│   ├── detail_artikel.php      # Baca artikel lengkap
│   ├── detail_laporan.php      # Detail status laporan pribadi & linimasa
│   ├── detail_publik.php       # Detail laporan terbuka publik
│   ├── edit_laporan.php        # Edit/perbaikan laporan (Pending/Revision)
│   ├── hapus_laporan.php       # Hapus laporan draft/pending
│   ├── laporan_publik.php      # Katalog peta & daftar laporan terverifikasi
│   ├── laporan_saya.php        # Riwayat pelaporan saya
│   ├── notifikasi.php          # Notifikasi status pelapor
│   └── profil.php              # Pengaturan profil & ubah sandi
├── index.php                   # Landing Page publik SDG 15
└── README.md                   # Dokumentasi teknis proyek
```

---

## 🛠️ 7. Panduan Instalasi & Pengaturan Lingkungan

### 1. Kebutuhan Perangkat Lunak
- **XAMPP** dengan PHP versi 8.0 atau lebih tinggi.
- Web Server **Apache** dan DBMS **MySQL / MariaDB**.
- Ekstensi PHP wajib aktif: `pdo_mysql`, `fileinfo`.

### 2. Pengaturan `php.ini`
Buka berkas `php.ini` (melalui XAMPP Control Panel > Tombol Config di Apache > `php.ini`), kemudian pastikan direktif berikut disesuaikan untuk mendukung upload bukti foto:
```ini
upload_max_filesize = 8M
post_max_size = 16M
memory_limit = 128M
max_file_uploads = 20
```
*Simpan berkas `php.ini`, lalu lakukan **Restart Apache** di XAMPP Control Panel.*

### 3. Pemasangan Database
1. Buka browser dan akses **phpMyAdmin** (`http://localhost/phpmyadmin/` atau port XAMPP Anda).
2. Buat database baru bernama: `terrawatch_db`.
3. Buka tab **Import**, pilih berkas:
   ```
   c:\xampp\htdocs\TeraWatch\database\terrawatch_db.sql
   ```
4. Klik tombol **Import / Kirim**. Skema tabel, foreign key, dan data awal akan tercipta secara otomatis tanpa kendala.

### 4. Menjalankan Aplikasi
Akses aplikasi melalui peramban web pada tautan:
```
http://localhost/TeraWatch/
```
*(Sesuaikan nomor port jika Apache Anda menggunakan port kustom, misalnya `http://localhost:8080/TeraWatch/`)*.

---

## 🛡️ 8. Aspek Kualitas & Keamanan yang Diterapkan
- **Pencegahan SQL Injection:** 100% operasi query basis data menggunakan Prepared Statement PDO (`$stmt->prepare()` dan `$stmt->execute()`).
- **Pencegahan Cross-Site Scripting (XSS):** Semua data yang berasal dari input pengguna atau database di-*escape* saat pencetakan HTML menggunakan helper `e($string)` / `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Sanitasi Bersih:** Input teks diolah dengan `trim()` tanpa distorsi `stripslashes` atau double-escaping.
- **Keamanan Berkas Unggahan:**
  - Penamaan berkas acak kriptografis 16 karakter hex (`bin2hex(random_bytes(8))`).
  - Validasi multi-lapis: ekstensi berkas, ukuran, `finfo(FILEINFO_MIME_TYPE)`, serta integritas struktur gambar asli `getimagesize()`.
  - Berkas [uploads/.htaccess](file:///c:/xampp/htdocs/TeraWatch/uploads/.htaccess) mematikan eksekusi skrip PHP di folder media.
- **Integritas Transaksi:** Operasi penulisan laporan dan riwayat status dijalankan dalam blok Database Transaction (`beginTransaction()`, `commit()`, `rollBack()`) dengan *auto-cleanup* file fisik jika terjadi kendala.
