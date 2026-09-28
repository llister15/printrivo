<?php
namespace App\Controllers;
class Marketing extends BaseController
{
    public function index(): string { return view('marketing/home'); }
    public function terms(): string { return view('marketing/terms'); }
    public function privacy(): string { return view('marketing/privacy'); }
    public function pricing(): string { return view('marketing/pricing'); }
}
