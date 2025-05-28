<?php
require_once('koneksi.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die('ID tidak valid.');

// Ambil informasi user dan tanggal berdasarkan ID absen
$queryInfo = "
    SELECT u.nama, DATE(a.tanggal) as tanggal, u.id as user_id
    FROM attendance a
    JOIN user u ON a.user_id = u.id
    WHERE a.id = $id
    LIMIT 1
";
$infoResult = $koneksi->query($queryInfo);
if (! $infoResult || $infoResult->num_rows === 0) {
    die('Data tidak ditemukan.');
}
$info = $infoResult->fetch_assoc();
$nama     = $info['nama'];
$tanggal  = $info['tanggal'];
$user_id  = $info['user_id'];

// Ambil semua absen user pada tanggal tersebut
$queryDetail = "
    SELECT tanggal, id as id_absen 
    FROM attendance 
    WHERE user_id = {$user_id} AND DATE(tanggal) = '{$tanggal}'
    ORDER BY tanggal ASC
";
$resultDetail = $koneksi->query($queryDetail);
if (! $resultDetail) die('Query error: ' . $koneksi->error);

$absenTimes = [];
$absenIds = [];
while ($row = $resultDetail->fetch_assoc()) {
    $absenTimes[] = $row['tanggal'];
    $absenIds[] = $row['id_absen'];
}



$sesiList = [];
$totalDuration = new DateInterval('PT0S');

for ($i = 0; $i < count($absenTimes); $i += 2) {
    $checkIn      = new DateTime($absenTimes[$i]);
    $checkOut     = isset($absenTimes[$i + 1]) ? new DateTime($absenTimes[$i + 1]) : null;
    $checkInId    = $absenIds[$i];
    $checkOutId   = isset($absenIds[$i + 1]) ? $absenIds[$i + 1] : null;

    $durasi = null;
    if ($checkOut) {
        $durasi = $checkIn->diff($checkOut);
        $totalDuration->h += $durasi->h;
        $totalDuration->i += $durasi->i;
    }

    $sesiList[] = [
        'check_in'     => $checkIn,
        'check_out'    => $checkOut,
        'check_in_id'  => $checkInId,
        'check_out_id' => $checkOutId,
        'durasi'       => $durasi,
    ];
}

// Koreksi menit ke jam jika lebih dari 60
if ($totalDuration->i >= 60) {
    $extraHours = intdiv($totalDuration->i, 60);
    $totalDuration->h += $extraHours;
    $totalDuration->i = $totalDuration->i % 60;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Absensi - <?= htmlspecialchars($nama) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light text-dark">
    <div class="container mt-5">
        <h3 class="mb-4">Detail Absensi - <?= htmlspecialchars($nama) ?> (<?= date('d-m-Y', strtotime($tanggal)) ?>)</h3>

        <div class="mb-3">
            <a href="tambah.php?user_id=<?= $user_id ?>&tanggal=<?= $tanggal ?>" class="btn btn-success">+ Tambah Absen</a>
        </div>
        <table class="table table-bordered">
            <thead class="table-light text-center">
                <tr>
                    <th>No</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Durasi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($sesiList) > 0): ?>
                    <?php foreach ($sesiList as $index => $sesi): ?>
                        <tr class="text-center">
                            <td><?= $index + 1 ?></td>
                            <td>
    <?= $sesi['check_in']->format('H:i:s') ?>
    <a href="edit.php?id_absen=<?= $sesi['check_in_id'] ?>&detail_id=<?= $_GET['id']?>" class="btn btn-sm btn-primary ms-2">Edit</a>
</td>
<td>
    <?php if ($sesi['check_out']): ?>
        <?= $sesi['check_out']->format('H:i:s') ?>
        <a href="edit.php?id_absen=<?= $sesi['check_out_id'] ?>&detail_id=<?= $_GET['id'] ?>" class="btn btn-sm btn-primary ms-2">Edit</a>
    <?php else: ?>
        -
    <?php endif; ?>
    </td>
                            <td>
                                <?php
                                if ($sesi['durasi']) {
                                    echo $sesi['durasi']->h . ' jam ' . $sesi['durasi']->i . ' menit';
                                } else {
                                    echo '-';
                                }
                                ?>

                            </td>
                            
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold text-center table-warning">
                        <td colspan="3">Total Durasi</td>
                        <td><?= $totalDuration->h ?> jam <?= $totalDuration->i ?> menit</td>
                    </tr>
                <?php else: ?>
                    <tr class="text-center">
                        <td colspan="4">Tidak ada data absen.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="index.php" class="btn btn-secondary mt-3">Kembali</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>