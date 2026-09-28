<?php
namespace App\Controllers;
use App\Services\PrintOrder;
use Config\CswPrint;
class PrintOrders extends BaseController
{
    public function create()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        if(!(new \App\Services\PrintOrderStorage())->ensureReady(auth()->user()->inGroup('admin'))){
            return $this->error(503,'Print ordering database setup is incomplete. An administrator must finish setup before orders can be sent.');
        }
        if (!config('CswPrint')->ready()) { return $this->error(503,'Send to printer is not connected yet. Please contact the administrator.'); }
        if (!service('throttler')->check('print-order-'.auth()->id(),10,60)) {
            return $this->error(429,'Please wait a minute before sending another print request.')->setHeader('Retry-After','60');
        }
        $file=$this->request->getFile('artwork');
        if (!$file || !$file->isValid()) { return $this->error(413,'The artwork upload did not complete. Check the connection and server upload limit, then retry.'); }
        if ($file->getSize()>CswPrint::MAX_BYTES) { return $this->error(413,'CSW accepts artwork up to 8 MB. Download your PNG and contact CSW for larger files.'); }
        try {
            $input=$this->request->getPost(['request_id','tool','dpi','filename','fulfillment','shipping_address','confirmed_price_cents']);
            $orders=new PrintOrder();
            $payload=$orders->payload(auth()->user(),$input,file_get_contents($file->getTempName()));
            $receipt=$orders->send((int)auth()->id(),$input['request_id'],$payload);
            return $this->response->setStatusCode($receipt['duplicate']?200:201)->setJSON(['ok'=>true]+$receipt);
        } catch (\InvalidArgumentException $error) {
            return $this->error($error->getCode()?:422,$error->getMessage());
        } catch (\Throwable) {
            // Never log credentials, uploaded artwork, or customer payloads.
            log_message('error','CSW print request could not be confirmed for member {id}.',['id'=>(int)auth()->id()]);
            return $this->error(502,'The order could not be confirmed. Click Send to printer again to retry the same request safely.');
        }
    }
    private function error(int $status,string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['ok'=>false,'error'=>$message]);
    }
}
