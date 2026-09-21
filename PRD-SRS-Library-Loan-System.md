# PRD & SRS — Sistem Peminjaman Buku Perpustakaan (Library Loan API)

| | |
|---|---|
| **Versi** | 1.0 |
| **Status** | Draft |
| **Tipe** | Portfolio Project — Backend Engineering |
| **Author** | Bakudapa |
| **Stack utama** | PHP 8.x, Laravel, PostgreSQL, Redis, Pest |

---

## 1. Ringkasan Produk

Library Loan API adalah backend REST API untuk sistem peminjaman buku perpustakaan. Anggota dapat mencari buku, meminjam, dan mengembalikan buku. Sistem menjamin konsistensi stok buku di bawah kondisi akses bersamaan (concurrent), menyediakan rekomendasi/ringkasan buku berbasis AI, dan dibangun dengan praktik backend production-oriented (logging terstruktur, caching, automated testing, CI/CD).

Project ini bukan aplikasi komersial — tujuannya adalah portofolio yang mendemonstrasikan kompetensi backend engineering secara end-to-end, dari desain data sampai deployment.

## 2. Tujuan & Sasaran

- **Tujuan produk:** anggota bisa meminjam dan mengembalikan buku tanpa risiko double-booking, admin bisa mengelola katalog buku dengan bantuan AI (ringkasan otomatis, rekomendasi).
- **Tujuan pembelajaran (portofolio):** mendemonstrasikan penguasaan atas race condition handling, logging & custom error handling, caching, environment/secrets management, CI/CD, database indexing, dan integrasi API AI eksternal secara nyata (bukan hardcoded).

## 3. Target Pengguna

| Peran | Deskripsi |
|---|---|
| **Anggota (Member)** | Pengguna terautentikasi yang mencari, meminjam, dan mengembalikan buku. |
| **Admin/Pustakawan** | Mengelola data buku (tambah/edit/hapus), memantau peminjaman yang overdue. |
| **Sistem AI (aktor eksternal)** | Layanan API AI pihak ketiga yang dipanggil sistem untuk generate ringkasan buku dan/atau embedding untuk rekomendasi. |

## 4. Ruang Lingkup

**In scope:**
- CRUD katalog buku, manajemen anggota, autentikasi & otorisasi
- Alur pinjam & kembali dengan penanganan concurrency yang aman
- Rekomendasi & ringkasan buku berbasis AI
- Logging terstruktur, caching, rate limiting
- Automated testing & CI pipeline
- Deployment dasar (single server)

**Out of scope (untuk versi 1.0):**
- Pembayaran/denda otomatis (opsional untuk versi berikutnya)
- Multi-cabang perpustakaan (multi-tenant)
- Aplikasi frontend/mobile (fokus murni backend API)
- Load balancer multi-instance (dicatat sebagai future work, lihat §12)

## 5. Functional Requirements

### 5.1 Manajemen Buku (Admin)

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-1 | Admin dapat menambah buku baru (judul, penulis, kategori, deskripsi, jumlah kopi) | Data tersimpan; `available_copies` = `total_copies` saat pembuatan |
| FR-2 | Admin dapat mengedit/menghapus data buku | Validasi mencegah `total_copies` < jumlah buku yang sedang dipinjam |
| FR-3 | Sistem otomatis membuat ringkasan buku via AI saat buku baru ditambahkan | Ringkasan tersimpan di kolom `summary`; jika API AI gagal, buku tetap tersimpan tanpa ringkasan dan error dicatat di log |
| FR-4 | Anggota dapat mencari buku berdasarkan judul/kategori | Hasil pencarian mengembalikan hanya field yang relevan (whitelisted) |

### 5.2 Manajemen Anggota & Auth

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-5 | Anggota dapat registrasi & login, menerima token akses | Token dibuat via Sanctum; password di-hash |
| FR-6 | Endpoint yang butuh autentikasi menolak request tanpa token valid | Response 401 untuk token invalid/kosong |
| FR-7 | Anggota hanya bisa mengembalikan buku yang dipinjam olehnya sendiri | Response 403 jika mencoba return loan milik anggota lain |

### 5.3 Peminjaman & Pengembalian

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-8 | Anggota dapat meminjam buku yang tersedia | `available_copies` berkurang 1; record `Loan` dibuat dengan `due_at` = now + N hari |
| FR-9 | Sistem menolak peminjaman jika stok buku habis | Response 409 dengan pesan yang jelas, bukan 500 |
| FR-10 | Sistem tetap konsisten saat banyak anggota meminjam buku yang sama secara bersamaan | Di bawah uji concurrent request, `available_copies` tidak pernah bernilai negatif dan tidak ada dua `Loan` aktif untuk kopi yang sama |
| FR-11 | Anggota dapat mengembalikan buku yang dipinjam | `available_copies` bertambah 1; `returned_at` diisi; status loan berubah jadi `returned` |
| FR-12 | Sistem menandai peminjaman sebagai overdue jika melewati `due_at` tanpa dikembalikan | Status otomatis terhitung `overdue` saat query, atau via scheduled job |

### 5.4 Rekomendasi AI

| ID | Requirement | Acceptance Criteria |
|---|---|---|
| FR-13 | Sistem dapat merekomendasikan buku ke anggota berdasarkan riwayat pinjam | Endpoint mengembalikan daftar buku terurut berdasarkan kemiripan embedding |
| FR-14 | Jika layanan AI eksternal tidak tersedia, sistem tetap memberikan rekomendasi fallback | Fallback berbasis kategori buku yang pernah dipinjam, bukan error 500 |

## 6. Non-Functional Requirements

| Kategori | Requirement |
|---|---|
| **Reliability** | Operasi pinjam/kembali harus atomik (DB transaction); tidak boleh ada silent failure — kegagalan harus terlihat sebagai error/log, bukan sukses semu |
| **Performance** | Daftar buku per kategori di-cache; target pengurangan jumlah query DB terverifikasi (dibandingkan sebelum-sesudah cache) |
| **Security** | Kredensial (API key AI, DB) disimpan di `.env`, tidak pernah hardcode atau masuk version control |
| **Observability** | Setiap kegagalan bisnis (stok habis, API AI gagal) dicatat dengan context terstruktur (member_id, book_id, alasan) |
| **Scalability (baseline)** | Query yang sering dipakai (pencarian buku, cek overdue) punya index yang terverifikasi lewat `EXPLAIN ANALYZE` |
| **Testability** | Setiap functional requirement kritikal (FR-9, FR-10, FR-11) memiliki automated test; CI menjalankan test tersebut di setiap push |
| **Rate limiting** | Endpoint peminjaman dibatasi per-anggota untuk mencegah spam request |

## 7. Data Model (Ringkasan Entitas)

- **Book**: id, title, author, category, description, summary (AI-generated), total_copies, available_copies, timestamps
- **BookEmbedding**: id, book_id (FK), vector, timestamps
- **Member**: id, name, email, password, timestamps *(bisa reuse tabel `users`)*
- **Loan**: id, member_id (FK), book_id (FK), borrowed_at, due_at, returned_at, status (`active`/`returned`/`overdue`)

**Relasi:** Book 1—N Loan, Member 1—N Loan, Book 1—1 BookEmbedding

## 8. Spesifikasi API (Ringkasan Endpoint)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/register`, `/api/login` | - | Registrasi & login anggota |
| GET | `/api/books` | - | Cari/list buku (mendukung filter kategori, keyword) |
| POST | `/api/books` | Admin | Tambah buku (trigger AI summary) |
| PUT/DELETE | `/api/books/{id}` | Admin | Edit/hapus buku |
| POST | `/api/loans` | Anggota | Pinjam buku |
| POST | `/api/loans/{id}/return` | Anggota (pemilik loan) | Kembalikan buku |
| GET | `/api/loans/me` | Anggota | Riwayat pinjam sendiri |
| GET | `/api/recommendations` | Anggota | Rekomendasi buku berbasis AI |

## 9. Alur Kritis: Peminjaman Buku (Concurrency-Safe)

1. Anggota kirim `POST /api/loans` dengan `book_id`
2. Sistem buka `DB::transaction()`
3. Lock baris `Book` yang dituju (`lockForUpdate()`)
4. Cek `available_copies > 0` — jika tidak, lempar `BookUnavailableException` (→ 409), transaction rollback
5. Jika tersedia: decrement `available_copies`, buat record `Loan`, commit transaction
6. Response 201 dengan detail loan

## 10. Spesifikasi Fitur AI

| Aspek | Ketentuan |
|---|---|
| **Trigger ringkasan** | Otomatis saat buku baru dibuat (FR-3) |
| **Trigger rekomendasi** | On-demand saat anggota memanggil `/api/recommendations` |
| **Timeout handling** | Panggilan API AI punya timeout eksplisit; gagal → dicatat di log, tidak menggagalkan operasi utama (tambah buku tetap sukses) |
| **Fallback rekomendasi** | Berdasarkan kategori buku yang pernah dipinjam anggota jika API AI/embedding tidak tersedia |
| **Secrets** | API key disimpan di `.env`, diakses via `config('services.ai.key')` |

## 11. Strategi Testing

- Unit/feature test dengan Pest, `RefreshDatabase`, factory relationship-aware
- Test concurrency khusus untuk FR-10 (simulasi request paralel ke buku stok terbatas)
- Test AI-related endpoint di-mock (tidak memanggil API AI asli di test suite) agar test tetap deterministik dan cepat
- CI (GitHub Actions) menjalankan seluruh test suite di setiap push ke branch utama

## 12. Deployment & Future Work

- **v1.0:** deploy single server, environment config production-safe
- **Future work (di luar scope v1.0):** load balancer/multi-instance, sistem denda otomatis, multi-cabang perpustakaan

## 13. Asumsi & Batasan

- Satu anggota maksimal meminjam N buku aktif sekaligus (nilai N dapat dikonfigurasi, default: 3)
- Durasi pinjam default 7 hari, dapat dikonfigurasi
- Layanan AI eksternal diasumsikan punya rate limit sendiri — sistem harus tetap berjalan meski layanan itu lambat/down

## 14. Pemetaan ke Roadmap Belajar

| Fase build (lihat step-by-step sebelumnya) | Requirement terkait |
|---|---|
| Step 1–2 (schema, CRUD) | FR-1, FR-2, FR-4, FR-5, FR-6 |
| Step 3–4 (loan naif → race condition) | FR-8, FR-9, FR-10 |
| Step 5 (logging & exception) | Semua NFR Observability & Reliability |
| Step 6 (caching) | NFR Performance |
| Step 7–8 (AI) | FR-3, FR-13, FR-14 |
| Testing & CI (menyatu di tiap step) | §11 |
