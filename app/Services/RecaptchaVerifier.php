<?php
namespace App\Services;
class RecaptchaVerifier
{
    public function verify(string $token, string $action): bool
    {
        $config=config('Recaptcha');
        if (!$config->ready() || $token==='' || strlen($token)>8192) { return $this->deny('configuration-or-token'); }
        try {
            $response=service('curlrequest',[],null,null,false)->request('POST','https://www.google.com/recaptcha/api/siteverify',[
                'form_params'=>['secret'=>$config->secretKey,'response'=>$token],
                'timeout'=>10,'connect_timeout'=>5,'http_errors'=>false,'allow_redirects'=>false,
            ]);
            if ($response->getStatusCode()!==200) { return $this->deny('provider-http-error'); }
            $result=json_decode($response->getBody(),true);
            return is_array($result) ? $this->accepts($result,$action) : $this->deny('provider-response');
        } catch (\Throwable) {
            // Only a fixed reason code is logged, never tokens, secrets or Google's response.
            return $this->deny('provider-unreachable');
        }
    }
    public function accepts(array $result,string $action): bool
    {
        $config=config('Recaptcha');
        $time=is_string($result['challenge_ts']??null)?strtotime($result['challenge_ts']):false;
        if (($result['success']??false)!==true) {
            $expired=in_array('timeout-or-duplicate',(array)($result['error-codes']??[]),true);
            return $this->deny($expired?'expired-or-reused':'provider-rejected');
        }
        if (($result['action']??null)!==$action) return $this->deny('action-mismatch');
        if (($result['hostname']??null)!==$config->hostname) return $this->deny('hostname-mismatch');
        if (!is_numeric($result['score']??null) || (float)$result['score']<$config->minimumScore || (float)$result['score']>1) return $this->deny('score-rejected');
        if ($time===false || $time<time()-120 || $time>time()+30) return $this->deny('expired-challenge');
        return true;
    }
    private function deny(string $reason): bool
    {
        log_message('warning','Security verification rejected: {reason}.',['reason'=>$reason]);
        return false;
    }
}
