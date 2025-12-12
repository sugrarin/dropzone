let files = [];
let categories = ['All files'];
let currentCategory = 'All files';
let searchQuery = '';
let sortBy = 'date';
let currentCategoryForEdit = null;
let lastCopiedFileId = null;

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadBtn = document.getElementById('uploadBtn');
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
const sortToggle = document.getElementById('sortToggle');
const filesContainer = document.getElementById('filesContainer');
const categoriesList = document.getElementById('categoriesList');
const addCategoryBtn = document.getElementById('addCategoryBtn');
const categoryModal = document.getElementById('categoryModal');
const modalTitle = document.getElementById('modalTitle');
const categoryNameInput = document.getElementById('categoryNameInput');
const closeModal = document.getElementById('closeModal');
const cancelModal = document.getElementById('cancelModal');
const saveCategory = document.getElementById('saveCategory');
const categoryMenu = document.getElementById('categoryMenu');
const renameCategoryBtn = document.getElementById('renameCategoryBtn');
const deleteCategoryBtn = document.getElementById('deleteCategoryBtn');
const toastContainer = document.getElementById('toastContainer');

async function init() {
    await loadData();
    renderCategories();
    renderFiles();
    updateStats();
    setupEventListeners();
}

function setupEventListeners() {
    uploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFileSelect);

    document.body.addEventListener('dragenter', handleDragEnter);
    document.body.addEventListener('dragover', handleDragOver);
    document.body.addEventListener('dragleave', handleDragLeave);
    document.body.addEventListener('drop', handleDrop);

    dropZone.addEventListener('dragenter', handleDragEnter);
    dropZone.addEventListener('dragover', handleDragOver);
    dropZone.addEventListener('dragleave', handleDragLeave);
    dropZone.addEventListener('drop', handleDrop);

    searchInput.addEventListener('input', handleSearch);
    clearSearch.addEventListener('click', handleClearSearch);

    sortToggle.addEventListener('click', handleSortToggle);

    addCategoryBtn.addEventListener('click', () => openCategoryModal());
    closeModal.addEventListener('click', closeCategoryModal);
    cancelModal.addEventListener('click', closeCategoryModal);
    saveCategory.addEventListener('click', handleSaveCategory);
    categoryNameInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSaveCategory();
    });

    const closeFileRenameBtn = document.getElementById('closeFileRenameModal');
    const cancelFileRename = document.getElementById('cancelFileRename');
    const saveFileRename = document.getElementById('saveFileRename');
    const fileNameInput = document.getElementById('fileNameInput');

    closeFileRenameBtn.addEventListener('click', closeFileRenameModal);
    cancelFileRename.addEventListener('click', closeFileRenameModal);
    saveFileRename.addEventListener('click', handleSaveFileRename);
    fileNameInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleSaveFileRename();
    });

    renameCategoryBtn.addEventListener('click', handleRenameCategory);
    deleteCategoryBtn.addEventListener('click', handleDeleteCategory);

    document.addEventListener('click', (e) => {
        if (!categoryMenu.contains(e.target) && !e.target.classList.contains('category-menu-btn')) {
            categoryMenu.style.display = 'none';
        }
    });

    let mouseDownTarget = null;

    categoryModal.addEventListener('mousedown', (e) => {
        mouseDownTarget = e.target;
    });

    categoryModal.addEventListener('click', (e) => {
        if (e.target === categoryModal && mouseDownTarget === categoryModal) {
            closeCategoryModal();
        }
    });

    const fileRenameModal = document.getElementById('fileRenameModal');

    fileRenameModal.addEventListener('mousedown', (e) => {
        mouseDownTarget = e.target;
    });

    fileRenameModal.addEventListener('click', (e) => {
        if (e.target === fileRenameModal && mouseDownTarget === fileRenameModal) {
            closeFileRenameModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (categoryModal.style.display === 'flex') {
                closeCategoryModal();
            }
            if (fileRenameModal.style.display === 'flex') {
                closeFileRenameModal();
            }
        }
    });
}

async function loadData() {
    // Client-side initialization

    // DEMO DATA: Put your static files in the project folder and list them here
    files = [
        {
            id: 'demo-1',
            name: 'Demo Screenshot.png',
            size: 82188,
            type: 'image/png',
            url: 'demo-screenshot.png',
            uploadDate: new Date().toISOString(),
            category: 'Images',
            extension: 'png'
        },
        {
            id: 'demo-2',
            name: 'The Productivity Project Summary.pdf',
            size: 2306867,
            type: 'application/pdf',
            url: 'The-Productivity-Project-Summary.pdf',
            uploadDate: new Date().toISOString(),
            category: 'Documents',
            extension: 'pdf'
        },
        {
            id: 'demo-3',
            name: 'iPhone User Guide.pdf',
            size: 3880531,
            type: 'application/pdf',
            url: 'iPhone-User-Guide.pdf',
            uploadDate: new Date().toISOString(),
            category: 'Documents',
            extension: 'pdf'
        }
    ];

    // Start with some default categories
    categories = ['All files', 'Documents', 'Images', 'Design'];
    renderCategories();
    renderFiles();
    updateStats();
}

function renderCategories() {
    categoriesList.innerHTML = '';

    categories.forEach((category, index) => {
        const li = document.createElement('li');
        li.className = `category-item ${category === currentCategory ? 'active' : ''}`;

        const nameSpan = document.createElement('span');
        nameSpan.className = 'category-name';
        nameSpan.textContent = category;
        nameSpan.addEventListener('click', () => selectCategory(category));

        li.appendChild(nameSpan);

        if (category !== 'All files') {
            const menuBtn = document.createElement('button');
            menuBtn.className = 'category-btn-icon category-menu-btn';
            menuBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg-small">
                    <circle cx="6" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="18" cy="12" r="1.5" fill="currentColor"/>
                </svg>
            `;
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showCategoryMenu(e, category);
            });
            li.appendChild(menuBtn);
        }

        categoriesList.appendChild(li);
    });
}

function selectCategory(category) {
    currentCategory = category;
    renderCategories();
    renderFiles();
}

function showCategoryMenu(e, category) {
    currentCategoryForEdit = category;
    categoryMenu.style.display = 'block';
    categoryMenu.style.left = e.pageX + 'px';
    categoryMenu.style.top = e.pageY + 'px';
}

function openCategoryModal(mode = 'create', categoryName = '') {
    modalTitle.textContent = mode === 'create' ? 'New category' : 'Rename category';
    categoryNameInput.value = categoryName;
    categoryModal.style.display = 'flex';
    categoryNameInput.focus();
    categoryModal.dataset.mode = mode;
}

function closeCategoryModal() {
    categoryModal.style.display = 'none';
    categoryNameInput.value = '';
    currentCategoryForEdit = null;
}

async function handleSaveCategory() {
    const name = categoryNameInput.value.trim();
    if (!name) {
        showToast('Category name is required', 'error');
        return;
    }

    const mode = categoryModal.dataset.mode;

    if (mode === 'create') {
        if (!categories.includes(name)) {
            categories.push(name);
            renderCategories();
            showToast('Category created', 'success');
        } else {
            showToast('Category already exists', 'error');
        }
    } else {
        const index = categories.indexOf(currentCategoryForEdit);
        if (index !== -1) {
            categories[index] = name;
            // Update files in this category
            files.forEach(f => {
                if (f.category === currentCategoryForEdit) {
                    f.category = name;
                }
            });

            if (currentCategory === currentCategoryForEdit) {
                currentCategory = name;
            }
            renderCategories();
            renderFiles();
            showToast('Category renamed', 'success');
        }
    }

    closeCategoryModal();
}

function handleRenameCategory() {
    categoryMenu.style.display = 'none';
    openCategoryModal('rename', currentCategoryForEdit);
}

async function handleDeleteCategory() {
    categoryMenu.style.display = 'none';

    const categoryItems = document.querySelectorAll('.category-item');
    let categoryElement = null;
    categoryItems.forEach(item => {
        const nameSpan = item.querySelector('.category-name');
        if (nameSpan && nameSpan.textContent === currentCategoryForEdit) {
            categoryElement = item;
        }
    });

    if (!categoryElement) return;

    const nameSpan = categoryElement.querySelector('.category-name');
    const menuBtn = categoryElement.querySelector('.category-menu-btn');
    const originalOpacity = nameSpan.style.opacity;

    const originalHandler = (e) => {
        e.stopPropagation();
        showCategoryMenu(e, currentCategoryForEdit);
    };

    nameSpan.style.opacity = '0.4';
    menuBtn.className = 'category-btn-icon category-undo-btn';
    menuBtn.innerHTML = `<img src="icons/undo.png" alt="Undo" class="icon-img">`;
    menuBtn.style.opacity = '1';

    const newMenuBtn = menuBtn.cloneNode(true);
    menuBtn.parentNode.replaceChild(newMenuBtn, menuBtn);
    const currentMenuBtn = newMenuBtn;

    let undoTimeout = null;
    let cancelled = false;

    const undoHandler = () => {
        cancelled = true;
        clearTimeout(undoTimeout);
        nameSpan.style.opacity = originalOpacity;
        currentMenuBtn.className = 'category-btn-icon category-menu-btn';
        currentMenuBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg-small">
                <circle cx="6" cy="12" r="1.5" fill="currentColor"/>
                <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                <circle cx="18" cy="12" r="1.5" fill="currentColor"/>
            </svg>
        `;
        currentMenuBtn.removeEventListener('click', undoHandler);
        currentMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showCategoryMenu(e, currentCategoryForEdit);
        });
    };

    currentMenuBtn.addEventListener('click', undoHandler);

    undoTimeout = setTimeout(async () => {
        if (cancelled) return;

        menuBtn.removeEventListener('click', undoHandler);

        // Local state update
        const index = categories.indexOf(currentCategoryForEdit);
        if (index !== -1) {
            categories.splice(index, 1);

            // Update files
            files.forEach(f => {
                if (f.category === currentCategoryForEdit) {
                    f.category = 'Uncategorized';
                }
            });

            if (currentCategory === currentCategoryForEdit) {
                currentCategory = 'All files';
            }
            renderCategories();
            renderFiles();
            updateStats();
            showToast('Category deleted', 'success');
        } else {
            showToast('Error deleting category', 'error');
            nameSpan.style.opacity = originalOpacity;
        }
    }, 4000);
}

function renderFiles() {
    let filteredFiles = files;

    if (currentCategory !== 'All files') {
        filteredFiles = filteredFiles.filter(f => f.category === currentCategory);
    }

    if (searchQuery) {
        filteredFiles = filteredFiles.filter(f =>
            f.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            f.id.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }

    filteredFiles.sort((a, b) => {
        if (sortBy === 'date') {
            return new Date(b.uploadDate) - new Date(a.uploadDate);
        } else {
            return b.size - a.size;
        }
    });

    filesContainer.innerHTML = '';

    if (filteredFiles.length === 0) {
        const emptyMessage = searchQuery
            ? 'Nothing found'
            : 'No files';
        filesContainer.innerHTML = `
            <div class="empty-state">
                <img src="icons/empty.png" alt="Empty" class="empty-state-icon">
                <p>${emptyMessage}</p>
            </div>
        `;
        return;
    }

    filteredFiles.forEach(file => {
        const fileItem = createFileItem(file);
        filesContainer.appendChild(fileItem);
    });
}

function createFileItem(file) {
    const div = document.createElement('div');
    div.className = 'file-item';

    const icon = document.createElement('div');
    icon.className = 'file-icon';
    icon.innerHTML = getFileIcon(file.extension, file.id);

    const info = document.createElement('div');
    info.className = 'file-info';

    const name = document.createElement('div');
    name.className = 'file-name';
    name.textContent = file.id === lastCopiedFileId ? file.name + ' •' : file.name;

    const meta = document.createElement('div');
    meta.className = 'file-meta';
    const dateStr = file.modified ? `🔄 ${formatDate(file.uploadDate)}` : formatDate(file.uploadDate);
    meta.textContent = `${dateStr} · ${formatSize(file.size)}`;

    info.appendChild(name);
    info.appendChild(meta);

    const actions = document.createElement('div');
    actions.className = 'file-actions';

    const copyBtn = createActionButton('copy-link.png', 'Скопировать ссылку', () => copyLink(file));
    const openBtn = createActionButton('see-open.png', 'Открыть', () => openFile(file));

    const categorySelect = document.createElement('select');
    categorySelect.className = 'category-select';
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        option.selected = cat === file.category;
        categorySelect.appendChild(option);
    });
    categorySelect.addEventListener('change', (e) => updateFileCategory(file.id, e.target.value));

    const renameBtn = createActionButton('edit.png', 'Rename', () => renameFile(file));
    const replaceBtn = createActionButton('update.png', 'Replace file', () => replaceFile(file));
    const deleteBtn = createActionButton('delete.png', 'Delete', () => deleteFile(file), true);

    actions.appendChild(copyBtn);
    actions.appendChild(openBtn);
    actions.appendChild(renameBtn);
    actions.appendChild(replaceBtn);
    actions.appendChild(deleteBtn);
    actions.appendChild(categorySelect);

    div.appendChild(icon);
    div.appendChild(info);
    div.appendChild(actions);

    return div;
}

function createActionButton(iconFile, title, onClick, isDanger = false) {
    const btn = document.createElement('button');
    btn.className = `action-btn ${isDanger ? 'danger' : ''}`;
    btn.innerHTML = `<img src="icons/${iconFile}" alt="${title}" class="icon-img">`;
    btn.title = title;
    btn.addEventListener('click', onClick);
    return btn;
}

function getFileIcon(ext, fileId) {
    let hash = 0;
    for (let i = 0; i < fileId.length; i++) {
        hash = ((hash << 5) - hash) + fileId.charCodeAt(i);
        hash = hash & hash;
    }

    const iconNumber = (Math.abs(hash) % 4) + 1;
    const iconFile = `file-${iconNumber}.png`;

    return `<img src="icons/${iconFile}" alt="${ext}" class="file-icon-img">`;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const fileDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());

    const time = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    if (fileDate.getTime() === today.getTime()) {
        return `Today, ${time}`;
    }

    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    if (fileDate.getTime() === yesterday.getTime()) {
        return `Yesterday, ${time}`;
    }

    return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) + ', ' + time;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

let dragCounter = 0;

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    dropZone.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();

    dragCounter--;
    if (dragCounter === 0) {
        dropZone.classList.remove('drag-over');
    }
}

function handleDragEnter(e) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter++;
    dropZone.classList.add('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    dragCounter = 0;
    dropZone.classList.remove('drag-over');

    const files = Array.from(e.dataTransfer.files);
    uploadFiles(files);
}

function handleFileSelect(e) {
    const files = Array.from(e.target.files);
    uploadFiles(files);
    e.target.value = '';
}

async function uploadFiles(filesToUpload) {
    for (const file of filesToUpload) {
        await uploadFile(file);
    }
}

async function uploadFile(file) {
    // Simulate network delay for better UX
    const btn = document.getElementById('uploadBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Uploading...';
    btn.disabled = true;

    await new Promise(resolve => setTimeout(resolve, 1500));

    btn.textContent = originalText;
    btn.disabled = false;

    // Create a Blob URL
    const blobUrl = URL.createObjectURL(file);

    // Create new file object
    const newFile = {
        id: Date.now() + Math.random().toString(36).substr(2, 9),
        name: file.name,
        size: file.size,
        type: file.type,
        url: blobUrl,
        uploadDate: new Date().toISOString(),
        category: currentCategory === 'All files' ? 'Uncategorized' : currentCategory,
        extension: file.name.split('.').pop()
    };

    files.unshift(newFile); // Add to beginning
    renderFiles();
    updateStats();
    showToast(`File uploaded: ${file.name}`, 'success');
}

async function copyLink(file) {
    const link = file.url;
    await copyToClipboard(link);
    lastCopiedFileId = file.id;
    renderFiles();
    showToast('Blob link copied to clipboard', 'success');
}

async function copyToClipboard(text) {
    if (!navigator.clipboard) {
        throw new Error('Clipboard API not available');
    }

    try {
        await navigator.clipboard.writeText(text);
        console.log('Clipboard write successful');
    } catch (err) {
        console.error('Clipboard write failed:', err);
        throw err;
    }
}

function openFile(file) {
    if (file.url) {
        window.open(file.url, '_blank');
    } else {
        showToast('File URL not found', 'error');
    }
}

let currentFileForRename = null;

function renameFile(file) {
    currentFileForRename = file;
    const fileRenameModal = document.getElementById('fileRenameModal');
    const fileNameInput = document.getElementById('fileNameInput');

    fileNameInput.value = file.name;
    fileRenameModal.style.display = 'flex';

    setTimeout(() => {
        fileNameInput.focus();
        fileNameInput.select();
    }, 100);
}

async function handleSaveFileRename() {
    const newName = document.getElementById('fileNameInput').value.trim();

    if (!newName || newName === currentFileForRename.name) {
        closeFileRenameModal();
        return;
    }

    const fileIndex = files.findIndex(f => f.id === currentFileForRename.id);
    if (fileIndex !== -1) {
        files[fileIndex].name = newName;
        // Update extension if the user changed it? usually we keep extension, but for demo let's just take the name
        // Ideally we should preserve extension if not provided, but let's keep it simple
        renderFiles();
        updateStats();
        showToast('File renamed', 'success');
    }

    closeFileRenameModal();
}

function closeFileRenameModal() {
    document.getElementById('fileRenameModal').style.display = 'none';
    document.getElementById('fileNameInput').value = '';
    currentFileForRename = null;
}

function replaceFile(file) {
    const input = document.createElement('input');
    input.type = 'file';
    input.style.display = 'none';
    input.onchange = async (e) => {
        const newFile = e.target.files[0];
        if (!newFile) {
            document.body.removeChild(input);
            return;
        }

        // Simulate upload delay
        showToast('Replacing file...', 'info');
        await new Promise(resolve => setTimeout(resolve, 1000));

        const blobUrl = URL.createObjectURL(newFile);

        const fileIndex = files.findIndex(f => f.id === file.id);
        if (fileIndex !== -1) {
            files[fileIndex] = {
                ...files[fileIndex],
                name: newFile.name,
                size: newFile.size,
                type: newFile.type,
                url: blobUrl,
                uploadDate: new Date().toISOString(),
                extension: newFile.name.split('.').pop()
            };
            renderFiles();
            showToast('File replaced', 'success');
        }

        document.body.removeChild(input);
    };

    document.body.appendChild(input);
    input.click();
}

function deleteFile(file) {
    const fileItems = document.querySelectorAll('.file-item');
    let fileElement = null;
    fileItems.forEach(item => {
        const fileNameEl = item.querySelector('.file-name');
        if (fileNameEl && fileNameEl.textContent === file.name) {
            fileElement = item;
        }
    });

    if (!fileElement) return;

    const fileName = fileElement.querySelector('.file-name');
    const fileIcon = fileElement.querySelector('.file-icon');
    const fileMeta = fileElement.querySelector('.file-meta');
    const fileActions = fileElement.querySelector('.file-actions');
    const originalNameOpacity = fileName.style.opacity;
    const originalIconOpacity = fileIcon.style.opacity;
    const originalMetaOpacity = fileMeta.style.opacity;
    const originalMetaText = fileMeta.textContent;
    const originalActionsHTML = fileActions.innerHTML;

    fileName.style.opacity = '0.5';
    fileIcon.style.opacity = '0.5';
    fileMeta.style.opacity = '0.9';
    fileMeta.textContent = 'File deleted';
    fileActions.innerHTML = `
        <button class="action-btn undo-btn" title="Cancel deletion">
            <img src="icons/undo.png" alt="Undo" class="icon-img">
        </button>
    `;

    const undoBtn = fileActions.querySelector('.undo-btn');
    let undoTimeout = null;
    let cancelled = false;

    const undoHandler = () => {
        cancelled = true;
        clearTimeout(undoTimeout);
        fileName.style.opacity = originalNameOpacity;
        fileIcon.style.opacity = originalIconOpacity;
        fileMeta.style.opacity = originalMetaOpacity;
        fileMeta.textContent = originalMetaText;
        fileActions.innerHTML = originalActionsHTML;

        const copyBtn = fileActions.querySelector('[title="Copy link"]');
        const openBtn = fileActions.querySelector('[title="Open"]');
        const renameBtn = fileActions.querySelector('[title="Rename"]');
        const replaceBtn = fileActions.querySelector('[title="Replace file"]');
        const deleteBtn = fileActions.querySelector('[title="Delete"]');
        const categorySelect = fileActions.querySelector('.category-select');

        if (copyBtn) copyBtn.addEventListener('click', () => copyLink(file));
        if (openBtn) openBtn.addEventListener('click', () => openFile(file));
        if (renameBtn) renameBtn.addEventListener('click', () => renameFile(file));
        if (replaceBtn) replaceBtn.addEventListener('click', () => replaceFile(file));
        if (deleteBtn) deleteBtn.addEventListener('click', () => deleteFile(file));
        if (categorySelect) categorySelect.addEventListener('change', (e) => updateFileCategory(file.id, e.target.value));
    };

    undoBtn.addEventListener('click', undoHandler);

    undoTimeout = setTimeout(async () => {
        if (cancelled) return;

        // Finalize deletion (just remove from local array)
        files = files.filter(f => f.id !== file.id);
        renderFiles();
        updateStats();
        showToast('File deleted', 'success');
    }, 4000);
}

async function updateFileCategory(fileId, newCategory) {
    const fileIndex = files.findIndex(f => f.id === fileId);
    if (fileIndex !== -1) {
        files[fileIndex].category = newCategory;
        renderFiles();
        showToast('Category updated', 'success');
    }
}

function handleSearch(e) {
    searchQuery = e.target.value;
    clearSearch.style.display = searchQuery ? 'block' : 'none';
    renderFiles();
}

function handleClearSearch() {
    searchInput.value = '';
    searchQuery = '';
    clearSearch.style.display = 'none';
    renderFiles();
}

function handleSortToggle() {
    sortBy = sortBy === 'date' ? 'size' : 'date';
    const sortIcon = document.getElementById('sortIcon');
    sortIcon.src = sortBy === 'date' ? 'icons/filter-date.png' : 'icons/filter-size.png';
    const message = sortBy === 'date' ? 'Sorted by date' : 'Sorted by size';
    showToast(message, 'info');
    renderFiles();
}

function updateStats() {
    const fileCount = document.getElementById('fileCount');
    const totalSize = document.getElementById('totalSize');
    const todayCount = document.getElementById('todayCount');

    fileCount.textContent = files.length;

    const totalBytes = files.reduce((sum, f) => sum + f.size, 0);
    totalSize.textContent = formatSize(totalBytes);

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayFiles = files.filter(f => new Date(f.uploadDate) >= today);
    todayCount.textContent = todayFiles.length;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const msg = document.createElement('span');
    msg.className = 'toast-message';
    msg.textContent = message;

    toast.appendChild(msg);
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

init();
