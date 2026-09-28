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
        if (trim(uri_string(),'/')==='api/print-orders') {
            // PHP discards POST fields when post_max_size is exceeded. Report the real error.
            helper('dtf_upload');
            $limit=dtf_size_to_bytes((string)ini_get('post_max_size'));
            if($limit>0 && (int)$request->getServer('CONTENT_LENGTH')>$limit){
                return service('response')->setStatusCode(413)->setHeader('Cache-Control','no-store')
                    ->setJSON(['ok'=>false,'error'=>'This upload exceeds the server request limit of '.ini_get('post_max_size').'. Please contact the administrator to raise the upload limit.']);
            }
            $ticket=$request->getHeaderLine('X-Printrivo-Upload-Ticket');
            if($ticket!==''){
                if((new \App\Services\PrintUploadTicket())->consume((int)auth()->id(),$request->getPost('request_id'),$ticket))return;
                return service('response')->setStatusCode(403)->setHeader('Cache-Control','no-store')
                    ->setJSON(['ok'=>false,'error'=>'Your print authorization expired or was already used. Click Send to printer again for a fresh verification.']);
            }
            // Older cached clients can still submit a fresh reCAPTCHA token directly.
        }
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
