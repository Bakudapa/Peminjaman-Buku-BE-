![tests](https://github.com/Bakudapa/Peminjaman-Buku-BE-/actions/workflows/tests.yml/badge.svg)

# Library Loan API

Backend REST API untuk peminjaman buku perpustakaan.
Fokus: konsistensi stok di bawah akses bersamaan (concurrent), otorisasi berlapis, dan testing otomatis.

**Stack:** PHP 8.5, Laravel, PostgreSQL, Sanctum, Pest, GitHub Actions

## Fitur
- Registrasi, login, dan token akses (Sanctum)
- CRUD buku (admin) dan pencarian buku
- Pinjam dan kembalikan buku dengan DB transaction dan `lockForUpdate()`
- Batas pinjaman aktif per anggota dan status overdue
- Otorisasi per role lewat FormRequest (admin, pemilik data)
- Rate limiting per endpoint, exception khusus dengan log terstruktur

## Menjalankan project
```bash
git clone https://github.com/Bakudapa/Peminjaman-Buku-BE-.git
cd Peminjaman-Buku-BE-
composer install
cp .env.example .env
php artisan key:generate
# isi DB_* di .env (PostgreSQL), lalu:
php artisan migrate
php artisan serve
```

## Membuat admin pertama
Register lewat API selalu menghasilkan role member. Buat admin lewat tinker:
```bash
php artisan tinker
>>> User::where('email', 'kamu@email.com')->update(['role' => 'admin']);
```

## Menjalankan test
Test memakai PostgreSQL (bukan SQLite), karena `lockForUpdate()` tidak berefek di SQLite.
```bash
./vendor/bin/pest --compact
```

## Concurrency (FR-10)
Test FR-10 menjalankan beberapa proses sungguhan yang meminjam buku dengan stok terbatas.
Saya memverifikasi test ini benar-benar mendeteksi race condition dengan menghapus
`lockForUpdate()`: test gagal tanpa lock, lolos dengan lock.

## Endpoint utama
| Method | Endpoint | Akses |
|---|---|---|
| POST | /api/register, /api/login | publik |
| GET | /api/books | publik |
| POST/PUT/DELETE | /api/books | admin |
| POST | /api/loans | anggota |
| POST | /api/loans/{id}/return | pemilik loan |
| GET | /api/loans/me | anggota |