<?php
include 'koneksi.php';
require 'zklibrary.php';

// 1. Connect & disable device
$zk = new ZKLibrary('192.168.0.107', 4370);
$zk->connect();
$zk->disableDevice();

// 2. Ambil data user dari mesin
$dataUser = $zk->getUser();

$dataAbsen = $zk->getAttendance();


// 3. Matikan cek foreign key, lalu drop dan recreate tabel
// --------------------------------------------------------
if (! $koneksi->query("SET FOREIGN_KEY_CHECKS = 0")) {
    die("Gagal mematikan FK_CHECKS: " . $koneksi->error);
}

// Drop attendance dulu, lalu user
if (! $koneksi->query("DROP TABLE IF EXISTS attendance")) {
    die("Gagal drop attendance: " . $koneksi->error);
}
if (! $koneksi->query("DROP TABLE IF EXISTS user")) {
    die("Gagal drop user: " . $koneksi->error);
}

// Re-enable FK_CHECKS
if (! $koneksi->query("SET FOREIGN_KEY_CHECKS = 1")) {
    die("Gagal mengaktifkan FK_CHECKS: " . $koneksi->error);
}

// 4. Buat ulang tabel user
$sql_user = "
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
";
if (! $koneksi->query($sql_user)) {
    die("Gagal membuat tabel user: " . $koneksi->error);
}

// 5. Buat ulang tabel attendance
$sql_attendance = "
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tanggal DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB
";
if (! $koneksi->query($sql_attendance)) {
    die("Gagal membuat tabel attendance: " . $koneksi->error);
}

// 6. Insert data user
if (is_array($dataUser)) {
    foreach ($dataUser as $entry) {
        $userid = intval($entry[0]);
        $nama   = $koneksi->real_escape_string(trim($entry[1]));

        if ($userid > 0 && $nama !== '') {
            // Jika kamu ingin pakai AUTO_INCREMENT id, hilangkan kolom id di bawah
            $sql_insert = "INSERT INTO user (id, nama) VALUES ($userid, '$nama')";
            if (! $koneksi->query($sql_insert)) {
                // Tampilkan, tapi lanjutkan ke entry berikutnya
                echo "Gagal insert user $userid: " . $koneksi->error . "<br>";
            }
        }
    }
}

if (is_array($dataAbsen)) {
      
    foreach ($dataAbsen as $entry) {
        $userid = intval($entry[1]);
        $tanggal = ($entry[3]);

        if ($userid > 0 && $tanggal !== '') {
            // Jika kamu ingin pakai AUTO_INCREMENT id, hilangkan kolom id di bawah
            $sql_insert = "INSERT INTO attendance (user_id, tanggal) VALUES ($userid, '$tanggal')";
           
          
            if (! $koneksi->query($sql_insert)) {
                // Tampilkan, tapi lanjutkan ke entry berikutnya
                echo "Gagal insert attendance $userid: " . $koneksi->error . "<br>";
            }
        }
    }
}

// 7. Re-enable device & cleanup
$zk->enableDevice();
$zk->disconnect();
$koneksi->close();

// 8. Redirect
header("Location: index.php");
exit;
?>
