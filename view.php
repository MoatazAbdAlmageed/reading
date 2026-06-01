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
    </style>
</head>
<body>
    <!-- Immersive Reading Progress Bar -->
    <div id="progress-bar" class="no-print"></div>

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
        <div>
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
    </div>

    <!-- Rendered Markdown Container -->
    <main id="content">
        <!-- Rendered HTML will be injected dynamically -->
    </main>

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
         * Reading Progress Indicator script
         */
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('progress-bar').style.width = scrolled + '%';
        });
    </script>
</body>
</html>
