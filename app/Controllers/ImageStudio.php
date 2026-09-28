<?php

namespace App\Controllers;

class ImageStudio extends BaseController
{
    public function index(): string
    {
        return view('tools/image_studio');
    }
}
