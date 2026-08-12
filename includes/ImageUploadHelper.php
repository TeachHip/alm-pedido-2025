<?php
/**
 * Image Upload Helper
 * Validates and processes an uploaded listing image (product or section):
 * enforces format/size/dimension limits, center-crops to a square if not
 * already one, resizes to a fixed 800x800, and saves it under a
 * collision-safe filename into the caller's target folder (products use
 * primgs/, sections use grimgs/ -- see AI/CHANGELOG.md).
 */

define('LISTING_IMAGE_MIN_DIMENSION', 600);
define('LISTING_IMAGE_MAX_BYTES', 2 * 1024 * 1024);
define('LISTING_IMAGE_OUTPUT_SIZE', 800);

const LISTING_IMAGE_EXTENSIONS_BY_TYPE = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * Process an uploaded listing image ($_FILES['image']) into $targetDir
 * (relative to the project root, e.g. 'primgs' or 'grimgs'). Returns the
 * new filename (relative to that folder) on success, or null if no file
 * was uploaded (caller should then keep whatever image the listing already
 * had -- neither products nor sections are required to have one). Throws
 * Exception with a user-facing Spanish message on any validation failure.
 */
function processListingImageUpload($file, $targetDir = 'primgs') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen');
    }

    if ($file['size'] > LISTING_IMAGE_MAX_BYTES) {
        throw new Exception('La imagen supera el tamaño máximo de 2MB');
    }

    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('El archivo no es una imagen válida');
    }

    [$width, $height, $type] = $imageInfo;

    if ($width < LISTING_IMAGE_MIN_DIMENSION || $height < LISTING_IMAGE_MIN_DIMENSION) {
        throw new Exception('La imagen debe medir al menos ' . LISTING_IMAGE_MIN_DIMENSION . 'x' . LISTING_IMAGE_MIN_DIMENSION . 'px');
    }

    if (!isset(LISTING_IMAGE_EXTENSIONS_BY_TYPE[$type])) {
        throw new Exception('Formato de imagen no soportado (usa JPG, PNG, GIF o WEBP)');
    }
    $extension = LISTING_IMAGE_EXTENSIONS_BY_TYPE[$type];

    $source = loadImageResourceByType($file['tmp_name'], $type);

    // Center-crop to a square using the smaller dimension, then resize to
    // the fixed output size.
    $squareSize = min($width, $height);
    $srcX = (int) (($width - $squareSize) / 2);
    $srcY = (int) (($height - $squareSize) / 2);

    $output = imagecreatetruecolor(LISTING_IMAGE_OUTPUT_SIZE, LISTING_IMAGE_OUTPUT_SIZE);
    // Preserve transparency for PNG/WEBP/GIF sources (harmless no-op for JPEG).
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefilledrectangle($output, 0, 0, LISTING_IMAGE_OUTPUT_SIZE, LISTING_IMAGE_OUTPUT_SIZE, $transparent);

    imagecopyresampled(
        $output, $source,
        0, 0, $srcX, $srcY,
        LISTING_IMAGE_OUTPUT_SIZE, LISTING_IMAGE_OUTPUT_SIZE,
        $squareSize, $squareSize
    );
    imagedestroy($source);

    $filename = generateListingImageFilename($file['name'], $extension, $targetDir);
    saveImageResourceByType($output, dirname(__FILE__) . '/../' . $targetDir . '/' . $filename, $type);
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
 * on every upload (so replacing a listing's photo can never serve a stale
 * cached copy under the old name -- same reasoning as APP_VERSION_SAFE for
 * JS/CSS).
 */
function generateListingImageFilename($originalName, $extension, $targetDir = 'primgs') {
    $base = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'imagen';
    }
    $base = substr($base, 0, 60);

    $dir = dirname(__FILE__) . '/../' . $targetDir . '/';
    do {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 5);
        $filename = "{$base}-{$suffix}.{$extension}";
    } while (file_exists($dir . $filename));

    return $filename;
}

/**
 * Delete a listing image file from $targetDir, if it exists. Safe to call
 * with null/empty (no-op) -- neither products nor sections require one.
 */
function deleteListingImage($filename, $targetDir = 'primgs') {
    if (empty($filename)) {
        return;
    }
    $path = dirname(__FILE__) . '/../' . $targetDir . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}
