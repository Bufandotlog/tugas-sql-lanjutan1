USE KuliahSQLLanjut;

-- Hapus trigger jika sudah ada
DROP TRIGGER IF EXISTS setelah_update_gaji;

-- Buat trigger setelah_update_gaji
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
