<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Получение параметров фильтрации (те же что и в view.php)
$documentFilter = $_GET['document'] ?? '';
$countryFilter = $_GET['country'] ?? '';
$minRpcFilter = $_GET['min_rpc'] ?? '';
$minYahooRateFilter = $_GET['min_yahoo_rate'] ?? '';

try {
    $pdo = getDBConnection();
    
    // Построение запроса с фильтрами (аналогично view.php)
    $whereConditions = [];
    $params = [];
    
    if (!empty($documentFilter)) {
        $whereConditions[] = "d.id = ?";
        $params[] = $documentFilter;
    }
    
    if (!empty($countryFilter)) {
        $whereConditions[] = "d.country_code = ?";
        $params[] = $countryFilter;
    }
    
    if (!empty($minRpcFilter) && is_numeric($minRpcFilter)) {
        $whereConditions[] = "e.est_rpc >= ?";
        $params[] = floatval($minRpcFilter);
    }
    
    if (!empty($minYahooRateFilter) && is_numeric($minYahooRateFilter)) {
        $whereConditions[] = "CAST(REPLACE(REPLACE(e.yahoo_show_rate, '%', ''), ',', '.') AS DECIMAL(5,2)) >= ?";
        $params[] = floatval($minYahooRateFilter);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Получение всех данных (без пагинации для экспорта)
    $dataQuery = "
        SELECT e.keyword, e.yahoo_show_rate, e.advertiser, e.est_rpc,
               d.original_filename, d.country_code, d.upload_date
        FROM excel_data e 
        JOIN documents d ON e.document_id = d.id 
        $whereClause
        ORDER BY e.id DESC
    ";
    $dataStmt = $pdo->prepare($dataQuery);
    $dataStmt->execute($params);
    $data = $dataStmt->fetchAll();
    
    if (empty($data)) {
        die('Нет данных для экспорта');
    }
    
    // Создание Excel файла
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Exported Data');
    
    // Заголовки
    $headers = [
        'A1' => 'Keyword',
        'B1' => 'Yahoo Show Rate',
        'C1' => 'Advertiser',
        'D1' => 'Est. RPC $',
        'E1' => 'Document',
        'F1' => 'Country Code',
        'G1' => 'Upload Date'
    ];
    
    // Установка заголовков
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // Стилизация заголовков
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667eea']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    
    // Заполнение данными
    $row = 2;
    foreach ($data as $record) {
        $sheet->setCellValue('A' . $row, $record['keyword']);
        $sheet->setCellValue('B' . $row, $record['yahoo_show_rate']);
        $sheet->setCellValue('C' . $row, $record['advertiser']);
        $sheet->setCellValue('D' . $row, $record['est_rpc']);
        $sheet->setCellValue('E' . $row, $record['original_filename']);
        $sheet->setCellValue('F' . $row, $record['country_code']);
        $sheet->setCellValue('G' . $row, date('d.m.Y H:i', strtotime($record['upload_date'])));
        $row++;
    }
    
    // Автоматическая ширина колонок
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Стилизация данных
    $dataRange = 'A2:G' . ($row - 1);
    $dataStyle = [
        'alignment' => [
            'vertical' => Alignment::VERTICAL_TOP,
            'wrapText' => true
        ]
    ];
    $sheet->getStyle($dataRange)->applyFromArray($dataStyle);
    
    // Стилизация колонки Est. RPC $ (числовой формат)
    $rpcRange = 'D2:D' . ($row - 1);
    $sheet->getStyle($rpcRange)->getNumberFormat()->setFormatCode('0.0000');
    $sheet->getStyle($rpcRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Добавление фильтров
    $sheet->setAutoFilter('A1:G' . ($row - 1));
    
    // Создание имени файла
    $filename = 'export_' . date('Y-m-d_H-i-s');
    
    // Добавление информации о фильтрах в имя файла
    $filterInfo = [];
    if (!empty($documentFilter)) {
        $filterInfo[] = 'doc' . $documentFilter;
    }
    if (!empty($countryFilter)) {
        $filterInfo[] = $countryFilter;
    }
    if (!empty($minRpcFilter)) {
        $filterInfo[] = 'rpc' . str_replace('.', '_', $minRpcFilter);
    }
    if (!empty($minYahooRateFilter)) {
        $filterInfo[] = 'yahoo' . str_replace('.', '_', $minYahooRateFilter);
    }
    
    if (!empty($filterInfo)) {
        $filename .= '_' . implode('_', $filterInfo);
    }
    
    $filename .= '.xlsx';
    
    // Отправка файла
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    die('Ошибка при экспорте: ' . $e->getMessage());
}
?>

