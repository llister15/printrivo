<?php
declare(strict_types=1);

const DTF_ASSUMED_DPI = 300.0;
const DTF_TEMP_FILE_TTL_SECONDS = 600;

function dtf_cleanup_temp_uploads(string $uploadDir, int $maxAgeSeconds = DTF_TEMP_FILE_TTL_SECONDS): int
{
    if (!is_dir($uploadDir)) {
        return 0;
    }

    $deleted = 0;
    $expiresBefore = time() - $maxAgeSeconds;
    $files = scandir($uploadDir);
    if ($files === false) {
        return 0;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file[0] === '.') {
            continue;
        }

        $path = $uploadDir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }

        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'img'], true)) {
            continue;
        }

        $modifiedAt = filemtime($path);
        if ($modifiedAt !== false && $modifiedAt < $expiresBefore && @unlink($path)) {
            $deleted += 1;
        }
    }

    return $deleted;
}

function dtf_inspect_image(string $path, string $originalName): array
{
    $imageSize = @getimagesize($path);
    if ($imageSize === false) {
        throw new RuntimeException('The uploaded file is not a readable image.');
    }

    [$widthPx, $heightPx] = $imageSize;
    $mime = $imageSize['mime'] ?? mime_content_type($path) ?: 'application/octet-stream';
    $dpiResult = dtf_read_dpi($path, $mime);
    $dpi = $dpiResult['dpi'] ?? DTF_ASSUMED_DPI;
    $dpiMissing = $dpiResult['dpi'] === null;
    $warnings = [];

    if ($dpiMissing) {
        $warnings[] = 'DPI metadata missing. Calculated using 300 DPI assumption.';
    } elseif ($dpi < 299.5) {
        $warnings[] = 'DPI is below 300.';
    }

    $printWidth = $widthPx / $dpi;
    $printHeight = $heightPx / $dpi;
    $squareInches = $printWidth * $printHeight;

    return [
        'name' => $originalName,
        'mime' => $mime,
        'widthPx' => $widthPx,
        'heightPx' => $heightPx,
        'dpi' => round($dpi, 2),
        'dpiSource' => $dpiResult['source'],
        'dpiMissing' => $dpiMissing,
        'printWidth' => round($printWidth, 3),
        'printHeight' => round($printHeight, 3),
        'squareInches' => round($squareInches, 3),
        'warnings' => $warnings,
    ];
}

function dtf_read_dpi(string $path, string $mime): array
{
    if (extension_loaded('imagick')) {
        $imagickDpi = dtf_read_imagick_dpi($path);
        if ($imagickDpi !== null) {
            return ['dpi' => $imagickDpi, 'source' => 'imagick'];
        }
    }

    if ($mime === 'image/jpeg') {
        $jpegDpi = dtf_read_jpeg_exif_dpi($path);
        if ($jpegDpi !== null) {
            return ['dpi' => $jpegDpi, 'source' => 'exif'];
        }
    }

    if ($mime === 'image/png') {
        $pngDpi = dtf_read_png_dpi($path);
        if ($pngDpi !== null) {
            return ['dpi' => $pngDpi, 'source' => 'png-pHYs'];
        }
    }

    return ['dpi' => null, 'source' => 'assumed'];
}

function dtf_read_imagick_dpi(string $path): ?float
{
    try {
        $image = new Imagick($path);
        $resolution = $image->getImageResolution();
        $units = $image->getImageUnits();
        $image->clear();
        $image->destroy();

        $x = isset($resolution['x']) ? (float) $resolution['x'] : 0.0;
        $y = isset($resolution['y']) ? (float) $resolution['y'] : 0.0;
        if ($x <= 0 && $y <= 0) {
            return null;
        }

        $dpi = $x > 0 && $y > 0 ? min($x, $y) : max($x, $y);
        if (defined('Imagick::RESOLUTION_PIXELSPERCENTIMETER') && $units === Imagick::RESOLUTION_PIXELSPERCENTIMETER) {
            $dpi *= 2.54;
        }

        return $dpi > 0 ? $dpi : null;
    } catch (Throwable $error) {
        return null;
    }
}

function dtf_read_jpeg_exif_dpi(string $path): ?float
{
    if (!function_exists('exif_read_data')) {
        return null;
    }

    $exif = @exif_read_data($path);
    if (!is_array($exif)) {
        return null;
    }

    $x = dtf_rational_to_float($exif['XResolution'] ?? null);
    $y = dtf_rational_to_float($exif['YResolution'] ?? null);
    if (($x === null || $x <= 0) && ($y === null || $y <= 0)) {
        return null;
    }

    $dpi = $x !== null && $y !== null ? min($x, $y) : (float) ($x ?? $y);
    $unit = (int) ($exif['ResolutionUnit'] ?? 2);

    if ($unit === 3) {
        $dpi *= 2.54;
    } elseif ($unit !== 2) {
        return null;
    }

    return $dpi > 0 ? $dpi : null;
}

function dtf_read_png_dpi(string $path): ?float
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    $signature = fread($handle, 8);
    if ($signature !== "\x89PNG\r\n\x1a\n") {
        fclose($handle);
        return null;
    }

    while (!feof($handle)) {
        $lengthBytes = fread($handle, 4);
        if (strlen($lengthBytes) !== 4) {
            break;
        }

        $length = unpack('N', $lengthBytes)[1];
        $type = fread($handle, 4);
        if (strlen($type) !== 4) {
            break;
        }

        if ($type === 'pHYs') {
            $data = fread($handle, $length);
            fclose($handle);

            if (strlen($data) < 9) {
                return null;
            }

            $parts = unpack('Nx/Ny/Cunit', $data);
            if (($parts['unit'] ?? 0) !== 1) {
                return null;
            }

            $xDpi = (float) $parts['x'] * 0.0254;
            $yDpi = (float) $parts['y'] * 0.0254;

            return min($xDpi, $yDpi);
        }

        fseek($handle, $length + 4, SEEK_CUR);
    }

    fclose($handle);
    return null;
}

function dtf_rational_to_float($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    if (!is_string($value) || !str_contains($value, '/')) {
        return null;
    }

    [$numerator, $denominator] = array_map('trim', explode('/', $value, 2));
    if (!is_numeric($numerator) || !is_numeric($denominator) || (float) $denominator === 0.0) {
        return null;
    }

    return (float) $numerator / (float) $denominator;
}
