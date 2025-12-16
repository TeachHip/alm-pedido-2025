<?php
// includes/data-loader.php - Simplified single structure

function parseSectionsFile($filename) {
    if (!file_exists($filename)) return [];

    $sections = [];
    $content = file_get_contents($filename);
    $lines = explode("\n", $content);

    $currentSection = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if ($line === '[section]') {
            // Save previous section if exists
            if (!empty($currentSection['key'])) {
                $sections[$currentSection['key']] = [
                    'name' => $currentSection['name'] ?? '',
                    'image' => $currentSection['image'] ?? ''
                ];
            }
            $currentSection = [];
        } elseif (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $currentSection[trim($key)] = trim($value);
        }
    }

    // Save the last section
    if (!empty($currentSection['key'])) {
        $sections[$currentSection['key']] = [
            'name' => $currentSection['name'] ?? '',
            'image' => $currentSection['image'] ?? ''
        ];
    }

    return $sections;
}

function parseProductsFile($filename) {
    if (!file_exists($filename)) return [];

    $products = [];
    $content = file_get_contents($filename);
    $blocks = explode("---", $content);

    foreach ($blocks as $block) {
        $productData = [];
        $lines = explode("\n", $block);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $productData[trim($key)] = trim($value);
            }
        }

        if (!empty($productData['section']) && !empty($productData['name']) && !empty($productData['product_id'])) {
            $productId = $productData['product_id'];
            
            $products[$productId] = [
                'product_id' => $productId,
                'section' => $productData['section'],
                'name' => $productData['name'],
                'price' => floatval($productData['price_member'] ?? $productData['price'] ?? 0),
                'price2' => floatval($productData['price_public'] ?? $productData['price2'] ?? 0),
                'image' => $productData['image'] ?? '',
                'description' => $productData['description'] ?? '',
                'visible' => ($productData['visible'] ?? '1') === '1'
            ];
        }
    }

    return $products;
}

function loadAppData() {
    $sectionsFile = dirname(__FILE__) . '/../data/sections.data';
    $productsFile = dirname(__FILE__) . '/../data/products.data';

    if (!file_exists($sectionsFile) || !file_exists($productsFile)) {
        return ['sections' => [], 'products' => []];
    }

    try {
        $sections = parseSectionsFile($sectionsFile);
        $products = parseProductsFile($productsFile);
        
        return [
            'sections' => $sections,
            'products' => $products
        ];
    } catch (Exception $e) {
        error_log("Data file parsing error: " . $e->getMessage());
        return ['sections' => [], 'products' => []];
    }
}

// Helper function to get products by section (for frontend)
function getProductsBySection($products) {
    $bySection = [];
    foreach ($products as $productId => $product) {
        $section = $product['section'];
        if (!isset($bySection[$section])) {
            $bySection[$section] = [];
        }
        $bySection[$section][] = $product;
    }
    return $bySection;
}

// Helper function to get product by ID
function getProductById($products, $productId) {
    return $products[$productId] ?? null;
}
?>