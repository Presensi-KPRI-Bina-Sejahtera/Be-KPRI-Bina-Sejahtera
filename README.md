<p align="center">
  <img src="https://pasla.jambiprov.go.id/wp-content/uploads/2023/02/lambang-koperasi.png" width="120" alt="KPRI Logo">
</p>

<h1 align="center">Sistem Presensi KPRI Bina Sejahtera</h1>

<p align="center">
  <strong>Backend API untuk Manajemen Presensi Karyawan KPRI Bina Sejahtera</strong>
</p>

<p align="center">
  <a href="#fitur-utama">Fitur</a> • 
  <a href="#teknologi">Teknologi</a> • 
  <a href="#instalasi">Instalasi</a> • 
  <a href="#lisensi">Lisensi</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/Status-Active-success?style=flat-square" alt="Status">
</p>

---

Sistem backend utama untuk KPRI Bina Sejahtera yang dibangun menggunakan Laravel. Repository ini berfungsi sebagai sistem manajemen presensi karyawan, lokasi, dan pelaporan kehadiran berbasis API.

## Deskripsi

Proyek ini adalah repositori default untuk sistem presensi internal KPRI Bina Sejahtera. Menangani fitur-fitur utama seperti manajemen lokasi presensi, pencatatan kehadiran, serta pelaporan dan monitoring aktivitas karyawan.

<p align="center">
  <img src="https://perindustrian.gunungkidulkab.go.id/wp-content/uploads/2024/03/Snapinsta.app_431492495_721723126778649_1757782079401867317_n_1080-1-1024x535-1.jpg" width="400" alt="Presensi Dashboard">
</p>

## Fitur Utama

- 📍 Manajemen Lokasi Presensi (toko/cabang)
- 👥 Database Karyawan & Role Management
- ⏰ Pencatatan Kehadiran (datang/pulang, lokasi, jarak)
- 📊 Dashboard Admin (API endpoint)
- 📈 Sistem Pelaporan Kehadiran & Rekap

## Teknologi

### Backend
- **Framework**: Laravel 12.x (API Only)
- **Authentication**: Sanctum Token-Based Auth
- **Database**: MySQL, PostgreSQL, SQLite
- **PHP Version**: ^8.2

### Dependencies
- Laravel Sanctum (API Authentication)
- Laravel Tinker (REPL)

## Instalasi

### Prasyarat
- PHP 8.2+
- Composer
- Git
- Database (MySQL/PostgreSQL/SQLite)

### Setup

1. Clone repository
```bash
git clone <repository-url>
cd Be-KPRI-Bina-Sejahtera
```

2. Install dependencies
```bash
composer install
```

3. Setup environment
```bash
cp .env.example .env
php artisan key:generate
```

Untuk login dengan Google, isi env berikut di `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

4. Setup database
```bash
php artisan migrate:fresh --seed
```

5. Run development server
```bash
php artisan serve
```

API akan running di `http://localhost:8000/api`

## Struktur Database

### Tabel Utama
- **users**: Data karyawan & admin
- **presence_locations**: Lokasi presensi (toko/cabang)
- **attendances**: Catatan kehadiran karyawan
- **cashflows**: Laporan arus kas
- **deposits**: Simpanan karyawan

## API Endpoints

### Health Check
```
GET /api/health
```

### User Profile (Authenticated)
```
GET /api/me
Authorization: Bearer {token}
```

### Auth (Public)
```
POST /api/auth/login
POST /api/auth/login-google
```

Body untuk `POST /api/auth/login-google`:

```json
{
  "id_token": "<google-id-token>",
  "presence_location_id": 1,
  "device_name": "android"
}
```

## Development

### Running Tests
```bash
php artisan test
```

### Artisan Commands
```bash
# Generate API documentation
php artisan route:list

# Fresh migration with seed
php artisan migrate:fresh --seed
```

## Kontribusi

Kontribusi dipersilahkan! Silakan buat branch baru untuk fitur atau bug fix:

```bash
git checkout -b feature/nama-fitur
# atau
git checkout -b bugfix/deskripsi-bug
```

## Support

Untuk pertanyaan atau issues, silakan hubungi tim development KPRI Bina Sejahtera.

## Lisensi

© 2026 KPRI Bina Sejahtera. Seluruh hak cipta dilindungi undang-undang.
