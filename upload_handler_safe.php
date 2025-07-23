<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelUploadHandler {
    private $pdo;
    private $hasAgentTypeColumn = false;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->checkAgentTypeColumn();
    }
    
    private function checkAgentTypeColumn() {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM documents LIKE 'agent_type'");
            $this->hasAgentTypeColumn = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $this->hasAgentTypeColumn = false;
        }
    }
    
    public function handleUpload($file) {
        try {
            // Проверка файла
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Сохранение файла
            $savedFile = $this->saveFile($file);
            if (!$savedFile['success']) {
                return $savedFile;
            }
            
            // Обработка Excel файла
            $result = $this->processExcelFile($savedFile['filepath'], $file['name']);
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при обработке файла: ' . $e->getMessage()
            ];
        }
    }
    
    private function validateFile($file) {
        // Проверка на ошибки загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Ошибка при загрузке файла'
            ];
        }
        
        // Проверка размера файла
        if ($file['size'] > MAX_FILE_SIZE) {
            return [
                'success' => false,
                'message' => 'Файл слишком большой. Максимальный размер: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'
            ];
        }
        
        // Проверка расширения файла
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            return [
                'success' => false,
                'message' => 'Неподдерживаемый формат файла. Разрешены: ' . implode(', ', ALLOWED_EXTENSIONS)
            ];
        }
        
        return ['success' => true];
    }
    
    private function saveFile($file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '.' . $extension;
        $filepath = UPLOAD_DIR . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Не удалось сохранить файл'
            ];
        }
    }
    
    private function processExcelFile($filepath, $originalFilename) {
        try {
            // Загрузка Excel файла
            $spreadsheet = IOFactory::load($filepath);
            
            // Получение листа "without ads"
            $worksheet = null;
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                if ($sheet->getTitle() === 'without ads') {
                    $worksheet = $sheet;
                    break;
                }
            }
            
            if (!$worksheet) {
                return [
                    'success' => false,
                    'message' => 'Лист "without ads" не найден в файле'
                ];
            }
            
            // Получение данных из листа
            $data = $worksheet->toArray();
            
            if (empty($data)) {
                return [
                    'success' => false,
                    'message' => 'Файл пуст'
                ];
            }
            
            // Поиск заголовков
            $headers = $data[0];
            $columnMap = $this->mapColumns($headers);
            
            if (!$columnMap['success']) {
                return $columnMap;
            }
            
            // Сохранение документа в БД
            $countryCode = extractCountryCode($originalFilename);
            $agentType = extractAgentType($originalFilename);
            $documentId = $this->saveDocument($originalFilename, $filepath, $countryCode, $agentType);
            
            // Обработка данных
            $processedCount = 0;
            for ($i = 1; $i < count($data); $i++) {
                $row = $data[$i];
                
                // Пропускаем пустые строки
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $rowData = [
                    'keyword' => $row[$columnMap['keyword']] ?? '',
                    'yahoo_show_rate' => $row[$columnMap['yahoo']] ?? '',
                    'advertiser' => $row[$columnMap['advertiser']] ?? '',
                    'est_rpc' => $this->parseFloat($row[$columnMap['est_rpc']] ?? null)
                ];
                
                if ($this->saveRowData($documentId, $rowData)) {
                    $processedCount++;
                }
            }
            
            // Обновление количества записей в документе
            $this->updateDocumentRecordsCount($documentId, $processedCount);
            
            return [
                'success' => true,
                'message' => "Файл успешно обработан. Сохранено записей: $processedCount",
                'document_id' => $documentId,
                'records_count' => $processedCount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при обработке Excel файла: ' . $e->getMessage()
            ];
        }
    }
    
    private function mapColumns($headers) {
        $map = [];
        
        // Поиск нужных колонок
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            if (stripos($header, 'keyword') !== false) {
                $map['keyword'] = $index;
            } elseif (stripos($header, 'yahoo') !== false && stripos($header, 'advertiser') === false && stripos($header, 'business') === false && stripos($header, 'show rate') === false) {
                $map['yahoo'] = $index;
            } elseif (stripos($header, 'yahoo advertiser') !== false) {
                $map['advertiser'] = $index;
            } elseif (stripos($header, 'est. rpc') !== false) {
                $map['est_rpc'] = $index;
            }
        }
        
        // Проверка наличия всех необходимых колонок
        $required = ['keyword', 'yahoo', 'advertiser', 'est_rpc'];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($map[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return [
                'success' => false,
                'message' => 'Не найдены колонки: ' . implode(', ', $missing)
            ];
        }
        
        return array_merge(['success' => true], $map);
    }
    
    private function saveDocument($originalFilename, $filepath, $countryCode, $agentType) {
        if ($this->hasAgentTypeColumn) {
            // Новая версия с agent_type
            $stmt = $this->pdo->prepare("
                INSERT INTO documents (filename, original_filename, agent_type, country_code) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                basename($filepath),
                $originalFilename,
                $agentType,
                $countryCode
            ]);
        } else {
            // Старая версия без agent_type
            $stmt = $this->pdo->prepare("
                INSERT INTO documents (filename, original_filename, country_code) 
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                basename($filepath),
                $originalFilename,
                $countryCode
            ]);
        }
        
        return $this->pdo->lastInsertId();
    }
    
    private function saveRowData($documentId, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO excel_data (document_id, keyword, yahoo_show_rate, advertiser, est_rpc) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $documentId,
                $data['keyword'],
                $data['yahoo_show_rate'],
                $data['advertiser'],
                $data['est_rpc']
            ]);
        } catch (Exception $e) {
            error_log("Ошибка при сохранении строки: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateDocumentRecordsCount($documentId, $count) {
        $stmt = $this->pdo->prepare("UPDATE documents SET records_count = ? WHERE id = ?");
        $stmt->execute([$count, $documentId]);
    }
    
    private function parseFloat($value) {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Удаляем все символы кроме цифр, точки и минуса
        $cleaned = preg_replace('/[^0-9.-]/', '', $value);
        
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }
}
?>

