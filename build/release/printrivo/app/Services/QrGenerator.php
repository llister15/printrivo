<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use CodeIgniter\HTTP\Files\UploadedFile;
use enshrined\svgSanitize\Sanitizer;
use RuntimeException;

class QrGenerator
{
    public function generate(string $content): string
    {
        return (new QRCode(new QROptions([
            'version' => Version::AUTO, 'versionMin' => 11, 'versionMax' => 40,
            'eccLevel' => EccLevel::H, 'scale' => 20, 'addQuietzone' => true,
            'outputInterface' => QRGdImagePNG::class, 'outputBase64' => true,
        ])))->render($content);
    }

    public function icons(): array
    {
        $files = glob(FCPATH . 'assets/qr-icons/*.svg') ?: [];
        $names = array_map('basename', $files);
        sort($names);
        return $names;
    }

    public function storeLogo(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 10 * 1024 * 1024) {
            throw new RuntimeException('Upload a valid logo no larger than 10 MB.');
        }
        $mime = $file->getMimeType();
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $svg = null;
        if (strtolower($file->getClientExtension()) === 'svg') {
            $sanitizer = new Sanitizer();
            $sanitizer->removeRemoteReferences(true);
            $svg = $sanitizer->sanitize(file_get_contents($file->getTempName()));
            if ($svg === false || !str_contains($svg, '<svg')) {
                throw new RuntimeException('The SVG logo could not be read.');
            }
            $extension = 'svg';
        } else {
            $size = @getimagesize($file->getTempName());
            if (!isset($extensions[$mime]) || $size === false || $size[0] * $size[1] > 40000000) {
                throw new RuntimeException('Upload a PNG, JPG, WebP, or SVG logo (up to 40 megapixels).');
            }
            $extension = $extensions[$mime];
        }
        $dir = WRITEPATH . 'uploads/qr-logos/';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Logo storage is unavailable.');
        }
        helper('qr_files');
        qr_cleanup_temp_files($dir);
        $name = 'qr-logo-' . bin2hex(random_bytes(16)) . '.' . $extension;
        if ($svg !== null) {
            if (file_put_contents($dir . $name, $svg) === false) {
                throw new RuntimeException('The logo could not be saved.');
            }
        } else {
            $file->move($dir, $name);
        }
        return $name;
    }
}
