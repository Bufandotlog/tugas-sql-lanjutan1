-- =========================================================================
-- TUGAS MANDIRI - KULIAH SQL LANJUTAN
-- Nama Database: KuliahSQLLanjut
-- Kumpulan Query & Logika: Langkah 0 s.d Langkah 3
-- =========================================================================

-- =========================================================================
-- LANGKAH 0: SETUP AWAL DATABASE & TABEL
-- =========================================================================

-- 1. Buat database baru bernama KuliahSQLLanjut
CREATE DATABASE IF NOT EXISTS KuliahSQLLanjut;
USE KuliahSQLLanjut;

-- Hapus tabel lama jika ada (untuk menghindari konflik foreign key saat pembuatan ulang)
DROP TABLE IF EXISTS Log_Gaji;
DROP TABLE IF EXISTS Karyawan;
DROP TABLE IF EXISTS Departemen;

-- 2. Buat tabel Departemen
CREATE TABLE Departemen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_departemen VARCHAR(100) NOT NULL
);

-- 3. Buat tabel Karyawan
CREATE TABLE Karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    gaji DECIMAL(15, 2) NOT NULL,
    departemen_id INT,
    manajer_id INT,
    FOREIGN KEY (departemen_id) REFERENCES Departemen(id) ON DELETE SET NULL,
    FOREIGN KEY (manajer_id) REFERENCES Karyawan(id) ON DELETE SET NULL
);

-- 4. Buat tabel Log_Gaji
CREATE TABLE Log_Gaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NOT NULL,
    gaji_lama DECIMAL(15, 2) NOT NULL,
    gaji_baru DECIMAL(15, 2) NOT NULL,
    tanggal_ubah DATETIME NOT NULL
);

-- 5. Masukkan data dummy (5 karyawan di 3 departemen: IT, HRD, Finance) dengan hierarki manajer
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


-- =========================================================================
-- LANGKAH 1: TUGAS 1 - SELF JOIN ANALYSIS
-- =========================================================================
-- Query untuk menampilkan nama karyawan beserta nama manajernya. 
-- Menggunakan LEFT JOIN agar karyawan yang tidak memiliki manajer tetap tampil, 
-- dan dibungkus COALESCE() untuk menampilkan 'Tanpa Manajer' jika manajer_id NULL.

SELECT 
    e.nama AS Karyawan, 
    COALESCE(m.nama, 'Tanpa Manajer') AS Manajer 
FROM Karyawan e 
LEFT JOIN Karyawan m ON e.manajer_id = m.id;


-- =========================================================================
-- LANGKAH 2: TUGAS 2 - TRIGGER IMPLEMENTATION
-- =========================================================================

-- 1. Buat Trigger 'setelah_update_gaji'
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

-- 2. Uji coba trigger dengan mengubah gaji karyawan bernama "Andi" menjadi 9.000.000
UPDATE Karyawan SET gaji = 9000000 WHERE nama = 'Andi';

-- 3. Buktikan trigger berjalan dengan menampilkan isi tabel log
SELECT * FROM Log_Gaji;


-- =========================================================================
-- LANGKAH 3: TUGAS 3 - STORED PROCEDURE
-- =========================================================================

-- 1. Buat Stored Procedure 'PindahDepartemen' dengan validasi input
DELIMITER //
CREATE PROCEDURE PindahDepartemen(
    IN p_karyawan_id INT,
    IN p_dept_id_baru INT
)
BEGIN
    -- Validasi keberadaan departemen tujuan
    IF EXISTS (SELECT 1 FROM Departemen WHERE id = p_dept_id_baru) THEN
        -- Jika departemen ditemukan, update departemen_id karyawan
        UPDATE Karyawan 
        SET departemen_id = p_dept_id_baru 
        WHERE id = p_karyawan_id;
    ELSE
        -- Jika departemen tidak ditemukan, batalkan transaksi dengan pesan error
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: Departemen tujuan tidak ditemukan!';
    END IF;
END //
DELIMITER ;

-- 2. Contoh Eksekusi Sukses: Memindahkan Andi (ID: 4) ke departemen Finance (ID: 3)
CALL PindahDepartemen(4, 3);
SELECT * FROM Karyawan WHERE id = 4;

-- 3. Contoh Eksekusi Gagal (Membuktikan Validasi): Memindahkan Andi ke departemen non-existent (ID: 99)
-- Query ini akan mengembalikan error: "Error: Departemen tujuan tidak ditemukan!"
-- CALL PindahDepartemen(4, 99);
