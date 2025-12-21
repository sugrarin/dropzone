<?php
require_once 'auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
</head>
<body>
    <!-- Sidebar with categories -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Categories</h2>
            <button class="btn-ui-icon" id="addCategoryBtn" title="Add category">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg">
                    <line x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <ul class="categories-list" id="categoriesList">
            <!-- Categories will be populated by JavaScript -->
        </ul>
    </aside>

    <!-- Main content area -->
    <main class="main-content">
        <!-- Drop zone -->
        <div class="drop-zone" id="dropZone">
            <div class="drop-zone-content">
                <img src="icons/cloud.png" alt="Upload" class="upload-icon">
                <button class="btn-primary" id="uploadBtn">Upload document</button>
                <p class="drop-zone-text">or drag and drop it here</p>
            </div>
        </div>

        <!-- Search and sort controls -->
        <div class="controls">
            <div class="search-container">
                <img src="icons/search.png" alt="Search" class="search-icon">
                <input type="text" id="searchInput" placeholder="Search by name or link" class="search-input">
                <button class="btn-clear" id="clearSearch" style="display: none;">✕</button>
            </div>
            <button class="btn-icon sort-toggle" id="sortToggle" title="Sort">
                <img src="icons/filter-date.png" alt="Sort" class="icon-img" id="sortIcon">
            </button>
        </div>

        <!-- Files list -->
        <div class="files-container" id="filesContainer">
            <!-- Files will be populated by JavaScript -->
        </div>

        <!-- Statistics footer -->
        <div class="stats" id="stats">
            <span>Files: <span id="fileCount">0</span></span>
            <span>Size: <span id="totalSize">0 MB</span></span>
            <span>Today: <span id="todayCount">0</span></span>
            <a href="logout.php" class="btn-ui-icon" title="Logout" style="margin-left: auto;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
                    <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
        </div>
    </main>

    <!-- Toast notifications container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Hidden file input -->
    <input type="file" id="fileInput" style="display: none;" multiple>

    <!-- Category modal -->
    <div class="modal" id="categoryModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">New category</h3>
                <button class="btn-ui-icon" id="closeModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg">
                        <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="text" id="categoryNameInput" placeholder="Category name" class="modal-input">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelModal">Cancel</button>
                <button class="btn-primary" id="saveCategory">Save</button>
            </div>
        </div>
    </div>

    <!-- File rename modal -->
    <div class="modal" id="fileRenameModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rename file</h3>
                <button class="btn-ui-icon" id="closeFileRenameModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="icon-svg">
                        <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="text" id="fileNameInput" placeholder="File name" class="modal-input">
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" id="cancelFileRename">Cancel</button>
                <button class="btn-primary" id="saveFileRename">Save</button>
            </div>
        </div>
    </div>

    <!-- Category actions menu -->
    <div class="context-menu" id="categoryMenu" style="display: none;">
        <button class="menu-item" id="renameCategoryBtn">
            <img src="icons/edit.png" alt="Edit" class="icon-img-inline"> Rename
        </button>
        <button class="menu-item menu-item-danger" id="deleteCategoryBtn">
            <img src="icons/delete.png" alt="Delete" class="icon-img-inline"> Delete
        </button>
    </div>

    <!-- File actions menu -->
    <div class="context-menu" id="fileActionsMenu" style="display: none;">
        <button class="menu-item" id="fileMenuCopyLink">
            <img src="icons/copy-link.png" alt="Copy" class="icon-img-inline"> Copy link
        </button>
        <button class="menu-item" id="fileMenuOpen">
            <img src="icons/see-open.png" alt="Open" class="icon-img-inline"> Open
        </button>
        <button class="menu-item" id="fileMenuRename">
            <img src="icons/edit.png" alt="Rename" class="icon-img-inline"> Rename
        </button>
        <button class="menu-item" id="fileMenuReplace">
            <img src="icons/update.png" alt="Replace" class="icon-img-inline"> Replace
        </button>
        <button class="menu-item menu-item-danger" id="fileMenuDelete">
            <img src="icons/delete.png" alt="Delete" class="icon-img-inline"> Delete
        </button>
    </div>

    <script src="app.js"></script>
</body>
</html>
