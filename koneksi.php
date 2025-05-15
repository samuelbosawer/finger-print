<?php
// Konfigurasi koneksi database
$host     = "localhost";     // atau IP server database
$user     = "root";          // username database
$password = "root";              // password database
$database = "fp";  // nama database

// Membuat koneksi
$koneksi = new mysqli($host, $user, $password, $database);

// Cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
} else {
    // echo "Koneksi berhasil!";
}
?>
