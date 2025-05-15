<?php
// Matikan semua error untuk mencegah output yang merusak file Excel
error_reporting(0);
ini_set('display_errors', 0);

// Bersihkan semua output buffer
if (ob_get_length()) ob_end_clean();
ob_start();

require_once('koneksi.php');
require_once 'vendor/PHPExcel/Classes/PHPExcel.php'; // PHPExcel versi 1.8

// Ambil parameter filter
$keyword    = isset($_GET['keyword'])     ? $koneksi->real_escape_string(trim($_GET['keyword']))      : '';
$nameSelect = isset($_GET['name_select']) ? $koneksi->real_escape_string(trim($_GET['name_select'])) : '';
$filter     = isset($_GET['filter'])      ? intval($_GET['filter'])                                 : 0;

// Bangun clause WHERE
$wheres = [];
if ($keyword !== '') {
    $wheres[] = "u.nama LIKE '%{$keyword}%'";
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
$sql = "
SELECT
  u.nama,
  DATE(a.tanggal) AS tanggal,
  MIN(a.tanggal)    AS check_in,
  MAX(a.tanggal)    AS check_out,
  TIMEDIFF(MAX(a.tanggal), MIN(a.tanggal)) AS durasi
FROM attendance a
JOIN user u ON a.user_id = u.id
{$where}
GROUP BY u.id, DATE(a.tanggal)
ORDER BY a.tanggal DESC
";
$result = $koneksi->query($sql);

// Buat objek PHPExcel
$oExcel = new PHPExcel();
$oExcel->getProperties()->setCreator('SAGU Foundation')->setTitle('Attendance Record');
$sheet = $oExcel->setActiveSheetIndex(0);
$sheet->setTitle('Attendance');

// Header kolom
$headers = ['No', 'Name', 'Date', 'Check In', 'Check Out', 'Durasi'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValueExplicit($col . '1', $header, PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Isi data
$rowNum = 2;
$no = 1;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $date     = date('d-m-Y', strtotime($row['tanggal']));
        $checkIn  = date('d-m-Y H:i:s', strtotime($row['check_in']));
        $checkOut = date('d-m-Y H:i:s', strtotime($row['check_out']));
        list($h, $i) = explode(':', $row['durasi']);
        $durasi = "{$h} jam {$i} menit";

        $sheet->setCellValueExplicit('A' . $rowNum, (string)$no++, PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $rowNum, $row['nama'], PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $rowNum, $date, PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $rowNum, $checkIn, PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E' . $rowNum, $checkOut, PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F' . $rowNum, $durasi, PHPExcel_Cell_DataType::TYPE_STRING);
        $rowNum++;
    }
} else {
    $sheet->setCellValueExplicit('A2', 'No data found', PHPExcel_Cell_DataType::TYPE_STRING);
}

// Set header HTTP untuk Excel5 (xls)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="attendance_record.xls"');
header('Cache-Control: max-age=0');

// Bersihkan buffer output sebelum kirim file
ob_end_clean();
$objWriter = PHPExcel_IOFactory::createWriter($oExcel, 'Excel5');
$objWriter->save('php://output');
exit;
