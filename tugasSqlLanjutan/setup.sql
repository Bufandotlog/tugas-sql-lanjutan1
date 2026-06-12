-- Setup Awal Database
CREATE DATABASE IF NOT EXISTS KuliahSQLLanjut;
USE KuliahSQLLanjut;

-- Hapus tabel lama jika ada untuk menghindari konflik (sesuai urutan foreign key)
DROP TABLE IF EXISTS Log_Gaji;
DROP TABLE IF EXISTS Karyawan;
DROP TABLE IF EXISTS Departemen;

-- 1. Buat tabel Departemen
CREATE TABLE Departemen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_departemen VARCHAR(100) NOT NULL
);

-- 2. Buat tabel Karyawan
CREATE TABLE Karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    gaji DECIMAL(15, 2) NOT NULL,
    departemen_id INT,
    manajer_id INT,
    FOREIGN KEY (departemen_id) REFERENCES Departemen(id) ON DELETE SET NULL,
    FOREIGN KEY (manajer_id) REFERENCES Karyawan(id) ON DELETE SET NULL
);

-- 3. Buat tabel Log_Gaji
CREATE TABLE Log_Gaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NOT NULL,
    gaji_lama DECIMAL(15, 2) NOT NULL,
    gaji_baru DECIMAL(15, 2) NOT NULL,
    tanggal_ubah DATETIME NOT NULL
);

-- 4. Masukkan data dummy (5 karyawan di 3 departemen: IT, HRD, Finance)
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
