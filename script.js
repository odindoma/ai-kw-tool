document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('excelFile');
    const fileInputLabel = document.querySelector('.file-input-label');
    const fileInputText = document.querySelector('.file-input-text');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadButton = document.getElementById('uploadButton');
    const buttonText = document.querySelector('.button-text');
    const loadingSpinner = document.querySelector('.loading-spinner');
    const resultsSection = document.getElementById('resultsSection');
    const alertMessage = document.getElementById('alertMessage');

    // Обработка выбора файла
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            fileInputLabel.classList.add('has-file');
            fileInputText.textContent = 'Файл выбран';
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            
            // Проверка расширения файла
            const allowedExtensions = ['xlsx', 'xls'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExtension)) {
                showAlert('Неподдерживаемый формат файла. Разрешены только .xlsx и .xls файлы.', 'error');
                resetFileInput();
                return;
            }
            
            // Проверка размера файла (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                showAlert('Файл слишком большой. Максимальный размер: 10MB', 'error');
                resetFileInput();
                return;
            }
            
            hideAlert();
        } else {
            resetFileInput();
        }
    });

    // Обработка отправки формы
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = fileInput.files[0];
        if (!file) {
            showAlert('Пожалуйста, выберите файл для загрузки.', 'error');
            return;
        }
        
        uploadFile(file);
    });

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('excel_file', file);
        
        // Показать индикатор загрузки
        setLoadingState(true);
        hideAlert();
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            setLoadingState(false);
            
            if (data.success) {
                showAlert(data.message, 'success');
                resetFileInput();
                
                // Автоматически перенаправить на страницу просмотра через 2 секунды
                setTimeout(() => {
                    window.location.href = 'view.php';
                }, 2000);
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            setLoadingState(false);
            console.error('Ошибка:', error);
            showAlert('Произошла ошибка при загрузке файла. Попробуйте еще раз.', 'error');
        });
    }

    function setLoadingState(loading) {
        uploadButton.disabled = loading;
        
        if (loading) {
            buttonText.textContent = 'Загрузка...';
            loadingSpinner.style.display = 'inline-block';
        } else {
            buttonText.textContent = 'Загрузить файл';
            loadingSpinner.style.display = 'none';
        }
    }

    function showAlert(message, type) {
        alertMessage.textContent = message;
        alertMessage.className = `alert ${type}`;
        resultsSection.style.display = 'flex';
        
        // Прокрутить к сообщению
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideAlert() {
        resultsSection.style.display = 'none';
    }

    function resetFileInput() {
        fileInput.value = '';
        fileInputLabel.classList.remove('has-file');
        fileInputText.textContent = 'Выберите файл';
        fileInfo.style.display = 'none';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Drag and drop функциональность
    const uploadCard = document.querySelector('.upload-card');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadCard.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadCard.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadCard.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        uploadCard.classList.add('drag-over');
    }
    
    function unhighlight(e) {
        uploadCard.classList.remove('drag-over');
    }
    
    uploadCard.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    }
});

// Добавить CSS для drag and drop
const style = document.createElement('style');
style.textContent = `
    .upload-card.drag-over {
        border-color: #667eea;
        background: #f0f4ff;
        transform: scale(1.02);
    }
`;
document.head.appendChild(style);

