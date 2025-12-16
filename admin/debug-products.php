<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

include dirname(__FILE__) . '/../includes/data-loader.php';
$appData = loadAppData();

echo "<h3>Product Count Debug</h3>";

if ($appData === false) {
    echo "Data loading failed!";
    exit;
}

$products = $appData['products'];

echo "Total sections: " . count($products) . "<br>";

foreach ($products as $sectionKey => $sectionProducts) {
    echo "Section '$sectionKey' has " . count($sectionProducts) . " products:<br>";
    foreach ($sectionProducts as $index => $product) {
        echo "- [$index] " . $product['name'] . "<br>";
    }
    echo "<br>";
}

// Also check the original data.php for comparison
echo "<h3>Original data.php products (for comparison):</h3>";
include dirname(__FILE__) . '/../assets/data.php';
echo "Bakery section: " . count($products['bakery']) . " products<br>";
echo "Produce section: " . count($products['produce']) . " products<br>";

foreach ($products as $sectionKey => $sectionProducts) {
    echo "Section '$sectionKey' has " . count($sectionProducts) . " products:<br>";
    foreach ($sectionProducts as $index => $product) {
        echo "- [$index] " . $product['name'] . "<br>";
    }
    echo "<br>";
}
?>