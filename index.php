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

 if ($urutkan === 'tanggal' OR $urutkan === '') {
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
}

$where = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Query data attendance
$query = "
SELECT
  u.nama,
  DATE(a.tanggal) AS tanggal,
  MIN(a.tanggal) AS check_in,
  MAX(a.tanggal) AS check_out,
  TIMEDIFF(MAX(a.tanggal), MIN(a.tanggal)) AS durasi
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
            <div class="col-md-2">
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="Cari teks...">
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
                    <option value="0" <?= $filter===0?'selected':'' ?>>Semua</option>
                    <option value="1" <?= $filter===1?'selected':'' ?>>Hari Ini</option>
                    <option value="2" <?= $filter===2?'selected':'' ?>>Bulan Ini</option>
                    <option value="3" <?= $filter===3?'selected':'' ?>>Tahun Ini</option>
                   
                </select>
            </div>
              <div class="col-md-2">
                <select name="urutkan" class="form-select">
                    <option value="0" <?= $urutkan=== ''?'selected':'' ?>>Urutkan</option>
                    <option value="tanggal" <?= $urutkan==='tanggal'?'selected':'' ?>>Berdasarkan tanggal</option>
                    <option value="nama" <?= $urutkan==='nama'?'selected':'' ?>>Berdasarkan nama</option>
                   
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary">Cari</button>
            </div>
            <div class="col-md-6 mt-3">
                <a target="_blank" href="export_pdf.php?keyword=<?= urlencode($keyword) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?>&urutkan=<?= $urutkan ?>" class="mr-2 btn btn-danger">Export PDF</a>
                <a target="_blank" href="export_excel.php?keyword=<?= urlencode($keyword) ?>&name_select=<?= urlencode($nameSelect) ?>&filter=<?= $filter ?>&urutkan=<?= $urutkan ?>" class="mr-2 btn btn-success">Export Excel</a>
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
                    <?php if ($result->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $checkIn  = date('d-m-Y H:i:s', strtotime($row['check_in']));
                            $checkOut = date('d-m-Y H:i:s', strtotime($row['check_out']));
                            list($h, $i) = explode(':', $row['durasi']);
                            $durasiFormatted = "{$h} jam {$i} menit";
                        ?>
                        <tr class="text-center">
                            <td><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nama']); ?></td>
                            <td><?= $checkIn; ?></td>
                            <td><?= $checkOut; ?></td>
                            <td> <a href=""><?= $durasiFormatted; ?> </a>   </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="text-center"><td colspan="5">Data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
