<?php
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_length()) ob_end_clean();
ob_start();

require_once('koneksi.php');
require_once 'vendor/PHPExcel/Classes/PHPExcel.php';

$nameList = [];
$namesRes = $koneksi->query("SELECT DISTINCT nama FROM user ORDER BY nama");
if ($namesRes) {
    while ($nr = $namesRes->fetch_assoc()) {
        $nameList[] = $nr['nama'];
    }
}

$keyword    = isset($_GET['keyword'])     ? $koneksi->real_escape_string(trim($_GET['keyword']))      : '';
$tanggal    = isset($_GET['tanggal'])     ? $koneksi->real_escape_string(trim($_GET['tanggal']))      : '';
$urutkan    = isset($_GET['urutkan'])     ? $koneksi->real_escape_string(trim($_GET['urutkan']))      : '';
$nameSelect = isset($_GET['name_select']) ? $koneksi->real_escape_string(trim($_GET['name_select']))  : '';
$filter     = isset($_GET['filter'])      ? intval($_GET['filter'])                                   : 0;
$order      = "a.tanggal DESC";

$wheres = [];
if ($keyword !== '') $wheres[] = "u.nama LIKE '%{$keyword}%'";
if ($tanggal !== '') $wheres[] = "DATE(a.tanggal) = '{$tanggal}'";
if ($urutkan === 'nama') $order = "u.nama ASC";
if ($urutkan === 'tanggal' || $urutkan === '') $order = "a.tanggal DESC";
if ($nameSelect !== '') $wheres[] = "u.nama = '{$nameSelect}'";
switch ($filter) {
    case 1: $wheres[] = "DATE(a.tanggal) = CURDATE()"; break;
    case 2: $wheres[] = "MONTH(a.tanggal) = MONTH(CURDATE()) AND YEAR(a.tanggal) = YEAR(CURDATE())"; break;
    case 3: $wheres[] = "YEAR(a.tanggal) = YEAR(CURDATE())"; break;
    case 4:
        // Bulan lalu
        $wheres[] = "MONTH(a.tanggal) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                     AND YEAR(a.tanggal) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
}
$where = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

$sql ="
SELECT u.id AS user_id, u.nama, DATE(a.tanggal) AS tanggal
FROM attendance a
JOIN user u ON a.user_id = u.id
{$where}
GROUP BY u.id, DATE(a.tanggal)
ORDER BY {$order}
";

$result = $koneksi->query($sql);

$oExcel = new PHPExcel();
$oExcel->getProperties()->setCreator('SAGU Foundation')->setTitle('Attendance Record');
$sheet = $oExcel->setActiveSheetIndex(0);
$sheet->setTitle('Attendance');

$headers = ['No', 'Name', 'Date', 'Check In', 'Check Out', 'Durasi'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValueExplicit($col . '1', $header, PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

$rowNum = 2;
$no = 1;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $tanggal = $row['tanggal'];

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

        if ($totalDurasi->i >= 60) {
            $totalDurasi->h += intdiv($totalDurasi->i, 60);
            $totalDurasi->i = $totalDurasi->i % 60;
        }

        $durasiFormatted = "{$totalDurasi->h} jam {$totalDurasi->i} menit";

        $sheet->setCellValueExplicit('A' . $rowNum, (string)$no++, PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B' . $rowNum, $row['nama'], PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C' . $rowNum, date('d-m-Y', strtotime($tanggal)), PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D' . $rowNum, $checkIn->format('d-m-Y H:i:s'), PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E' . $rowNum, $checkOut->format('d-m-Y H:i:s'), PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F' . $rowNum, $durasiFormatted, PHPExcel_Cell_DataType::TYPE_STRING);
        $rowNum++;
    }
} else {
    $sheet->setCellValueExplicit('A2', 'No data found', PHPExcel_Cell_DataType::TYPE_STRING);
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="attendance_record.xls"');
header('Cache-Control: max-age=0');

ob_end_clean();
$objWriter = PHPExcel_IOFactory::createWriter($oExcel, 'Excel5');
$objWriter->save('php://output');
exit;
