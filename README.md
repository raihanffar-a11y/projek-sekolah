# EnglishHub MVP

Platform kuis Bahasa Inggris untuk kegiatan kelas/English Night. Dibangun dengan PHP Native, PDO, MySQL, HTML, CSS, dan JavaScript.

## Fitur MVP
- Registrasi siswa mandiri (role selalu `student`)
- Login dan logout
- Admin dashboard
- Admin membuat kuis dan soal pilihan ganda
- Admin membuat kode sesi
- Siswa bergabung menggunakan kode
- Penilaian otomatis di server
- Hasil dan review jawaban
- Admin melihat seluruh hasil

## Kebutuhan
- XAMPP (Apache + MySQL)
- PHP 8.1+ disarankan
- MySQL/MariaDB

## Instalasi di XAMPP
1. Ekstrak folder `EnglishHub` ke `C:\xampp\htdocs\EnglishHub`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Import file `database.sql`.
5. Periksa `config/config.php`. Default XAMPP: user `root`, password kosong, BASE_URL `/EnglishHub`.
6. Buka `http://localhost/EnglishHub`.

## Membuat akun admin pertama
Registrasi biasa selalu membuat akun `student`. Setelah mendaftar, buka tab SQL di phpMyAdmin dan jalankan:

```sql
UPDATE users SET role = 'admin' WHERE email = 'email-kamu@example.com';
```

Ganti email dengan email akunmu sendiri. Jangan menyediakan pilihan role admin pada formulir registrasi publik.

## Alur penggunaan
1. Login sebagai admin.
2. Buka Quiz Management > Create quiz.
3. Tambahkan pertanyaan dan tandai jawaban benar.
4. Buat session code dan bagikan kepada siswa.
5. Siswa daftar/login, masukkan kode, lalu kerjakan kuis.
6. Nilai muncul setelah submit; admin dapat melihat seluruh hasil.

## Batasan MVP yang diketahui
- Soal yang didukung saat ini: multiple choice dengan empat pilihan.
- Satu percobaan per siswa per sesi.
- Timer ditampilkan sebagai informasi durasi, belum dipaksakan oleh server.
- Belum ada auto-save/resume jawaban saat koneksi terputus.
- Belum ada ekspor Excel, essay, matching, leaderboard, atau materi belajar.
- Sebelum dipakai secara publik, tambahkan HTTPS, pembatasan percobaan login, penguatan session cookie, audit keamanan, dan backup database.

## Struktur folder
- `config/` koneksi dan konfigurasi
- `includes/` autentikasi dan layout
- `admin/` halaman admin
- `assets/` CSS dan JavaScript
- file PHP root untuk login, dashboard, kuis, dan hasil
