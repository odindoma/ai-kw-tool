-- Скрипт для обновления существующей базы данных
-- Добавление поля agent_type в таблицу documents

USE excel_data_db;

-- Добавляем поле agent_type если его еще нет
ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS agent_type VARCHAR(50) NOT NULL DEFAULT 'UNKNOWN' AFTER original_filename;

-- Добавляем индекс для нового поля
ALTER TABLE documents 
ADD INDEX IF NOT EXISTS idx_agent_type (agent_type);

-- Обновляем существующие записи, извлекая тип агента из original_filename
UPDATE documents 
SET agent_type = CASE 
    WHEN original_filename REGEXP '^([^-]+)-' THEN 
        SUBSTRING_INDEX(original_filename, '-', 1)
    ELSE 'UNKNOWN'
END
WHERE agent_type = 'UNKNOWN' OR agent_type = '';

-- Убираем значение по умолчанию после обновления данных
ALTER TABLE documents 
ALTER COLUMN agent_type DROP DEFAULT;

