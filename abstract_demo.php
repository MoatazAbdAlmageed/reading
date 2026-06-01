<?php
/**
 * PHP OOP — Abstract Class Definition & Usage Demo
 */

// 1. Defining the Abstract Class
abstract class ReadingMaterial {
    // Abstract classes can have standard properties
    protected $title;
    protected $wordCount;

    // Constructor to initialize standard properties
    public function __construct(string $title, int $wordCount) {
        $this->title = $title;
        $this->wordCount = $wordCount;
    }

    // Concrete method (has implementation)
    public function getTitle(): string {
        return $this->title;
    }

    // Concrete method with logic shared by all child classes
    public function getEstimatedReadTime(int $wordsPerMinute = 150): int {
        return max(1, (int)ceil($this->wordCount / $wordsPerMinute));
    }

    // Abstract method: NO implementation allowed here. 
    // Any child class MUST implement this method.
    abstract public function renderSnippet(): string;
}

// 2. Extending the Abstract Class (Child Class A)
class TechnicalArticle extends ReadingMaterial {
    private $primaryLanguage;

    public function __construct(string $title, int $wordCount, string $primaryLanguage) {
        // Call parent constructor
        parent::__construct($title, $wordCount);
        $this->primaryLanguage = $primaryLanguage;
    }

    // Implementing the required abstract method
    public function renderSnippet(): string {
        return "🛠️ [Technical Article in {$this->primaryLanguage}]: '{$this->title}' " .
               "({$this->wordCount} words, ~{$this->getEstimatedReadTime()} min read)";
    }
}

// 3. Extending the Abstract Class (Child Class B)
class ShortStory extends ReadingMaterial {
    private $genre;

    public function __construct(string $title, int $wordCount, string $genre) {
        parent::__construct($title, $wordCount);
        $this->genre = $genre;
    }

    // Implementing the required abstract method
    public function renderSnippet(): string {
        return "📚 [{$this->genre} Story]: '{$this->title}' " .
               "({$this->wordCount} words, read time: ~{$this->getEstimatedReadTime(200)} min at leisure pace)";
    }
}

// --- Execution & Demonstration ---

echo "=== PHP Abstract Class Demonstration ===\n\n";

// NOTE: Trying to instantiate an abstract class directly will cause a fatal error:
// $material = new ReadingMaterial("Generic Title", 1000); // Fatal Error!

// Instantiate Concrete Child Classes
$article = new TechnicalArticle("Understanding JWT Authentication", 1200, "PHP");
$story = new ShortStory("The Antigravity AI Journey", 800, "Sci-Fi");

// Use the Concrete implementations of the abstract method
echo $article->renderSnippet() . "\n";
echo $story->renderSnippet() . "\n\n";

// Call shared concrete methods inherited from the abstract parent
echo "Estimated time to read '{$article->getTitle()}': " . $article->getEstimatedReadTime() . " mins.\n";
echo "Estimated time to read '{$story->getTitle()}': " . $story->getEstimatedReadTime() . " mins.\n";
