<?php
/**
 * Reading System — Immersive Reader
 */

require_once __DIR__ . '/includes/functions.php';

// Get slug from URL
if (!isset($_GET['slug'])) {
    header("Location: index.php");
    exit;
}

$slug = $_GET['slug'];
$topic = get_topic($slug);

if (!$topic) {
    header("Location: index.php");
    exit;
}

$title = $topic['metadata']['title'];
$lang = $topic['metadata']['lang'];
$date = $topic['metadata']['date'];
$read_time = $topic['metadata']['read_time'];
$word_count = $topic['metadata']['word_count'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Dynamic SEO Optimization -->
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="Read <?php echo htmlspecialchars($title); ?> on Knowledge Reader. An educational resource curated in <?php echo $lang === 'ar' ? 'Arabic' : 'English'; ?>.">
    <link rel="icon" type="image/svg+xml" href="./assets/favicon.svg">
    
    <!-- Load Core Stylesheets as requested -->
    <link rel="stylesheet" href="./assets/style.css">
    <?php if ($lang === 'ar'): ?>
        <link rel="stylesheet" href="./assets/rtl.css">
    <?php endif; ?>

    <!-- Load Google Fonts for outstanding typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Outfit:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Noto+Serif+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons for reader bar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PrismJS Theme for beautiful syntax-highlighted code blocks -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">

    <!-- Scoped styles to style the navigation bar without inheriting the huge global body font size -->
    <style>
        .reader-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--reading-surface, #181c27);
            border: 1px solid var(--reading-border, #2a2f3e);
            border-radius: 12px;
            padding: 0.6rem 1.2rem;
            margin-bottom: 2.5rem;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .reader-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--reading-text, #d4d8e8);
            text-decoration: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            background-color: transparent;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .reader-btn:hover {
            background-color: var(--reading-border, #2a2f3e);
            color: var(--reading-text-accent, #7c9ef5);
        }
        
        .reader-btn-accent {
            color: var(--reading-link, #7c9ef5);
        }

        .reader-btn-accent:hover {
            background-color: rgba(124, 158, 245, 0.1);
            color: var(--reading-link-hover, #a8bfff);
        }
        
        .metadata-pill-box {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--reading-text-muted, #7a8099);
            margin-top: -1.5rem;
            margin-bottom: 2rem;
            font-family: 'Outfit', sans-serif;
            align-items: center;
        }

        .metadata-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background-color: var(--reading-surface, #181c27);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            border: 1px solid var(--reading-border, #2a2f3e);
        }

        /* Reading scroll progress indicator */
        #progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--reading-text-accent, #7c9ef5) 0%, var(--reading-link-hover, #a8bfff) 100%);
            width: 0%;
            z-index: 9999;
            transition: width 0.1s ease;
        }

        /* Responsive overrides */
        @media (max-width: 600px) {
            .reader-header {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
            }
            .metadata-pill-box {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
        }

        /* Print formatting */
        @media print {
            .reader-header, #progress-bar, .no-print {
                display: none !important;
            }
        }

        /* Override style.css body constraints */
        html, body {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: clip !important; /* clip prevents scrollbar but preserves position: sticky */
        }

        /* Responsive HR */
        hr {
            max-width: 100%;
            overflow: hidden;
        }

        /* TOC Sidebar Layout */
        .reader-layout {
            display: flex;
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            padding: 2rem 1rem;
        }

        .toc-sidebar {
            width: 250px;
            flex-shrink: 0;
            position: sticky;
            top: 2rem;
            height: calc(100vh - 4rem);
            overflow-y: auto;
            font-family: 'Outfit', sans-serif;
            padding-right: 1rem;
            display: none; /* Shown by JS if headings exist */
        }
        
        .toc-sidebar::-webkit-scrollbar {
            width: 4px;
        }
        .toc-sidebar::-webkit-scrollbar-thumb {
            background-color: var(--reading-border, #2a2f3e);
            border-radius: 4px;
        }

        .toc-sidebar h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--reading-text-muted, #7a8099);
            margin-bottom: 1rem;
        }

        .toc-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .toc-link {
            display: block;
            padding: 0.35rem 0;
            color: var(--reading-text, #d4d8e8);
            text-decoration: none;
            font-size: 0.9rem;
            line-height: 1.4;
            transition: color 0.2s;
            border-left: 2px solid transparent;
            padding-left: 1rem;
            margin-bottom: 0.25rem;
        }

        .toc-link:hover {
            color: var(--reading-text-accent, #7c9ef5);
        }

        .toc-link.active {
            color: var(--reading-text-accent, #7c9ef5);
            border-left-color: var(--reading-text-accent, #7c9ef5);
            font-weight: 600;
        }

        .toc-link.depth-1 { padding-left: 1rem; font-weight: 500; }
        .toc-link.depth-2 { padding-left: 1rem; }
        .toc-link.depth-3 { padding-left: 2rem; font-size: 0.85rem; }
        .toc-link.depth-4 { padding-left: 3rem; font-size: 0.85rem; }

        .reader-main {
            flex: 1;
            min-width: 0;
            max-width: 75ch; /* Keeps reading line length optimal */
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .reader-layout {
                flex-direction: column;
                gap: 1rem;
                padding: 1.5rem 1rem;
            }
            .toc-sidebar {
                position: static;
                width: 100%;
                height: auto;
                max-height: 250px;
                border-bottom: 1px solid var(--reading-border, #2a2f3e);
                margin-bottom: 1rem;
                padding-bottom: 1rem;
            }
            .reader-header {
                flex-wrap: wrap;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Immersive Reading Progress Bar -->
    <div id="progress-bar" class="no-print"></div>

    <!-- Toast Notification for Progress Recovery -->
    <div id="toast-notification" class="no-print" style="position: fixed; bottom: 2rem; right: 2rem; background-color: var(--reading-surface, #181c27); border: 1px solid var(--reading-text-accent, #7c9ef5); color: var(--reading-text, #d4d8e8); padding: 0.75rem 1.25rem; border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 0.9rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3); transform: translateY(150%); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 10000;">
        <i class="fa-solid fa-circle-check" style="color: var(--reading-text-accent, #7c9ef5);"></i>
        <span id="toast-message">Resumed progress</span>
        <button onclick="resetProgress()" style="background: none; border: none; color: var(--reading-link, #7c9ef5); cursor: pointer; text-decoration: underline; font-family: inherit; font-size: 0.85rem; margin-left: 0.5rem; padding: 0; font-weight: 600;">Restart</button>
    </div>

    <div class="reader-layout">
        <!-- Sidebar TOC -->
        <aside class="toc-sidebar no-print" id="tocSidebar">
            <h3>Table of Contents</h3>
            <div id="tocNav" class="toc-nav"></div>
        </aside>

        <!-- Main Content Area -->
        <div class="reader-main">
            <!-- Reader Header Toolbar -->
            <div class="reader-header no-print">
                <div>
                    <a href="index.php" class="reader-btn">
                        <i class="fa-solid fa-arrow-left"></i> Dashboard
                    </a>
                </div>
                <div style="font-family: 'Outfit', sans-serif; font-size: 0.85rem; color: var(--reading-text-muted);">
                    <i class="fa-solid fa-book-open"></i> Immersive Mode
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <button id="toggleSidebarBtn" onclick="toggleSidebar()" class="reader-btn" style="display: none; cursor: pointer; border: none; font-family: inherit; font-size: inherit;" title="Toggle Table of Contents">
                        <i class="fa-solid fa-list-ul"></i>
                    </button>
                    <a href="editor.php?slug=<?php echo urlencode($slug); ?>" class="reader-btn reader-btn-accent">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Topic
                    </a>
                </div>
            </div>

            <!-- Article Header -->
            <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($title); ?></h1>

            <!-- Metadata Row -->
            <div class="metadata-pill-box">
                <span class="metadata-pill">
                    <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($date)); ?>
                </span>
                <span class="metadata-pill">
                    <i class="fa-regular fa-clock"></i> <?php echo $read_time; ?> min read
                </span>
                <span class="metadata-pill">
                    <i class="fa-solid fa-lines-leaning"></i> <?php echo $word_count; ?> words
                </span>
                <span class="metadata-pill">
                    <i class="fa-solid fa-globe"></i> <?php echo $lang === 'ar' ? 'العربية' : 'English'; ?>
                </span>
                <?php if (!empty($topic['metadata']['categories'])): ?>
                    <?php foreach ($topic['metadata']['categories'] as $cat): ?>
                        <span class="metadata-pill" style="border-color: rgba(124, 158, 245, 0.4); color: var(--reading-link, #7c9ef5); font-weight: 600;">
                            <i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($cat['name']); ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Rendered Markdown Container -->
            <main id="content">
                <!-- Rendered HTML will be injected dynamically -->
            </main>
        </div>
    </div>

    <!-- Markdown Parser and Highlight Engine -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- Prism Syntax Highlighting Core -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <!-- Prism Languages Support -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup-templating.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>

    <script>
        const slug = <?php echo json_encode($slug); ?>;
        const dbProgress = <?php echo json_encode($topic['metadata']['reading_progress'] ?? 0); ?>;
        
        // Inject safely escaped raw markdown from PHP directly into JS
        const rawMarkdown = <?php echo json_encode($topic['markdown']); ?>;
        
        // Render Markdown using marked.js
        marked.setOptions({
            breaks: true,
            gfm: true
        });
        
        document.getElementById('content').innerHTML = marked.parse(rawMarkdown);

        // Highlight code blocks with PrismJS after content loading
        Prism.highlightAll();

        /**
         * Generate Table of Contents
         */
        function generateTOC() {
            const content = document.getElementById('content');
            const headings = content.querySelectorAll('h1, h2, h3, h4');
            const tocNav = document.getElementById('tocNav');
            const tocSidebar = document.getElementById('tocSidebar');
            
            if (headings.length === 0) return;
            
            tocSidebar.style.display = 'block';
            const toggleBtn = document.getElementById('toggleSidebarBtn');
            if (toggleBtn) toggleBtn.style.display = 'inline-flex';
            
            let html = '';
            
            headings.forEach((heading, index) => {
                if (!heading.id) {
                    heading.id = 'heading-' + index + '-' + heading.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                }
                
                const level = parseInt(heading.tagName.substring(1));
                const depthClass = 'depth-' + level;
                
                html += `<a href="#${heading.id}" class="toc-link ${depthClass}" data-target="${heading.id}">${heading.textContent}</a>`;
            });
            
            tocNav.innerHTML = html;
            
            // Smooth scrolling for TOC links
            document.querySelectorAll('.toc-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.getBoundingClientRect().top + window.scrollY - 20,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        }
        
        generateTOC();

        function toggleSidebar() {
            const sidebar = document.getElementById('tocSidebar');
            if (!sidebar) return;
            
            if (sidebar.style.display === 'none' || sidebar.style.display === '') {
                sidebar.style.display = 'block';
            } else {
                sidebar.style.display = 'none';
            }
        }

        /**
         * Reading Progress Indicator and Storage script
         */
        let isScrolling;
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = height > 0 ? (winScroll / height) * 100 : 0;
            
            document.getElementById('progress-bar').style.width = scrolled + '%';
            
            // Highlight active TOC item
            const headings = Array.from(document.getElementById('content').querySelectorAll('h1, h2, h3, h4'));
            let currentId = null;
            
            if (headings.length > 0) {
                currentId = headings[0].id; // default
                
                for (let i = headings.length - 1; i >= 0; i--) {
                    const rect = headings[i].getBoundingClientRect();
                    if (rect.top <= 150) {
                        currentId = headings[i].id;
                        break;
                    }
                }
                
                if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 10) {
                    currentId = headings[headings.length - 1].id;
                }
                
                document.querySelectorAll('.toc-link').forEach(link => link.classList.remove('active'));
                const activeLink = document.querySelector(`.toc-link[data-target="${currentId}"]`);
                if (activeLink) activeLink.classList.add('active');
            }
            
            // Debounce saving progress to localStorage and DB
            window.clearTimeout(isScrolling);
            isScrolling = setTimeout(() => {
                const prog = scrolled.toFixed(1);
                localStorage.setItem('reading_progress_' + slug, prog);
                
                fetch('api_save_progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'slug=' + encodeURIComponent(slug) + '&progress=' + encodeURIComponent(prog)
                }).catch(err => console.error(err));
            }, 500);
        });

        // Restore saved reading progress
        window.addEventListener('load', () => {
            let savedProgress = localStorage.getItem('reading_progress_' + slug);
            let progress = 0;
            if (savedProgress) {
                progress = parseFloat(savedProgress);
            }
            if (dbProgress > progress) {
                progress = parseFloat(dbProgress);
            }
            
                if (progress > 1 && progress < 99) { // Only restore if significant progress and not completed
                    // Wait for fonts & rendering
                    setTimeout(() => {
                        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                        const targetScroll = (progress / 100) * scrollHeight;
                        window.scrollTo({
                            top: targetScroll,
                            behavior: 'smooth'
                        });
                        
                        showToast(`Resumed from ${Math.round(progress)}% progress`);
                    }, 300);
                }
        });

        function showToast(message) {
            const toast = document.getElementById('toast-notification');
            document.getElementById('toast-message').textContent = message;
            toast.style.transform = 'translateY(0)';
            
            // Auto-hide after 4 seconds
            setTimeout(() => {
                toast.style.transform = 'translateY(150%)';
            }, 4000);
        }

        function resetProgress() {
            localStorage.removeItem('reading_progress_' + slug);
            
            fetch('api_save_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'slug=' + encodeURIComponent(slug) + '&progress=0'
            }).catch(err => console.error(err));

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            document.getElementById('toast-notification').style.transform = 'translateY(150%)';
        }
    </script>
</body>
</html>
