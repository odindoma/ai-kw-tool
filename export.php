<?php
require_once 'config.php';

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
    
    // Получение всех данных с группировкой (аналогично обновленному view.php)
    $dataQuery = "
        SELECT 
            e.keyword,
            MAX(e.yahoo_show_rate) as yahoo_show_rate,
            MAX(e.est_rpc) as est_rpc,
            d.original_filename, 
            d.country_code,
            COUNT(*) as duplicate_count
        FROM excel_data e 
        JOIN documents d ON e.document_id = d.id 
        $whereClause
        GROUP BY e.document_id, e.keyword, d.original_filename, d.country_code
        ORDER BY MAX(e.est_rpc) DESC, e.document_id DESC, e.keyword ASC
    ";
    
    $dataStmt = $pdo->prepare($dataQuery);
    $dataStmt->execute($params);
    $data = $dataStmt->fetchAll();
    
    if (empty($data)) {
        die('Нет данных для экспорта');
    }
    
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
    
    $filename .= '.csv';
    
    // Установка заголовков для CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Открытие потока вывода
    $output = fopen('php://output', 'w');
    
    // Добавление BOM для корректного отображения в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Заголовки CSV (без Advertiser и Upload Date)
    $headers = [
        'Keyword',
        'Yahoo Show Rate',
        'Est. RPC $',
        'Document',
        'Country Code',
        'Records Count'
    ];
    
    // Запись заголовков
    fputcsv($output, $headers, ';'); // Используем ; как разделитель для лучшей совместимости с Excel
    
    // Запись данных
    foreach ($data as $record) {
        $row = [
            $record['keyword'],
            $record['yahoo_show_rate'],
            $record['est_rpc'] !== null ? number_format($record['est_rpc'], 4, '.', '') : '',
            $record['original_filename'],
            $record['country_code'],
            $record['duplicate_count']
        ];
        
        fputcsv($output, $row, ';');
    }
    
    // Закрытие потока
    fclose($output);
    
} catch (Exception $e) {
    die('Ошибка при экспорте: ' . $e->getMessage());
}
?>
