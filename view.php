<?php
require_once 'config.php';

// Получение параметров фильтрации
$documentFilter = $_GET['document'] ?? '';
$countryFilter = $_GET['country'] ?? '';
$minRpcFilter = $_GET['min_rpc'] ?? '';
$minYahooRateFilter = $_GET['min_yahoo_rate'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 1000;

try {
    $pdo = getDBConnection();
    
    // Получение списка документов для фильтра
    $documentsStmt = $pdo->query("
        SELECT d.id, d.original_filename, d.country_code, d.upload_date, d.records_count
        FROM documents d 
        ORDER BY d.upload_date DESC
    ");
    $documents = $documentsStmt->fetchAll();
    
    // Получение уникальных кодов стран
    $countriesStmt = $pdo->query("
        SELECT DISTINCT country_code 
        FROM documents 
        ORDER BY country_code
    ");
    $countries = $countriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Получение уникальных статусов
    $statusesStmt = $pdo->query("
        SELECT DISTINCT status 
        FROM excel_data 
        WHERE status IS NOT NULL AND status != ''
        ORDER BY status
    ");
    $statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Построение запроса с фильтрами
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
    
    if (!empty($statusFilter)) {
        $whereConditions[] = "e.status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Подсчет общего количества записей (уникальных комбинаций document_id + keyword)
    $countQuery = "
        SELECT COUNT(DISTINCT CONCAT(e.document_id, '|', e.keyword)) 
        FROM excel_data e 
        JOIN documents d ON e.document_id = d.id 
        $whereClause
    ";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Получение данных с пагинацией
    $offset = ($page - 1) * $perPage;
    $dataQuery = "
        SELECT 
            e.document_id,
            e.keyword,
            MAX(e.yahoo_show_rate) as yahoo_show_rate,
            MAX(e.est_rpc) as est_rpc,
            e.status,
            d.original_filename, 
            d.country_code, 
            d.upload_date,
            COUNT(*) as duplicate_count,
            GROUP_CONCAT(e.id) as record_ids
        FROM excel_data e 
        JOIN documents d ON e.document_id = d.id 
        $whereClause
        GROUP BY e.document_id, e.keyword, e.status, d.original_filename, d.country_code, d.upload_date
        ORDER BY MAX(e.est_rpc) DESC, e.document_id DESC, e.keyword ASC
        LIMIT $perPage OFFSET $offset
    ";

    $dataStmt = $pdo->prepare($dataQuery);
    $dataStmt->execute($params);
    $data = $dataStmt->fetchAll();
    
    $totalPages = ceil($totalRecords / $perPage);
    
} catch (Exception $e) {
    $error = "Ошибка при получении данных: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр результатов</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="view-styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Просмотр результатов</h1>
            <p>Анализ данных из загруженных Excel файлов</p>
        </header>

        <nav class="navigation">
            <a href="index.php" class="nav-link">Загрузка файлов</a>
            <a href="view.php" class="nav-link active">Просмотр результатов</a>
        </nav>

        <main class="main-content">
            <?php if (isset($error)): ?>
                <div class="alert error"><?= h($error) ?></div>
            <?php else: ?>
                
                <!-- Фильтры -->
                <div class="filters-section">
                    <div class="filters-card">
                        <h3>Фильтры</h3>
                        <form method="GET" class="filters-form">
                            <div class="filter-group">
                                <label for="document">Документ:</label>
                                <select name="document" id="document">
                                    <option value="">Все документы</option>
                                    <?php foreach ($documents as $doc): ?>
                                        <option value="<?= $doc['id'] ?>" <?= $documentFilter == $doc['id'] ? 'selected' : '' ?>>
                                            <?= h($doc['original_filename']) ?> (<?= $doc['records_count'] ?> записей)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="country">Код страны:</label>
                                <select name="country" id="country">
                                    <option value="">Все страны</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= h($country) ?>" <?= $countryFilter == $country ? 'selected' : '' ?>>
                                            <?= h($country) ?>
                                        </option>   
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="status">Статус:</label>
                                <select name="status" id="status">
                                    <option value="">Все статусы</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= h($status) ?>" <?= $statusFilter == $status ? 'selected' : '' ?>>
                                            <?= h($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="min_rpc">Мин. Est. RPC $:</label>
                                <input type="number" name="min_rpc" id="min_rpc" step="0.0001" 
                                       value="<?= h($minRpcFilter) ?>" placeholder="0.0000">
                            </div>
                            
                            <div class="filter-group">
                                <label for="min_yahoo_rate">Мин. Y! Show Rate %:</label>
                                <input type="number" name="min_yahoo_rate" id="min_yahoo_rate" step="0.01" 
                                       value="<?= h($minYahooRateFilter) ?>" placeholder="0.00">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="filter-button">Применить фильтры</button>
                                <a href="view.php" class="reset-button">Сбросить</a>
                                <?php if (!empty($data)): ?>
                                    <a href="export.php?<?= http_build_query($_GET) ?>" class="export-button">
                                        Экспорт результатов
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Статистика -->
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?= number_format($totalRecords) ?></div>
                            <div class="stat-label">Всего записей</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= count($documents) ?></div>
                            <div class="stat-label">Документов</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= count($countries) ?></div>
                            <div class="stat-label">Стран</div>
                        </div>
                    </div>
                </div>

                <!-- Таблица данных -->
                <?php if (!empty($data)): ?>
                    <!-- Панель массовых действий -->
                    <div id="bulk-actions" class="bulk-actions" style="display: none;">
                        <div class="bulk-actions-content">
                            <span class="bulk-info">
                                Выбрано кейвордов: <strong id="selected-count">0</strong>
                            </span>
                            <div class="bulk-buttons">
                                <button id="copy-keywords" class="bulk-button copy-button">
                                    📋 Копировать кейворды
                                </button>
                                <button id="set-used" class="bulk-button used-button">
                                    Пометить как Used
                                </button>
                                <button id="set-new" class="bulk-button new-button">
                                    Пометить как New
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-section">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="checkbox-column">
                                            <input type="checkbox" id="select-all" title="Выбрать все">
                                        </th>
                                        <th>Keyword</th>
                                        <th>Yahoo Show Rate</th>
                                        <th>Est. RPC $</th>
                                        <th>Status</th>
                                        <th>Документ</th>
                                        <th>Страна</th>
                                        <th>Адверты</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td class="checkbox-cell">
                                                <input type="checkbox" class="keyword-checkbox" 
                                                    value="<?= h($row['record_ids']) ?>" 
                                                    data-keyword="<?= h($row['keyword']) ?>"
                                                    data-document="<?= h($row['document_id']) ?>">
                                            </td>
                                            <td class="keyword-cell"><?= h($row['keyword']) ?></td>
                                            <td class="rate-cell"><?= h($row['yahoo_show_rate']) ?></td>
                                            <td class="rpc-cell">
                                                <?= $row['est_rpc'] !== null ? number_format($row['est_rpc'], 4) : '-' ?>
                                            </td>
                                            <td class="status-cell">
                                                <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                                    <?= h($row['status']) ?>
                                                </span>
                                            </td>
                                            <td class="document-cell"><?= h($row['original_filename']) ?></td>
                                            <td class="country-cell">
                                                <span class="country-badge"><?= h($row['country_code']) ?></span>
                                            </td>
                                            <td class="count-cell"><?= $row['duplicate_count'] ?></td>
                                        </tr>

                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-section">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="pagination-link">← Предыдущая</a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                       class="pagination-link">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="pagination-dots">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="pagination-link active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                           class="pagination-link"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="pagination-dots">...</span>
                                    <?php endif; ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" 
                                       class="pagination-link"><?= $totalPages ?></a>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="pagination-link">Следующая →</a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pagination-info">
                                Показано <?= ($offset + 1) ?> - <?= min($offset + $perPage, $totalRecords) ?> 
                                из <?= number_format($totalRecords) ?> записей
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>Данные не найдены</h3>
                        <p>Попробуйте изменить параметры фильтрации или загрузите новые файлы.</p>
                        <a href="index.php" class="upload-link">Загрузить файлы</a>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const keywordCheckboxes = document.querySelectorAll('.keyword-checkbox');
    const bulkActionsDiv = document.getElementById('bulk-actions');
    const selectedCountSpan = document.getElementById('selected-count');
    
    let lastCheckedIndex = -1; // Индекс последнего выбранного чекбокса
    let isShiftSelection = false; // Флаг для предотвращения двойного срабатывания
    
    // Обработчик для "Выбрать все"
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            keywordCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
            lastCheckedIndex = -1; // Сбрасываем последний выбранный индекс
        });
    }
    
    // Обработчики для отдельных чекбоксов с поддержкой Shift
    keywordCheckboxes.forEach((checkbox, index) => {
        checkbox.addEventListener('mousedown', function(event) {
            if (event.shiftKey && lastCheckedIndex !== -1 && lastCheckedIndex !== index) {
                event.preventDefault(); // Предотвращаем стандартное поведение
                isShiftSelection = true; // Устанавливаем флаг
                
                // Определяем, какое состояние будет у текущего чекбокса
                const targetState = !this.checked;
                
                // Устанавливаем состояние текущего чекбокса
                this.checked = targetState;
                
                // Выбираем диапазон с тем же состоянием
                selectRange(lastCheckedIndex, index, targetState);
                
                // Обновляем последний выбранный индекс
                lastCheckedIndex = index;
                
                updateBulkActions();
                
                // Сбрасываем флаг через небольшую задержку
                setTimeout(() => {
                    isShiftSelection = false;
                }, 10);
            }
        });
        
        checkbox.addEventListener('change', function(event) {
            // Игнорируем событие change если это было Shift+клик
            if (isShiftSelection) {
                return;
            }
            
            // Обрабатываем обычные клики (без Shift)
            if (!event.shiftKey) {
                lastCheckedIndex = index;
                updateBulkActions();
            }
        });
        
        // Добавляем обработчик click для дополнительной защиты
        checkbox.addEventListener('click', function(event) {
            if (isShiftSelection) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
        });
    });
    
    function selectRange(startIndex, endIndex, checked) {
        const start = Math.min(startIndex, endIndex);
        const end = Math.max(startIndex, endIndex);
        
        // Выделяем все чекбоксы в диапазоне
        for (let i = start; i <= end; i++) {
            if (keywordCheckboxes[i]) {
                keywordCheckboxes[i].checked = checked;
            }
        }
        
        // Добавляем визуальную обратную связь
        showRangeSelection(start, end);
    }
    
    function showRangeSelection(start, end) {
        // Добавляем временный класс для визуального выделения диапазона
        for (let i = start; i <= end; i++) {
            if (keywordCheckboxes[i]) {
                const row = keywordCheckboxes[i].closest('tr');
                if (row) {
                    row.classList.add('range-selected');
                    // Убираем класс через короткое время
                    setTimeout(() => {
                        row.classList.remove('range-selected');
                    }, 300);
                }
            }
        }
    }
    
    function updateBulkActions() {
        const selectedCheckboxes = document.querySelectorAll('.keyword-checkbox:checked');
        const count = selectedCheckboxes.length;
        
        // Обновляем классы для выбранных строк (fallback для браузеров без :has())
        keywordCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            if (row) {
                if (checkbox.checked) {
                    row.classList.add('selected-row');
                } else {
                    row.classList.remove('selected-row');
                }
            }
        });
        
        if (count > 0) {
            bulkActionsDiv.style.display = 'block';
            selectedCountSpan.textContent = count;
        } else {
            bulkActionsDiv.style.display = 'none';
        }
        
        // Обновляем состояние "Выбрать все"
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = count === keywordCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < keywordCheckboxes.length;
        }
    }
    
    // Обработчики для кнопок изменения статуса
    document.getElementById('set-used')?.addEventListener('click', function() {
        updateSelectedStatus('Used');
    });
    
    document.getElementById('set-new')?.addEventListener('click', function() {
        updateSelectedStatus('New');
    });
    
    // Обработчик для кнопки копирования
    document.getElementById('copy-keywords')?.addEventListener('click', function() {
        copySelectedKeywords();
    });
    
    function updateSelectedStatus(status) {
        const selectedCheckboxes = document.querySelectorAll('.keyword-checkbox:checked');
        const recordIds = [];
        
        selectedCheckboxes.forEach(checkbox => {
            recordIds.push(checkbox.value);
        });
        
        if (recordIds.length === 0) {
            alert('Выберите хотя бы один кейворд');
            return;
        }
        
        // Отправляем AJAX запрос
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                record_ids: recordIds,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Перезагружаем страницу для обновления данных
            } else {
                alert('Ошибка при обновлении статуса: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при обновлении статуса');
        });
    }
    
    function copySelectedKeywords(format = 'line') {
        const selectedCheckboxes = document.querySelectorAll('.keyword-checkbox:checked');
        
        if (selectedCheckboxes.length === 0) {
            alert('Выберите хотя бы один кейворд для копирования');
            return;
        }
        
        // Собираем кейворды из data-атрибутов
        const keywords = [];
        selectedCheckboxes.forEach(checkbox => {
            const keyword = checkbox.getAttribute('data-keyword');
            if (keyword && !keywords.includes(keyword)) {
                keywords.push(keyword);
            }
        });
        
        if (keywords.length === 0) {
            alert('Не удалось получить кейворды для копирования');
            return;
        }
        
        // Форматируем текст в зависимости от выбранного формата
        let textToCopy;
        switch (format) {
            case 'comma':
                textToCopy = keywords.join(', ');
                break;
            case 'quotes':
                textToCopy = '"' + keywords.join('", "') + '"';
                break;
            case 'line':
            default:
                textToCopy = keywords.join('\n');
                break;
        }
        
        // Копируем в буфер обмена
        if (navigator.clipboard && window.isSecureContext) {
            // Современный способ через Clipboard API
            navigator.clipboard.writeText(textToCopy).then(() => {
                showCopySuccess(keywords.length);
            }).catch(() => {
                fallbackCopyToClipboard(textToCopy, keywords.length);
            });
        } else {
            // Fallback для старых браузеров
            fallbackCopyToClipboard(textToCopy, keywords.length);
        }
    }
    
    function fallbackCopyToClipboard(text, count) {
        // Создаем временный textarea элемент
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess(count);
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('Не удалось скопировать кейворды в буфер обмена');
        } finally {
            document.body.removeChild(textArea);
        }
    }
    
    function showCopySuccess(count) {
        // Показываем уведомление об успешном копировании
        const copyButton = document.getElementById('copy-keywords');
        const originalText = copyButton.textContent;
        
        copyButton.textContent = `✅ Скопировано ${count} кейворд${count > 1 ? (count < 5 ? 'а' : 'ов') : ''}!`;
        copyButton.style.backgroundColor = '#28a745';
        
        setTimeout(() => {
            copyButton.textContent = originalText;
            copyButton.style.backgroundColor = '';
        }, 2000);
    }
    
    // Добавляем подсказку о функции Shift
    if (keywordCheckboxes.length > 0) {
        const firstCheckbox = keywordCheckboxes[0];
        if (firstCheckbox) {
            // Создаем элемент подсказки
            const tooltip = document.createElement('div');
            tooltip.className = 'shift-tooltip';
            tooltip.innerHTML = '💡 Удерживайте <kbd>Shift</kbd> для выделения диапазона';
            tooltip.style.display = 'none';
            
            // Добавляем подсказку после таблицы
            const tableSection = document.querySelector('.table-section');
            if (tableSection) {
                tableSection.appendChild(tooltip);
                
                // Показываем подсказку на короткое время при загрузке страницы
                setTimeout(() => {
                    tooltip.style.display = 'block';
                    setTimeout(() => {
                        tooltip.style.display = 'none';
                    }, 5000);
                }, 1000);
            }
        }
    }
});
</script>


</body>
</html>

