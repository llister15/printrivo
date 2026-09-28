<?php
namespace App\Controllers;

class Docs extends BaseController
{
    public function index(): string { return $this->page('index', 'Documentation'); }
    public function gangSheet(): string { return $this->page('gang-sheet', 'Printrivo Gang Sheet Builder'); }
    public function imageStudio(): string { return $this->page('image-studio', 'Printrivo Image Studio'); }
    public function qrGenerator(): string { return $this->page('qr-generator', 'Printrivo QR Generator'); }

    private function page(string $page, string $title): string
    {
        return view('docs/page', ['page' => $page, 'title' => $title]);
    }
}
