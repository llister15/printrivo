<?php

namespace App\Controllers;

use App\Services\QrGenerator;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class QrCode extends BaseController
{
    public function index(): string
    {
        $generator = new QrGenerator();
        $data = [
            'content' => '', 'result' => '', 'error' => '', 'logoPath' => '',
            'selectedLogo' => '', 'useCustomLogo' => false,
            'iconsDirWeb' => base_url('assets/qr-icons') . '/',
            'iconFiles' => $generator->icons(),
        ];
        if ($this->request->is('post')) {
            $content = $this->request->getPost('content');
            $logo = $this->request->getPost('logo_select');
            $data['content'] = is_string($content) ? $content : '';
            $data['selectedLogo'] = is_string($logo) ? $logo : '';
            $data['useCustomLogo'] = !empty($this->request->getPost('custom_logo_checkbox'));
            if ($data['content'] !== '') {
                try {
                    $data['result'] = $generator->generate($data['content']);
                    if ($data['useCustomLogo']) {
                        $file = $this->request->getFile('custom_logo');
                        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                            $name = $generator->storeLogo($file);
                            $session = session();
                            $logos = $session->get('qr_logos') ?? [];
                            $logos = array_filter($logos, static fn ($expiry) => $expiry > time());
                            $logos[$name] = time() + 3600;
                            $session->set('qr_logos', $logos);
                            $data['logoPath'] = site_url('files/qr-logo/' . $name);
                        }
                    } elseif (in_array($data['selectedLogo'], $data['iconFiles'], true)) {
                        $data['logoPath'] = $data['iconsDirWeb'] . $data['selectedLogo'];
                    }
                } catch (Throwable $error) {
                    log_message('error', 'QR generation failed: {message}', ['message' => $error->getMessage()]);
                    $data['error'] = 'Could not generate the QR code. Check the content and use a PNG, JPG, WebP or SVG logo under 10 MB.';
                }
            }
        }
        return view('tools/qr', $data);
    }

    public function logo(string $name): ResponseInterface
    {
        if (!preg_match('/\Aqr-logo-[a-f0-9]{32}\.(png|jpg|webp|svg)\z/', $name, $match)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $logos = session()->get('qr_logos') ?? [];
        $path = WRITEPATH . 'uploads/qr-logos/' . $name;
        if (($logos[$name] ?? 0) <= time() || !is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        return $this->response->setContentType($mime[$match[1]])
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox")
            ->setBody(file_get_contents($path));
    }
}
