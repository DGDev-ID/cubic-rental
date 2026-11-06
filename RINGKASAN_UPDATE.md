# Ringkasan Pembaruan Sistem Cubic Rental PS

Dokumentasi perubahan, penambahan fitur, dan perbaikan sistem sejak penanganan bug zona waktu reservasi hingga pembaruan terakhir.

---

## 1. Reservasi & Room Monitor
* **Perbaikan Jam Reservasi (Timezone)**:
  * Memperbaiki bug input jam 15:00 yang tersimpan sebagai jam 08:00 akibat perbedaan waktu UTC dan zona lokal WIB (`Asia/Jakarta` / `+07:00`).
* **Alert Overtime di Room Monitor**:
  * Memberikan penanda visual border merah berkedip saat durasi sewa habis agar operator segera mengetahui status kamar/meja.
* **Fitur Bayar di Awal (Prepaid / DP)**:
  * Dukungan pembayaran langsung saat sesi rental baru dibuat (bisa lunas atau DP).
  * Penandaan status "Terbayar" pada kartu Room Monitor.
* **Fitur Pindah Room (Switch Console)**:
  * Operator dapat memindahkan sesi rental aktif ke room/console lain tanpa mereset atau menghapus riwayat durasi yang sedang berjalan.

---

## 2. Riwayat Transaksi & Pembukuan
* **Aksi Cepat Riwayat**:
  * Ditambahkan tombol **Hapus Transaksi** dan **Edit Catatan Transaksi** langsung pada tabel riwayat.
* **Modal Detail Transaksi**:
  * Rincian lengkap transaksi (durasi, console, rincian item FnB beserta add-on, metode bayar) dapat dilihat langsung melalui popup modal tanpa perlu berpindah halaman.
* **Pembulatan Billing Rental**:
  * Penyesuaian billing biaya bermain rental PS ke kelipatan ribuan terdekat (Rp 1.000).
* **Breakdown Statistik Omset Berkala**:
  * Pilihan visualisasi omset: **Tahunan**, **Bulanan**, dan **Harian**.
  * Pemisahan kartu ringkasan: **Total Omset Gabungan**, **Total Rental Saja**, dan **Total FnB Saja**.
  * Filter periode interaktif dan tombol reset ke semua periode.

---

## 3. Sistem Member Cubic (Membership)
* **Manajemen Paket & Member**:
  * Menu baru **Member Cubic** di sidebar navigasi.
  * 4 Paket Membership Lifetime (tanpa masa kedaluwarsa):
    * **Reguler**: 12 Jam (Rp 100.000)
    * **VIP**: 12 Jam (Rp 150.000)
    * **VVIP**: 12 Jam (Rp 250.000)
    * **Suite**: 12 Jam (Rp 350.000)
  * Modul pendaftaran member, top-up deposit kuota, dan mutasi saldo.
* **Alur Bermain Member**:
  * Pemilihan nama member saat pembuatan rental baru.
  * Biaya sewa PS otomatis Rp 0 (memotong deposit jam).
  * Durasi sewa dibatasi maksimal sesuai sisa kuota saldo deposit (tidak ada overtime berbayar).
  * Kuota deposit waktu otomatis terpotong saat sesi rental diselesaikan.

---

## 4. Penyempurnaan Export Excel
* **Export 2 Sheet (Gabungan & Member)**:
  * *Sheet 1*: Pembukuan transaksi harian rental & FnB.
  * *Sheet 2*: Rekap khusus transaksi dan mutasi deposit member.
* **Urutan Kronologis Baris Transaksi**:
  * Urutan baris dibalik dari jam buka paling atas ke jam tutup paling bawah (`started_at asc`).
* **Operator Kasir Pembayaran**:
  * Pilihan nama kasir saat proses pelunasan/pembayaran (memisahkan operator pembuat sesi dengan kasir penerima uang shift berikutnya).
  * Kasir penerima tercatat pada kolom N Excel dan struk.
* **Tampilan Add-on FnB (Telur, dsb)**:
  * Menampilkan varian tambahan menu (contoh: `INDOMIE GORENG JUMBO DBL (+ TELUR)`) pada detail riwayat dan kolom FnB di Excel.

---

## 5. Cetak Struk (Printer Thermal POS 58mm)
* **Optimasi Teks Tebal & Hitam Pekat**:
  * Pengubahan font ke `font-weight: 700` hingga `900`.
  * Seluruh teks dan pembatas diubah menjadi warna solid black (`#000000`), menghilangkan efek buram/titik-titik (*dithering*) pada printer thermal.
  * Menggunakan font tebal `'Consolas'` / `'Courier New'`.
  * Penambahan CSS print `-webkit-text-stroke` untuk mempertebal fisik cetak jarum panas pada kertas thermal.
* **Struk Mandiri untuk FnB Only**:
  * Halaman cetak struk tersendiri khusus pesanan FnB non-rental.
  * Tombol **Struk** pada tabel tab FnB di Riwayat, di modal detail FnB, dan di halaman Penjualan FnB.

---

## 6. Menu Pengeluaran Kas (Cash Outbound)
* **Fitur Export Excel Pengeluaran**:
  * Tombol **Export Excel** di header menu Pengeluaran Kas.
  * Pilihan rentang periode export:
    * **Semua Periode**
    * **Per Tahun**
    * **Per Bulan**
    * **Per Hari / Tanggal** (otomatis mengambil tanggal filter aktif).
  * Format Excel rapi bertema merah pengeluaran Cubic: nomor, tanggal, keterangan, nama operator, nominal format rupiah, dan baris **TOTAL PENGELUARAN**.

