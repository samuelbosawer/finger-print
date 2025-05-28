<?php
require_once('koneksi.php');

// Ambil list nama untuk dropdown\

$nameList = [];
$namesRes = $koneksi->query("SELECT DISTINCT nama FROM user ORDER BY nama");
if ($namesRes) {
    while ($nr = $namesRes->fetch_assoc()) {
        $nameList[] = $nr['nama'];
    }
}

// Tangani pencarian via text, dropdown nama, dan filter waktu
$keyword    = isset($_GET['keyword'])     ? $koneksi->real_escape_string(trim($_GET['keyword']))      : '';
$tanggal    = isset($_GET['tanggal'])     ? $koneksi->real_escape_string(trim($_GET['tanggal']))      : '';
$urutkan    = isset($_GET['urutkan'])     ? $koneksi->real_escape_string(trim($_GET['urutkan']))      : '';
$nameSelect = isset($_GET['name_select']) ? $koneksi->real_escape_string(trim($_GET['name_select'])) : '';
$filter     = isset($_GET['filter'])      ? intval($_GET['filter'])                                   : 0;
$order      = "a.tanggal DESC"; // default order

// Bangun clause WHERE
$wheres = [];
if ($keyword !== '') {
    $wheres[] = "u.nama LIKE '%{$keyword}%'";
}
if ($tanggal !== '') {
    $wheres[] = "DATE(a.tanggal) = '{$tanggal}'";
}
if ($urutkan === 'nama') {
    $order = "u.nama ASC"; // ubah urutan jika ingin urutkan nama
}

if ($urutkan === 'tanggal' or $urutkan === '') {
    $order = "a.tanggal DESC"; // ubah urutan jika ingin urutkan nama
}

if ($nameSelect !== '') {
    $wheres[] = "u.nama = '{$nameSelect}'";
}

switch ($filter) {
    case 1:
        $wheres[] = "DATE(a.tanggal) = CURDATE()";
        break;
    case 2:
        $wheres[] = "MONTH(a.tanggal) = MONTH(CURDATE()) AND YEAR(a.tanggal) = YEAR(CURDATE())";
        break;
    case 3:
        $wheres[] = "YEAR(a.tanggal) = YEAR(CURDATE())";
        break;
    case 4:
        // Bulan lalu
        $wheres[] = "MONTH(a.tanggal) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                     AND YEAR(a.tanggal) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
}

$where = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Query data attendance
$query = "
SELECT 
  u.id as user_id,
  a.id as id,
  u.nama,
  DATE(a.tanggal) as tanggal
FROM attendance a
JOIN user u ON a.user_id = u.id
{$where}
GROUP BY u.id, DATE(a.tanggal)
ORDER BY {$order}
";
// var_dump($query);
// var_dump($query);


$result = $koneksi->query($query);
if (! $result) die('Query error: ' . $koneksi->error);
$no = 1;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Record SAGU Foundation</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light text-dark">
    <div class="container mt-5">
        <h1 class="mb-4">Attendance Record</h1>

        <!-- Form Pencarian (Text), Dropdown Nama, Filter, dan Tindakan -->
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-0">
                <input type="text" hidden name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="Cari teks...">
            </div>
            <div class="col-md-2">
                <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="form-control" placeholder="Cari teks...">
            </div>
            <div class="col-md-2">
                <select name="name_select" class="form-select">
                    <option value="">-- Semua tutor --</option>
                    <?php foreach ($nameList as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>" <?= $name === $nameSelect ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="filter" class="form-select">
                    <option value="0" <?= $filter === 0 ? 'selected' : '' ?>>Semua</option>
                    <option value="1" <?= $filter === 1 ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="2" <?= $filter === 2 ? 'selected' : '' ?>>Bulan Ini</option>
                    <option value="3" <?= $filter === 3 ? 'selected' : '' ?>>Tahun Ini</option>
                    <option value="4" <?= $filter === 4 ? 'selected' : '' ?>>Bulan Lalu</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="urutkan" class="form-select">
                    <option value="0" <?= $urutkan === '' ? 'selected' : '' ?>>Urutkan</option>
                    <option value="tanggal" <?= $urutkan === 'tanggal' ? 'selected' : '' ?>>Berdasarkan tanggal</option>
                    <option value="nama" <?= $urutkan === 'nama' ? 'selected' : '' ?>>Berdasarkan nama</option>

                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary">Cari</button>
            </div>
            <div class="col-md-6 mt-3">
            <a target="_blank" href="export_pdf.php?keyword=<?= urlencode($keyword) ?>&tanggal=<?= urlencode($tanggal) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?>&urutkan=<?= $urutkan ?>" class="mr-2 btn btn-danger">Export PDF</a>
                <a target="_blank" href="export_excel.php?keyword=<?= urlencode($keyword) ?>&tanggal=<?= urlencode($tanggal) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?> &urutkan=<?= $urutkan ?>" class="mr-2 btn btn-success">Export Excel</a>

            <a target="_blank" href="detail_lengkap_excel.php?keyword=<?= urlencode($keyword)?>&tanggal=<?= urlencode($tanggal) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?>&urutkan=<?= $urutkan ?>" class="mr-2 btn btn-success">Export Excel Lengkap</a>

                <a href="fresh.php" class="mr-2 btn btn-primary">Refresh</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>No</th>
                        <th>Name</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        // Ambil semua absen user di tanggal itu
                        $tanggal    = $row['tanggal'];
                        $userId     = $row['user_id'];
                        $absenQuery = $koneksi->query("
        SELECT tanggal 
        FROM attendance 
        WHERE user_id = {$userId} AND DATE(tanggal) = '{$tanggal}'
        ORDER BY tanggal ASC
    ");

                        $absenTimes = [];
                        while ($absen = $absenQuery->fetch_assoc()) {
                            $absenTimes[] = $absen['tanggal'];
                        }

                        $checkIn = new DateTime($absenTimes[0]);
                        $checkOut = new DateTime(end($absenTimes));
                        $totalDurasi = new DateInterval('PT0S');

                        for ($i = 0; $i < count($absenTimes); $i += 2) {
                            if (isset($absenTimes[$i + 1])) {
                                $in = new DateTime($absenTimes[$i]);
                                $out = new DateTime($absenTimes[$i + 1]);
                                $durasi = $in->diff($out);
                                $totalDurasi->h += $durasi->h;
                                $totalDurasi->i += $durasi->i;
                            }
                        }

                        // Koreksi menit lebih dari 60
                        if ($totalDurasi->i >= 60) {
                            $totalDurasi->h += intdiv($totalDurasi->i, 60);
                            $totalDurasi->i = $totalDurasi->i % 60;
                        }

                        $durasiFormatted = "{$totalDurasi->h} jam {$totalDurasi->i} menit";
                        ?>
                        <tr class="text-center">
                            <td><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nama']); ?></td>
                            <td><?= $checkIn->format('d-m-Y H:i:s'); ?></td>
                            <td><?= $checkOut->format('d-m-Y H:i:s'); ?></td>
                            <td>
                                <a href="detail.php?keyword=<?= urlencode($keyword) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?>&urutkan=<?= $urutkan ?>&id=<?= $row['id'] ?>"><?= $durasiFormatted; ?></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>