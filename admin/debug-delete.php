<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

$section = $_GET['section'] ?? '';
$productId = $_GET['id'] ?? '';

echo "<h3>Debug Delete</h3>";
echo "Section: $section<br>";
echo "Product ID: $productId<br>";

$dataFile = dirname(__FILE__) . '/../data/products.data';
$content = file_get_contents($dataFile);

echo "<h4>Searching for: id: {$section}_{$productId}</h4>";

$blocks = explode("---", $content);
foreach ($blocks as $index => $block) {
    if (strpos($block, "section: $section") !== false) {
        echo "<h4>Block $index (Section: $section):</h4>";
        echo "<pre>" . htmlspecialchars($block) . "</pre>";

        // Check what ID format is actually used
        if (preg_match('/id:\s*(.+)/', $block, $matches)) {
            echo "Actual ID found: " . $matches[1] . "<br>";
        }
    }
}
?>