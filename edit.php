<?php
require_once('koneksi.php');

$id_absen = isset($_GET['id_absen']) ? intval($_GET['id_absen']) : 0;
if ($id_absen <= 0) die("ID Absen tidak valid.");

$query = "SELECT * FROM attendance WHERE id = $id_absen LIMIT 1";
$result = $koneksi->query($query);
if (! $result || $result->num_rows === 0) die("Data tidak ditemukan.");
$data = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_datetime = $_POST['tanggal'];

    $stmt = $koneksi->prepare("UPDATE attendance SET tanggal = ? WHERE id = ?");
    $stmt->bind_param("si", $new_datetime, $id_absen);
    if ($stmt->execute()) {
        // Redirect ke detail
        header("Location: detail.php?id=" . $_POST['detail_id']);
        exit;
    } else {
        echo "Gagal mengubah data.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Waktu Absen</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light text-dark">
<div class="container mt-5">
    <h3>Edit Waktu Absen</h3>
    <form method="post">
        <input type="hidden" name="detail_id" value="<?= htmlspecialchars($_GET['detail_id'] ?? '') ?>">
        <div class="mb-3">
            <label for="tanggal" class="form-label">Tanggal & Waktu</label>
            <input type="datetime-local" class="form-control" name="tanggal" required
                   value="<?= date('Y-m-d\TH:i', strtotime($data['tanggal'])) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="javascript:history.back()" class="btn btn-secondary">Batal</a>
    </form>
</div>
</body>
</html>
