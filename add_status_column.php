<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "Начинаем обновление базы данных...\n";
    
    // Проверяем, существует ли уже колонка status
    $checkColumnQuery = "SHOW COLUMNS FROM excel_data LIKE 'status'";
    $result = $pdo->query($checkColumnQuery);
    
    if ($result->rowCount() > 0) {
        echo "Колонка 'status' уже существует в таблице excel_data.\n";
    } else {
        // Добавляем колонку status
        $addColumnQuery = "ALTER TABLE excel_data ADD COLUMN status ENUM('New', 'Used') DEFAULT 'New' AFTER est_rpc";
        $pdo->exec($addColumnQuery);
        echo "Колонка 'status' успешно добавлена в таблицу excel_data.\n";
    }
    
    // Устанавливаем статус 'New' для всех существующих записей (если они NULL)
    $updateStatusQuery = "UPDATE excel_data SET status = 'New' WHERE status IS NULL";
    $updatedRows = $pdo->exec($updateStatusQuery);
    echo "Обновлено записей со статусом 'New': $updatedRows\n";
    
    // Проверяем результат
    $countQuery = "SELECT 
        status, 
        COUNT(*) as count 
    FROM excel_data 
    GROUP BY status";
    
    $countResult = $pdo->query($countQuery);
    echo "\nСтатистика по статусам:\n";
    while ($row = $countResult->fetch()) {
        echo "- {$row['status']}: {$row['count']} записей\n";
    }
    
    echo "\nОбновление базы данных завершено успешно!\n";
    
} catch (Exception $e) {
    echo "Ошибка при обновлении базы данных: " . $e->getMessage() . "\n";
    exit(1);
}
?>
