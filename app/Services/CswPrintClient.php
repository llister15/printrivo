<?php
namespace App\Services;
use Config\CswPrint;
class CswPrintClient
{
    public function send(array $payload,?string $artworkPath=null): array
    {
        $config=config('CswPrint');
        if (!$config->ready()) { throw new \RuntimeException('Printing is not connected.'); }
        if ($artworkPath!==null) { return $this->sendFile($payload,$artworkPath,$config->token); }
        // The destination is fixed; browser input can never redirect the token.
        $response=service('curlrequest', [], null, null, false)->post(CswPrint::ENDPOINT,[
            'headers'=>['Content-Type'=>'application/json','X-FlowPress-Token'=>$config->token],
            'body'=>json_encode($payload,JSON_THROW_ON_ERROR),
            'timeout'=>60,'connect_timeout'=>10,'http_errors'=>false,'allow_redirects'=>false,
        ]);
        $body=json_decode($response->getBody(),true);
        return ['status'=>$response->getStatusCode(),'body'=>is_array($body)?$body:[],
            'retry_after'=>$response->getHeaderLine('Retry-After')];
    }

    private function sendFile(array $payload,string $path,string $token): array
    {
        // CURLFile streams the PNG from disk instead of copying it into base64 JSON.
        $curl=curl_init(CswPrint::ENDPOINT);$retryAfter='';
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_TIMEOUT=>600,CURLOPT_CONNECTTIMEOUT=>15,
            CURLOPT_HTTPHEADER=>['X-FlowPress-Token: '.$token,'Accept: application/json'],
            CURLOPT_POSTFIELDS=>['order'=>json_encode($payload,JSON_THROW_ON_ERROR),
                'artwork'=>new \CURLFile($path,'image/png','artwork.png')],
            CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use(&$retryAfter): int {
                if(stripos($line,'Retry-After:')===0)$retryAfter=trim(substr($line,12));
                return strlen($line);
            },
        ]);
        try {
            $response=curl_exec($curl);
            if($response===false)throw new \RuntimeException('CSW has not confirmed the upload. Retry the same artwork.');
            $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$body=json_decode($response,true);
            return ['status'=>$status,'body'=>is_array($body)?$body:[],'retry_after'=>$retryAfter];
        } finally { curl_close($curl); }
    }
}
