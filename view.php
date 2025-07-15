<?php
require_once 'config.php';

// Получение параметров фильтрации
$documentFilter = $_GET['document'] ?? '';
$countryFilter = $_GET['country'] ?? '';
$minRpcFilter = $_GET['min_rpc'] ?? '';
$minYahooRateFilter = $_GET['min_yahoo_rate'] ?? '';
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
            d.original_filename, 
            d.country_code, 
            d.upload_date,
            COUNT(*) as duplicate_count
        FROM excel_data e 
        JOIN documents d ON e.document_id = d.id 
        $whereClause
        GROUP BY e.document_id, e.keyword, d.original_filename, d.country_code, d.upload_date
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
                                <label for="min_rpc">Минимальное Est. RPC $:</label>
                                <input type="number" name="min_rpc" id="min_rpc" step="0.0001" 
                                       value="<?= h($minRpcFilter) ?>" placeholder="0.0000">
                            </div>
                            
                            <div class="filter-group">
                                <label for="min_yahoo_rate">Минимальное Yahoo Show Rate %:</label>
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
                    <div class="table-section">
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Keyword</th>
                                        <th>Yahoo Show Rate</th>
                                        <th>Est. RPC $</th>
                                        <th>Документ</th>
                                        <th>Страна</th>
                                        <th>Кол-во адвертов</th> <!-- Новая колонка -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td class="keyword-cell"><?= h($row['keyword']) ?></td>
                                            <td class="rate-cell"><?= h($row['yahoo_show_rate']) ?></td>
                                            <td class="rpc-cell">
                                                <?= $row['est_rpc'] !== null ? number_format($row['est_rpc'], 4) : '-' ?>
                                            </td>
                                            <td class="document-cell"><?= h($row['original_filename']) ?></td>
                                            <td class="country-cell">
                                                <span class="country-badge"><?= h($row['country_code']) ?></span>
                                            </td>
                                            <td class="count-cell"><?= $row['duplicate_count'] ?></td> <!-- Новая колонка -->
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
</body>
</html>

