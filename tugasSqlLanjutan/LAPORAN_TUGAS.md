# LAPORAN TUGAS MANDIRI - KULIAH SQL LANJUTAN
**Mata Kuliah:** SQL Lanjutan  
**Topik:** Self Join, Trigger, dan Stored Procedure  
**Database:** `KuliahSQLLanjut`  
**Penyusun:** Bu Fan  

---

## Langkah 0: Persiapan Database (Setup Awal)

Sebelum mengerjakan tugas utama, struktur database dan data awal disiapkan terlebih dahulu dengan membuat 3 tabel: `Departemen`, `Karyawan`, dan `Log_Gaji`.

### 1. Sintaks SQL Pembuatan Struktur Tabel
```sql
CREATE DATABASE IF NOT EXISTS KuliahSQLLanjut;
USE KuliahSQLLanjut;

-- Pembuatan Tabel Departemen
CREATE TABLE Departemen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_departemen VARCHAR(100) NOT NULL
);

-- Pembuatan Tabel Karyawan
CREATE TABLE Karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    gaji DECIMAL(15, 2) NOT NULL,
    departemen_id INT,
    manajer_id INT,
    FOREIGN KEY (departemen_id) REFERENCES Departemen(id) ON DELETE SET NULL,
    FOREIGN KEY (manajer_id) REFERENCES Karyawan(id) ON DELETE SET NULL
);

-- Pembuatan Tabel Log_Gaji
CREATE TABLE Log_Gaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NOT NULL,
    gaji_lama DECIMAL(15, 2) NOT NULL,
    gaji_baru DECIMAL(15, 2) NOT NULL,
    tanggal_ubah DATETIME NOT NULL
);
```

### 2. Sintaks SQL Pengisian Data Dummy
Data dummy mencakup **5 karyawan di 3 departemen** (IT, HRD, Finance) dengan hierarki manajer. Karyawan bernama **Andi** disiapkan di departemen IT dengan manajer bernama **Budi**.
```sql
INSERT INTO Departemen (id, nama_departemen) VALUES
(1, 'IT'),
(2, 'HRD'),
(3, 'Finance');

INSERT INTO Karyawan (id, nama, gaji, departemen_id, manajer_id) VALUES
(1, 'Budi', 12000000.00, 1, NULL),  -- Manajer IT
(2, 'Citra', 11000000.00, 2, NULL), -- Manajer HRD
(3, 'Eko', 10000000.00, 3, NULL),   -- Staff Finance
(4, 'Andi', 8000000.00, 1, 1),      -- Staff IT (bawahan Budi)
(5, 'Dewi', 7500000.00, 2, 2);      -- Staff HRD (bawahan Citra)
```

---

## Langkah 1: Mengerjakan Tugas 1 - Self Join Analysis (Bobot 30%)

Fokus tugas ini adalah menampilkan hubungan relasi hirarki antara karyawan dengan manajernya menggunakan metode **Self Join** (menghubungkan tabel `Karyawan` ke dirinya sendiri).

### 1. Sintaks SQL Query
```sql
SELECT 
    e.nama AS Karyawan, 
    COALESCE(m.nama, 'Tanpa Manajer') AS Manajer 
FROM Karyawan e 
LEFT JOIN Karyawan m ON e.manajer_id = m.id;
```

### 2. Hasil Eksekusi Query
| Karyawan | Manajer |
| :--- | :--- |
| Budi | Tanpa Manajer |
| Citra | Tanpa Manajer |
| Eko | Tanpa Manajer |
| Andi | Budi |
| Dewi | Citra |

*(Karyawan "Andi" berhasil ditampilkan memiliki manajer "Budi", sedangkan Budi, Citra, dan Eko ditampilkan sebagai "Tanpa Manajer" karena nilai `manajer_id` mereka adalah `NULL`)*

---

## Langkah 2: Mengerjakan Tugas 2 - Trigger Implementation (Bobot 40%)

Tugas ini bertujuan untuk mengotomatisasi pencatatan perubahan gaji karyawan ke dalam tabel log setiap kali ada proses `UPDATE` gaji.

### 1. Sintaks Pembuatan Trigger
```sql
DELIMITER //
CREATE TRIGGER setelah_update_gaji AFTER UPDATE ON Karyawan 
FOR EACH ROW 
BEGIN  
   IF OLD.gaji <> NEW.gaji THEN  
      INSERT INTO Log_Gaji (karyawan_id, gaji_lama, gaji_baru, tanggal_ubah)  
      VALUES (OLD.id, OLD.gaji, NEW.gaji, NOW());  
   END IF; 
END //
DELIMITER ;
```

### 2. Eksekusi Perubahan Data (Update Gaji Andi)
Mengubah gaji karyawan bernama Andi menjadi Rp 9.000.000 (dari Rp 8.000.000).
```sql
UPDATE Karyawan SET gaji = 9000000 WHERE nama = 'Andi';
```

### 3. Hasil Pembuktian (Melihat Tabel `Log_Gaji`)
```sql
SELECT * FROM Log_Gaji;
```

**Hasil Output:**
| id | karyawan_id | gaji_lama | gaji_baru | tanggal_ubah |
| :--- | :--- | :--- | :--- | :--- |
| 1 | 4 | 8000000.00 | 9000000.00 | 2026-06-12 16:50:27 |

*(Trigger berhasil mendeteksi perubahan gaji Andi dari 8.000.000 menjadi 9.000.000 dan langsung mencatatkan entri log baru ke tabel `Log_Gaji` lengkap dengan stempel waktu)*

---

## Langkah 3: Mengerjakan Tugas 3 - Stored Procedure (Bobot 30%)

Mengerjakan pembuatan fungsi tersimpan (`Stored Procedure`) untuk memindahkan karyawan ke departemen baru dengan menambahkan validasi keberadaan departemen tujuan.

### 1. Sintaks Pembuatan Stored Procedure
```sql
DELIMITER //
CREATE PROCEDURE PindahDepartemen(
    IN p_karyawan_id INT,
    IN p_dept_id_baru INT
)
BEGIN
    -- Validasi keberadaan departemen tujuan
    IF EXISTS (SELECT 1 FROM Departemen WHERE id = p_dept_id_baru) THEN
        -- Jika departemen valid, update data departemen karyawan
        UPDATE Karyawan 
        SET departemen_id = p_dept_id_baru 
        WHERE id = p_karyawan_id;
    ELSE
        -- Jika departemen TIDAK valid, gagalkan transaksi dan tampilkan pesan error
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: Departemen tujuan tidak ditemukan!';
    END IF;
END //
DELIMITER ;
```

### 2. Eksekusi Uji Coba

#### Kasus A: Eksekusi Berhasil (Departemen Tujuan Valid)
Memindahkan Andi (ID: 4) ke departemen Finance (ID: 3):
```sql
CALL PindahDepartemen(4, 3);
SELECT * FROM Karyawan WHERE id = 4;
```
**Hasil Output:**
* Karyawan Andi (ID 4) sukses berpindah ke `departemen_id` = 3 (Finance).

#### Kasus B: Eksekusi Gagal (Membuktikan Validasi Departemen Tidak Valid)
Memindahkan Andi (ID: 4) ke departemen non-existent (ID: 99):
```sql
CALL PindahDepartemen(4, 99);
```
**Hasil Output:**
```
ERROR 1644 (45000): Error: Departemen tujuan tidak ditemukan!
```
*(Stored Procedure berhasil membatalkan transaksi dan melemparkan pesan kesalahan kustom ketika ID departemen yang dimasukkan tidak terdaftar di tabel `Departemen`)*

---

## Kesimpulan
Seluruh langkah pengerjaan Tugas Mandiri dari Langkah 0 hingga Langkah 3 telah berhasil diimplementasikan dan diuji secara langsung menggunakan database server lokal. Semua fungsionalitas SQL tingkat lanjut seperti relasi Self Join, otomasi audit log via Trigger, dan logika kondisional terenkapsulasi dalam Stored Procedure telah berjalan dengan sempurna sesuai instruksi modul.
