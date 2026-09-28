<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class GangSheet extends BaseController
{
    public function index(): string
    {
        return view('tools/gang_sheet');
    }

    public function legacyIndex(): ResponseInterface
    {
        return redirect()->to(site_url('tools/gang-sheet'));
    }

    public function inspect(): ResponseInterface
    {
        helper(['dtf_image', 'dtf_upload']);
        ini_set('serialize_precision', '-1');
        $file = $this->request->getFile('image');
        if ($file === null) {
            $length = (int) $this->request->getServer('CONTENT_LENGTH');
            $limit = dtf_size_to_bytes((string) ini_get('post_max_size'));
            return $this->uploadError($limit > 0 && $length > $limit ? 413 : 400, 'No image was uploaded. Check the server upload limit.');
        }
        if (!$file->isValid()) {
            return $this->uploadError(400, dtf_upload_error($file->getError()));
        }
        if ($file->getSize() > 500 * 1024 * 1024) {
            return $this->uploadError(413, 'Image is larger than the 500 MB limit.');
        }
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return $this->uploadError(415, 'Upload a JPG, PNG, WebP, or GIF image.');
        }
        try {
            $details = dtf_inspect_image($file->getTempName(), basename($file->getClientName()));
            return $this->response->setJSON(['ok' => true, 'image' => $details]);
        } catch (Throwable $error) {
            log_message('error', 'DTF image inspection failed: {message}', ['message' => $error->getMessage()]);
            return $this->uploadError(422, 'The uploaded file is not a readable image.');
        }
    }

    public function uploadMethodNotAllowed(): ResponseInterface
    {
        helper('dtf_upload');
        return $this->uploadError(405, 'Use POST to upload an image.')->setHeader('Allow', 'POST');
    }

    private function uploadError(int $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'ok' => false, 'error' => $message, 'limits' => dtf_upload_limits(),
        ]);
    }
}
