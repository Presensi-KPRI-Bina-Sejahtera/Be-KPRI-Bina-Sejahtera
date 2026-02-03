# Panduan Hosting (cPanel Terminal) — Be-KPRI-Bina-Sejahtera (Laravel)

Dokumen ini fokus pada **perintah yang dijalankan di Terminal cPanel** untuk deploy backend Laravel.

> Catatan:
> - Pastikan versi PHP di cPanel kompatibel dengan project (cek `composer.json`).
> - Di shared hosting, terkadang **Node.js** tidak tersedia. Bagian Vite/Node bersifat opsional.
> - Jalankan perintah dari folder project (contoh: `~/Be-KPRI-Bina-Sejahtera`).

---

## 0) Masuk ke folder project

Sesuaikan dengan lokasi upload kamu (contoh pakai home directory):

```bash
cd ~
ls
cd Be-KPRI-Bina-Sejahtera
pwd
```

---

## 1) Siapkan `.env`

Jika belum ada `.env` di server:

```bash
cp .env.example .env
```

Edit `.env` (pakai editor yang tersedia di server):

```bash
nano .env
# atau
vi .env
```

Yang umumnya wajib diisi:

- `APP_NAME=...`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://domain-kamu.tld`
- `DB_CONNECTION=mysql`
- `DB_HOST=...`
- `DB_PORT=3306`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

Jika pakai Sanctum/cookie (SPA), sesuaikan juga:

- `SESSION_DOMAIN=.domain-kamu.tld`
- `SANCTUM_STATEFUL_DOMAINS=domain-kamu.tld,www.domain-kamu.tld`

---

## 2) Install dependency (Composer)

Jika perintah `composer` tersedia:

```bash
composer --version
composer install --no-dev --optimize-autoloader
```

Jika `composer` tidak dikenali, biasanya bisa lewat:

```bash
php -v
php /usr/local/bin/composer --version
php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

> Jika path `/usr/local/bin/composer` tidak ada, cari lokasi composer:
>
> ```bash
> which composer
> whereis composer
> ```

---

## 3) Generate APP_KEY

```bash
php artisan key:generate
```

---

## 4) Permission folder penting

Umumnya Laravel butuh write access untuk `storage` dan `bootstrap/cache`:

```bash
chmod -R 775 storage bootstrap/cache
```

Jika server mengharuskan (tergantung konfigurasi user/group), kadang perlu 755:

```bash
chmod -R 755 storage bootstrap/cache
```

---

## 5) Migrasi database (Production)

```bash
php artisan migrate --force
```

Jika kamu punya seeder dan memang ingin isi data awal:

```bash
php artisan db:seed --force
```

---

## 6) Storage link (untuk akses file publik)

```bash
php artisan storage:link
```

---

## 7) Cache & optimize untuk Production

Setelah `.env` benar, jalankan:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## 8) (Opsional) Build Vite assets di server

Jika cPanel kamu mendukung Node.js:

```bash
node -v
npm -v
npm install
npm run build
```

Jika tidak ada Node.js, build di lokal lalu upload hasil build (biasanya ada di `public/build`).

---

# A. Setting Document Root (PENTING)

Laravel sebaiknya **hanya mengekspos folder `public/`**.

## Opsi A (disarankan): Document Root diarahkan ke folder `public/`

Di cPanel:
- Domain/Add-on domain → **Document Root** set ke: `.../Be-KPRI-Bina-Sejahtera/public`

Jika sudah benar, biasanya tidak perlu ubah `index.php`.

## Opsi B: Document Root tidak bisa ke `public/` (umum di shared hosting)

Kalau domain kamu harus tetap menunjuk ke `public_html/`, lakukan skema ini:

1) Simpan project Laravel **di luar** `public_html` (misalnya `~/laravel-app/Be-KPRI-Bina-Sejahtera`).
2) Copy isi folder `public/` ke `~/public_html/`.
3) Edit file `~/public_html/index.php` agar path mengarah ke folder laravel app.

Contoh (sesuaikan path):

```bash
# misal laravel app ada di: ~/laravel-app/Be-KPRI-Bina-Sejahtera
mkdir -p ~/laravel-app

# pindahkan project ke laravel-app (contoh kalau kamu uploadnya di root home)
# mv ~/Be-KPRI-Bina-Sejahtera ~/laravel-app/

# bersihkan public_html (HATI-HATI: pastikan tidak menghapus file penting)
# rm -rf ~/public_html/*

# copy isi folder public Laravel ke public_html
cp -R ~/laravel-app/Be-KPRI-Bina-Sejahtera/public/* ~/public_html/
```

Lalu ubah `~/public_html/index.php` (pakai `nano`/`vi`) pada bagian path:

- dari:
  - `require __DIR__.'/../vendor/autoload.php';`
  - `require_once __DIR__.'/../bootstrap/app.php';`
- menjadi (contoh):
  - `require __DIR__.'/../laravel-app/Be-KPRI-Bina-Sejahtera/vendor/autoload.php';`
  - `require_once __DIR__.'/../laravel-app/Be-KPRI-Bina-Sejahtera/bootstrap/app.php';`

---

# B. Perintah cek cepat kalau ada error

Cek log Laravel:

```bash
tail -n 200 storage/logs/laravel.log
```

Jika pakai Opsi B (log ada di folder app, bukan public_html), sesuaikan path:

```bash
tail -n 200 ~/laravel-app/Be-KPRI-Bina-Sejahtera/storage/logs/laravel.log
```

Cek route terdaftar:

```bash
php artisan route:list
```

Cek config terbaca:

```bash
php artisan about
```

---

# C. Rekomendasi urutan deploy (ringkas)

```bash
cd ~/Be-KPRI-Bina-Sejahtera
cp .env.example .env
nano .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```
