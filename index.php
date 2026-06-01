<?php
/**
 * Reading System — Dashboard
 */

require_once __DIR__ . '/includes/functions.php';

// Handle deletion
$delete_success = false;
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    if (delete_topic($slug)) {
        $delete_success = true;
        // Redirect to clear URL parameters
        header("Location: index.php?deleted=1");
        exit;
    }
}

$topics = get_topics();

// Calculate counts
$total_count = count($topics);
$en_count = 0;
$ar_count = 0;

foreach ($topics as $t) {
    if ($t['lang'] === 'ar') {
        $ar_count++;
    } else {
        $en_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Reader — Dashboard</title>
    <link rel="stylesheet" href="./assets/dashboard.css">
    <!-- FontAwesome for beautiful icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="container">
        <!-- Dashboard Header -->
        <header>
            <div class="logo-section">
                <h1><i class="fa-solid fa-book-open"></i> Knowledge Reader</h1>
                <p>Curate, draft, and enjoy high-fidelity reading resources in English & Arabic</p>
            </div>
            <div>
                <a href="editor.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create New Topic</a>
            </div>
        </header>

        <!-- Display Success Messages -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert-banner">
                <i class="fa-solid fa-circle-check"></i> Topic has been successfully deleted from the database and disk storage.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert-banner">
                <i class="fa-solid fa-circle-check"></i> Topic has been successfully saved and synced.
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Curated Topics</div>
                <div class="stat-val"><?php echo $total_count; ?></div>
            </div>
            <div class="stat-card stat-en">
                <div class="stat-label">English Topics (LTR)</div>
                <div class="stat-val"><?php echo $en_count; ?></div>
            </div>
            <div class="stat-card stat-ar">
                <div class="stat-label">Arabic Topics (RTL)</div>
                <div class="stat-val"><?php echo $ar_count; ?></div>
            </div>
        </section>

        <!-- Controls (Search & Language Filters) -->
        <section class="control-bar">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search topics by title or keyword..." onkeyup="filterTopics()">
            </div>
            <div class="filter-wrapper">
                <button class="filter-btn active" id="filter-all" onclick="setLanguageFilter('all')">All Languages</button>
                <button class="filter-btn" id="filter-en" onclick="setLanguageFilter('en')">English</button>
                <button class="filter-btn" id="filter-ar" onclick="setLanguageFilter('ar')">العربية (Arabic)</button>
            </div>
        </section>

        <!-- Topics Grid -->
        <main class="topics-grid" id="topicsContainer">
            <?php if (empty($topics)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fa-solid fa-box-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; display: block;"></i>
                    <h3>No topics available</h3>
                    <p>Get started by writing your very first reading resource.</p>
                    <a href="editor.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-pencil"></i> Write Topic</a>
                </div>
            <?php else: ?>
                <?php foreach ($topics as $topic): ?>
                    <article class="topic-card" data-title="<?php echo htmlspecialchars(strtolower($topic['title']), ENT_QUOTES, 'UTF-8'); ?>" data-lang="<?php echo $topic['lang']; ?>">
                        <div class="topic-badge-wrapper">
                            <span class="badge badge-<?php echo $topic['lang']; ?>">
                                <?php echo $topic['lang'] === 'ar' ? 'العربية' : 'English'; ?>
                            </span>
                            <span class="topic-time">
                                <i class="fa-regular fa-clock"></i> <?php echo $topic['read_time']; ?> min read
                            </span>
                        </div>
                        <h2 class="topic-title"><?php echo htmlspecialchars($topic['title']); ?></h2>
                        <div class="topic-date">
                            <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($topic['date'])); ?>
                            <span style="float: right;"><i class="fa-solid fa-lines-leaning"></i> <?php echo $topic['word_count']; ?> words</span>
                        </div>
                        <div class="topic-actions">
                            <a href="view.php?slug=<?php echo urlencode($topic['slug']); ?>" class="btn btn-secondary btn-sm" title="Read Topic">
                                <i class="fa-solid fa-book-open"></i> Read
                            </a>
                            <a href="editor.php?slug=<?php echo urlencode($topic['slug']); ?>" class="btn btn-secondary btn-sm" style="color: var(--warning-color);" title="Edit Topic">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </a>
                            <a href="javascript:void(0)" onclick="confirmDelete('<?php echo htmlspecialchars($topic['slug'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($topic['title'], ENT_QUOTES, 'UTF-8'); ?>')" class="btn btn-danger btn-sm" title="Delete Topic">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Scripting for Snappy Interaction -->
    <script>
        let currentLangFilter = 'all';

        /**
         * Delete confirmation
         */
        function confirmDelete(slug, title) {
            if (confirm(`Are you absolutely sure you want to delete "${title}"?\n\nThis will remove the file from your computer and erase it from the database permanently.`)) {
                window.location.href = `index.php?action=delete&slug=${encodeURIComponent(slug)}`;
            }
        }

        /**
         * Set current language filter
         */
        function setLanguageFilter(lang) {
            currentLangFilter = lang;
            
            // Toggle active state in buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(`filter-${lang}`).classList.add('active');
            
            filterTopics();
        }

        /**
         * Search and Language filter core logic
         */
        function filterTopics() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.topic-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                const lang = card.getAttribute('data-lang');
                
                const matchesSearch = title.includes(query);
                const matchesLang = (currentLangFilter === 'all' || lang === currentLangFilter);
                
                if (matchesSearch && matchesLang) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Handle empty search/filter result state
            let emptyState = document.getElementById('filterEmptyState');
            if (visibleCount === 0 && cards.length > 0) {
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'filterEmptyState';
                    emptyState.className = 'empty-state';
                    emptyState.style.gridColumn = '1 / -1';
                    emptyState.innerHTML = `
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; display: block;"></i>
                        <h3>No matching results</h3>
                        <p>Try refining your search query or language filter.</p>
                    `;
                    document.getElementById('topicsContainer').appendChild(emptyState);
                }
            } else if (emptyState) {
                emptyState.remove();
            }
        }
    </script>
</body>
</html>
