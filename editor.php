<?php
/**
 * Reading System — Topic Editor (Create & Edit)
 */

require_once __DIR__ . '/includes/functions.php';

$is_edit = false;
$slug = '';
$title = '';
$lang = 'en';
$content = '';
$topic_category_ids = [];

// Fetch all categories for selection
$categories = get_categories();

// Load existing topic if editing
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    $topic = get_topic($slug);
    if ($topic) {
        $is_edit = true;
        $title = $topic['metadata']['title'];
        $lang = $topic['metadata']['lang'];
        $content = $topic['markdown'];
        
        // Extract array of category IDs
        if (!empty($topic['metadata']['categories'])) {
            $topic_category_ids = array_column($topic['metadata']['categories'], 'id');
        }
    }
}

// Handle Form Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $lang = $_POST['lang'] ?? 'en';
    $content = trim($_POST['content'] ?? '');
    $category_ids = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : [];
    
    if (empty($title)) {
        $error = 'Topic title is required.';
    } elseif (empty($content)) {
        $error = 'Content is required.';
    } else {
        if ($is_edit) {
            // Keep the same slug when editing
            $target_slug = $slug;
        } else {
            // Generate clean unique slug for new topics
            $target_slug = slugify($title);
            
            $db = Database::connect();
            $stmt = $db->prepare("SELECT id FROM topics WHERE slug = :slug");
            $stmt->execute([':slug' => $target_slug]);
            
            if ($stmt->fetch()) {
                // Handle collision: append suffix
                $counter = 1;
                while (true) {
                    $test_slug = $target_slug . '-' . $counter;
                    $stmt_test = $db->prepare("SELECT id FROM topics WHERE slug = :test");
                    $stmt_test->execute([':test' => $test_slug]);
                    if (!$stmt_test->fetch()) {
                        $target_slug = $test_slug;
                        break;
                    }
                    $counter++;
                }
            }
        }
        
        if (save_topic($target_slug, $title, $lang, $content, $category_ids)) {
            header("Location: index.php?saved=1");
            exit;
        } else {
            $error = 'An error occurred while saving the topic. Please check file permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Reader — <?php echo $is_edit ? 'Edit Topic' : 'Create Topic'; ?></title>
    <link rel="icon" type="image/svg+xml" href="./assets/favicon.svg">
    <link rel="stylesheet" href="./assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .category-pills-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .category-pill-label {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }

        .category-pill-label input[type="checkbox"] {
            display: none;
        }

        .category-pill-label span {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            user-select: none;
            transition: all 0.2s;
        }

        .category-pill-label input[type="checkbox"]:checked + span {
            background-color: var(--accent-glow);
            border-color: var(--accent-color);
            color: var(--accent-color);
            box-shadow: 0 0 10px rgba(75, 110, 245, 0.2);
        }

        .category-pill-label:hover span {
            border-color: var(--text-secondary);
            background-color: var(--bg-surface-hover);
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="container">
        <!-- Editor Header -->
        <header style="margin-bottom: 1.5rem;">
            <div class="logo-section">
                <h1><i class="fa-solid fa-pen-nib"></i> <?php echo $is_edit ? 'Edit Reading Topic' : 'Draft New Reading Topic'; ?></h1>
                <p><?php echo $is_edit ? "Modifying article content: " . htmlspecialchars($title) : "Write a markdown topic and define its default reading language"; ?></p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Cancel & Return</a>
            </div>
        </header>

        <!-- Display Errors if any -->
        <?php if (!empty($error)): ?>
            <div class="alert-banner" style="background-color: rgba(239, 68, 68, 0.1); border-color: var(--danger-color); color: var(--danger-color);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Split Screen Editor Layout -->
        <form method="POST" action="" style="display: flex; flex-direction: column; flex: 1;">
            <div class="editor-layout">
                <!-- Left Pane: Editor Inputs -->
                <div class="editor-pane">
                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="titleInput"><i class="fa-solid fa-heading"></i> Topic Title</label>
                            <input type="text" name="title" id="titleInput" class="form-control" placeholder="e.g. Understanding API Authentication" value="<?php echo htmlspecialchars($title); ?>" required autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="langSelect"><i class="fa-solid fa-language"></i> Language</label>
                            <select name="lang" id="langSelect" class="form-control" onchange="togglePreviewLanguage()">
                                <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English (LTR)</option>
                                <option value="ar" <?php echo $lang === 'ar' ? 'selected' : ''; ?>>العربية (RTL)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fa-solid fa-tags"></i> Topic Categories</span>
                            <a href="categories.php" target="_blank" style="font-size: 0.8rem; color: var(--accent-color); text-decoration: none; font-weight: normal;"><i class="fa-solid fa-gear"></i> Manage Categories</a>
                        </label>
                        <?php if (empty($categories)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                                No categories defined yet. <a href="categories.php" style="color: var(--accent-color); text-decoration: underline;">Create categories first</a> to organize your topics.
                            </p>
                        <?php else: ?>
                            <div class="category-pills-selector">
                                <?php foreach ($categories as $cat): ?>
                                    <label class="category-pill-label">
                                        <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
                                            <?php echo in_array($cat['id'], $topic_category_ids) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <label for="markdownEditor" style="display: flex; justify-content: space-between;">
                        <span><i class="fa-brands fa-markdown"></i> Markdown Content</span>
                        <span style="font-weight: normal; color: var(--text-muted); font-size: 0.8rem;">Supports full GFM syntax</span>
                    </label>
                    <textarea name="content" id="markdownEditor" class="textarea-editor" placeholder="Write your topic in Markdown here... Use standard syntax like # Headings, * Lists, **Bold**, `code`, etc." required oninput="updateLivePreview()"><?php echo htmlspecialchars($content); ?></textarea>

                    <div class="editor-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> <?php echo $is_edit ? 'Save & Sync Changes' : 'Publish Topic'; ?>
                        </button>
                    </div>
                </div>

                <!-- Right Pane: Live HTML Preview -->
                <div class="preview-pane <?php echo $lang === 'ar' ? 'rtl' : ''; ?>" id="previewPane">
                    <div class="preview-header">
                        <span><i class="fa-solid fa-eye"></i> Live Reading Preview</span>
                        <span id="previewLangLabel"><?php echo $lang === 'ar' ? 'Arabic (RTL)' : 'English (LTR)'; ?></span>
                    </div>
                    <div class="preview-body" id="previewContent">
                        <!-- Rendered Markdown goes here -->
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Marked Library for Live Markdown Parsing -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        /**
         * Dynamic real-time preview updating
         */
        function updateLivePreview() {
            const markdown = document.getElementById('markdownEditor').value;
            const previewContainer = document.getElementById('previewContent');
            
            if (markdown.trim() === '') {
                previewContainer.innerHTML = '<em style="color: var(--text-muted); display: block; text-align: center; margin-top: 3rem;">Start typing to see live formatting...</em>';
                return;
            }
            
            // Set marked options for security and line breaks
            marked.setOptions({
                breaks: true,
                gfm: true
            });
            
            previewContainer.innerHTML = marked.parse(markdown);
        }

        /**
         * Dynamic toggling of RTL styles for Arabic preview
         */
        function togglePreviewLanguage() {
            const langSelect = document.getElementById('langSelect');
            const previewPane = document.getElementById('previewPane');
            const langLabel = document.getElementById('previewLangLabel');
            
            if (langSelect.value === 'ar') {
                previewPane.classList.add('rtl');
                langLabel.textContent = 'Arabic (RTL)';
            } else {
                previewPane.classList.remove('rtl');
                langLabel.textContent = 'English (LTR)';
            }
        }

        // Initialize preview on page load
        window.addEventListener('DOMContentLoaded', () => {
            updateLivePreview();
            togglePreviewLanguage();
        });
    </script>
</body>
</html>
