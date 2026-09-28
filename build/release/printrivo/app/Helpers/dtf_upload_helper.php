<?php
declare(strict_types=1);

function dtf_upload_error(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Image is larger than the server upload limit of ' . ini_get('upload_max_filesize') . '.';
        case UPLOAD_ERR_PARTIAL:
            return 'Image upload was interrupted.';
        case UPLOAD_ERR_NO_FILE:
            return 'No image was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server upload temp folder is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server could not write the uploaded image.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the upload.';
        default:
            return 'Image upload failed.';
    }
}

function dtf_size_to_bytes(string $size): int
{
    $size = trim($size);
    if ($size === '') {
        return 0;
    }

    $unit = strtolower($size[strlen($size) - 1]);
    $value = (float) $size;

    switch ($unit) {
        case 'g':
            return (int) ($value * 1024 * 1024 * 1024);
        case 'm':
            return (int) ($value * 1024 * 1024);
        case 'k':
            return (int) ($value * 1024);
        default:
            return (int) $value;
    }
}

function dtf_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
    }

    if ($bytes >= 1024 * 1024) {
        return round($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' bytes';
}

function dtf_upload_limits(): array
{
    return [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'content_length' => isset($_SERVER['CONTENT_LENGTH'])
            ? dtf_format_bytes((int) $_SERVER['CONTENT_LENGTH'])
            : null,
    ];
}
