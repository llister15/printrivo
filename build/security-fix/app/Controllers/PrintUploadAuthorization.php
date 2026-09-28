<?php
namespace App\Controllers;
use App\Services\PrintUploadTicket;
class PrintUploadAuthorization extends BaseController
{
    public function create()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        if(!service('throttler')->check('print-authorization-'.auth()->id(),10,60)){
            return $this->response->setStatusCode(429)->setJSON(['ok'=>false,'error'=>'Please wait a minute before trying again.']);
        }
        $id=$this->request->getPost('request_id');
        if(!is_string($id))return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'error'=>'Missing print request ID.']);
        try {
            // This route has already passed reCAPTCHA, CSRF, login and subscription filters.
            $ticket=(new PrintUploadTicket())->issue((int)auth()->id(),$id);
            return $this->response->setJSON(['ok'=>true,'upload_ticket'=>$ticket]);
        }catch(\InvalidArgumentException $error){
            return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'error'=>$error->getMessage()]);
        }
    }
}
