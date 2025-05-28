<?php
require_once('koneksi.php');

$user_id = $_GET['user_id'] ?? '';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $waktu = $_POST['waktu'] ?? '';
    if ($user_id && $waktu) {
        $stmt = $koneksi->prepare("INSERT INTO attendance (user_id, tanggal) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $waktu);
        if ($stmt->execute()) {
            header("Location: detail.php?id=" . $koneksi->insert_id);
            exit;
        } else {
            echo "Gagal menyimpan: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Absen</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light text-dark">
<div class="container mt-5">
    <h3>Tambah Waktu Absen</h3>
    <form method="post">
        <div class="mb-3">
            <label for="waktu" class="form-label">Tanggal & Waktu Absen</label>
            <input type="datetime-local" class="form-control" name="waktu" required>
        </div>
        <button type="submit" class="btn btn-success">Simpan</button>
        <a href="javascript:history.back()" class="btn btn-secondary">Batal</a>
    </form>
</div>
</body>
</html>
