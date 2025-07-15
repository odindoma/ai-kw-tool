<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка Excel файлов</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Система загрузки Excel файлов</h1>
            <p>Загрузите Excel файлы для анализа данных из вкладки "without ads"</p>
        </header>

        <nav class="navigation">
            <a href="index.php" class="nav-link active">Загрузка файлов</a>
            <a href="view.php" class="nav-link">Просмотр результатов</a>
        </nav>

        <main class="main-content">
            <div class="upload-section">
                <div class="upload-card">
                    <div class="upload-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                    </div>
                    
                    <h2>Загрузить Excel файл</h2>
                    <p>Поддерживаемые форматы: .xlsx, .xls</p>
                    
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="file-input-wrapper">
                            <input type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" required>
                            <label for="excelFile" class="file-input-label">
                                <span class="file-input-text">Выберите файл</span>
                                <span class="file-input-button">Обзор</span>
                            </label>
                        </div>
                        
                        <div class="file-info" id="fileInfo" style="display: none;">
                            <div class="file-details">
                                <span class="file-name" id="fileName"></span>
                                <span class="file-size" id="fileSize"></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="upload-button" id="uploadButton">
                            <span class="button-text">Загрузить файл</span>
                            <div class="loading-spinner" style="display: none;"></div>
                        </button>
                    </form>
                </div>
            </div>

            <div class="results-section" id="resultsSection" style="display: none;">
                <div class="alert" id="alertMessage"></div>
            </div>

            <div class="info-section">
                <h3>Информация о формате файла</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <h4>Требуемая вкладка</h4>
                        <p>Файл должен содержать вкладку с названием "without ads"</p>
                    </div>
                    <div class="info-item">
                        <h4>Необходимые колонки</h4>
                        <ul>
                            <li>Keyword</li>
                            <li>Yahoo (будет сохранено как Yahoo Show Rate)</li>
                            <li>Yahoo Advertiser (поле Advertisers)</li>
                            <li>Est. RPC $</li>
                        </ul>
                    </div>
                    <div class="info-item">
                        <h4>Код страны</h4>
                        <p>Код страны автоматически извлекается из имени файла (например, UK из "Genspark1-UK-20250715120738.xlsx")</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>

