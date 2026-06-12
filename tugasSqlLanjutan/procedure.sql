USE KuliahSQLLanjut;

-- Hapus procedure jika sudah ada
DROP PROCEDURE IF EXISTS PindahDepartemen;

-- Buat procedure PindahDepartemen
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
        -- Jika departemen tidak ditemukan, lemparkan error
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: Departemen tujuan tidak ditemukan!';
    END IF;
END //
DELIMITER ;
