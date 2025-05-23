<?php
// export_pdf.php
require_once('koneksi.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php'); // pastikan TCPDF terinstall via Composer

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

var_dump($urutkan);
die;

$result = $koneksi->query($query);

// Inisialisasi TCPDF
$tcpdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$tcpdf->SetCreator(PDF_CREATOR);
$tcpdf->SetAuthor('SAGU Foundation');
$tcpdf->SetTitle('Attendance Record');
$tcpdf->setPrintHeader(false);
$tcpdf->setPrintFooter(false);
$tcpdf->SetMargins(15, 15);
$tcpdf->AddPage();

// Judul
$tcpdf->SetFont('dejavusans', 'B', 16);
$tcpdf->Cell(0, 10, 'Attendance Record', 0, 1, 'C');
$tcpdf->Ln(5);

// Tabel header
$tcpdf->SetFont('dejavusans', 'B', 12);
$header = ['No', 'Name', 'Check In', 'Check Out', 'Durasi'];
$w = [10, 50, 40, 40, 30];
foreach ($header as $i => $col) {
    $tcpdf->Cell($w[$i], 7, $col, 1, 0, 'C');
}
$tcpdf->Ln();

// Isi tabel
$tcpdf->SetFont('dejavusans', '', 10);
$no = 1;
while ($row = $result->fetch_assoc()) {
    $checkIn  = date('d-m-Y H:i:s', strtotime($row['check_in']));
    $checkOut = date('d-m-Y H:i:s', strtotime($row['check_out']));
    list($h, $i) = explode(':', $row['durasi']);
    $durasi = "{$h} jam {$i} menit";

    $tcpdf->Cell($w[0], 6, $no++, 'LR', 0, 'C');
    $tcpdf->Cell($w[1], 6, $row['nama'], 'LR', 0, 'L');
    $tcpdf->Cell($w[2], 6, $checkIn, 'LR', 0, 'C');
    $tcpdf->Cell($w[3], 6, $checkOut, 'LR', 0, 'C');
    $tcpdf->Cell($w[4], 6, $durasi, 'LR', 0, 'C');
    $tcpdf->Ln();
}
// Garis bawah tabel
$tcpdf->Cell(array_sum($w), 0, '', 'T');

// Output PDF
$tcpdf->Output('attendance_record.pdf', 'I');
