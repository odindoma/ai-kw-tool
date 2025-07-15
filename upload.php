<?php
require_once 'upload_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit;
}

if (!isset($_FILES['excel_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Файл не был загружен']);
    exit;
}

$handler = new ExcelUploadHandler();
$result = $handler->handleUpload($_FILES['excel_file']);

if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(400);
}

echo json_encode($result);
?>

