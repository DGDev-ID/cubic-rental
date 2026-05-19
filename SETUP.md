# 📋 Setup Project Laravel Cubic - Panduan Lengkap

Panduan step-by-step untuk setup aplikasi **Cubic Gaming Lounge Rental Management System** dari awal (instalasi environment hingga running).

---

## 📌 Prasyarat Sistem

- **OS**: Windows 10/11
- **Internet Connection**: Aktif
- **Disk Space**: Min. 5GB
- **RAM**: Min. 4GB

---

## 🔧 Step 1: Install Environment

### 1.1 Download & Install PHP 8.2+

1. Kunjungi: https://windows.php.net/download/
2. Download **"PHP 8.2 (atau lebih baru) Thread Safe"** (file `.zip`)
3. Extract ke folder: `C:\php` (atau folder pilihan Anda)
4. **Atur Environment Variable:**
   - Buka `Control Panel` → `System` → `Advanced System Settings`
   - Klik `Environment Variables`
   - Di bagian "System variables", klik `New`
   - Variable name: `PATH`
   - Variable value: `C:\php` (sesuai folder PHP Anda)
   - Klik `OK`

**Verifikasi:**
```bash
php -v
```
Pastikan PHP version muncul.

---

### 1.2 Download & Install Composer

1. Kunjungi: https://getcomposer.org/download/
2. Download **Composer-Setup.exe** (Windows Installer)
3. Jalankan installer, ikuti wizard
4. Pilih PHP path saat diminta
5. Selesaikan instalasi

**Verifikasi:**
```bash
composer -v
```

---

### 1.3 Download & Install PostgreSQL 15+

1. Kunjungi: https://www.postgresql.org/download/windows/
2. Download installer (versi 15 atau lebih baru)
3. Jalankan installer
   - **Password superuser (postgres)**: Ingat baik-baik, contoh: `postgres`
   - **Port**: `5432` (default)
   - Selesaikan instalasi
4. Buka **pgAdmin** (tools yang ter-install)
5. Buat database baru:
   - Klik kanan `Databases`
   - `Create` → `Database`
   - **Name**: `rental_ps`
   - **Owner**: `postgres`
   - Klik `Save`

**Verifikasi:**
```bash
psql -U postgres -c "SELECT version();"
```

---

### 1.4 Download & Install Git

1. Kunjungi: https://git-scm.com/download/win
2. Download installer
3. Jalankan installer, ikuti wizard (gunakan default settings)
4. Selesaikan

**Verifikasi:**
```bash
git --version
```

---

### 1.5 Download & Install Node.js (untuk frontend assets)

1. Kunjungi: https://nodejs.org/ (LTS version)
2. Download & install
3. Selesaikan

**Verifikasi:**
```bash
node -v
npm -v
```

---

## 📥 Step 2: Clone Repository

```bash
cd C:\Projects  # atau folder pilihan Anda
git clone https://github.com/DGDev-ID/cubic-rental.git
cd cubic-rental
```

---

## ⚙️ Step 3: Setup Laravel Project

### 3.1 Install Dependencies

```bash
composer install
```

Tunggu hingga semua package terinstall (bisa memakan waktu beberapa menit).

---

### 3.2 Copy Environment File

```bash
copy .env.example .env
```

Atau di PowerShell:
```powershell
Copy-Item .env.example .env
```

---

### 3.3 Generate Application Key

```bash
php artisan key:generate
```

---

### 3.4 Konfigurasi Database di `.env`

Buka file `.env` dengan editor (Notepad, VS Code, dll)

Cari section database, ubah menjadi:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rental_ps
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

Sesuaikan dengan password PostgreSQL yang Anda set di Step 1.3

---

### 3.5 Generate Database Tables

```bash
php artisan migrate:fresh --seed
```

Command ini akan:
- Drop semua table (jika ada)
- Buat table baru dari migrations
- Seed data master (employees, consoles, games, fnb items)

---

### 3.6 Install Frontend Dependencies

```bash
npm install
```

---

## 🚀 Step 4: Run Development Server

Buka **2 terminal/PowerShell** terpisah:

### Terminal 1 - Laravel Development Server

```bash
cd C:\Projects\cubic-rental
php artisan serve
```

Output akan seperti:
```
Laravel development server started: http://127.0.0.1:8000
```

---

### Terminal 2 - Vite Frontend Dev Server

```bash
cd C:\Projects\cubic-rental
npm run dev
```

Output akan menampilkan bahwa Vite berjalan.

---

## 🌐 Step 5: Akses Aplikasi

1. Buka browser: **http://localhost:8000**
2. Login dengan:
   - **Email**: `admin@rentalps.com`
   - **Password**: `password`

---

## 📝 Struktur Folder Penting

```
cubic-rental/
├── app/
│   ├── Http/Controllers/       # Controller logic
│   ├── Models/                 # Database models
│   └── Services/               # Business logic
├── database/
│   ├── migrations/             # Table structures
│   └── seeders/                # Sample data
├── resources/
│   ├── js/
│   │   ├── Components/         # Vue components
│   │   ├── Pages/              # Page components
│   │   └── Layouts/            # Layout components
│   └── css/                    # Tailwind CSS
├── routes/
│   └── web.php                 # Web routes
├── public/                     # Public assets
├── vendor/                     # PHP dependencies (auto-generated)
├── node_modules/               # NPM dependencies (auto-generated)
├── .env                        # Environment config (JANGAN commit ke git)
├── composer.json               # PHP dependencies list
├── package.json                # NPM dependencies list
└── vite.config.js              # Vite config
```

---

## 🔐 Credentials Default

| Field | Value |
|-------|-------|
| Email | admin@rentalps.com |
| Password | password |
| DB User | postgres |
| DB Password | postgres (sesuai setup Anda) |

---

## 🛠️ Command-Command Berguna

### Development
```bash
# Start Laravel dev server
php artisan serve

# Start Vite frontend dev server
npm run dev

# Run both (simplified)
composer run dev   # Jika script tersedia di composer.json
```

### Database
```bash
# Fresh migration dengan seed
php artisan migrate:fresh --seed

# Rollback & migrate ulang
php artisan migrate:refresh

# Reset database (hati-hati: hapus semua data)
php artisan migrate:reset

# Seed ulang data
php artisan db:seed
```

### Make Commands (generate files)
```bash
# Generate model dengan migration
php artisan make:model ModelName -m

# Generate controller
php artisan make:controller ControllerName

# Generate migration
php artisan make:migration create_table_name

# Generate request validation class
php artisan make:request RequestName
```

### Maintenance
```bash
# Clear cache
php artisan cache:clear

# Clear semua cache
php artisan optimize:clear

# Clear view cache
php artisan view:clear

# Run tests
php artisan test

# Lint code
./vendor/bin/pint
```

---

## ⚠️ Troubleshooting

### Error: "php: command not found"
- PHP tidak di-add ke PATH
- Restart terminal/PowerShell setelah menambah PATH
- Cek PATH dengan: `echo $env:PATH`

### Error: "SQLSTATE[HY000]: General error: 7 ERROR: could not connect to server"
- PostgreSQL tidak running
- Username/password salah di `.env`
- Database `rental_ps` belum dibuat

### Error: "npm: command not found"
- Node.js belum install atau tidak di PATH
- Restart terminal

### Vite dev server tidak muncul
- Pastikan kedua terminal (Laravel + Vite) berjalan
- Port 5173 tidak di-block firewall

### Assets tidak loading (CSS/JS kosong)
- Pastikan `npm run dev` berjalan
- Clear browser cache (Ctrl+Shift+Delete)
- Rebuild dengan: `npm run build`

---

## 🎯 Workflow Development

1. **Buat branch baru:**
   ```bash
   git checkout -b feature/nama-fitur
   ```

2. **Buat changes** di file-file yang sesuai

3. **Test di browser** sambil `npm run dev` & `php artisan serve` jalan

4. **Commit changes:**
   ```bash
   git add .
   git commit -m "feat: deskripsi perubahan"
   ```

5. **Push ke repository:**
   ```bash
   git push origin feature/nama-fitur
   ```

6. **Buat Pull Request** di GitHub

---

## 📦 Production Deployment

Untuk deploy ke production (VPS/hosting):

```bash
# Di server production
git clone <repo-url>
cd cubic-rental

composer install --optimize-autoloader --no-dev
npm install
npm run build

php artisan migrate:fresh --seed
php artisan optimize

# Setup web server (Nginx/Apache) pointing to public/ folder
```

---

## 📞 Support & Resources

- **Laravel Docs**: https://laravel.com/docs
- **Vue 3 Docs**: https://vuejs.org/
- **Tailwind CSS**: https://tailwindcss.com/
- **PostgreSQL**: https://www.postgresql.org/docs/

---

**Terakhir diupdate**: 19 Mei 2026
**Aplikasi**: Cubic Gaming Lounge Rental Management
**Versi Laravel**: 12.x
**Versi Node**: 18.x+
