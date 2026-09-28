<?php
namespace App\Controllers;

class AccountSetup extends BaseController
{
    public function index(): string
    {
        $this->response->setHeader('Cache-Control','private, no-store')->setHeader('Referrer-Policy','no-referrer');
        return view('auth/setup');
    }
    public function accept()
    {
        $this->response->setHeader('Cache-Control','private, no-store')->setHeader('Referrer-Policy','no-referrer');
        $token=$this->request->getPost('token'); $password=$this->request->getPost('password');
        $confirm=$this->request->getPost('password_confirm');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) {
            return redirect()->to(site_url('account/setup'))->with('setup_error','Open the complete setup link from your invitation.');
        }
        if (!is_string($password) || strlen($password)>255 || $password!==$confirm) {
            return $this->retry($token,'Enter matching passwords within the supported length.');
        }
        try {
            (new \App\Services\AdminMembers())->accept($token,$password);
            return redirect()->to(site_url('login'))->with('message','Your password is set. Log in to open your account.');
        } catch (\InvalidArgumentException $e) {
            return $this->retry($token,$e->getMessage());
        } catch (\Throwable) {
            return redirect()->to(site_url('account/setup'))->with('setup_error','This setup link is invalid, expired, or already used. Ask your administrator for a new invitation.');
        }
    }
    private function retry(string $token, string $error): string
    {
        // Keep the invitation in this uncached response only, never in a URL or flashed form data.
        $this->response->setStatusCode(422);
        return view('auth/setup',['setupToken'=>$token,'setupError'=>$error]);
    }
}
