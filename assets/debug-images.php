<?php
// Debug script to check image paths
include 'data.php';

echo "<h1>Image Path Debug</h1>";
echo "<p>Current working directory: " . getcwd() . "</p>";

foreach ($products as $section => $items) {
    echo "<h2>Section: $section</h2>";
    foreach ($items as $product) {
        $imagePath = $product['image'];
        $fullPath = getcwd() . '/' . $imagePath;
        $exists = file_exists($fullPath);
        $readable = is_readable($fullPath);

        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px;'>";
        echo "<strong>{$product['name']}</strong><br>";
        echo "Image path: $imagePath<br>";
        echo "Full path: $fullPath<br>";
        echo "Exists: " . ($exists ? 'YES' : 'NO') . "<br>";
        echo "Readable: " . ($readable ? 'YES' : 'NO') . "<br>";

        if ($exists && $readable) {
            echo "<img src='$imagePath' style='max-width: 100px; max-height: 100px;'><br>";
        }

        echo "</div>";
    }
}
?>