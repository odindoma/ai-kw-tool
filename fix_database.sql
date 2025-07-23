-- Скрипт для исправления базы данных
-- Добавление поля agent_type в таблицу documents

USE excel_data_db;

-- Проверяем существование колонки и добавляем её если нет
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'excel_data_db' 
AND table_name = 'documents' 
AND column_name = 'agent_type';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE documents ADD COLUMN agent_type VARCHAR(50) NOT NULL DEFAULT "UNKNOWN" AFTER original_filename',
    'SELECT "Column agent_type already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем индекс если его нет
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = 'excel_data_db' 
AND table_name = 'documents' 
AND index_name = 'idx_agent_type';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE documents ADD INDEX idx_agent_type (agent_type)',
    'SELECT "Index idx_agent_type already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Обновляем существующие записи, извлекая тип агента из original_filename
UPDATE documents 
SET agent_type = CASE 
    WHEN original_filename REGEXP '^([^-]+)-' THEN 
        SUBSTRING_INDEX(original_filename, '-', 1)
    ELSE 'UNKNOWN'
END
WHERE agent_type = 'UNKNOWN' OR agent_type = '';

-- Показываем результат
SELECT 'Database updated successfully' as status;
SELECT agent_type, COUNT(*) as count FROM documents GROUP BY agent_type;

