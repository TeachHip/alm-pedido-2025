<?php
// includes/data-loader.php - Fixed with section descriptions

function parseSectionsFile($filename) {
    if (!file_exists($filename)) return [];
    $sections = [];
    $sectionData = []; // NEW: Store complete section data
    $content = file_get_contents($filename);
    $lines = explode("\n", $content);
    $currentSection = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if ($line === '[section]') {
            // Save previous section if exists
            if (!empty($currentSection['key'])) {
                $sections[$currentSection['key']] = $currentSection['name'] ?? '';
                $sectionData[$currentSection['key']] = [ // NEW: Store complete data
                    'name' => $currentSection['name'] ?? '',
                    'description' => $currentSection['description'] ?? '',
                    'image' => $currentSection['image'] ?? '',
                    'order' => $currentSection['order'] ?? '',
                    'active' => $currentSection['active'] ?? ''
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
        $sections[$currentSection['key']] = $currentSection['name'] ?? '';
        $sectionData[$currentSection['key']] = [
            'name' => $currentSection['name'] ?? '',
            'description' => $currentSection['description'] ?? '',
            'image' => $currentSection['image'] ?? '',
            'order' => $currentSection['order'] ?? '',
            'active' => $currentSection['active'] ?? ''
        ];
    }

    return ['names' => $sections, 'full' => $sectionData]; // MODIFIED: Return both
}

function parseProductsFile($filename) {
    if (!file_exists($filename)) return ['flat' => [], 'section' => []];

    $productsFlat = [];
    $productsSection = [];
    $content = file_get_contents($filename);

    // Split by --- separator
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
            $section = $productData['section'];

            // Flat array for admin (new system)
            $productsFlat[$productId] = [
                'product_id' => $productId,
                'section' => $section,
                'name' => $productData['name'],
                'price' => floatval($productData['price_member'] ?? $productData['price'] ?? 0),
                'price2' => floatval($productData['price_public'] ?? $productData['price2'] ?? 0),
                'image' => $productData['image'] ?? '',
                'description' => $productData['description'] ?? '',
                'visible' => ($productData['visible'] ?? '1') === '1'
            ];

            // Section-based array for frontend (backward compatibility) - WITH product_id
            if (!isset($productsSection[$section])) {
                $productsSection[$section] = [];
            }

            $productsSection[$section][] = [
                'product_id' => $productId,
                'name' => $productData['name'],
                'price' => floatval($productData['price_member'] ?? $productData['price'] ?? 0),
                'price2' => floatval($productData['price_public'] ?? $productData['price2'] ?? 0),
                'image' => $productData['image'] ?? '',
                'description' => $productData['description'] ?? '',
                'visible' => ($productData['visible'] ?? '1') === '1'
            ];
        }
    }

    return [
        'flat' => $productsFlat,
        'section' => $productsSection
    ];
}

function extractSectionImages($sectionsData) {
    $sectionImages = [];

    // Use the full section data we already have
    if (isset($sectionsData['full'])) {
        foreach ($sectionsData['full'] as $key => $section) {
            if (!empty($section['image'])) {
                $sectionImages[$key] = $section['image'];
            }
        }
    }

    return $sectionImages;
}

// Main data loading function
function loadAppData() {
    // First, try to load from new .data files
    $newData = loadFromDataFiles();
    if ($newData !== false) {
        return $newData;
    }

    // Fall back to the current working system (data.php)
    return loadFromLegacySystem();
}

function loadFromDataFiles() {
    $sectionsFile = dirname(__FILE__) . '/../data/sections.data';
    $productsFile = dirname(__FILE__) . '/../data/products.data';

    // Check if data files exist
    if (!file_exists($sectionsFile) || !file_exists($productsFile)) {
        return false;
    }

    try {
        $sectionsData = parseSectionsFile($sectionsFile); // MODIFIED: Get both names and full data
        $productsData = parseProductsFile($productsFile);
        $sectionImages = extractSectionImages($sectionsData);

        return [
            'sections' => $sectionsData['names'], // For backward compatibility
            'sectionsFull' => $sectionsData['full'], // NEW: Complete section data
            'products' => $productsData['section'], // For frontend compatibility
            'productsFlat' => $productsData['flat'], // For admin new system
            'sectionImages' => $sectionImages
        ];
    } catch (Exception $e) {
        error_log("Data file parsing error: " . $e->getMessage());
        return false;
    }
}

function loadFromLegacySystem() {
    $legacyFile = dirname(__FILE__) . '/../assets/data.php';
    if (file_exists($legacyFile)) {
        include $legacyFile;
        return [
            'sections' => $sections,
            'sectionsFull' => [], // Empty for legacy system
            'products' => $products,
            'productsFlat' => [], // Empty for legacy system
            'sectionImages' => $sectionImages
        ];
    }
    return false;
}

// Helper function for frontend compatibility
if (!function_exists('getProductId')) {
    function getProductId($sectionKey, $productIndex) {
        return "{$sectionKey}_{$productIndex}";
    }
}
?>