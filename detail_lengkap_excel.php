<?php
error_reporting(0);
if (ob_get_length()) ob_end_clean();
ob_start();

require_once('koneksi.php');
require_once 'vendor/PHPExcel/Classes/PHPExcel.php';

// Ambil parameter filter dari URL
$keyword    = isset($_GET['keyword'])     ? $koneksi->real_escape_string(trim($_GET['keyword'])) : '';
$tanggal    = isset($_GET['tanggal'])     ? $koneksi->real_escape_string(trim($_GET['tanggal'])) : '';
$nameSelect = isset($_GET['name_select']) ? $koneksi->real_escape_string(trim($_GET['name_select'])) : '';
$filter     = isset($_GET['filter'])      ? intval($_GET['filter']) : 0;
$urutkan    = isset($_GET['urutkan'])     ? $koneksi->real_escape_string(trim($_GET['urutkan'])) : '';

// Bangun WHERE clause
$wheres = [];
if ($keyword !== '')    $wheres[] = "u.nama LIKE '%{$keyword}%'";
if ($tanggal !== '')    $wheres[] = "DATE(a.tanggal) = '{$tanggal}'";
if ($nameSelect !== '') $wheres[] = "u.nama = '{$nameSelect}'";
switch ($filter) {
    case 1: $wheres[] = "DATE(a.tanggal) = CURDATE()"; break;
    case 2: $wheres[] = "MONTH(a.tanggal) = MONTH(CURDATE()) AND YEAR(a.tanggal) = YEAR(CURDATE())"; break;
    case 3: $wheres[] = "YEAR(a.tanggal) = YEAR(CURDATE())"; break;
}
$where = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Tentukan urutan
$orderUser = ($urutkan === 'tanggal') ? 'tanggal DESC' : 'u.nama ASC';

// Inisialisasi PHPExcel
$oExcel = new PHPExcel();
$oExcel->getProperties()->setCreator('SAGU Foundation')->setTitle('Detail Attendance Time Calculation');
$sheet = $oExcel->setActiveSheetIndex(0);
$sheet->setTitle('Detail Attendance');

// Header kolom detail
$headers = ['No','Nama','Tanggal','Check In','Check Out','Durasi'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col.'1',$h);
    $sheet->getStyle($col.'1')->getFont()->setBold(true);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}

// Header rekap total di kanan
$sheet->setCellValue('H1','Rekap Total Waktu');
$sheet->getStyle('H1')->getFont()->setBold(true);
$sheet->setCellValue('H2','Nama');
$sheet->setCellValue('I2','Total Durasi');
$sheet->getStyle('H2:I2')->getFont()->setBold(true);
$sheet->getColumnDimension('H')->setAutoSize(true);
$sheet->getColumnDimension('I')->setAutoSize(true);

// Query user list berdasarkan filter
$sqlUser = "SELECT DISTINCT u.id, u.nama FROM attendance a JOIN user u ON a.user_id=u.id {$where} ORDER BY {$orderUser}";
$resultUser = $koneksi->query($sqlUser);

$rowNum = 2;
$rekapRow = 3;
$no = 1;

while ($user = $resultUser->fetch_assoc()) {
    $userId = $user['id'];
    $nama   = $user['nama'];

    // Ambil semua tanggal unik absensi untuk user ini
    $sqlTanggal = // Rebuild WHERE khusus untuk user_id
$whereUser = $wheres;
$whereUser[] = "a.user_id = {$userId}";
$whereUserStr = 'WHERE ' . implode(' AND ', $whereUser);

// Ambil semua tanggal unik absensi user yang sesuai filter
$sqlTanggal = "SELECT DISTINCT DATE(a.tanggal) AS tanggal 
               FROM attendance a 
               JOIN user u ON a.user_id = u.id 
               {$whereUserStr} 
               ORDER BY tanggal ASC";


    $resTgl = $koneksi->query($sqlTanggal);
    $totalDurasiAll = 0; // dalam detik

    while ($tglRow = $resTgl->fetch_assoc()) {
        $tgl = $tglRow['tanggal'];

      $sqlTimes = "SELECT a.tanggal 
             FROM attendance a 
             JOIN user u ON a.user_id = u.id 
             WHERE a.user_id = {$userId} AND DATE(a.tanggal) = '{$tgl}'";

if ($keyword !== '')    $sqlTimes .= " AND u.nama LIKE '%{$keyword}%'";
if ($nameSelect !== '') $sqlTimes .= " AND u.nama = '{$nameSelect}'";

$sqlTimes .= " ORDER BY a.tanggal ASC";
$resTimes = $koneksi->query($sqlTimes);

        $times = [];
        while ($r = $resTimes->fetch_assoc()) {
            $times[] = strtotime($r['tanggal']);
        }
        if (count($times) < 2) continue;

        // Check In & Check Out
        $checkIn  = date('d-m-Y H:i:s',$times[0]);
        $checkOut = date('d-m-Y H:i:s',end($times));

        // Hitung total durasi harian (pasangan)
        $durasiDetik = 0;
        for ($i = 0; $i < count($times); $i += 2) {
            if (isset($times[$i+1])) {
                $durasiDetik += ($times[$i+1] - $times[$i]);
            }
        }

        // Akumulasi total user
        $totalDurasiAll += $durasiDetik;

        // Format durasi harian
        $jam   = floor($durasiDetik/3600);
        $menit = floor(($durasiDetik%3600)/60);
        $durasiFmt = "{$jam} jam {$menit} menit";

        // Tulis row detail
        $sheet->setCellValue('A'.$rowNum, $no++);
        $sheet->setCellValue('B'.$rowNum, $nama);
        $sheet->setCellValue('C'.$rowNum, date('d-m-Y',strtotime($tgl)));
        $sheet->setCellValue('D'.$rowNum, $checkIn);
        $sheet->setCellValue('E'.$rowNum, $checkOut);
        $sheet->setCellValue('F'.$rowNum, $durasiFmt);
        $rowNum++;
    }

    // Format total durasi user
    $jamAll   = floor($totalDurasiAll/3600);
    $menitAll = floor(($totalDurasiAll%3600)/60);
    $totalFmt = "{$jamAll} jam {$menitAll} menit";

    // Tulis rekap kanan
    $sheet->setCellValue('H'.$rekapRow, $nama);
    $sheet->setCellValue('I'.$rekapRow, $totalFmt);

    // Tulis total di bawah detail
    $sheet->setCellValue('B'.$rowNum, 'Total');
    $sheet->setCellValue('F'.$rowNum, $totalFmt);
    $sheet->getStyle('B'.$rowNum.':F'.$rowNum)->getFont()->setBold(true);
    $rowNum++;
    $rekapRow++;
}

// Output file Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="detail_perhitungan_waktu.xls"');
header('Cache-Control: max-age=0');
ob_end_clean();
PHPExcel_IOFactory::createWriter($oExcel,'Excel5')->save('php://output');
exit;
