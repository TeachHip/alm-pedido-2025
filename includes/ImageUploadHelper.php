<?php
/**
 * Image Upload Helper
 * Validates and processes a product-image upload: enforces format/size/
 * dimension limits, center-crops to a square if not already one, resizes to
 * a fixed 800x800, and saves it into primgs/ under a collision-safe
 * filename. See AI/CHANGELOG.md.
 */

define('PRODUCT_IMAGE_MIN_DIMENSION', 600);
define('PRODUCT_IMAGE_MAX_BYTES', 2 * 1024 * 1024);
define('PRODUCT_IMAGE_OUTPUT_SIZE', 800);

const PRODUCT_IMAGE_EXTENSIONS_BY_TYPE = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * Process an uploaded product image ($_FILES['image']). Returns the new
 * filename (relative to primgs/) on success, or null if no file was
 * uploaded (caller should then keep whatever image the product already
 * had -- a product isn't required to have one). Throws Exception with a
 * user-facing Spanish message on any validation failure.
 */
function processProductImageUpload($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen');
    }

    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        throw new Exception('La imagen supera el tamaño máximo de 2MB');
    }

    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('El archivo no es una imagen válida');
    }

    [$width, $height, $type] = $imageInfo;

    if ($width < PRODUCT_IMAGE_MIN_DIMENSION || $height < PRODUCT_IMAGE_MIN_DIMENSION) {
        throw new Exception('La imagen debe medir al menos ' . PRODUCT_IMAGE_MIN_DIMENSION . 'x' . PRODUCT_IMAGE_MIN_DIMENSION . 'px');
    }

    if (!isset(PRODUCT_IMAGE_EXTENSIONS_BY_TYPE[$type])) {
        throw new Exception('Formato de imagen no soportado (usa JPG, PNG, GIF o WEBP)');
    }
    $extension = PRODUCT_IMAGE_EXTENSIONS_BY_TYPE[$type];

    $source = loadImageResourceByType($file['tmp_name'], $type);

    // Center-crop to a square using the smaller dimension, then resize to
    // the fixed output size.
    $squareSize = min($width, $height);
    $srcX = (int) (($width - $squareSize) / 2);
    $srcY = (int) (($height - $squareSize) / 2);

    $output = imagecreatetruecolor(PRODUCT_IMAGE_OUTPUT_SIZE, PRODUCT_IMAGE_OUTPUT_SIZE);
    // Preserve transparency for PNG/WEBP/GIF sources (harmless no-op for JPEG).
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefilledrectangle($output, 0, 0, PRODUCT_IMAGE_OUTPUT_SIZE, PRODUCT_IMAGE_OUTPUT_SIZE, $transparent);

    imagecopyresampled(
        $output, $source,
        0, 0, $srcX, $srcY,
        PRODUCT_IMAGE_OUTPUT_SIZE, PRODUCT_IMAGE_OUTPUT_SIZE,
        $squareSize, $squareSize
    );
    imagedestroy($source);

    $filename = generateProductImageFilename($file['name'], $extension);
    saveImageResourceByType($output, dirname(__FILE__) . '/../primgs/' . $filename, $type);
    imagedestroy($output);

    return $filename;
}

function loadImageResourceByType($path, $type) {
    switch ($type) {
        case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG: return imagecreatefrompng($path);
        case IMAGETYPE_GIF: return imagecreatefromgif($path);
        case IMAGETYPE_WEBP: return imagecreatefromwebp($path);
    }
    throw new Exception('Formato de imagen no soportado');
}

function saveImageResourceByType($image, $destPath, $type) {
    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($image, $destPath, 85); break;
        case IMAGETYPE_PNG: imagepng($image, $destPath, 6); break;
        case IMAGETYPE_GIF: imagegif($image, $destPath); break;
        case IMAGETYPE_WEBP: imagewebp($image, $destPath, 85); break;
    }
}

/**
 * '{sanitized-original-name}-{random5}.{ext}' -- keeps the uploaded file's
 * name recognizable while guaranteeing a fresh, never-before-seen filename
 * on every upload (so replacing a product's photo can never serve a stale
 * cached copy under the old name -- same reasoning as APP_VERSION_SAFE for
 * JS/CSS).
 */
function generateProductImageFilename($originalName, $extension) {
    $base = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'producto';
    }
    $base = substr($base, 0, 60);

    $primgsDir = dirname(__FILE__) . '/../primgs/';
    do {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 5);
        $filename = "{$base}-{$suffix}.{$extension}";
    } while (file_exists($primgsDir . $filename));

    return $filename;
}

/**
 * Delete a product image file from primgs/, if it exists. Safe to call with
 * null/empty (no-op) -- products aren't required to have an image.
 */
function deleteProductImage($filename) {
    if (empty($filename)) {
        return;
    }
    $path = dirname(__FILE__) . '/../primgs/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}
