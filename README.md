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
- Query database diindeks dan dibuktikan lewat `EXPLAIN ANALYZE`
- Caching daftar buku per kategori dengan invalidasi otomatis

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

## Performance: Index

Diuji dengan data sintetis: ~98.000 buku, ~5.000 anggota, 200.000 loan.

| Query | Sebelum (Seq Scan) | Sesudah | Execution Time (Sebelum → Sesudah) | Speedup |
|---|---|---|---|---|
| Cari buku per kategori | Seq Scan, buang 78.509 baris | Bitmap Index Scan (`books_category_index`) | 11.75 ms → 4.79 ms | ~2.4x |
| Loan aktif per anggota | Seq Scan, buang 200.000 baris | Index Scan (`loans_member_id_status_index`) | 16.27 ms → 0.031 ms | ~525x |
| Cek overdue | Seq Scan, buang 141.444 baris | Bitmap Index Scan (`loans_status_due_at_index`) | 22.13 ms → 11.11 ms | ~2x |

Index yang ditambahkan: `books(category)`, `loans(book_id)`, `loans(member_id, status)`, `loans(status, due_at)`.

Speedup query loan aktif per anggota jauh lebih besar karena hasilnya kosong/sedikit,
sehingga index cukup menjawab tanpa membuka tabel sama sekali. Dua query lain tetap
harus membuka banyak blok tabel untuk mengambil data, jadi speedup-nya lebih kecil
meski tetap signifikan.

## Performance: Cache

`GET /api/books?category=...` di-cache 60 detik per kategori. Cache di-invalidate
otomatis (lewat version key) saat: admin menambah/mengubah/menghapus buku, atau saat
ada peminjaman/pengembalian yang mengubah `available_copies`.

Dibuktikan lewat test (`tests/Feature/BookCacheTest.php`):
- Request kedua ke kategori yang sama menghasilkan jumlah query DB lebih sedikit dari request pertama
- Cache otomatis basi (tidak dipakai lagi) setelah buku baru ditambahkan
- Cache otomatis basi setelah stok berubah akibat peminjaman

## Endpoint utama
| Method | Endpoint | Akses |
|---|---|---|
| POST | /api/register, /api/login | publik |
| GET | /api/books | publik |
| POST/PUT/DELETE | /api/books | admin |
| POST | /api/loans | anggota |
| POST | /api/loans/{id}/return | pemilik loan |
| GET | /api/loans/me | anggota |

Dokumentasi API lengkap untuk konsumsi frontend: lihat [`docs/API.md`](docs/API.md).