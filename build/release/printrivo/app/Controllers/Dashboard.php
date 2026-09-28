<?php

namespace App\Controllers;

class Dashboard extends BaseController
{
    public function index(): string
    {
        return view('dashboard', [
            'siteName' => 'Printrivo',
            'siteDescription' => 'Professional print-production tools from Mber Digital. Build gang sheets, prepare artwork, and generate QR codes in one place.',
            'pageUrl' => site_url('dashboard'),
            'subscription' => (new \App\Services\SubscriptionAccess())->activeFor((int) auth()->id()),
            'logoUrl' => base_url('assets/img/printrivo_light_logo.png'),
        ]);
    }
}
