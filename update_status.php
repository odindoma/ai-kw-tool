<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['record_ids']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$recordIds = $input['record_ids'];
$status = $input['status'];

// Валидация статуса
if (!in_array($status, ['New', 'Used'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Валидация record_ids
if (!is_array($recordIds) || empty($recordIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Обрабатываем каждую группу record_ids (они могут быть в формате "1,2,3")
    $allRecordIds = [];
    foreach ($recordIds as $recordIdGroup) {
        $ids = explode(',', $recordIdGroup);
        foreach ($ids as $id) {
            if (is_numeric(trim($id))) {
                $allRecordIds[] = trim($id);
            }
        }
    }
    
    if (empty($allRecordIds)) {
        echo json_encode(['success' => false, 'message' => 'No valid record IDs found']);
        exit;
    }
    
    // Создаем плейсхолдеры для IN условия
    $placeholders = str_repeat('?,', count($allRecordIds) - 1) . '?';
    
    // Обновляем статус
    $updateQuery = "UPDATE excel_data SET status = ? WHERE id IN ($placeholders)";
    $params = array_merge([$status], $allRecordIds);
    
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute($params);
    
    if ($result) {
        $affectedRows = $updateStmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => "Обновлено записей: $affectedRows",
            'affected_rows' => $affectedRows
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update records']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
