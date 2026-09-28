<?php
namespace App\Controllers;

class PayPalDiagnostics extends BaseController
{
    public function user(int $id): string
    {
        if (!model(\App\Models\UserModel::class)->findById($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $this->response->setHeader('Cache-Control','private, no-store')->setHeader('Referrer-Policy','no-referrer');
        return view('admin/paypal_diagnostics',['userId'=>$id,'report'=>(new \App\Services\PayPalDiagnostics())->inspect($id)]);
    }
}
