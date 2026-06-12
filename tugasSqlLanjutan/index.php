<?php
// Konfigurasi Database
$host = '127.0.0.1';
$port = '33066';
$db   = 'KuliahSQLLanjut';
$user = 'root';
$pass = ''; // Tanpa password pada instance privat

// Pesan & status
$message = '';
$message_type = 'success'; // success, error

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Pastikan database KuliahSQLLanjut ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $message = "Koneksi database gagal: " . $e->getMessage();
    $message_type = "error";
}

// Proses Aksi User
if ($db_connected && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'reset') {
        try {
            // Jalankan setup.sql
            $setup_sql = file_get_contents(__DIR__ . '/setup.sql');
            $pdo->exec($setup_sql);
            
            // Jalankan trigger.sql
            $trigger_sql = file_get_contents(__DIR__ . '/trigger.sql');
            $pdo->exec($trigger_sql);
            
            // Jalankan procedure.sql
            $procedure_sql = file_get_contents(__DIR__ . '/procedure.sql');
            $pdo->exec($procedure_sql);
            
            $message = "Database berhasil di-reset ke data awal!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Gagal mereset database: " . $e->getMessage();
            $message_type = "error";
        }
    } 
    elseif ($action === 'update_gaji') {
        try {
            $nama = $_POST['nama'] ?? 'Andi';
            $gaji_baru = $_POST['gaji_baru'] ?? 9000000;
            
            $stmt = $pdo->prepare("UPDATE Karyawan SET gaji = :gaji WHERE nama = :nama");
            $stmt->execute(['gaji' => $gaji_baru, 'nama' => $nama]);
            
            $message = "Berhasil mengubah gaji Karyawan '$nama' menjadi Rp " . number_format($gaji_baru, 0, ',', '.') . ". Trigger 'setelah_update_gaji' telah mencatat log perubahan!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Gagal mengubah gaji: " . $e->getMessage();
            $message_type = "error";
        }
    }
    elseif ($action === 'pindah_dept') {
        try {
            $karyawan_id = $_POST['karyawan_id'] ?? 4;
            $dept_id = $_POST['dept_id'] ?? 3;
            
            // Panggil stored procedure dengan validasi
            $stmt = $pdo->prepare("CALL PindahDepartemen(:karyawan_id, :dept_id)");
            $stmt->execute(['karyawan_id' => $karyawan_id, 'dept_id' => $dept_id]);
            
            // Dapatkan nama departemen dan karyawan untuk pesan sukses
            $stmt_info = $pdo->prepare("
                SELECT k.nama, d.nama_departemen 
                FROM Karyawan k 
                LEFT JOIN Departemen d ON k.departemen_id = d.id 
                WHERE k.id = :karyawan_id
            ");
            $stmt_info->execute(['karyawan_id' => $karyawan_id]);
            $info = $stmt_info->fetch();
            
            $nama_karyawan = $info['nama'] ?? 'Karyawan';
            $nama_dept = $info['nama_departemen'] ?? 'Tanpa Departemen';
            
            $message = "Stored Procedure dipanggil sukses! Karyawan '$nama_karyawan' dipindahkan ke departemen '$nama_dept' (ID $dept_id).";
            $message_type = "success";
        } catch (PDOException $e) {
            // Tangkap pesan error dari SIGNAL SQLSTATE di stored procedure
            $message = "Stored Procedure Gagal (Validasi): " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Mengambil Data untuk Ditampilkan
$departemen_list = [];
$karyawan_list = [];
$log_gaji_list = [];
$self_join_results = [];

if ($db_connected) {
    try {
        // Daftar Departemen
        $departemen_list = $pdo->query("SELECT * FROM Departemen ORDER BY id ASC")->fetchAll();
        
        // Daftar Karyawan Lengkap
        $karyawan_list = $pdo->query("
            SELECT k.id, k.nama, k.gaji, d.nama_departemen, m.nama AS nama_manajer 
            FROM Karyawan k
            LEFT JOIN Departemen d ON k.departemen_id = d.id
            LEFT JOIN Karyawan m ON k.manajer_id = m.id
            ORDER BY k.id ASC
        ")->fetchAll();
        
        // Log Gaji
        $log_gaji_list = $pdo->query("
            SELECT l.id, k.nama AS nama_karyawan, l.gaji_lama, l.gaji_baru, l.tanggal_ubah 
            FROM Log_Gaji l
            JOIN Karyawan k ON l.karyawan_id = k.id
            ORDER BY l.tanggal_ubah DESC
        ")->fetchAll();
        
        // Hasil Query Tugas 1 (Self Join Analysis)
        $self_join_results = $pdo->query("
            SELECT e.nama AS Karyawan, COALESCE(m.nama, 'Tanpa Manajer') AS Manajer 
            FROM Karyawan e 
            LEFT JOIN Karyawan m ON e.manajer_id = m.id
            ORDER BY e.id ASC
        ")->fetchAll();
    } catch (Exception $e) {
        $message = "Gagal memuat data dari database: " . $e->getMessage();
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard SQL Lanjutan - KuliahSQLLanjut</title>
    <!-- Google Fonts: Outfit & Space Grotesk -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(20, 27, 45, 0.65);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.4);
            --accent-cyan: #06b6d4;
            --accent-purple: #a855f7;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --success: #10b981;
            --error: #ef4444;
            --code-bg: #07090e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 10% 20%, rgba(79, 70, 229, 0.15) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(168, 85, 247, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 80px;
            overflow-x: hidden;
        }

        /* Container */
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px 24px;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 0;
            border-bottom: 1px solid var(--card-border);
            margin-bottom: 40px;
        }

        .logo-section h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 30%, var(--accent-cyan) 70%, var(--accent-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .logo-section p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            text-decoration: none;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            color: white;
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.6);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
            border-color: var(--card-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: translateY(-2px);
        }

        /* Database Status Badge */
        .db-status {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.04);
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid var(--card-border);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-dot.active {
            background-color: var(--success);
            box-shadow: 0 0 10px var(--success);
        }

        .status-dot.inactive {
            background-color: var(--error);
            box-shadow: 0 0 10px var(--error);
        }

        /* Alert Messages */
        .alert {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 0.98rem;
            line-height: 1.5;
            border: 1px solid;
            animation: fadeIn 0.4s ease-out;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #a7f3d0;
            border-color: rgba(16, 185, 129, 0.25);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.25);
        }

        /* Layout Grid */
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 900px) {
            .grid {
                grid-template-columns: 2fr 1fr;
            }
            .full-width {
                grid-column: span 2;
            }
        }

        /* Card System */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            transition: border-color 0.3s ease;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-tugas1 { background: rgba(6, 182, 212, 0.15); color: var(--accent-cyan); border: 1px solid rgba(6, 182, 212, 0.3); }
        .badge-tugas2 { background: rgba(168, 85, 247, 0.15); color: var(--accent-purple); border: 1px solid rgba(168, 85, 247, 0.3); }
        .badge-tugas3 { background: rgba(79, 70, 229, 0.15); color: #818cf8; border: 1px solid rgba(79, 70, 229, 0.3); }

        /* Code Block Styling */
        .code-container {
            position: relative;
            background-color: var(--code-bg);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .code-block {
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
            font-size: 0.88rem;
            overflow-x: auto;
            white-space: pre;
            color: #d1d5db;
            line-height: 1.6;
        }

        .code-label {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.05);
            padding: 4px 12px;
            border-bottom-left-radius: 12px;
            border-top-right-radius: 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* SQL Syntax Highlighting colors */
        .sql-keyword { color: #f472b6; font-weight: 600; }
        .sql-type { color: #38bdf8; }
        .sql-string { color: #34d399; }
        .sql-number { color: #fbbf24; }
        .sql-comment { color: #6b7280; font-style: italic; }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0, 0, 0, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        th {
            background-color: rgba(255, 255, 255, 0.03);
            color: var(--text-main);
            font-weight: 600;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-family: 'Space Grotesk', sans-serif;
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: #d1d5db;
            transition: background 0.2s ease;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .empty-row {
            text-align: center;
            color: var(--text-muted);
            padding: 24px;
            font-style: italic;
        }

        /* Interactive Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--card-border);
            padding: 12px 16px;
            border-radius: 10px;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Info boxes */
        .info-box {
            background: rgba(6, 182, 212, 0.05);
            border-left: 4px solid var(--accent-cyan);
            border-radius: 0 12px 12px 0;
            padding: 18px 20px;
            margin: 20px 0;
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .info-box p {
            color: #c7d2fe;
        }

        /* Numbered steps indicators */
        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.85rem;
            margin-right: 8px;
        }

        .salary-tag {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: 2px 8px;
            border-radius: 6px;
            font-family: 'Fira Code', monospace;
            font-size: 0.88rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- Header -->
        <header>
            <div class="logo-section">
                <h1>SQL Advanced Dashboard</h1>
                <p>Implementasi Relasi, Trigger, & Stored Procedure (KuliahSQLLanjut)</p>
            </div>
            <div class="header-actions">
                <div class="db-status">
                    <span class="status-dot <?php echo $db_connected ? 'active' : 'inactive'; ?>"></span>
                    <span>MariaDB Server: <strong>127.0.0.1:33066</strong></span>
                </div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-danger">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.22M9 11l3-3m0 0l3 3m-3-3v8"></path></svg>
                        Reset Database
                    </button>
                </form>
            </div>
        </header>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <div>
                    <strong><?php echo $message_type === 'success' ? 'Sukses!' : 'Perhatian!'; ?></strong>
                    <p style="margin-top: 4px;"><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Grid Layout -->
        <div class="grid">

            <!-- Kiri: Tugas-Tugas SQL -->
            <div style="display: flex; flex-direction: column; gap: 35px;">

                <!-- KARTU 1: TUGAS 1 - SELF JOIN ANALYSIS -->
                <div class="card" id="tugas-1">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">
                                <span class="step-num">1</span>
                                Tugas 1: Self Join Analysis
                            </h2>
                            <p class="card-subtitle">Menampilkan relasi atasan-bawahan dengan mencocokkan ID Manajer</p>
                        </div>
                        <span class="badge badge-tugas1">Bobot 30%</span>
                    </div>

                    <p>Mencocokkan data karyawan ke dirinya sendiri menggunakan <code>LEFT JOIN</code> untuk mendapatkan nama manajer. Apabila karyawan tidak memiliki manajer (bernilai <code>NULL</code>), ditampilkan teks <strong>"Tanpa Manajer"</strong> menggunakan fungsi <code>COALESCE()</code>.</p>

                    <div class="code-container">
                        <span class="code-label">SQL Query</span>
                        <div class="code-block"><span class="sql-keyword">SELECT</span> e.nama <span class="sql-keyword">AS</span> Karyawan, 
       <span class="sql-keyword">COALESCE</span>(m.nama, <span class="sql-string">'Tanpa Manajer'</span>) <span class="sql-keyword">AS</span> Manajer 
<span class="sql-keyword">FROM</span> Karyawan e 
<span class="sql-keyword">LEFT JOIN</span> Karyawan m <span class="sql-keyword">ON</span> e.manajer_id = m.id;</div>
                    </div>

                    <h3 style="font-family:'Space Grotesk', sans-serif; font-size:1.1rem; margin-bottom:12px; margin-top:20px;">Hasil Eksekusi Query:</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Karyawan (e.nama)</th>
                                    <th>Manajer (COALESCE)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($self_join_results as $row): ?>
                                    <tr>
                                        <td style="font-weight: 500; color: white;"><?php echo htmlspecialchars($row['Karyawan']); ?></td>
                                        <td>
                                            <?php if ($row['Manajer'] === 'Tanpa Manajer'): ?>
                                                <span style="color: var(--text-muted); font-style: italic;"><?php echo htmlspecialchars($row['Manajer']); ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--accent-cyan); font-weight: 500;"><?php echo htmlspecialchars($row['Manajer']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KARTU 2: TUGAS 2 - TRIGGER IMPLEMENTATION -->
                <div class="card" id="tugas-2">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">
                                <span class="step-num">2</span>
                                Tugas 2: Trigger setelah_update_gaji
                            </h2>
                            <p class="card-subtitle">Mengotomatisasi pencatatan perubahan gaji ke tabel Log_Gaji</p>
                        </div>
                        <span class="badge badge-tugas2">Bobot 40%</span>
                    </div>

                    <p>Trigger <code>setelah_update_gaji</code> terpicu secara otomatis <code>AFTER UPDATE</code> pada tabel <code>Karyawan</code>. Jika nilai gaji baru berbeda dengan gaji lama, perubahan dicatat secara instan ke tabel <code>Log_Gaji</code>.</p>

                    <div class="code-container">
                        <span class="code-label">SQL Trigger Definition</span>
                        <div class="code-block"><span class="sql-keyword">CREATE TRIGGER</span> setelah_update_gaji <span class="sql-keyword">AFTER UPDATE ON</span> Karyawan 
<span class="sql-keyword">FOR EACH ROW</span> 
<span class="sql-keyword">BEGIN</span>  
   <span class="sql-keyword">IF</span> OLD.gaji &lt;&gt; NEW.gaji <span class="sql-keyword">THEN</span>  
      <span class="sql-keyword">INSERT INTO</span> Log_Gaji (karyawan_id, gaji_lama, gaji_baru, tanggal_ubah)  
      <span class="sql-keyword">VALUES</span> (OLD.id, OLD.gaji, NEW.gaji, <span class="sql-keyword">NOW</span>());  
   <span class="sql-keyword">END IF</span>; 
<span class="sql-keyword">END</span>;</div>
                    </div>

                    <div class="info-box">
                        <p><strong>Uji Coba Interaktif:</strong> Ubah gaji karyawan bernama <strong>Andi</strong> menjadi <strong>Rp 9.000.000</strong> menggunakan formulir di bawah ini untuk melihat trigger bekerja secara langsung!</p>
                    </div>

                    <form method="POST" style="background: rgba(0,0,0,0.15); padding: 20px; border-radius: 12px; border: 1px solid var(--card-border);">
                        <input type="hidden" name="action" value="update_gaji">
                        <input type="hidden" name="nama" value="Andi">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Target Karyawan</label>
                                <input type="text" class="form-control" value="Andi" readonly style="opacity: 0.7;">
                            </div>
                            <div class="form-group">
                                <label>Gaji Baru (Rupiah)</label>
                                <input type="number" name="gaji_baru" class="form-control" value="9000000" min="1000000" step="100000">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Jalankan: UPDATE Karyawan SET gaji = [Gaji Baru] WHERE nama = 'Andi';
                        </button>
                    </form>

                    <h3 style="font-family:'Space Grotesk', sans-serif; font-size:1.1rem; margin-bottom:12px; margin-top:25px;">Data Tabel Log_Gaji (Hasil Trigger):</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Log</th>
                                    <th>Karyawan</th>
                                    <th>Gaji Lama</th>
                                    <th>Gaji Baru</th>
                                    <th>Tanggal & Waktu Ubah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($log_gaji_list)): ?>
                                    <tr>
                                        <td colspan="5" class="empty-row">Belum ada log terekam. Silakan lakukan update gaji di atas!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($log_gaji_list as $log): ?>
                                        <tr>
                                            <td style="font-family: monospace;"><?php echo $log['id']; ?></td>
                                            <td style="font-weight: 500; color: white;"><?php echo htmlspecialchars($log['nama_karyawan']); ?></td>
                                            <td><span class="salary-tag" style="background: rgba(239, 68, 68, 0.1); color: var(--error);">Rp <?php echo number_format($log['gaji_lama'], 0, ',', '.'); ?></span></td>
                                            <td><span class="salary-tag">Rp <?php echo number_format($log['gaji_baru'], 0, ',', '.'); ?></span></td>
                                            <td style="font-size: 0.9rem; color: var(--text-muted);"><?php echo $log['tanggal_ubah']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KARTU 3: TUGAS 3 - STORED PROCEDURE -->
                <div class="card" id="tugas-3">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">
                                <span class="step-num">3</span>
                                Tugas 3: Stored Procedure PindahDepartemen
                            </h2>
                            <p class="card-subtitle">Prosedur tersimpan untuk memindahkan departemen karyawan dengan validasi keberadaan departemen tujuan</p>
                        </div>
                        <span class="badge badge-tugas3">Bobot 30%</span>
                    </div>

                    <p>Stored Procedure <code>PindahDepartemen</code> menerima parameter <code>p_karyawan_id</code> dan <code>p_dept_id_baru</code>. Prosedur ini melakukan validasi menggunakan <code>IF EXISTS</code> pada tabel <code>Departemen</code>. Jika departemen tujuan tidak ditemukan, transaksi dibatalkan dengan melemparkan error menggunakan <code>SIGNAL SQLSTATE</code>.</p>

                    <div class="code-container">
                        <span class="code-label">Stored Procedure Definition</span>
                        <div class="code-block"><span class="sql-keyword">CREATE PROCEDURE</span> PindahDepartemen(
    <span class="sql-keyword">IN</span> p_karyawan_id <span class="sql-type">INT</span>,
    <span class="sql-keyword">IN</span> p_dept_id_baru <span class="sql-type">INT</span>
)
<span class="sql-keyword">BEGIN</span>
    <span class="sql-comment">-- Validasi keberadaan departemen tujuan</span>
    <span class="sql-keyword">IF EXISTS</span> (<span class="sql-keyword">SELECT</span> 1 <span class="sql-keyword">FROM</span> Departemen <span class="sql-keyword">WHERE</span> id = p_dept_id_baru) <span class="sql-keyword">THEN</span>
        <span class="sql-keyword">UPDATE</span> Karyawan 
        <span class="sql-keyword">SET</span> departemen_id = p_dept_id_baru 
        <span class="sql-keyword">WHERE</span> id = p_karyawan_id;
    <span class="sql-keyword">ELSE</span>
        <span class="sql-comment">-- Lemparkan error jika tidak valid</span>
        <span class="sql-keyword">SIGNAL SQLSTATE</span> <span class="sql-string">'45000'</span> 
        <span class="sql-keyword">SET</span> MESSAGE_TEXT = <span class="sql-string">'Error: Departemen tujuan tidak ditemukan!'</span>;
    <span class="sql-keyword">END IF</span>;
<span class="sql-keyword">END</span>;</div>
                    </div>

                    <div class="info-box" style="background: rgba(168, 85, 247, 0.05); border-left-color: var(--accent-purple);">
                        <p style="color: #e9d5ff;"><strong>Uji Coba Stored Procedure:</strong> Pindahkan Andi (ID: 4) ke departemen baru. Gunakan ID Departemen yang tidak valid (contoh: 99) untuk menguji validasi error!</p>
                    </div>

                    <form method="POST" style="background: rgba(0,0,0,0.15); padding: 20px; border-radius: 12px; border: 1px solid var(--card-border);">
                        <input type="hidden" name="action" value="pindah_dept">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Target Karyawan</label>
                                <select name="karyawan_id" class="form-control">
                                    <?php foreach ($karyawan_list as $k): ?>
                                        <option value="<?php echo $k['id']; ?>" <?php echo ($k['nama'] === 'Andi') ? 'selected' : ''; ?>>
                                            ID <?php echo $k['id']; ?>: <?php echo htmlspecialchars($k['nama']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pilih Departemen Tujuan</label>
                                <select name="dept_id" class="form-control">
                                    <?php foreach ($departemen_list as $d): ?>
                                        <option value="<?php echo $d['id']; ?>">
                                            ID <?php echo $d['id']; ?>: <?php echo htmlspecialchars($d['nama_departemen']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="99" style="color: var(--error); font-weight: bold;">ID 99: [TIDAK VALID / ERROR TEST]</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, var(--primary), var(--accent-purple));">
                            Jalankan: CALL PindahDepartemen(Karyawan_ID, Dept_ID);
                        </button>
                    </form>
                </div>

            </div>

            <!-- Kanan: Preview Data Real-time -->
            <div style="display: flex; flex-direction: column; gap: 35px;">

                <!-- DATA DEPARTEMEN -->
                <div class="card">
                    <div class="card-header" style="margin-bottom: 16px;">
                        <div>
                            <h3 style="font-family:'Space Grotesk', sans-serif; font-size:1.2rem; font-weight:600;">Data Departemen</h3>
                            <p class="card-subtitle">Isi tabel Departemen saat ini</p>
                        </div>
                    </div>
                    <div class="table-responsive" style="margin: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Departemen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departemen_list as $d): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: bold;"><?php echo $d['id']; ?></td>
                                        <td style="color: white; font-weight: 500;"><?php echo htmlspecialchars($d['nama_departemen']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DATA KARYAWAN -->
                <div class="card">
                    <div class="card-header" style="margin-bottom: 16px;">
                        <div>
                            <h3 style="font-family:'Space Grotesk', sans-serif; font-size:1.2rem; font-weight:600;">Data Karyawan</h3>
                            <p class="card-subtitle">Isi tabel Karyawan secara real-time</p>
                        </div>
                    </div>
                    <div class="table-responsive" style="margin: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID/Nama</th>
                                    <th>Departemen</th>
                                    <th>Gaji</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($karyawan_list as $k): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: white;"><?php echo htmlspecialchars($k['nama']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">ID: <?php echo $k['id']; ?> | Mgr: <?php echo $k['nama_manajer'] ? htmlspecialchars($k['nama_manajer']) : '-'; ?></div>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.9rem; color: var(--accent-cyan); font-weight: 500;">
                                                <?php echo htmlspecialchars($k['nama_departemen'] ?? 'Tanpa Departemen'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="salary-tag">Rp <?php echo number_format($k['gaji'], 0, ',', '.'); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LAPORAN PENYUSUNAN -->
                <div class="card" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(6, 182, 212, 0.1)); border-color: rgba(79, 70, 229, 0.25);">
                    <h3 style="font-family:'Space Grotesk', sans-serif; font-size:1.2rem; font-weight:600; margin-bottom:12px;">Sintaks SQL Lengkap</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">Semua file query SQL tersimpan rapi di direktori kerja:</p>
                    
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <svg width="18" height="18" fill="none" stroke="var(--accent-cyan)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <a href="./setup.sql" target="_blank" style="color: var(--text-main); font-weight: 500; text-decoration: none;">setup.sql (Langkah 0)</a>
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <svg width="18" height="18" fill="none" stroke="var(--accent-cyan)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <a href="./trigger.sql" target="_blank" style="color: var(--text-main); font-weight: 500; text-decoration: none;">trigger.sql (Langkah 2)</a>
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <svg width="18" height="18" fill="none" stroke="var(--accent-cyan)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <a href="./procedure.sql" target="_blank" style="color: var(--text-main); font-weight: 500; text-decoration: none;">procedure.sql (Langkah 3)</a>
                        </li>
                    </ul>
                </div>

            </div>

        </div>

    </div>

</body>
</html>
