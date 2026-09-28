<?php
declare(strict_types=1);

const QR_TEMP_FILE_TTL_SECONDS = 3600;

function qr_cleanup_temp_files(string $dir, int $maxAgeSeconds = QR_TEMP_FILE_TTL_SECONDS): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $deleted = 0;
    $expiresBefore = time() - $maxAgeSeconds;
    $files = scandir($dir);
    if ($files === false) {
        return 0;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file[0] === '.') {
            continue;
        }

        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }

        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
            continue;
        }

        $modifiedAt = filemtime($path);
        if ($modifiedAt !== false && $modifiedAt < $expiresBefore && @unlink($path)) {
            $deleted += 1;
        }
    }

    return $deleted;
}

