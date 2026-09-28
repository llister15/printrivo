<?php
namespace App\Services;
class RecaptchaVerifier
{
    public function verify(string $token, string $action): bool
    {
        $config=config('Recaptcha');
        if (!$config->ready() || $token==='' || strlen($token)>8192) { return false; }
        try {
            $response=service('curlrequest',[],null,null,false)->request('POST','https://www.google.com/recaptcha/api/siteverify',[
                'form_params'=>['secret'=>$config->secretKey,'response'=>$token],
                'timeout'=>10,'connect_timeout'=>5,'http_errors'=>false,'allow_redirects'=>false,
            ]);
            if ($response->getStatusCode()!==200) { return false; }
            $result=json_decode($response->getBody(),true);
            return is_array($result) && $this->accepts($result,$action);
        } catch (\Throwable) {
            // Do not log tokens, secrets or Google's response. Network failures deny submission.
            return false;
        }
    }
    public function accepts(array $result,string $action): bool
    {
        $config=config('Recaptcha');
        $time=is_string($result['challenge_ts']??null)?strtotime($result['challenge_ts']):false;
        return ($result['success']??false)===true
            && ($result['action']??null)===$action
            && ($result['hostname']??null)===$config->hostname
            && is_numeric($result['score']??null)
            && (float)$result['score'] >= $config->minimumScore && (float)$result['score'] <= 1
            && $time!==false && $time>=time()-120 && $time<=time()+30;
    }
}
