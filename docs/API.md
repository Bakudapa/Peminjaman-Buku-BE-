# Library Loan API — Dokumentasi untuk Frontend

Base URL (local): `http://localhost:8000/api`

Dokumen ini ditulis untuk dikonsumsi langsung sebagai konteks oleh AI code generator
saat membangun frontend. Semua field JSON di bawah diambil dari kode asli backend
(Resource classes, FormRequest rules, Exception handlers), bukan asumsi.

---

## 1. Autentikasi

Auth pakai Laravel Sanctum (bearer token). Tidak ada session/cookie — setiap request
ke endpoint yang butuh auth wajib menyertakan header:

```
Authorization: Bearer <token>
Accept: application/json
```

### POST /api/register

**Body:**
```json
{
  "name": "string, required",
  "email": "string, required, email, unique",
  "password": "string, required, min:8"
}
```

**Response 201:**
```json
{
  "user": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "role": "member",
    "password": "$2y$10$...(hashed)",
    "created_at": "2026-09-22T05:00:00.000000Z",
    "updated_at": "2026-09-22T05:00:00.000000Z"
  },
  "token": "1|abcdef123456..."
}
```

> Catatan: `user` di sini adalah model mentah (bukan lewat Resource), field-nya
> berbeda dari `UserResource` yang dipakai di endpoint lain (lihat §4).
> `role` selalu `"member"` saat register — tidak ada cara membuat admin lewat API.

**Response 422** (validasi gagal — format default Laravel):
```json
{
  "message": "The email field is required. (and 1 more error)",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field must be at least 8 characters."]
  }
}
```

---

### POST /api/login

**Body:**
```json
{
  "email": "string, required, email",
  "password": "string, required, min:8"
}
```

**Response 200:** sama persis bentuknya dengan register (`{ user, token }`, lihat di atas).

**Response 401** (kredensial salah):
```json
{
  "message": "Email atau password salah"
}
```

---

## 2. Konvensi umum

### Pagination
Semua endpoint list (`GET /api/books`, `GET /api/loans`, `GET /api/loans/me`) pakai
pagination default Laravel, 25 item per halaman:

```json
{
  "data": [ /* array item */ ],
  "links": {
    "first": "http://localhost:8000/api/books?page=1",
    "last": "http://localhost:8000/api/books?page=4",
    "prev": null,
    "next": "http://localhost:8000/api/books?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 25,
    "total": 98
  }
}
```

Query param: `?page=2`

### Error umum (berlaku di semua endpoint)

| Status | Kapan terjadi | Bentuk body |
|---|---|---|
| 401 | Token kosong/invalid | `{"message": "Unauthenticated."}` |
| 403 | Tidak punya akses (bukan admin / bukan pemilik data) | `{"message": "This action is unauthorized."}` |
| 404 | Resource tidak ditemukan | `{"message": "No query results for model [App\\Models\\X] N"}` |
| 422 | Validasi gagal | `{"message": "...", "errors": {"field": ["pesan"]}}` |
| 429 | Rate limit terlampaui | `{"message": "Too Many Attempts."}` |
| 409 | Konflik bisnis (lihat §5) | `{"message": "...", "code": "SOME_CODE"}` |

FE sebaiknya membedakan error 409 lewat field `code`, bukan mem-parsing `message`
(karena `message` dalam Bahasa Indonesia dan bisa berubah).

---

## 3. Buku

### GET /api/books
Publik, tidak perlu auth.

**Query params:**
- `category` (string, opsional) — filter kategori persis (bukan partial match)
- `keyword` (string, opsional) — cari di judul (`ILIKE '%keyword%'`)
- `page` (int, opsional)

> Catatan performa: kombinasi `category` tanpa `keyword` di-cache 60 detik di
> server. Kombinasi lain (pakai `keyword`, atau tanpa filter) selalu query langsung.

**Response 200:**
```json
{
  "data": [
    {
      "id": 11,
      "title": "Sample Title",
      "author": "Author Name",
      "category": "History",
      "description": "Lorem ipsum...",
      "total_copies": 5,
      "available_copies": 3,
      "created_at": "2026-09-22 05:00:00"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

> Catatan: `created_at` di sini formatnya `"Y-m-d H:i:s"` (string biasa),
> **berbeda** dari resource lain yang pakai ISO 8601. Field `summary` (AI-generated,
> §7 PRD) belum diekspos di response ini.

### GET /api/books/{id}
Publik. Response 200: satu object `BookResource` seperti di atas (tanpa wrapper `data` array, langsung `{"data": {...}}`).

### POST /api/books
**Auth:** admin only (403 kalau bukan admin).

**Body:**
```json
{
  "title": "string, required",
  "author": "string, required",
  "category": "string, required",
  "description": "string, nullable",
  "total_copies": "integer, required, min:1"
}
```
`available_copies` otomatis diset sama dengan `total_copies` oleh server, jangan dikirim dari FE.

**Response 201:** satu `BookResource` (dibungkus `{"data": {...}}`).

### PUT/PATCH /api/books/{id}
**Auth:** admin only.

**Body** (semua field opsional, `sometimes`):
```json
{
  "title": "string",
  "author": "string",
  "category": "string",
  "description": "string",
  "total_copies": "integer, min:1"
}
```

**Response 422 khusus:** kalau `total_copies` diisi lebih kecil dari jumlah loan aktif buku itu:
```json
{
  "message": "The total copies field is invalid.",
  "errors": {
    "total_copies": ["Total copies tidak boleh kurang dari 3 (jumlah buku yang sedang dipinjam)."]
  }
}
```

### DELETE /api/books/{id}
**Auth:** admin only.

**Response 204:** body kosong.

**Response 409** (masih ada peminjaman aktif):
```json
{
  "message": "Buku tidak bisa dihapus karena masih ada peminjaman aktif."
}
```
> Catatan: exception ini pakai `abort(409, ...)` biasa, **tidak** punya field `code`
> seperti error 409 lainnya di §5. FE perlu menangani ini terpisah (cek status 409
> di endpoint delete buku, bukan berdasarkan `code`).

---

## 4. User

Semua endpoint di bawah butuh auth. Member hanya bisa akses data dirinya sendiri;
admin bisa akses siapa saja.

### GET /api/users
**Auth:** admin only. List semua user, paginated. Item: `UserResource`.

### GET /api/users/{id}
**Auth:** diri sendiri atau admin.

**Response 200** (`UserResource`):
```json
{
  "data": {
    "id": 9,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "role": "member",
    "joined_at": "22 September 2026"
  }
}
```
> Catatan: `joined_at` sudah diformat human-readable (`"d F Y"`), bukan ISO 8601.

### PUT/PATCH /api/users/{id}
**Auth:** diri sendiri atau admin. `role` **tidak bisa** diubah lewat endpoint ini
(tidak ada di validated rules — dikirim pun akan diabaikan server).

### DELETE /api/users/{id}
**Auth:** diri sendiri atau admin.

---

## 5. Peminjaman (Loans)

Semua endpoint di bawah butuh auth.

### POST /api/loans
Meminjam buku. Auth: anggota (member biasa, semua role bisa).

**Body:**
```json
{
  "book_id": "integer, required, exists:books,id"
}
```

**Response 201** (`LoanResource`):
```json
{
  "data": {
    "id": 42,
    "status": "active",
    "is_overdue": false,
    "borrowed_at": "2026-09-22T05:00:00+00:00",
    "due_at": "2026-09-29T05:00:00+00:00",
    "returned_at": null,
    "book": { /* BookResource, hanya kalau di-load */ },
    "member": { "id": 9, "name": "Budi Santoso", "email": "budi@example.com" }
  }
}
```

**Response 409** — tiga kemungkinan, dibedakan lewat `code`:

```json
// stok habis
{ "message": "Buku sedang tidak tersedia.", "code": "BOOK_OUT_OF_STOCK" }

// sudah 3 pinjaman aktif
{ "message": "Batas maksimal 3 pinjaman aktif telah tercapai.", "code": "LOAN_LIMIT_EXCEEDED" }
```

### GET /api/loans
**Auth:** admin only.

**Query params:**
- `overdue` (boolean, opsional) — filter hanya yang overdue

Item: `LoanResource` dengan `book` dan `member` ter-load.

### GET /api/loans/me
**Auth:** anggota, hanya melihat loan miliknya sendiri (tidak perlu `member_id`
di query, otomatis dari token). Item: `LoanResource` dengan `book` ter-load
(`member` tidak di-load di endpoint ini, kemungkinan null/hidden).

### GET /api/loans/{id}
**Auth:** pemilik loan atau admin (403 kalau member lain mencoba akses).

### POST /api/loans/{id}/return
**Auth:** hanya pemilik loan (admin **tidak bisa** return-kan loan orang lain
lewat endpoint ini — 403 kalau dicoba).

**Body:** kosong.

**Response 200:** `LoanResource` dengan `status: "returned"`, `returned_at` terisi.

**Response 409:**
```json
{ "message": "Loan ini sudah dikembalikan sebelumnya.", "code": "LOAN_ALREADY_RETURNED" }
```

---

## 6. Ringkasan kode error 409 (bisnis)

| Code | Endpoint | Arti |
|---|---|---|
| `BOOK_OUT_OF_STOCK` | POST /api/loans | Stok buku habis |
| `LOAN_LIMIT_EXCEEDED` | POST /api/loans | Anggota sudah punya 3 pinjaman aktif |
| `LOAN_ALREADY_RETURNED` | POST /api/loans/{id}/return | Loan sudah pernah dikembalikan |
| *(tanpa code)* | DELETE /api/books/{id} | Buku masih dipinjam, tidak bisa dihapus |

---

## 7. Rate limiting

Endpoint dikelompokkan dalam beberapa limiter (nama internal Laravel):
- `auth` → login, register
- `loans` → POST /api/loans
- `read` → GET books/users/loans (list & show)
- `write` → POST/PUT/DELETE books/users, return loan

Kalau limit terlampaui, response 429 dengan header standar:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
```

FE sebaiknya menangani 429 secara umum (retry dengan delay / tampilkan pesan
"terlalu banyak percobaan"), bukan per-endpoint.

---

## 8. Hal yang belum tersedia (jangan dibuatkan UI-nya dulu)

Sesuai PRD, fitur berikut **belum diimplementasikan** di backend per dokumen ini:
- `GET /api/recommendations` (rekomendasi buku berbasis AI)
- Ringkasan buku otomatis (field `summary` ada di database tapi belum diisi otomatis)

Jangan generate halaman/komponen untuk fitur ini sampai backend mengonfirmasi
endpoint-nya sudah ada.
