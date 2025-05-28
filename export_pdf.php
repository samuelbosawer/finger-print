<?php
require_once('koneksi.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// Ambil parameter GET
$keyword    = isset($_GET['keyword'])     ? $koneksi->real_escape_string(trim($_GET['keyword']))      : '';
$tanggal    = isset($_GET['tanggal'])     ? $koneksi->real_escape_string(trim($_GET['tanggal']))      : '';
$urutkan    = isset($_GET['urutkan'])     ? $koneksi->real_escape_string(trim($_GET['urutkan']))      : '';
$nameSelect = isset($_GET['name_select']) ? $koneksi->real_escape_string(trim($_GET['name_select']))  : '';
$filter     = isset($_GET['filter'])      ? intval($_GET['filter'])                                   : 0;
$order      = "a.tanggal DESC";

// Klausa WHERE
$wheres = [];
if ($keyword !== '') {
    $wheres[] = "u.nama LIKE '%{$keyword}%'";
}
if ($tanggal !== '') {
    $wheres[] = "DATE(a.tanggal) = '{$tanggal}'";
}
if ($urutkan === 'nama') {
    $order = "u.nama ASC";
}
if ($urutkan === 'tanggal' || $urutkan === '') {
    $order = "a.tanggal DESC";
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

// Query utama
$query = "
SELECT 
    u.id AS user_id,
    u.nama,
    DATE(a.tanggal) AS tanggal
FROM attendance a
JOIN user u ON a.user_id = u.id
{$where}
GROUP BY u.id, DATE(a.tanggal)
ORDER BY {$order}
";

$result = $koneksi->query($query);
if (! $result) die("Query gagal: " . $koneksi->error);

// Siapkan PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('SAGU Foundation');
$pdf->SetTitle('Attendance Record');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15);
$pdf->AddPage();

// Judul
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'Attendance Record', 0, 1, 'C');
$pdf->Ln(5);

// Header tabel
$pdf->SetFont('dejavusans', 'B', 11);
$header = ['No', 'Name', 'Check In', 'Check Out', 'Durasi'];
$widths = [10, 50, 40, 40, 30];

foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 8, $col, 1, 0, 'C');
}
$pdf->Ln();

// Isi tabel
$pdf->SetFont('dejavusans', '', 10);
$no = 1;
while ($row = $result->fetch_assoc()) {
    $tanggal = $row['tanggal'];
    $userId  = $row['user_id'];

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

    if (count($absenTimes) === 0) continue;

    $checkIn  = new DateTime($absenTimes[0]);
    $checkOut = new DateTime(end($absenTimes));

    $totalDurasi = new DateInterval('PT0S');
    for ($i = 0; $i < count($absenTimes); $i += 2) {
        if (isset($absenTimes[$i + 1])) {
            $in  = new DateTime($absenTimes[$i]);
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

    // Output ke tabel PDF
    $pdf->Cell($widths[0], 7, $no++, 1, 0, 'C');
    $pdf->Cell($widths[1], 7, $row['nama'], 1, 0, 'L');
    $pdf->Cell($widths[2], 7, $checkIn->format('d-m-Y H:i:s'), 1, 0, 'C');
    $pdf->Cell($widths[3], 7, $checkOut->format('d-m-Y H:i:s'), 1, 0, 'C');
    $pdf->Cell($widths[4], 7, $durasiFormatted, 1, 0, 'C');
    $pdf->Ln();
}

// Output file PDF
$pdf->Output('attendance_record.pdf', 'I');
