<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
class Recaptcha implements FilterInterface
{
    public function before(RequestInterface $request,$arguments=null)
    {
        if ($request->getMethod()!=='POST' || !config('Recaptcha')->enabled) { return; }
        $ready=config('Recaptcha')->ready();
        $token=$request->getPost('g-recaptcha-response');
        $action=preg_match('~(?:api/tools/gang-sheet/inspect|dtf/upload\.php)$~',uri_string())?'artwork_upload':'form_submit';
        if ($ready && is_string($token) && service('recaptchaVerifier')->verify($token,$action)) { return; }
        $message=$ready?'Security verification failed or expired. Return to the form and try again.':'Security verification is temporarily unavailable. Please try again later.';
        $response=service('response')->setStatusCode($ready?403:503)->setHeader('Cache-Control','no-store');
        if ($request->isAJAX()) { return $response->setJSON(['ok'=>false,'error'=>$message]); }
        // Do not flash POST input: it may contain passwords or uploaded customer data.
        return $response->setBody(view('security/verification_failed',['message'=>$message]));
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null) {}
}
