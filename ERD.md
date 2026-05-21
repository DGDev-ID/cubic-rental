# Entity Relationship Diagram (ERD) - Cubic Gaming Lounge Rental System

## Database Schema Overview

```
┌─────────────────────────┐
│         USERS           │
├─────────────────────────┤
│ id_user (PK)            │
│ nama                    │
│ username                │
│ password                │
│ role                    │
├─────────────────────────┘
          │
          │ (1:N) manages
          ▼
┌─────────────────────────┐
│      EMPLOYEES          │
├─────────────────────────┤
│ id (PK)                 │
│ name                    │
│ phone                   │
│ position                │
│ deleted_at              │
├─────────────────────────┘
          │
          │ (1:N) operates
          │
          ├──────────────────────────────────┬──────────────────────────────────┐
          │                                  │                                  │
          ▼                                  ▼                                  ▼
┌──────────────────────┐      ┌──────────────────────┐      ┌──────────────────────┐
│      RENTALS         │      │  FNB_ORDERS          │      │   RESERVATIONS       │
├──────────────────────┤      ├──────────────────────┤      ├──────────────────────┤
│ id (PK)              │      │ id (PK)              │      │ id (PK)              │
│ transaction_code (U) │      │ code (U)             │      │ console_id (FK)      │
│ customer_name        │      │ customer_name        │      │ employee_id (FK)     │
│ console_id (FK)──────┼──────┼──────────────────┐   │      │ customer_name        │
│ employee_id (FK)─────┼──┐   │ employee_id (FK) │   │      │ customer_phone       │
│ package_id (FK)      │  │   │ total_amount     │   │      │ reserved_at          │
│ rental_type          │  │   │ status           │   │      │ duration_hours       │
│ status               │  │   │ payment_method   │   │      │ notes                │
│ started_at           │  │   │ paid_at          │   │      │ status               │
│ ended_at             │  │   │ notes            │   │      │ rental_id (FK)       │
│ scheduled_end_at     │  │   │ timestamps       │   │      │ timestamps           │
│ rental_amount        │  │   ├──────────────────┤   │      ├──────────────────────┘
│ fnb_amount           │  │   │ 1:N              │   │      │
│ extra_amount         │  │   │ ↓                │   │      │
│ total_amount         │  │   └────┬─────────────┘   │      │
│ paid_amount          │  │        │                 │      │
│ notes                │  │        ▼                 │      │
│ timestamps           │  │  ┌──────────────────────┐│      │
│ soft_deletes         │  │  │   FNB_ORDER_ITEMS    ││      │
├──────────────────────┤  │  ├──────────────────────┤│      │
│ 1:N ↓ extensions     │  │  │ id (PK)              ││      │
│ 1:N ↓ fnb_items      │  │  │ fnb_order_id (FK)    ││      │
│ 1:N ↓ payments       │  │  │ fnb_item_id (FK)────┼┼──────┼─────┐
└──────────────────────┘  │  │ qty                  ││      │     │
          │               │  │ unit_price           ││      │     │
          │               │  │ subtotal             ││      │     │
          │               │  │ addons (JSON)        ││      │     │
          │               │  │ addons_price         ││      │     │
          │               │  │ timestamps           ││      │     │
          │               │  ├──────────────────────┤│      │     │
          │               │  └────────────────────────┘      │     │
          │               │                                   │     │
          ▼               │                                   │     │
┌──────────────────────┐  │                                   │     │
│   RENTAL_EXTENSIONS  │  │                                   │     │
├──────────────────────┤  │                                   │     │
│ id (PK)              │  │                                   │     │
│ rental_id (FK)       │  │                                   │     │
│ added_minutes        │  │                                   │     │
│ additional_price     │  │                                   │     │
│ notes                │  │                                   │     │
│ timestamps           │  │                                   │     │
├──────────────────────┘  │                                   │     │
                          │                                   │     │
          ┌───────────────┘                                   │     │
          │                                                   │     │
          ▼                                                   │     │
┌──────────────────────┐                                      │     │
│   RENTAL_FNB_ITEMS   │                                      │     │
├──────────────────────┤                                      │     │
│ id (PK)              │                                      │     │
│ rental_id (FK)       │                                      │     │
│ fnb_item_id (FK)─────┼──────────────────────────────────────┘     │
│ qty                  │                                            │
│ unit_price           │                                            │
│ subtotal             │                                            │
│ addons (JSON)        │                                            │
│ addons_price         │                                            │
│ timestamps           │                                            │
├──────────────────────┘                                            │
                                                                    │
          ┌─────────────────────────────────────────────────────────┘
          │
          ▼
┌──────────────────────┐
│      FNB_ITEMS       │
├──────────────────────┤
│ id (PK)              │
│ name                 │
│ category             │
│ price                │
│ stock                │
│ is_available         │
│ deleted_at           │
├──────────────────────┤
│ 1:N ↓ addons         │
└──────────────────────┘
          │
          │ (1:N) has
          ▼
┌──────────────────────┐
│      FNB_ADDONS      │
├──────────────────────┤
│ id (PK)              │
│ name                 │
│ price                │
│ is_available         │
│ deleted_at           │
├──────────────────────┘
```

---

## Table Definitions

### 1. **USERS**
Menyimpan data pengguna/admin sistem.

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id_user     | BIGINT    | PRIMARY KEY       | Auto increment         |
| nama        | VARCHAR   | NOT NULL          | Nama lengkap user      |
| username    | VARCHAR   | UNIQUE, NOT NULL  | Username login         |
| password    | VARCHAR   | NOT NULL          | Hashed password        |
| role        | ENUM      | NOT NULL          | admin, operator, etc   |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 2. **EMPLOYEES**
Data operator/staff yang melayani rental dan transaksi.

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment         |
| name        | VARCHAR   | NOT NULL          | Nama lengkap karyawan  |
| phone       | VARCHAR   | NULLABLE          | No. telepon            |
| position    | VARCHAR   | NULLABLE          | Jabatan/posisi         |
| deleted_at  | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 3. **CONSOLES**
Data ruangan/console gaming yang tersedia.

| Column          | Type      | Constraint        | Notes                  |
|-----------------|-----------|-------------------|------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment         |
| name            | VARCHAR   | NOT NULL, UNIQUE  | Nama ruangan/console   |
| type            | VARCHAR   | NOT NULL          | regular/vip/vvip/suite |
| price_per_hour  | DECIMAL   | NOT NULL          | Tarif per jam          |
| is_available    | BOOLEAN   | DEFAULT: true     | Status ketersediaan    |
| deleted_at      | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan        |

---

### 4. **RENTALS**
Transaksi rental utama.

| Column          | Type      | Constraint        | Notes                              |
|-----------------|-----------|-------------------|------------------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment                     |
| transaction_code| VARCHAR   | UNIQUE, NOT NULL  | Kode transaksi unik                |
| customer_name   | VARCHAR   | NOT NULL          | Nama customer                      |
| console_id      | BIGINT    | FK, NOT NULL      | FK ke CONSOLES                     |
| employee_id     | BIGINT    | FK, NOT NULL      | FK ke EMPLOYEES                    |
| package_id      | BIGINT    | FK, NULLABLE      | FK ke PACKAGES (null=open time)    |
| rental_type     | ENUM      | DEFAULT: open_time| open_time atau package             |
| status          | ENUM      | DEFAULT: running  | running/finished/paid/half_paid    |
| started_at      | TIMESTAMP | NOT NULL          | Waktu mulai rental                 |
| ended_at        | TIMESTAMP | NULLABLE          | Waktu selesai rental               |
| scheduled_end_at| TIMESTAMP | NULLABLE          | Waktu rencana selesai (package)    |
| rental_amount   | DECIMAL   | DEFAULT: 0        | Biaya rental                       |
| fnb_amount      | DECIMAL   | DEFAULT: 0        | Biaya FnB                          |
| extra_amount    | DECIMAL   | DEFAULT: 0        | Biaya tambahan (overtime/ext)      |
| total_amount    | DECIMAL   | DEFAULT: 0        | Total biaya                        |
| paid_amount     | DECIMAL   | DEFAULT: 0        | Jumlah pembayaran                  |
| notes           | TEXT      | NULLABLE          | Catatan transaksi                  |
| deleted_at      | TIMESTAMP | NULLABLE          | Soft delete timestamp              |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan                    |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan                    |

---

### 5. **RENTAL_EXTENSIONS**
Perpanjangan waktu rental (tambah waktu).

| Column          | Type      | Constraint        | Notes                  |
|-----------------|-----------|-------------------|------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment         |
| rental_id       | BIGINT    | FK, NOT NULL      | FK ke RENTALS          |
| added_minutes   | INT       | NOT NULL          | Jumlah menit ditambah  |
| additional_price| DECIMAL   | NOT NULL          | Harga tambahan         |
| notes           | TEXT      | NULLABLE          | Catatan perpanjangan   |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan        |

---

### 6. **FNB_ITEMS**
Master data item FnB (makanan/minuman).

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment         |
| name        | VARCHAR   | NOT NULL          | Nama item FnB          |
| category    | VARCHAR   | DEFAULT: food     | food/drink/snack/etc   |
| price       | DECIMAL   | NOT NULL          | Harga item             |
| stock       | INT       | DEFAULT: 0        | Stok tersedia          |
| is_available| BOOLEAN   | DEFAULT: true     | Ketersediaan           |
| deleted_at  | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 7. **FNB_ADDONS**
Master data add-on FnB (topping, extra, dll).

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment         |
| name        | VARCHAR   | NOT NULL          | Nama add-on             |
| price       | DECIMAL   | NOT NULL          | Harga add-on            |
| is_available| BOOLEAN   | DEFAULT: true     | Ketersediaan           |
| deleted_at  | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 8. **RENTAL_FNB_ITEMS**
Detail item FnB yang dibeli saat rental.

| Column      | Type      | Constraint        | Notes                      |
|-------------|-----------|-------------------|----------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment             |
| rental_id   | BIGINT    | FK, NOT NULL      | FK ke RENTALS              |
| fnb_item_id | BIGINT    | FK, NOT NULL      | FK ke FNB_ITEMS            |
| qty         | INT       | DEFAULT: 1        | Jumlah item                |
| unit_price  | DECIMAL   | NOT NULL          | Harga satuan saat membeli  |
| subtotal    | DECIMAL   | NOT NULL          | Total untuk item ini       |
| addons      | JSON      | NULLABLE          | Array of add-on dipilih    |
| addons_price| DECIMAL   | DEFAULT: 0        | Total harga add-on         |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan            |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan            |

---

### 9. **FNB_ORDERS**
Transaksi FnB standalone (tanpa rental).

| Column          | Type      | Constraint        | Notes                          |
|-----------------|-----------|-------------------|--------------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment                 |
| code            | VARCHAR   | UNIQUE, NOT NULL  | Kode order FnB (FNB-XXXXXX)    |
| customer_name   | VARCHAR   | NULLABLE          | Nama customer (opsional)       |
| employee_id     | BIGINT    | FK, NOT NULL      | FK ke EMPLOYEES                |
| total_amount    | DECIMAL   | DEFAULT: 0        | Total harga pesanan            |
| status          | ENUM      | DEFAULT: pending  | pending/paid                   |
| payment_method  | ENUM      | NULLABLE          | cash/qris                      |
| notes           | TEXT      | NULLABLE          | Catatan order                  |
| paid_at         | TIMESTAMP | NULLABLE          | Waktu pembayaran               |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan                |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan                |

---

### 10. **FNB_ORDER_ITEMS**
Detail item dalam FNB order standalone.

| Column      | Type      | Constraint        | Notes                      |
|-------------|-----------|-------------------|----------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment             |
| fnb_order_id| BIGINT    | FK, NOT NULL      | FK ke FNB_ORDERS           |
| fnb_item_id | BIGINT    | FK, NOT NULL      | FK ke FNB_ITEMS            |
| qty         | INT       | DEFAULT: 1        | Jumlah item                |
| unit_price  | DECIMAL   | NOT NULL          | Harga satuan saat membeli  |
| subtotal    | DECIMAL   | NOT NULL          | Total untuk item ini       |
| addons      | JSON      | NULLABLE          | Array of add-on dipilih    |
| addons_price| DECIMAL   | DEFAULT: 0        | Total harga add-on         |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan            |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan            |

---

### 11. **RENTAL_PAYMENTS**
Riwayat pembayaran rental.

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment         |
| rental_id   | BIGINT    | FK, NOT NULL      | FK ke RENTALS          |
| method      | ENUM      | DEFAULT: cash     | cash/qris/split/half_paid |
| amount      | DECIMAL   | NOT NULL          | Jumlah pembayaran      |
| notes       | TEXT      | NULLABLE          | Catatan pembayaran     |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 12. **RESERVATIONS**
Reservasi console untuk rental berikutnya.

| Column          | Type      | Constraint        | Notes                          |
|-----------------|-----------|-------------------|--------------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment                 |
| console_id      | BIGINT    | FK, NOT NULL      | FK ke CONSOLES                 |
| employee_id     | BIGINT    | FK, NOT NULL      | FK ke EMPLOYEES                |
| customer_name   | VARCHAR   | NOT NULL          | Nama customer                  |
| customer_phone  | VARCHAR   | NULLABLE          | No. telepon customer           |
| reserved_at     | TIMESTAMP | NOT NULL          | Waktu reservasi dimulai        |
| duration_hours  | FLOAT     | NULLABLE          | Durasi rencana (jam)           |
| notes           | TEXT      | NULLABLE          | Catatan reservasi              |
| status          | ENUM      | DEFAULT: pending  | pending/confirmed/cancelled/converted |
| rental_id       | BIGINT    | FK, NULLABLE      | FK ke RENTALS (saat converted) |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan                |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan                |

---

### 13. **CASH_OUTBOUNDS**
Riwayat pengeluaran kas.

| Column      | Type      | Constraint        | Notes                  |
|-------------|-----------|-------------------|------------------------|
| id          | BIGINT    | PRIMARY KEY       | Auto increment         |
| nominal     | DECIMAL   | NOT NULL          | Jumlah pengeluaran     |
| notes       | TEXT      | NOT NULL          | Keterangan pengeluaran |
| employee_id | BIGINT    | FK, NOT NULL      | FK ke EMPLOYEES        |
| date        | DATE      | NOT NULL          | Tanggal pengeluaran    |
| deleted_at  | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at  | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at  | TIMESTAMP | -                 | Waktu perubahan        |

---

### 14. **PACKAGES**
Master data paket rental (durasi tetap).

| Column          | Type      | Constraint        | Notes                  |
|-----------------|-----------|-------------------|------------------------|
| id              | BIGINT    | PRIMARY KEY       | Auto increment         |
| name            | VARCHAR   | NOT NULL          | Nama paket             |
| description     | TEXT      | NULLABLE          | Deskripsi paket        |
| duration_hours  | INT       | NOT NULL          | Durasi paket (jam)     |
| price           | DECIMAL   | NOT NULL          | Harga paket            |
| is_available    | BOOLEAN   | DEFAULT: true     | Ketersediaan paket     |
| deleted_at      | TIMESTAMP | NULLABLE          | Soft delete timestamp  |
| created_at      | TIMESTAMP | -                 | Waktu pembuatan        |
| updated_at      | TIMESTAMP | -                 | Waktu perubahan        |

---

## Relationships Summary

| From              | To                  | Type    | Notes                                    |
|-------------------|---------------------|---------|------------------------------------------|
| RENTALS           | CONSOLES            | N:1     | Setiap rental menggunakan 1 console      |
| RENTALS           | EMPLOYEES           | N:1     | Setiap rental dilayani 1 operator        |
| RENTALS           | PACKAGES            | N:1     | Rental bisa menggunakan package atau null|
| RENTAL_EXTENSIONS | RENTALS             | N:1     | Bisa ada banyak perpanjangan per rental  |
| RENTAL_FNB_ITEMS  | RENTALS             | N:1     | Bisa ada banyak FnB per rental           |
| RENTAL_FNB_ITEMS  | FNB_ITEMS           | N:1     | Setiap order FnB merujuk ke master item  |
| RENTAL_PAYMENTS   | RENTALS             | N:1     | Bisa ada banyak pembayaran per rental    |
| RESERVATIONS      | CONSOLES            | N:1     | Reservasi untuk console tertentu        |
| RESERVATIONS      | EMPLOYEES           | N:1     | Reservasi diinput oleh operator          |
| RESERVATIONS      | RENTALS             | N:1     | Reservasi bisa di-convert ke rental      |
| FNB_ORDERS        | EMPLOYEES           | N:1     | Order FnB dibuat oleh operator           |
| FNB_ORDER_ITEMS   | FNB_ORDERS          | N:1     | Banyak item dalam 1 order FnB            |
| FNB_ORDER_ITEMS   | FNB_ITEMS           | N:1     | Merujuk ke master FnB item               |
| FNB_ADDONS        | FNB_ITEMS           | N:1     | Add-on tersedia untuk item tertentu      |
| CASH_OUTBOUNDS    | EMPLOYEES           | N:1     | Pengeluaran dicatat oleh operator        |

---

## Key Features

✅ **Multi-tenant Ready**: Support untuk multiple users/roles  
✅ **Soft Delete**: Data tidak dihapus tapi di-soft delete  
✅ **Audit Trail**: created_at, updated_at di setiap tabel  
✅ **Flexible Pricing**: Support open-time dan package-based  
✅ **FnB Management**: Master item + add-on system  
✅ **Standalone FnB Orders**: Bisa jual FnB tanpa rental  
✅ **Reservations**: Pre-booking console untuk rental mendatang  
✅ **Payment Tracking**: Track pembayaran per transaksi  
✅ **Cash Flow**: Pencatatan pengeluaran untuk analisis  
✅ **JSON Storage**: Add-on details disimpan sebagai JSON array  
