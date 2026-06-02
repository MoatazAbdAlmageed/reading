<?php
/**
 * Reading System — Categories Manager
 */

require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';

// Handle CRUD operations
$is_edit = false;
$edit_id = 0;
$edit_name = '';

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $cat = get_category($edit_id);
    if ($cat) {
        $is_edit = true;
        $edit_name = $cat['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $name = trim($_POST['name'] ?? '');
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (empty($name)) {
            $error = 'Category name cannot be empty.';
        } else {
            if (save_category($id, $name)) {
                $success = $id ? 'Category updated successfully!' : 'Category created successfully!';
                // Clear state
                $is_edit = false;
                $edit_id = 0;
                $edit_name = '';
                
                // Redirect to avoid form resubmission
                header("Location: categories.php?success=" . urlencode($success));
                exit;
            } else {
                $error = 'Failed to save category. A category with a similar name might already exist.';
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (delete_category($id)) {
        header("Location: categories.php?success=" . urlencode("Category deleted successfully!"));
        exit;
    } else {
        $error = 'Failed to delete category.';
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

$categories = get_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Reader — Manage Categories</title>
    <link rel="icon" type="image/svg+xml" href="./assets/favicon.svg">
    <link rel="stylesheet" href="./assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .category-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .category-list-card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .category-table th, .category-table td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .category-table th {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .category-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .category-tag-badge {
            display: inline-block;
            background-color: var(--accent-glow);
            color: var(--accent-color);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(75, 110, 245, 0.3);
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .category-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="container">
        <!-- Header -->
        <header>
            <div class="logo-section">
                <h1><i class="fa-solid fa-tags"></i> Manage Categories</h1>
                <p>Organize your reading materials by technical topics like PHP, JS, Architecture</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </header>

        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert-banner" style="background-color: rgba(239, 68, 68, 0.1); border-color: var(--danger-color); color: var(--danger-color);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert-banner">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Split Screen Layout -->
        <div class="category-layout">
            <!-- Form Pane -->
            <div class="editor-pane" style="height: fit-content;">
                <h3 style="margin-bottom: 1rem;"><i class="fa-solid <?php echo $is_edit ? 'fa-pen-to-square' : 'fa-plus'; ?>"></i> <?php echo $is_edit ? 'Edit Category' : 'Create Category'; ?></h3>
                
                <form method="POST" action="categories.php<?php echo $is_edit ? '?edit=' . $edit_id : ''; ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                    
                    <div class="form-group">
                        <label for="categoryName"><i class="fa-solid fa-tag"></i> Category Name</label>
                        <input type="text" name="name" id="categoryName" class="form-control" placeholder="e.g. PHP, JavaScript, Security" value="<?php echo htmlspecialchars($edit_name); ?>" required autocomplete="off">
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                            Keep it concise and clear. This will be shown as a badge on topics.
                        </p>
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <?php if ($is_edit): ?>
                            <a href="categories.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-floppy-disk"></i> <?php echo $is_edit ? 'Update Category' : 'Create Category'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- List Pane -->
            <div class="category-list-card">
                <h3 style="margin-bottom: 0.5rem;"><i class="fa-solid fa-list"></i> Category List</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">The master list of categories currently defined in the system.</p>
                
                <?php if (empty($categories)): ?>
                    <div class="empty-state" style="margin-top: 1.5rem; padding: 2rem;">
                        <i class="fa-solid fa-tags" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.75rem; display: block;"></i>
                        <h4>No categories created yet</h4>
                        <p>Use the form on the left to set up your first category.</p>
                    </div>
                <?php else: ?>
                    <table class="category-table">
                        <thead>
                            <tr>
                                <th>Category Tag</th>
                                <th>URL Slug</th>
                                <th>Topics Count</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="category-tag-badge"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.85rem; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($cat['slug']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-en"><?php echo $cat['topic_count']; ?> topics</span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm" style="color: var(--warning-color); padding: 0.25rem 0.5rem;" title="Edit Name">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>')" class="btn btn-danger btn-sm" style="padding: 0.25rem 0.5rem;" title="Delete Category">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete the category "${name}"?\n\nTopics currently using this category will NOT be deleted, but they will no longer be labeled with it.`)) {
                window.location.href = `categories.php?action=delete&id=${id}`;
            }
        }
    </script>
</body>
</html>
