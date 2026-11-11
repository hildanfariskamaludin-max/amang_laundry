<div align="center">

# ⚽ AMANG LAUNDRY #7

### Portugal Football Edition — CR7 Themed Laundry Management System

Sistem manajemen laundry berbasis web dengan **enkripsi AES-256-GCM** untuk seluruh data pelanggan.
Data asli hanya bisa dibaca jika admin memasukkan password yang benar — kalau salah, yang tampil adalah
**ciphertext mentah dari database**.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![OpenSSL](https://img.shields.io/badge/AES--256--GCM-Encrypted-DA291C?style=for-the-badge&logo=openssl&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-D4AF37?style=for-the-badge)

</div>

---

## 📖 Tentang Project

**Amang Laundry** adalah aplikasi laundry sederhana dengan tema tim nasional Portugal & Cristiano Ronaldo (CR7).
Project ini dibuat sebagai implementasi nyata **kriptografi di sisi aplikasi**: bukan cuma menyimpan data ke MySQL,
tapi seluruh isi tabel `pesanan` dan `layanan` disimpan dalam bentuk terenkripsi.

Password admin dipakai sebagai bahan derivasi kunci enkripsi (PBKDF2, 100.000 iterasi).
Artinya: **password benar = data terbaca**, **password salah = data tetap terenkripsi dan tidak bisa dibuka**.

---

## ✨ Fitur

### 🌐 Halaman Publik (`index.php`)

| Fitur | Keterangan |
|---|---|
| Landing page | Hero section bertema Portugal dengan warna hijau, merah, dan gold |
| Daftar layanan | 4 kartu layanan unggulan (Cuci Reguler, Cuci Express, Cuci Setrika, Setrika Saja) |
| Tabel harga | Harga per kilogram diambil langsung dari database terenkripsi |
| Tentang kami | Profil singkat Amang Laundry edisi Portugal |
| Login admin | Form login dengan validasi CSRF dan toggle show/hide password |

### 🖥️ Panel Admin (`admin.php`)

| Fitur | Keterangan |
|---|---|
| Dashboard | Statistik total pesanan, menunggu, diproses, dan selesai/diambil |
| Tabel pesanan | Daftar pesanan terbaru & seluruh pesanan dengan badge status berwarna |
| Tambah pesanan | Form input pesanan, total harga dihitung otomatis, data langsung dienkripsi |
| Edit pesanan | Ubah data pesanan, lalu dienkripsi ulang dengan nonce baru |
| Detail pesanan | Halaman rincian satu pesanan |
| Hapus pesanan | Dengan konfirmasi sebelum menghapus |
| CRUD layanan | Tambah, edit, dan hapus jenis layanan beserta tarif per kg |
| Mode ciphertext | Jika password salah, seluruh tabel & statistik menampilkan string acak dari database |

### 🔐 Keamanan

- **AES-256-GCM** (authenticated encryption) via OpenSSL
- **PBKDF2-SHA256**, 100.000 iterasi, salt 16 byte, untuk derivasi kunci dari password admin
- **Nonce acak 12 byte** untuk setiap baris data — tidak ada nonce yang dipakai dua kali
- **Bcrypt** (`password_hash`) untuk menyimpan password admin
- **CSRF token** di setiap form (`hash_equals` untuk perbandingan aman)
- **Session hardening**: `httponly`, `samesite=Lax`, `session_regenerate_id(true)` saat login
- **Output escaping** dengan `htmlspecialchars()` di seluruh tampilan
- **Prepared statement (PDO)** di semua query — anti SQL injection

---

## 📸 Screenshot

> **Catatan:** taruh file gambar ke folder [`screenshots/`](screenshots/) dengan nama persis seperti di bawah ini.
> Selama file belum ada, gambar akan tampil sebagai broken image.

### Halaman Publik

#### 1. Landing Page — Hero Section
![Landing Page Hero](screenshots/01-landing-hero.png)

#### 2. Layanan Unggulan
![Layanan Unggulan](screenshots/02-layanan.png)

#### 3. Daftar Harga Resmi
![Daftar Harga](screenshots/03-harga.png)

#### 4. Tentang Amang Laundry
![Tentang Kami](screenshots/04-tentang.png)

#### 5. Login Administrator
![Login Admin](screenshots/05-login-admin.png)

### Panel Admin

#### 6. Dashboard — Password Benar (data asli terdekripsi)
![Dashboard Password Benar](screenshots/06-dashboard-password-benar.png)

#### 7. Dashboard — Password Salah (ciphertext dari database)
![Dashboard Password Salah](screenshots/07-dashboard-password-salah.png)

#### 8. Tabel Pesanan
![Tabel Pesanan](screenshots/08-tabel-pesanan.png)

#### 9. Tambah Pesanan Baru
![Tambah Pesanan](screenshots/09-tambah-pesanan.png)

#### 10. Detail Pesanan
![Detail Pesanan](screenshots/10-detail-pesanan.png)

#### 11. Data Layanan & Harga
![Data Layanan](screenshots/11-data-layanan.png)

### Tampilan Mobile

#### 12. Responsive di Smartphone
![Mobile Responsive](screenshots/12-mobile-responsive.png)

---

## 🛠️ Teknologi

| Komponen | Detail |
|---|---|
| Bahasa | PHP 8.0+ (native, tanpa framework) |
| Database | MySQL / MariaDB |
| Akses Data | PDO + prepared statement |
| Enkripsi | OpenSSL — `aes-256-gcm` |
| KDF | `hash_pbkdf2('sha256', ...)` |
| Frontend | HTML5, CSS3 (custom, tanpa library), vanilla JavaScript |
| Server Lokal | Laragon / XAMPP |

---

## 📋 Kebutuhan Sistem

- PHP **8.0** atau lebih baru
- Extension PHP: `pdo_mysql`, `openssl`, `mbstring`
- MySQL 5.7+ / MariaDB 10.3+
- Web server: Apache atau Nginx (Laragon/XAMPP sudah cukup)

---

## 🚀 Instalasi

**1. Clone repository**

```bash
git clone https://github.com/hildanfariskamaludin-max/amang_laundry.git
```

**2. Pindahkan ke folder web server**

```text
C:\laragon\www\Amang_Laundry
```

**3. Buat database**

Buka phpMyAdmin / HeidiSQL, lalu buat database baru:

```sql
CREATE DATABASE amang_laundry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> Tabel (`admin`, `pesanan`, `layanan`) **dibuat otomatis** saat pertama kali halaman dibuka,
> lengkap dengan akun admin default, 4 layanan standar, dan 1 contoh pesanan.

**4. Sesuaikan konfigurasi database** (jika perlu)

Edit bagian atas [`config.php`](config.php):

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'amang_laundry');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**5. Jalankan**

```text
http://localhost/Amang_Laundry/index.php
```

Panel admin: `http://localhost/Amang_Laundry/admin.php`

---

## 🔑 Akun Default

| Field | Value |
|---|---|
| Username | `Kemal` |
| Password | `Portugal 33` |

> ⚠️ **Wajib diganti** sebelum dipakai di production. Lihat [Catatan Keamanan](#-catatan-keamanan).

---

## 🔐 Cara Kerja Enkripsi

```text
Password Admin
      │
      ├─► password_verify()  ──► validasi login (bcrypt)
      │
      └─► PBKDF2-SHA256 (100.000 iterasi, salt dari hash bcrypt)
                  │
                  ▼
             Kunci 32 byte
                  │
                  ▼
      ┌───────────────────────────┐
      │  AES-256-GCM Encryption   │
      │  nonce acak 12 byte       │
      │  auth tag 16 byte         │
      └───────────────────────────┘
                  │
                  ▼
   MySQL: encrypted_data | nonce | auth_tag
```

**Saat login berhasil:** kunci derivasi disimpan di session, seluruh baris didekripsi, data asli ditampilkan.

**Saat password salah:** kunci tidak cocok, `openssl_decrypt()` gagal, dan panel admin menampilkan
potongan `encrypted_data`, `nonce`, dan `auth_tag` apa adanya — memperlihatkan bahwa data di database
benar-benar tidak terbaca tanpa kunci yang tepat.

> Katalog `layanan` memakai kunci statis terpisah supaya daftar harga di landing page tetap tampil
> meskipun password admin direset.

---

## 📁 Struktur Folder

```text
Amang_Laundry/
├── index.php                 # Landing page publik + form login admin
├── admin.php                 # CR7 Admin Command Center (dashboard & CRUD)
├── config.php                # Koneksi DB, skema, kriptografi, autentikasi, helper CRUD
├── README.md
└── screenshots/              # Screenshot untuk dokumentasi
    ├── 01-landing-hero.png
    ├── 02-layanan.png
    ├── 03-harga.png
    ├── 04-tentang.png
    ├── 05-login-admin.png
    ├── 06-dashboard-password-benar.png
    ├── 07-dashboard-password-salah.png
    ├── 08-tabel-pesanan.png
    ├── 09-tambah-pesanan.png
    ├── 10-detail-pesanan.png
    ├── 11-data-layanan.png
    └── 12-mobile-responsive.png
```

---

## ⚠️ Catatan Keamanan

Project ini dibuat untuk **pembelajaran** dan demonstrasi enkripsi data. Sebelum dipakai di production:

1. **Ganti kredensial default** di `config.php` (`ADMIN_DEFAULT_USER`, `ADMIN_DEFAULT_PASS`).
2. **Pindahkan kredensial DB ke environment variable** / file `.env` yang tidak di-commit.
3. **Ganti salt statis** pada `getServiceEncryptionKey()` dengan nilai rahasia milik Anda.
4. Aktifkan **HTTPS** agar session cookie dan password tidak dikirim dalam plaintext.
5. Tambahkan **rate limiting** pada form login untuk mencegah brute force.
6. Backup kunci enkripsi — **jika password admin hilang, data pesanan lama tidak bisa dipulihkan.**

---

## 📄 Lisensi

Project ini bebas digunakan untuk keperluan belajar dan pengembangan.
Dibuat dengan semangat **CR7: kerja keras, disiplin, dan tanpa kompromi**. 🇵🇹

<div align="center">

**AMANG LAUNDRY #7** — *Bersih, Wangi, Juara.* ⚽

</div>
