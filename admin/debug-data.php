<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

echo "<h3>Debug Data Files</h3>";

// Check if files exist
$sectionsFile = '../data/sections.data';
$productsFile = '../data/products.data';

echo "Sections file exists: " . (file_exists($sectionsFile) ? 'YES' : 'NO') . "<br>";
echo "Products file exists: " . (file_exists($productsFile) ? 'YES' : 'NO') . "<br>";

if (file_exists($sectionsFile)) {
    echo "<h4>Sections file content:</h4>";
    echo "<pre>" . htmlspecialchars(file_get_contents($sectionsFile)) . "</pre>";
}

if (file_exists($productsFile)) {
    echo "<h4>Products file content (first 500 chars):</h4>";
    echo "<pre>" . htmlspecialchars(substr(file_get_contents($productsFile), 0, 500)) . "</pre>";
}

// Test the parser directly
echo "<h4>Testing parser functions:</h4>";
include dirname(__FILE__) . '/../includes/data-loader.php';

try {
    $sections = parseSectionsFile($sectionsFile);
    echo "Sections parsed: " . count($sections) . "<br>";
    var_dump($sections);

    $products = parseProductsFile($productsFile);
    echo "Products parsed: " . count($products) . "<br>";
    var_dump($products);
} catch (Exception $e) {
    echo "Parser error: " . $e->getMessage();
}
?>